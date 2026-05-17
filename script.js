document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const filterButtons = document.querySelectorAll('.filter-btn');
    const productGrid = document.getElementById('productsGrid');
    const modal = document.getElementById('productModal');
    const modalClose = document.querySelector('.custom-modal-close');

    const minPriceInput = document.getElementById('minPrice');
    const maxPriceInput = document.getElementById('maxPrice');

    let activeMainCategory = 'all';
    let activeSecondCategory = null;
    let activeDeepCategory = null;
    let searchQuery = '';
    let minPrice = 0;
    let maxPrice = Infinity;
    let searchTimeout;

    const productCardsMap = new Map();
    document.querySelectorAll('.product-card').forEach(card => {
        const title = card.querySelector('.card-product-title').textContent.toLowerCase();
        const price = parseFloat(card.getAttribute('data-price')) || 0;
        let allCats = [];
        try { allCats = JSON.parse(card.getAttribute('data-all-categories') || '[]'); } catch(e) {}
        productCardsMap.set(card, {
            mainCategory:   card.getAttribute('data-category'),
            secondCategory: card.getAttribute('data-second'),
            thirdCategory:  card.getAttribute('data-third'),
            fourthCategory: card.getAttribute('data-fourth'),
            allCategories:  allCats,
            titleLower: title,
            price: price
        });
    });

    function filterProducts() {
        productCardsMap.forEach((data, card) => {
            const matchesMain   = (activeMainCategory === 'all' || data.mainCategory === activeMainCategory);
            const matchesSecond = (!activeSecondCategory || data.secondCategory === activeSecondCategory);
            const matchesDeep   = (!activeDeepCategory || data.thirdCategory === activeDeepCategory || data.fourthCategory === activeDeepCategory);
            const matchesSearch = (searchQuery === '' || data.titleLower.includes(searchQuery));
            const matchesPrice  = (data.price >= minPrice && data.price <= maxPrice);
            card.style.display = (matchesMain && matchesSecond && matchesDeep && matchesSearch && matchesPrice) ? 'flex' : 'none';
        });
    }

    function updatePriceLimits() {
        minPrice = parseFloat(minPriceInput.value) || 0;
        maxPrice = parseFloat(maxPriceInput.value) || Infinity;
        filterProducts();
    }
    minPriceInput.addEventListener('input', updatePriceLimits);
    maxPriceInput.addEventListener('input', updatePriceLimits);

    searchInput.addEventListener('input', (e) => {
        searchQuery = e.target.value.toLowerCase().trim();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(filterProducts, 150);
    });

    function closeAllSubMenus(exceptSubMenu) {
        document.querySelectorAll('.sub-menu').forEach(sm => { if (sm !== exceptSubMenu) sm.classList.remove('open'); });
        document.querySelectorAll('.arrow').forEach(a => a.classList.remove('rotated'));
    }
    function closeAllDeepMenus(exceptDeep) {
        document.querySelectorAll('.deep-sub-menu').forEach(dm => { if (dm !== exceptDeep) dm.classList.remove('open'); });
        document.querySelectorAll('.arrow-deep').forEach(a => a.classList.remove('rotated'));
    }

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
    if (menuTree) {
        menuTree.addEventListener('click', (e) => {
            const btn = e.target.closest('.filter-btn');
            if (btn) handleFilterClick(btn);
        });
    }

    // Parse price history entries formatted as "27.99_17/5/26" or "27.99_5/17/26"
    // Returns array of { price, date } objects
    function parsePriceHistory(raw) {
        if (!raw) return [];
        return raw.split('|').map(entry => {
            entry = entry.trim();
            if (!entry) return null;
            const parts = entry.split('_');
            if (parts.length >= 2) {
                return { price: parts[0].trim(), date: parts.slice(1).join('_').trim() };
            }
            return { price: entry, date: '' };
        }).filter(Boolean);
    }

    function openProductModal(product) {
        document.getElementById('modalImage').src = product.image;

        const crumbParts = [product.main_category, product.second_category, product.third_category, product.fourth_category].filter(Boolean);
        document.getElementById('modalBreadcrumbs').textContent = crumbParts.join(' › ');
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

        // Show current price date
        if (product.date) {
            modalDateEl.textContent = product.date;
            modalDateEl.style.display = 'inline';
        } else {
            modalDateEl.style.display = 'none';
        }

        document.getElementById('modalDescription').textContent = product.description;

        // Price history: parse "price_date" format
        if (product.price_history) {
            const historyTagsEl = document.getElementById('modalHistoryTags');
            historyTagsEl.innerHTML = '';
            const entries = parsePriceHistory(product.price_history);
            entries.forEach(entry => {
                const tag = document.createElement('span');
                tag.innerHTML = `<strong>${entry.price} €</strong>${entry.date ? ` <em>${entry.date}</em>` : ''}`;
                historyTagsEl.appendChild(tag);
            });
            document.getElementById('modalPriceHistory').style.display = entries.length ? 'block' : 'none';
        } else {
            document.getElementById('modalPriceHistory').style.display = 'none';
        }

        if (product.tech_specs) {
            const specsListEl = document.getElementById('modalSpecsList');
            specsListEl.innerHTML = '';
            product.tech_specs.split('|').forEach(spec => {
                if (spec.trim()) specsListEl.innerHTML += `<li>${spec.trim()}</li>`;
            });
            document.getElementById('modalSpecs').style.display = 'block';
        } else {
            document.getElementById('modalSpecs').style.display = 'none';
        }

        if (product.youtube) {
            document.getElementById('modalYoutubeLink').href = product.youtube;
            document.getElementById('modalYoutube').style.display = 'block';
        } else {
            document.getElementById('modalYoutube').style.display = 'none';
        }

        modal.classList.add('show');
    }

    productGrid.addEventListener('click', (e) => {
        const card = e.target.closest('.product-card');
        if (card) openProductModal(JSON.parse(card.getAttribute('data-product-json')));
    });

    modalClose.addEventListener('click', () => modal.classList.remove('show'));
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('show'); });
});
