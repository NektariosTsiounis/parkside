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

// ── Language ──────────────────────────────────────────────────────────────────────────────────────
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
        if (txt === null) return;
        const labelSpan = el.querySelector('.i18n-label');
        if (labelSpan) {
            labelSpan.textContent = txt;
        } else {
            if (el.children.length === 0) {
                el.textContent = txt;
            } else {
                for (const node of el.childNodes) {
                    if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                        node.textContent = txt + ' ';
                        break;
                    }
                }
            }
        }
    });

    document.querySelectorAll('.product-card').forEach(card => {
        const el = card.querySelector('.card-title-el');
        const en = card.querySelector('.card-title-en');
        if (el) el.style.display = isEN ? 'none' : '';
        if (en) en.style.display = isEN ? '' : 'none';
    });

    productCardsMap.forEach((data, card) => {
        const p = data.productJson;
        const titleVal = isEN ? (p.title_en || p.title) : p.title;
        data.titleLower = titleVal.toLowerCase();
        data.searchableText = data.titleLower + ' ' + data.allCatsText;
    });

    scheduleFilter();
}

langToggle.addEventListener('click', () => applyLanguage(currentLang === 'el' ? 'en' : 'el'));

// ── Filter State ───────────────────────────────────────────────────────────────────────────────────
let activeMainCategory   = 'all';
let activeSecondCategory = null;
let activeDeepCategory   = null;
let searchQuery          = '';
let minPrice = 0, maxPrice = Infinity;
let filterRafId = null;
let searchTimeout;

// ── Cache card data ───────────────────────────────────────────────────────────────────────────────
const productCardsMap = new Map();
const allCards = document.querySelectorAll('.product-card');
allCards.forEach(card => {
    let allCats = [];
    try { allCats = JSON.parse(card.getAttribute('data-all-categories') || '[]'); } catch(e) {}
    const titleEl  = (card.querySelector('.card-title-el') || {textContent:''}).textContent.toLowerCase();
    const catsText = allCats.map(c => c.toLowerCase()).join(' ');
    let productJson = {};
    try { productJson = JSON.parse(card.getAttribute('data-product-json') || '{}'); } catch(e) {}
    productCardsMap.set(card, {
        mainCategory:   card.getAttribute('data-category')   || '',
        secondCategory: card.getAttribute('data-second')     || '',
        thirdCategory:  card.getAttribute('data-third')      || '',
        fourthCategory: card.getAttribute('data-fourth')     || '',
        titleLower:     titleEl,
        allCatsText:    catsText,
        searchableText: titleEl + ' ' + catsText,
        price:          parseFloat(card.getAttribute('data-price')) || 0,
        productJson,
    });
});

// ── Filter (rAF-batched) ──────────────────────────────────────────────────────────────────────────
function scheduleFilter() {
    if (filterRafId) return;
    filterRafId = requestAnimationFrame(() => {
        filterRafId = null;
        runFilter();
    });
}

function runFilter() {
    const visibility = new Map();
    productCardsMap.forEach((data, card) => {
        const visible =
            (activeMainCategory === 'all' || data.mainCategory === activeMainCategory)
            && (!activeSecondCategory || data.secondCategory === activeSecondCategory)
            && (!activeDeepCategory   || data.thirdCategory  === activeDeepCategory
                                     || data.fourthCategory  === activeDeepCategory)
            && (searchQuery === '' || data.searchableText.includes(searchQuery))
            && (data.price >= minPrice && data.price <= maxPrice);
        visibility.set(card, visible);
    });
    visibility.forEach((visible, card) => {
        const next = visible ? 'flex' : 'none';
        if (card.style.display !== next) card.style.display = next;
    });
}

minPriceInput.addEventListener('input', () => { minPrice = parseFloat(minPriceInput.value) || 0; scheduleFilter(); });
maxPriceInput.addEventListener('input', () => { maxPrice = parseFloat(maxPriceInput.value) || Infinity; scheduleFilter(); });
searchInput.addEventListener('input', e => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchQuery = e.target.value.toLowerCase().trim();
        scheduleFilter();
    }, 120);
});

// ── Sidebar ──────────────────────────────────────────────────────────────────────────────────────
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
    scheduleFilter();
}

filterButtons.forEach(btn => btn.addEventListener('click', () => handleFilterClick(btn)));

// ── Modal ─────────────────────────────────────────────────────────────────────────────────────────
const modalImage           = document.getElementById('modalImage');
const modalThumbs          = document.getElementById('modalThumbs');
const modalThumb1          = document.getElementById('modalThumb1');
const modalThumb2          = document.getElementById('modalThumb2');
const modalTitle           = document.getElementById('modalTitle');
const modalBreadcrumbs     = document.getElementById('modalBreadcrumbs');
const modalPrice           = document.getElementById('modalPrice');
const modalDate            = document.getElementById('modalDate');
const modalLidlBadge       = document.getElementById('modalLidlBadge');
const modalDescription     = document.getElementById('modalDescription');
const modalLidlHistory     = document.getElementById('modalLidlHistory');
const modalLidlHistoryTags = document.getElementById('modalLidlHistoryTags');
const modalPriceHistory    = document.getElementById('modalPriceHistory');
const modalHistoryTags     = document.getElementById('modalHistoryTags');
const modalSpecs           = document.getElementById('modalSpecs');
const modalSpecsList       = document.getElementById('modalSpecsList');
const modalYoutube         = document.getElementById('modalYoutube');
const modalYoutubeLink     = document.getElementById('modalYoutubeLink');

