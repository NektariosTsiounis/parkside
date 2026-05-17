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
    let searchQuery = '';
    let minPrice = 0;
    let maxPrice = Infinity;
    let searchTimeout;

    // Fast memory lookups
    const productCardsMap = new Map();
    document.querySelectorAll('.product-card').forEach(card => {
        const title = card.querySelector('.card-product-title').textContent.toLowerCase();
        const price = parseFloat(card.getAttribute('data-price')) || 0;
        productCardsMap.set(card, {
            mainCategory: card.getAttribute('data-category'),
            secondCategory: card.getAttribute('data-second'),
            titleLower: title,
            price: price,
            element: card
        });
    });

    function filterProducts() {
        productCardsMap.forEach((data, card) => {
            const matchesMainCategory = (activeMainCategory === 'all' || data.mainCategory === activeMainCategory);
            const matchesSecondCategory = (!activeSecondCategory || data.secondCategory === activeSecondCategory);
            const matchesSearch = searchQuery === '' || data.titleLower.includes(searchQuery);
            const matchesPrice = data.price >= minPrice && data.price <= maxPrice;

            if (matchesMainCategory && matchesSecondCategory && matchesSearch && matchesPrice) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
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

    function handleFilterClick(btn) {
        const filterType = btn.getAttribute('data-type');
        const category = btn.getAttribute('data-category');

        if (category === 'all') {
            activeMainCategory = 'all';
            activeSecondCategory = null;
            filterButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.sub-menu').forEach(sm => sm.classList.remove('open'));
            document.querySelectorAll('.arrow').forEach(a => a.classList.remove('rotated'));
        } 
        else if (filterType === 'main') {
            activeMainCategory = category;
            activeSecondCategory = null;

            filterButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const menuGroup = btn.closest('.menu-item-group');
            if (menuGroup) {
                const subMenu = menuGroup.querySelector('.sub-menu');
                const arrow = btn.querySelector('.arrow');
                
                document.querySelectorAll('.sub-menu').forEach(sm => {
                    if(sm !== subMenu) sm.classList.remove('open');
                });
                document.querySelectorAll('.arrow').forEach(a => {
                    if(a !== arrow) a.classList.remove('rotated');
                });

                if (subMenu) subMenu.classList.toggle('open');
                if (arrow) arrow.classList.toggle('rotated');
            }
        } else if (filterType === 'second') {
            const parent = btn.getAttribute('data-parent');
            activeMainCategory = parent;
            activeSecondCategory = category;

            filterButtons.forEach(b => {
                if(b.getAttribute('data-type') === 'second' || b.getAttribute('data-category') === 'all') {
                    b.classList.remove('active');
                }
            });
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

    function openProductModal(product) {
        document.getElementById('modalImage').src = product.image;
        let breadcrumbs = product.main_category;
        if (product.second_category) breadcrumbs += ` › ${product.second_category}`;
        document.getElementById('modalBreadcrumbs').textContent = breadcrumbs;
        document.getElementById('modalTitle').textContent = product.title;

        const modalPriceEl = document.getElementById('modalPrice');
        const modalLidlBadge = document.getElementById('modalLidlBadge');
        if (product.lidl_plus) {
            modalPriceEl.innerHTML = `<span class="was-price">${product.price} €</span> <span class="now-price">${product.lidl_plus} €</span>`;
            modalLidlBadge.style.display = 'inline-block';
        } else {
            modalPriceEl.textContent = `${product.price} €`;
            modalLidlBadge.style.display = 'none';
        }

        document.getElementById('modalDescription').textContent = product.description;

        if (product.price_history) {
            const historyTagsEl = document.getElementById('modalHistoryTags');
            historyTagsEl.innerHTML = '';
            product.price_history.split('|').forEach(price => {
                if (price.trim()) historyTagsEl.innerHTML += `<span>${price.trim()} €</span>`;
            });
            document.getElementById('modalPriceHistory').style.display = 'block';
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