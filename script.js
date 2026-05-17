document.addEventListener('DOMContentLoaded', () => {
const searchInput   = document.getElementById('searchInput');
const filterButtons = document.querySelectorAll('.filter-btn');
const productGrid   = document.getElementById('productsGrid');
const modal         = document.getElementById('productModal');
const modalClose    = document.querySelector('.custom-modal-close');
const minPriceInput = document.getElementById('minPrice');
const maxPriceInput = document.getElementById('maxPrice');
const langToggle    = document.getElementById('langToggle');
const langIcon      = document.getElementById('langIcon');
const langText      = document.getElementById('langText');

// ── Language State ──────────────────────────────────────────────────────────────────────────────
let currentLang = 'el';

function applyLanguage(lang) {
    currentLang = lang;
    document.documentElement.setAttribute('data-lang', lang);
    const isEN = lang === 'en';

    langIcon.textContent = isEN ? '🇬🇷' : '🇬🇧';
    langText.textContent = isEN ? 'ΕΛ' : 'EN';
    searchInput.placeholder = isEN ? 'Search product...' : 'Αναζήτηση προϊόντος...';
    minPriceInput.placeholder = isEN ? 'From' : 'Από';
    maxPriceInput.placeholder = isEN ? 'To' : 'Έως';

    document.querySelectorAll('.i18n').forEach(el => {
        const txt = el.getAttribute(isEN ? 'data-en' : 'data-el');
        if (txt !== null) el.textContent = txt;
    });

    document.querySelectorAll('.product-card').forEach(card => {
        const el = card.querySelector('.card-title-el');
        const en = card.querySelector('.card-title-en');
        if (el) el.style.display = isEN ? 'none' : '';
        if (en) en.style.display = isEN ? '' : 'none';
    });

    productCardsMap.forEach((data, card) => {
        const pJson = card.getAttribute('data-product-json');
        let p = {};
        try { p = JSON.parse(pJson); } catch(e){}
        const titleVal = isEN ? (p.title_en || p.title) : p.title;
        data.titleLower = titleVal.toLowerCase();
        data.searchableText = data.titleLower + ' ' + data.allCatsText;
    });

    filterProducts();
}

langToggle.addEventListener('click', () => {
    applyLanguage(currentLang === 'el' ? 'en' : 'el');
});

// ── Filtering State ────────────────────────────────────────────────────────────────────────
let activeMainCategory   = 'all';
let activeSecondCategory = null;
let activeDeepCategory   = null;
let searchQuery          = '';
let minPrice = 0, maxPrice = Infinity;
let searchTimeout;

// ── Cache card data ────────────────────────────────────────────────────────────────────────
const productCardsMap = new Map();
document.querySelectorAll('.product-card').forEach(card => {
    let allCats = [];
    try { allCats = JSON.parse(card.getAttribute('data-all-categories') || '[]'); } catch(e) {}
    const titleEl = (card.querySelector('.card-title-el') || {textContent:''}).textContent.toLowerCase();
    const catsText = allCats.map(c => c.toLowerCase()).join(' ');
    productCardsMap.set(card, {
        mainCategory:   card.getAttribute('data-category'),
        secondCategory: card.getAttribute('data-second'),
        thirdCategory:  card.getAttribute('data-third'),
        fourthCategory: card.getAttribute('data-fourth'),
        titleLower:     titleEl,
        allCatsText:    catsText,
        searchableText: titleEl + ' ' + catsText,
        price:          parseFloat(card.getAttribute('data-price')) || 0
    });
});

// ── Filter ─────────────────────────────────────────────────────────────────────────────────
function filterProducts() {
    productCardsMap.forEach((data, card) => {
        const ok =
            (activeMainCategory === 'all' || data.mainCategory === activeMainCategory)
            && (!activeSecondCategory || data.secondCategory === activeSecondCategory)
            && (!activeDeepCategory   || data.thirdCategory === activeDeepCategory || data.fourthCategory === activeDeepCategory)
            && (searchQuery === '' || data.searchableText.includes(searchQuery))
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

// ── Sidebar ─────────────────────────────────────────────────────────────────────────────────
function closeAllSubMenus(except) {
    document.querySelectorAll('.sub-menu').forEach(sm => { if (sm !== except) sm.classList.remove('open'); });
    document.querySelectorAll('.arrow').forEach(a => a.classList.remove('rotated'));
}
function closeAllDeepMenus(except) {
    document.querySelectorAll('.deep-sub-menu').forEach(dm => { if (dm !== except) dm.classList.remove('open'); });
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
        activeMainCategory   = btn.getAttribute('data-parent');
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

filterButtons.forEach(btn => btn.addEventListener('click', () => handleFilterClick(btn)));

// ── Modal ──────────────────────────────────────────────────────────────────────────────────
const modalImage         = document.getElementById('modalImage');
const modalThumbs        = document.getElementById('modalThumbs');
const modalThumb1        = document.getElementById('modalThumb1');
const modalThumb2        = document.getElementById('modalThumb2');
const modalTitle         = document.getElementById('modalTitle');
const modalBreadcrumbs   = document.getElementById('modalBreadcrumbs');
const modalPrice         = document.getElementById('modalPrice');
const modalDate          = document.getElementById('modalDate');
const modalLidlBadge     = document.getElementById('modalLidlBadge');
const modalDescription   = document.getElementById('modalDescription');
const modalLidlHistory   = document.getElementById('modalLidlHistory');
const modalLidlHistoryTags = document.getElementById('modalLidlHistoryTags');
const modalPriceHistory  = document.getElementById('modalPriceHistory');
const modalHistoryTags   = document.getElementById('modalHistoryTags');
const modalSpecs         = document.getElementById('modalSpecs');
const modalSpecsList     = document.getElementById('modalSpecsList');
const modalYoutube       = document.getElementById('modalYoutube');
const modalYoutubeLink   = document.getElementById('modalYoutubeLink');

let images = [];
let currentImageIdx = 0;

function setModalImage(idx) {
    currentImageIdx = idx;
    modalImage.src = images[idx];
    document.querySelectorAll('.modal-thumb').forEach((t, i) => t.classList.toggle('active', i === idx));
}

function parsePriceTag(raw) {
    raw = raw.trim();
    if (raw.includes('>')) {
        const parts = raw.split('>');
        const date  = parts[0].trim();
        const rest  = parts[1].trim();
        const priceDate = rest.split('_');
        return `<span>${priceDate[0].trim()} €</span><em>${date}${priceDate[1] ? ' (' + priceDate[1].trim() + ')' : ''}</em>`;
    }
    const pd = raw.split('_');
    return `<span>${pd[0].trim()} €</span>${pd[1] ? '<em>' + pd[1].trim() + '</em>' : ''}`;
}

function openModal(card) {
    let p = {};
    try { p = JSON.parse(card.getAttribute('data-product-json')); } catch(e) {}

    const isEN = currentLang === 'en';
    const title = isEN && p.title_en ? p.title_en : p.title;
    const desc  = isEN && p.description_en ? p.description_en : p.description;
    const specs = isEN && p.tech_specs_en ? p.tech_specs_en : p.tech_specs;

    const cats = [p.main_category, p.second_category, p.third_category, p.fourth_category].filter(Boolean);
    modalBreadcrumbs.textContent = cats.join(' › ');
    modalTitle.textContent = title || '';

    if (p.lidl_plus) {
        modalPrice.innerHTML = `<span class="was-price">${p.price} €</span> <span class="now-price">${p.lidl_plus} €</span>`;
        modalLidlBadge.style.display = 'inline-block';
    } else {
        modalPrice.textContent = p.price ? p.price + ' €' : '';
        modalLidlBadge.style.display = 'none';
    }
    if (p.date) { modalDate.textContent = p.date; modalDate.style.display = 'inline'; }
    else { modalDate.style.display = 'none'; }

    modalDescription.textContent = desc || '';

    const specsArr = (specs || '').split('|').map(s => s.trim()).filter(Boolean);
    if (specsArr.length) {
        modalSpecsList.innerHTML = specsArr.map(s => `<li>${s}</li>`).join('');
        modalSpecs.style.display = 'block';
    } else { modalSpecs.style.display = 'none'; }

    if (p.price_history) {
        const tags = p.price_history.split('|').filter(Boolean);
        modalHistoryTags.innerHTML = tags.map(t => `<span class="price-tag">${parsePriceTag(t)}</span>`).join('');
        modalPriceHistory.style.display = 'block';
    } else { modalPriceHistory.style.display = 'none'; }

    if (p.lidl_raw) {
        const entries = p.lidl_raw.split('|').filter(Boolean);
        modalLidlHistoryTags.innerHTML = entries.map(t => `<span class="price-tag lidl-tag">${parsePriceTag(t)}</span>`).join('');
        modalLidlHistory.style.display = 'block';
    } else { modalLidlHistory.style.display = 'none'; }

    if (p.youtube) {
        const label = isEN ? '📺 Watch on YouTube' : '📺 Δείτε το Video στο YouTube';
        modalYoutubeLink.textContent = label;
        modalYoutubeLink.href = p.youtube;
        modalYoutube.style.display = 'block';
    } else { modalYoutube.style.display = 'none'; }

    images = [p.image || ''];
    if (p.second_image) images.push(p.second_image);
    setModalImage(0);
    if (images.length > 1) {
        modalThumb1.src = images[0];
        modalThumb2.src = images[1];
        modalThumbs.style.display = 'flex';
    } else { modalThumbs.style.display = 'none'; }

    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    modal.setAttribute('aria-hidden', 'true');
    modal.classList.remove('open');
    document.body.style.overflow = '';
    modalImage.src = '';
}

productGrid.addEventListener('click', e => {
    const card = e.target.closest('.product-card');
    if (card) openModal(card);
});
modalClose.addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

modalThumbs.addEventListener('click', e => {
    const thumb = e.target.closest('.modal-thumb');
    if (thumb) setModalImage(parseInt(thumb.getAttribute('data-idx')));
});

});
