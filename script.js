document.addEventListener('DOMContentLoaded', () => {
    const searchInput   = document.getElementById('searchInput');
    const filterButtons = document.querySelectorAll('.filter-btn');
    const productGrid   = document.getElementById('productsGrid');
    const modal         = document.getElementById('productModal');
    const modalClose    = document.querySelector('.custom-modal-close');
    const minPriceInput = document.getElementById('minPrice');
    const maxPriceInput = document.getElementById('maxPrice');

    let activeMainCategory   = 'all';
    let activeSecondCategory = null;
    let activeDeepCategory   = null;
    let searchQuery = '';
    let minPrice = 0, maxPrice = Infinity;
    let searchTimeout;

    // Cache card data
    const productCardsMap = new Map();
    document.querySelectorAll('.product-card').forEach(card => {
        let allCats = [];
        try { allCats = JSON.parse(card.getAttribute('data-all-categories') || '[]'); } catch(e) {}
        productCardsMap.set(card, {
            mainCategory:   card.getAttribute('data-category'),
            secondCategory: card.getAttribute('data-second'),
            thirdCategory:  card.getAttribute('data-third'),
            fourthCategory: card.getAttribute('data-fourth'),
            titleLower: card.querySelector('.card-product-title').textContent.toLowerCase(),
            price: parseFloat(card.getAttribute('data-price')) || 0
        });
    });

    function filterProducts() {
        productCardsMap.forEach((data, card) => {
            const ok = (activeMainCategory === 'all' || data.mainCategory === activeMainCategory)
                    && (!activeSecondCategory || data.secondCategory === activeSecondCategory)
                    && (!activeDeepCategory   || data.thirdCategory === activeDeepCategory || data.fourthCategory === activeDeepCategory)
                    && (searchQuery === '' || data.titleLower.includes(searchQuery))
                    && (data.price >= minPrice && data.price <= maxPrice);
            card.style.display = ok ? 'flex' : 'none';
        });
    }

    minPriceInput.addEventListener('input', () => { minPrice = parseFloat(minPriceInput.value) || 0; filterProducts(); });
    maxPriceInput.addEventListener('input', () => { maxPrice = parseFloat(maxPriceInput.value) || Infinity; filterProducts(); });
    searchInput.addEventListener('input', e => {
        searchQuery = e.target.value.toLowerCase().trim();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(filterProducts, 150);
    });

    function closeAllSubMenus(except) { document.querySelectorAll('.sub-menu').forEach(sm => { if (sm !== except) sm.classList.remove('open'); }); document.querySelectorAll('.arrow').forEach(a => a.classList.remove('rotated')); }
    function closeAllDeepMenus(except) { document.querySelectorAll('.deep-sub-menu').forEach(dm => { if (dm !== except) dm.classList.remove('open'); }); document.querySelectorAll('.arrow-deep').forEach(a => a.classList.remove('rotated')); }

    function handleFilterClick(btn) {
        const filterType = btn.getAttribute('data-type');
        const category   = btn.getAttribute('data-category');
        filterButtons.forEach(b => b.classList.remove('active'));
        if (category === 'all') {
            activeMainCategory = 'all'; activeSecondCategory = null; activeDeepCategory = null;
            btn.classList.add('active');
            closeAllSubMenus(null); closeAllDeepMenus(null);
        } else if (filterType === 'main') {
            activeMainCategory = category; activeSecondCategory = null; activeDeepCategory = null;
            btn.classList.add('active');
            const menuGroup = btn.closest('.menu-item-group');
            if (menuGroup) {
                const subMenu = menuGroup.querySelector('.sub-menu');
                const arrow   = btn.querySelector('.arrow');
                closeAllSubMenus(subMenu); closeAllDeepMenus(null);
                if (subMenu) subMenu.classList.toggle('open');
                if (arrow)   arrow.classList.toggle('rotated');
            }
        } else if (filterType === 'second') {
            activeMainCategory = btn.getAttribute('data-parent');
            activeSecondCategory = category; activeDeepCategory = null;
            btn.classList.add('active');
            const subItemGroup = btn.closest('.sub-item-group');
            if (subItemGroup) {
                const deepMenu  = subItemGroup.querySelector('.deep-sub-menu');
                const arrowDeep = btn.querySelector('.arrow-deep');
                closeAllDeepMenus(deepMenu);
                if (deepMenu)  deepMenu.classList.toggle('open');
                if (arrowDeep) arrowDeep.classList.toggle('rotated');
            }
        } else if (filterType === 'deep') {
            activeMainCategory   = btn.getAttribute('data-parent');
            activeSecondCategory = btn.getAttribute('data-second');
            activeDeepCategory   = category;
            btn.classList.add('active');
        }
        filterProducts();
    }

    const menuTree = document.querySelector('.menu-tree');
    if (menuTree) menuTree.addEventListener('click', e => { const btn = e.target.closest('.filter-btn'); if (btn) handleFilterClick(btn); });

    // Parse regular price history: "27.99_17/5/26|24.99_1/3/26"
    function parsePriceHistory(raw) {
        if (!raw) return [];
        return raw.split('|').map(entry => {
            entry = entry.trim();
            if (!entry) return null;
            const parts = entry.split('_');
            return parts.length >= 2
                ? { price: parts[0].trim(), date: parts.slice(1).join('_').trim() }
                : { price: entry, date: '' };
        }).filter(Boolean);
    }

    // Parse Lidl Plus history.
    // Supported formats per pipe-entry:
    //   "59.99_17/5/26"            → single Lidl price, no regular shown
    //   "79.99>59.99_17/5/26"      → regular > lidl price on date
    //   "79.99>59.99"              → regular > lidl price, no date
    function parseLidlHistory(raw) {
        if (!raw) return [];
        return raw.split('|').map(entry => {
            entry = entry.trim();
            if (!entry) return null;
            if (entry.includes('>')) {
                // "regularPrice>lidlPrice_date"
                const [regularPart, rest] = entry.split('>', 2);
                const underIdx = rest.indexOf('_');
                const lidlPrice = underIdx !== -1 ? rest.slice(0, underIdx).trim() : rest.trim();
                const date      = underIdx !== -1 ? rest.slice(underIdx + 1).trim() : '';
                return { regular: regularPart.trim(), lidl: lidlPrice, date };
            } else {
                // plain "59.99_date" or "59.99"
                const parts = entry.split('_');
                return { regular: null, lidl: parts[0].trim(), date: parts[1] ? parts[1].trim() : '' };
            }
        }).filter(Boolean);
    }

    function openProductModal(product) {
        document.getElementById('modalImage').src = product.image;

        const crumbs = [product.main_category, product.second_category, product.third_category, product.fourth_category].filter(Boolean);
        document.getElementById('modalBreadcrumbs').textContent = crumbs.join(' › ');
        document.getElementById('modalTitle').textContent = product.title;

        const modalPriceEl   = document.getElementById('modalPrice');
        const modalLidlBadge = document.getElementById('modalLidlBadge');
        const modalDateEl    = document.getElementById('modalDate');

        if (product.lidl_plus) {
            modalPriceEl.innerHTML = `<span class="was-price">${product.price} €</span> <span class="now-price">${product.lidl_plus} €</span>`;
            modalLidlBadge.style.display = 'inline-block';
        } else {
            modalPriceEl.textContent = `${product.price} €`;
            modalLidlBadge.style.display = 'none';
        }

        modalDateEl.textContent    = product.date || '';
        modalDateEl.style.display  = product.date ? 'inline' : 'none';

        document.getElementById('modalDescription').textContent = product.description;

        // Lidl Plus offer history
        const lidlHistoryEl = document.getElementById('modalLidlHistory');
        const lidlTagsEl    = document.getElementById('modalLidlHistoryTags');
        const lidlEntries   = parseLidlHistory(product.lidl_raw);
        if (lidlEntries.length) {
            lidlTagsEl.innerHTML = '';
            lidlEntries.forEach(entry => {
                const tag = document.createElement('span');
                tag.classList.add('lidl-history-tag');
                let html = '';
                if (entry.regular) {
                    html += `<span class="lh-regular">${entry.regular} €</span>`
                         +  `<span class="lh-arrow">→</span>`
                         +  `<span class="lh-lidl">${entry.lidl} €</span>`;
                } else {
                    html += `<span class="lh-lidl">${entry.lidl} €</span>`;
                }
                if (entry.date) html += `<span class="lh-date">${entry.date}</span>`;
                tag.innerHTML = html;
                lidlTagsEl.appendChild(tag);
            });
            lidlHistoryEl.style.display = 'block';
        } else {
            lidlHistoryEl.style.display = 'none';
        }

        // Regular price history
        const priceHistoryEl = document.getElementById('modalPriceHistory');
        const historyTagsEl  = document.getElementById('modalHistoryTags');
        const historyEntries = parsePriceHistory(product.price_history);
        if (historyEntries.length) {
            historyTagsEl.innerHTML = '';
            historyEntries.forEach(entry => {
                const tag = document.createElement('span');
                tag.innerHTML = `<strong>${entry.price} €</strong>${entry.date ? ` <em>${entry.date}</em>` : ''}`;
                historyTagsEl.appendChild(tag);
            });
            priceHistoryEl.style.display = 'block';
        } else {
            priceHistoryEl.style.display = 'none';
        }

        // Specs
        if (product.tech_specs) {
            const specsListEl = document.getElementById('modalSpecsList');
            specsListEl.innerHTML = '';
            product.tech_specs.split('|').forEach(spec => { if (spec.trim()) specsListEl.innerHTML += `<li>${spec.trim()}</li>`; });
            document.getElementById('modalSpecs').style.display = 'block';
        } else { document.getElementById('modalSpecs').style.display = 'none'; }

        // YouTube
        if (product.youtube) {
            document.getElementById('modalYoutubeLink').href = product.youtube;
            document.getElementById('modalYoutube').style.display = 'block';
        } else { document.getElementById('modalYoutube').style.display = 'none'; }

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
    }

    productGrid.addEventListener('click', e => {
        const card = e.target.closest('.product-card');
        if (card) openProductModal(JSON.parse(card.getAttribute('data-product-json')));
    });

    modalClose.addEventListener('click', () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden','true'); });
    modal.addEventListener('click', e => { if (e.target === modal) { modal.classList.remove('show'); modal.setAttribute('aria-hidden','true'); } });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { modal.classList.remove('show'); modal.setAttribute('aria-hidden','true'); } });
});