let images = [];
let currentImageIdx = 0;

function setModalImage(idx) {
    currentImageIdx = idx;
    modalImage.src = images[idx];
    document.querySelectorAll('.modal-thumb').forEach((t, i) => t.classList.toggle('active', i === idx));
}

// Country code → flag emoji map
const COUNTRY_FLAGS = { GR: '🇬🇷', CY: '🇨🇾', DE: '🇩🇪', AT: '🇦🇹', NL: '🇳🇱', BE: '🇧🇪', PL: '🇵🇱' };
const KNOWN_COUNTRIES = new Set(Object.keys(COUNTRY_FLAGS));

/**
 * Parses a price history token.
 * Format: price_date_COUNTRY  (e.g. "79.99_17/5/26_GR")
 *
 * Handles duplicates like "79.99_17/5/26_GR_GR" by popping ALL
 * trailing country-code segments before extracting the date and price.
 */
function parsePriceTag(raw) {
    raw = (raw || '').trim();
    if (!raw) return '';
    // Strip legacy "date>" prefix if present
    if (raw.includes('>')) raw = raw.split('>').pop().trim();

    const parts = raw.split('_');

    // Pop ALL trailing country codes (handles duplicates: GR_GR, CY_CY, etc.)
    let country = '';
    while (parts.length >= 1) {
        const last = (parts[parts.length - 1] || '').trim().toUpperCase();
        if (KNOWN_COUNTRIES.has(last)) {
            country = last; // keep the last detected one
            parts.pop();
        } else {
            break; // stop as soon as we hit a non-country segment
        }
    }

    // Pop date from the new end (contains a /)
    let date = '';
    if (parts.length >= 1) {
        const maybeDate = (parts[parts.length - 1] || '').trim();
        if (maybeDate.includes('/')) {
            date = maybeDate;
            parts.pop();
        }
    }

    // Everything remaining is the price
    const price = parts.join('_').trim();
    if (!price) return '';

    const flag = country && COUNTRY_FLAGS[country] ? COUNTRY_FLAGS[country] : '';
    const countryHtml = country
        ? `<em class="ph-country">${flag ? flag + '\u00a0' : ''}${country}</em>`
        : '';
    const dateHtml = date ? `<em class="ph-date">${date}</em>` : '';

    return `<span class="ph-price">${price} €</span>${dateHtml}${countryHtml}`;
}

function openModal(card) {
    const data = productCardsMap.get(card);
    const p    = data ? data.productJson : {};

    const isEN  = currentLang === 'en';
    const title = isEN && p.title_en ? p.title_en : p.title;
    const desc  = isEN && p.description_en ? p.description_en : p.description;
    const specs = isEN && p.tech_specs_en ? p.tech_specs_en : p.tech_specs;

    const catKeys   = ['main_category',    'second_category',    'third_category',    'fourth_category'];
    const catKeysEN = ['main_category_en', 'second_category_en', 'third_category_en', 'fourth_category_en'];
    const crumbs    = (isEN ? catKeysEN : catKeys).map(k => p[k]).filter(Boolean);
    modalBreadcrumbs.textContent = crumbs.join(' › ');

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
        const tags = p.price_history.split('|').map(t => t.trim()).filter(Boolean);
        const html = tags.map(t => `<span class="price-tag">${parsePriceTag(t)}</span>`).join('');
        if (html) { modalHistoryTags.innerHTML = html; modalPriceHistory.style.display = 'block'; }
        else { modalPriceHistory.style.display = 'none'; }
    } else { modalPriceHistory.style.display = 'none'; }

    if (p.lidl_history) {
        const entries = p.lidl_history.split('|').map(t => t.trim()).filter(Boolean);
        const html = entries.map(t => `<span class="price-tag lidl-tag">${parsePriceTag(t)}</span>`).join('');
        if (html) { modalLidlHistoryTags.innerHTML = html; modalLidlHistory.style.display = 'block'; }
        else { modalLidlHistory.style.display = 'none'; }
    } else { modalLidlHistory.style.display = 'none'; }

    if (p.youtube) {
        modalYoutubeLink.textContent = isEN ? '📺 Watch on YouTube' : '📺 Δείτε το Video στο YouTube';
        modalYoutubeLink.href = p.youtube;
        modalYoutube.style.display = 'block';
    } else { modalYoutube.style.display = 'none'; }

    images = [p.image || ''];
    if (p.second_image) images.push(p.second_image);
    setModalImage(0);
    if (images.length > 1) {
        modalThumb1.src = images[0]; modalThumb2.src = images[1];
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

productGrid.addEventListener('click', e => { const card = e.target.closest('.product-card'); if (card) openModal(card); });
modalClose.addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
modalThumbs.addEventListener('click', e => {
    const thumb = e.target.closest('.modal-thumb');
    if (thumb) setModalImage(parseInt(thumb.getAttribute('data-idx')));
});

});
