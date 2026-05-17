<?php
/**
 * Parkside Tool Catalog — Bilingual (Greek / English)
 *
 * Data sources:
 *   Greek.csv     — Greek text: ID, Title, Description, Tech_Specs, Main_Cat, Second_Cat, Third_Cat, Forth_Cat
 *   English.csv   — English text: ID, Title_EN, Description_EN, Tech_Specs_EN, Main_Cat_EN, Second_Cat_EN, Third_Cat_EN, Forth_Cat_EN
 *   Parkside.csv  — Title[0] Price[1] Date[2] Desc[3] Specs[4] Image[5] MainCat[6] SecCat[7] ThirdCat[8] ForthCat[9] PriceHistory[10] LidlPlus[11] Youtube[12]
 *                   NO ID column — row position (1-based) maps to Greek/English ID
 */

$defaultImage = 'Media/Default/parkside.png';
$products     = [];
$sidebarMenu  = []; // [greek_main => ['_en'=>str, '_subs'=>[], '_en_subs'=>[], '_deepsubs'=>[], '_en_deepsubs'=>[]]]

function parseRawPrice($raw) {
    $raw = trim($raw, " \t\n\r\0\x0B\"");
    if (strpos($raw, '_') !== false) {
        $parts = explode('_', $raw, 2);
        $price = str_replace(',', '.', trim($parts[0]));
        $date  = trim($parts[1]);
    } else {
        $price = str_replace(',', '.', $raw);
        $date  = '';
    }
    return ['price' => $price, 'date' => $date];
}

function formatDate($raw) {
    if (empty($raw)) return '';
    $d = DateTime::createFromFormat('n/j/Y', $raw);
    if ($d) return $d->format('j/n/y');
    $d = DateTime::createFromFormat('n/j/y', $raw);
    if ($d) return $d->format('j/n/y');
    return $raw;
}

/**
 * Resolve image reference from Parkside.csv col[5].
 * That column stores a numeric row reference (1, 2, 9…) NOT a file path.
 * Convention: Media/Products/<num>.webp  OR  Products/<num>.webp
 */
function resolveImage($raw, $default) {
    $raw = trim($raw, " \t\n\r\0\x0B\"");
    if (empty($raw)) return $default;
    if (strpos($raw, 'http://') === 0 || strpos($raw, 'https://') === 0) return $raw;
    // Numeric reference → look for image file
    if (ctype_digit($raw)) {
        $path = 'Media/Products/' . $raw . '.webp';
        if (file_exists($path)) return $path;
        $path = 'Products/' . $raw . '.webp';
        if (file_exists($path)) return $path;
        return $default;
    }
    // Explicit relative path
    if (file_exists($raw)) return $raw;
    $path = 'Media/Products/' . $raw . '.webp';
    if (file_exists($path)) return $path;
    return $default;
}

/** Read Greek.csv / English.csv — keyed by integer ID (col 0) */
function readIdCsv($file) {
    $rows = [];
    if (!file_exists($file)) return $rows;
    ini_set('auto_detect_line_endings', true);
    $h = fopen($file, 'r');
    fgetcsv($h, 0, ','); // skip header (handles BOM too)
    while (($d = fgetcsv($h, 0, ',')) !== FALSE) {
        if (!isset($d[0])) continue;
        $id = intval(trim($d[0], " \t\n\r\0\x0B\"\xEF\xBB\xBF"));
        if ($id > 0) $rows[$id] = $d;
    }
    fclose($h);
    return $rows;
}

/** Read Parkside.csv — NO ID column; 1-based row index maps to Greek/English ID */
function readCatalogCsv($file) {
    $rows = [];
    if (!file_exists($file)) return $rows;
    ini_set('auto_detect_line_endings', true);
    $h = fopen($file, 'r');
    fgetcsv($h, 0, ','); // skip header
    $rowIdx = 1;
    while (($d = fgetcsv($h, 0, ',')) !== FALSE) {
        if (!empty($d) && isset($d[0]) && !empty(trim($d[0]))) {
            $rows[$rowIdx] = $d;
        }
        $rowIdx++;
    }
    fclose($h);
    return $rows;
}

$greekRows   = readIdCsv('Greek.csv');
$englishRows = readIdCsv('English.csv');
$catalogRows = readCatalogCsv('Parkside.csv');

foreach ($greekRows as $id => $gr) {
    // Skip ghost rows with no title
    $title = trim($gr[1] ?? '', " \t\n\r\0\x0B\"");
    if (empty($title)) continue;

    $en  = $englishRows[$id] ?? [];
    $cat = $catalogRows[$id] ?? [];

    $desc    = trim($gr[2] ?? '', "\"");
    $specs   = trim($gr[3] ?? '', "\"");

    // Greek categories
    $mainCat = !empty(trim($gr[4] ?? '')) ? trim($gr[4], " \t\n\r\0\x0B\"") : 'Γενικά';
    $secCat  = trim($gr[5] ?? '', " \t\n\r\0\x0B\"");
    $thrCat  = trim($gr[6] ?? '', " \t\n\r\0\x0B\"");
    $forCat  = trim($gr[7] ?? '', " \t\n\r\0\x0B\"");

    // English categories
    $mainCatEN = !empty(trim($en[4] ?? '')) ? trim($en[4], " \t\n\r\0\x0B\"") : 'General';
    $secCatEN  = trim($en[5] ?? '', " \t\n\r\0\x0B\"");
    $thrCatEN  = trim($en[6] ?? '', " \t\n\r\0\x0B\"");
    $forCatEN  = trim($en[7] ?? '', " \t\n\r\0\x0B\"");

    $titleEN = trim($en[1] ?? '', "\"");
    $descEN  = trim($en[2] ?? '', "\"");
    $specsEN = trim($en[3] ?? '', "\"");

    // Parkside.csv: Title[0] Price[1] Date[2] Desc[3] Specs[4] Image[5] ...
    $rawPrice    = isset($cat[1]) ? trim($cat[1], " \t\n\r\0\x0B\"") : '';
    $parsed      = parseRawPrice($rawPrice);
    $price       = !empty($parsed['price']) ? $parsed['price'] : '0';

    $rawDate     = isset($cat[2]) ? trim($cat[2], " \t\n\r\0\x0B\"") : '';
    $displayDate = !empty($rawDate) ? formatDate($rawDate) : formatDate($parsed['date']);

    $finalImage  = resolveImage($cat[5]  ?? '', $defaultImage);
    $secondImage = resolveImage($cat[13] ?? '', '');

    $priceHistory = isset($cat[10]) && !empty(trim($cat[10])) ? trim($cat[10], " \t\n\r\0\x0B\"") : null;
    $lidlRaw      = isset($cat[11]) && !empty(trim($cat[11])) ? trim($cat[11], " \t\n\r\0\x0B\"") : null;
    $youtube      = isset($cat[12]) && !empty(trim($cat[12])) ? trim($cat[12], " \t\n\r\0\x0B\"") : null;

    $lidlCurrentPrice = null;
    if ($lidlRaw) {
        $firstEntry = explode('|', $lidlRaw)[0];
        if (strpos($firstEntry, '>') !== false) {
            $sides    = explode('>', $firstEntry, 2);
            $lidlPart = explode('_', $sides[1])[0];
            $lidlCurrentPrice = trim($lidlPart);
        } else {
            $lidlCurrentPrice = parseRawPrice($firstEntry)['price'];
        }
    }

    $jsPrice           = floatval($price);
    $jsLidlPrice       = $lidlCurrentPrice ? floatval($lidlCurrentPrice) : null;
    $activeFilterPrice = $jsLidlPrice ?? $jsPrice;
    $allCats           = array_values(array_filter([$mainCat, $secCat, $thrCat, $forCat]));

    // Build bilingual sidebar menu
    if (!isset($sidebarMenu[$mainCat])) {
        $sidebarMenu[$mainCat] = [
            '_en'          => $mainCatEN,
            '_subs'        => [],
            '_en_subs'     => [],
            '_deepsubs'    => [],
            '_en_deepsubs' => [],
        ];
    }
    if (!empty($secCat)) {
        if (!in_array($secCat, $sidebarMenu[$mainCat]['_subs'])) {
            $sidebarMenu[$mainCat]['_subs'][]    = $secCat;
            $sidebarMenu[$mainCat]['_en_subs'][] = !empty($secCatEN) ? $secCatEN : $secCat;
        }
        if (!isset($sidebarMenu[$mainCat]['_deepsubs'][$secCat])) {
            $sidebarMenu[$mainCat]['_deepsubs'][$secCat]    = [];
            $sidebarMenu[$mainCat]['_en_deepsubs'][$secCat] = [];
        }
        foreach ([[$thrCat, $thrCatEN], [$forCat, $forCatEN]] as [$deep, $deepEN]) {
            if (!empty($deep) && !in_array($deep, $sidebarMenu[$mainCat]['_deepsubs'][$secCat])) {
                $sidebarMenu[$mainCat]['_deepsubs'][$secCat][]    = $deep;
                $sidebarMenu[$mainCat]['_en_deepsubs'][$secCat][] = !empty($deepEN) ? $deepEN : $deep;
            }
        }
    }

    $products[] = [
        'title'              => $title,
        'title_en'           => $titleEN,
        'price'              => $price,
        'date'               => $displayDate,
        'description'        => $desc,
        'description_en'     => $descEN,
        'tech_specs'         => $specs,
        'tech_specs_en'      => $specsEN,
        'image'              => $finalImage,
        'second_image'       => $secondImage,
        'main_category'      => $mainCat,
        'main_category_en'   => $mainCatEN,
        'second_category'    => $secCat,
        'second_category_en' => $secCatEN,
        'third_category'     => $thrCat,
        'third_category_en'  => $thrCatEN,
        'fourth_category'    => $forCat,
        'fourth_category_en' => $forCatEN,
        'price_history'      => $priceHistory,
        'lidl_plus'          => $lidlCurrentPrice,
        'lidl_raw'           => $lidlRaw,
        'youtube'            => $youtube,
        'js_price'           => $activeFilterPrice,
        'all_cats'           => $allCats,
    ];
}

ksort($sidebarMenu);
?>
<!DOCTYPE html>
<html lang="el" data-lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parkside Tool Catalog</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="main-header">
    <div class="header-content">
        <div class="logo-area"><h1><span>PARKSIDE</span> Tool Catalog</h1></div>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Αναζήτηση προϊόντος...">
        </div>
        <button class="lang-toggle-btn" id="langToggle" aria-label="Switch language">
            <span id="langIcon">🇬🇧</span>
            <span id="langText">EN</span>
        </button>
    </div>
</header>

<div class="full-screen-layout">

    <aside class="left-sidebar">
        <div class="filter-panel">
            <h3 class="i18n" data-el="Κατηγορίες" data-en="Categories">Κατηγορίες</h3>
            <div class="menu-tree">
                <button class="filter-btn active" data-category="all" data-type="main">
                    <span class="i18n" data-el="Όλα τα προϊόντα" data-en="All Products">Όλα τα προϊόντα</span>
                </button>
                <?php foreach ($sidebarMenu as $mainCatName => $catData): ?>
                <?php $mainCatEN = $catData['_en'] ?? $mainCatName; ?>
                <div class="menu-item-group">
                    <button class="filter-btn main-cat-toggle i18n"
                        data-category="<?= htmlspecialchars($mainCatName) ?>"
                        data-type="main"
                        data-el="<?= htmlspecialchars($mainCatName) ?>"
                        data-en="<?= htmlspecialchars($mainCatEN) ?>">
                        <?= htmlspecialchars($mainCatName) ?>
                        <?php if (!empty($catData['_subs'])): ?><span class="arrow">▼</span><?php endif; ?>
                    </button>
                    <?php if (!empty($catData['_subs'])): ?>
                    <div class="sub-menu">
                        <?php foreach ($catData['_subs'] as $idx => $subName): ?>
                        <?php
                            $subEN    = $catData['_en_subs'][$idx] ?? $subName;
                            $deepSubs = $catData['_deepsubs'][$subName] ?? [];
                            $deepENs  = $catData['_en_deepsubs'][$subName] ?? [];
                        ?>
                        <div class="sub-item-group">
                            <button class="filter-btn sub-cat-btn i18n"
                                data-parent="<?= htmlspecialchars($mainCatName) ?>"
                                data-category="<?= htmlspecialchars($subName) ?>"
                                data-type="second"
                                data-el="<?= htmlspecialchars($subName) ?>"
                                data-en="<?= htmlspecialchars($subEN) ?>">
                                — <?= htmlspecialchars($subName) ?>
                                <?php if (!empty($deepSubs)): ?><span class="arrow-deep">▼</span><?php endif; ?>
                            </button>
                            <?php if (!empty($deepSubs)): ?>
                            <div class="deep-sub-menu">
                                <?php foreach ($deepSubs as $di => $deepName): ?>
                                <?php $deepEN = $deepENs[$di] ?? $deepName; ?>
                                <button class="filter-btn deep-cat-btn i18n"
                                    data-parent="<?= htmlspecialchars($mainCatName) ?>"
                                    data-second="<?= htmlspecialchars($subName) ?>"
                                    data-category="<?= htmlspecialchars($deepName) ?>"
                                    data-type="deep"
                                    data-el="<?= htmlspecialchars($deepName) ?>"
                                    data-en="<?= htmlspecialchars($deepEN) ?>">
                                    &nbsp;&nbsp;· <?= htmlspecialchars($deepName) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter-panel">
            <h3 class="i18n" data-el="Εύρος Τιμής" data-en="Price Range">Εύρος Τιμής</h3>
            <div class="price-inputs-flex">
                <div class="input-container">
                    <input type="number" id="minPrice" placeholder="Από" min="0">
                    <span class="currency">€</span>
                </div>
                <div class="dash">—</div>
                <div class="input-container">
                    <input type="number" id="maxPrice" placeholder="Έως" min="0">
                    <span class="currency">€</span>
                </div>
            </div>
        </div>
    </aside>

    <main class="products-view-zone">
        <div class="modern-products-grid" id="productsGrid">
            <?php foreach ($products as $product): ?>
            <article class="product-card"
                data-category="<?= htmlspecialchars($product['main_category']) ?>"
                data-category-en="<?= htmlspecialchars($product['main_category_en']) ?>"
                data-second="<?= htmlspecialchars($product['second_category']) ?>"
                data-second-en="<?= htmlspecialchars($product['second_category_en']) ?>"
                data-third="<?= htmlspecialchars($product['third_category']) ?>"
                data-fourth="<?= htmlspecialchars($product['fourth_category']) ?>"
                data-all-categories="<?= htmlspecialchars(json_encode($product['all_cats'])) ?>"
                data-price="<?= $product['js_price'] ?>"
                data-product-json="<?= htmlspecialchars(json_encode([
                    'title'              => $product['title'],
                    'title_en'           => $product['title_en'],
                    'price'              => $product['price'],
                    'date'               => $product['date'],
                    'description'        => $product['description'],
                    'description_en'     => $product['description_en'],
                    'tech_specs'         => $product['tech_specs'],
                    'tech_specs_en'      => $product['tech_specs_en'],
                    'image'              => $product['image'],
                    'second_image'       => $product['second_image'],
                    'main_category'      => $product['main_category'],
                    'main_category_en'   => $product['main_category_en'],
                    'second_category'    => $product['second_category'],
                    'second_category_en' => $product['second_category_en'],
                    'third_category'     => $product['third_category'],
                    'third_category_en'  => $product['third_category_en'],
                    'fourth_category'    => $product['fourth_category'],
                    'fourth_category_en' => $product['fourth_category_en'],
                    'price_history'      => $product['price_history'],
                    'lidl_plus'          => $product['lidl_plus'],
                    'lidl_raw'           => $product['lidl_raw'],
                    'youtube'            => $product['youtube'],
                ])) ?>">

                <div class="card-img-holder">
                    <img src="<?= htmlspecialchars($product['image']) ?>"
                         alt="<?= htmlspecialchars($product['title']) ?>" loading="lazy">
                </div>
                <div class="card-body-holder">
                    <h3 class="card-title-el"><?= htmlspecialchars($product['title']) ?></h3>
                    <?php if (!empty($product['title_en'])): ?>
                    <h3 class="card-title-en" style="display:none;"><?= htmlspecialchars($product['title_en']) ?></h3>
                    <?php endif; ?>

                    <div class="card-price-block">
                        <?php if ($product['lidl_plus']): ?>
                            <div class="price-date-row">
                                <p class="card-price sale-active">
                                    <span class="was-price"><?= htmlspecialchars($product['price']) ?> €</span>
                                    <span class="now-price"><?= htmlspecialchars($product['lidl_plus']) ?> €</span>
                                </p>
                                <?php if ($product['date']): ?><span class="price-date"><?= htmlspecialchars($product['date']) ?></span><?php endif; ?>
                            </div>
                            <span class="l-plus-badge">💳 Lidl Plus</span>
                        <?php else: ?>
                            <div class="price-date-row">
                                <p class="card-price"><?= htmlspecialchars($product['price']) ?> €</p>
                                <?php if ($product['date']): ?><span class="price-date"><?= htmlspecialchars($product['date']) ?></span><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-btn-action i18n" data-el="Δείτε Περισσότερα →" data-en="View Details →">Δείτε Περισσότερα →</div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </main>

</div>

<!-- Modal -->
<div id="productModal" class="custom-modal-backdrop" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="custom-modal-window">
        <button class="custom-modal-close" aria-label="Close">&times;</button>
        <div class="custom-modal-wrapper">
            <div class="custom-modal-media">
                <img id="modalImage" src="" alt="" loading="lazy">
                <div id="modalThumbs" class="modal-thumbs" style="display:none;">
                    <img id="modalThumb1" src="" alt="Image 1" class="modal-thumb active" data-idx="0">
                    <img id="modalThumb2" src="" alt="Image 2" class="modal-thumb" data-idx="1">
                </div>
            </div>
            <div class="custom-modal-info">
                <div class="custom-modal-crumbs" id="modalBreadcrumbs"></div>
                <h2 id="modalTitle"></h2>
                <div class="custom-modal-pricing">
                    <p id="modalPrice" class="modal-price-output"></p>
                    <span id="modalDate" class="price-date modal-price-date" style="display:none;"></span>
                    <p id="modalLidlBadge" class="l-plus-badge" style="display:none;">💳 Lidl Plus</p>
                </div>
                <p id="modalDescription" class="modal-body-description"></p>
                <div id="modalLidlHistory" class="modal-extra-section" style="display:none;">
                    <h4 class="i18n" data-el="💳 Ιστορικό Lidl Plus" data-en="💳 Lidl Plus History">💳 Ιστορικό Lidl Plus</h4>
                    <div id="modalLidlHistoryTags" class="modal-tags-container"></div>
                </div>
                <div id="modalPriceHistory" class="modal-extra-section" style="display:none;">
                    <h4 class="i18n" data-el="Ιστορικό Τιμών" data-en="Price History">Ιστορικό Τιμών</h4>
                    <div id="modalHistoryTags" class="modal-tags-container"></div>
                </div>
                <div id="modalSpecs" class="modal-extra-section" style="display:none;">
                    <h4 class="i18n" data-el="Τεχνικά Χαρακτηριστικά" data-en="Technical Specifications">Τεχνικά Χαρακτηριστικά</h4>
                    <ul id="modalSpecsList"></ul>
                </div>
                <div id="modalYoutube" class="modal-extra-section" style="display:none;">
                    <h4>Video</h4>
                    <a id="modalYoutubeLink" href="" target="_blank" rel="noopener"
                        class="i18n" data-el="📺 Δείτε το Video στο YouTube" data-en="📺 Watch on YouTube">
                        📺 Δείτε το Video στο YouTube
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="site-footer">
    <div class="footer-disclaimer">
        <span class="disclaimer-icon">⚠️</span>
        <p>
            <strong class="i18n" data-el="Ανεξάρτητος Κατάλογος." data-en="Independent Catalog.">Ανεξάρτητος Κατάλογος.</strong>
            <span class="i18n"
                data-el="Αυτός ο κατάλογος δημιουργήθηκε από ιδιώτη χωρίς σχέση με Parkside ή Lidl. Όλα τα εμπορικά σήματα ανήκουν στους κατόχους τους. Οι τιμές ενδέχεται να μην είναι ενημερωμένες."
                data-en="This catalog was created by a private individual with no affiliation to Parkside or Lidl. All trademarks belong to their respective owners. Prices may not always be up to date.">
                Αυτός ο κατάλογος δημιουργήθηκε από ιδιώτη χωρίς σχέση με <strong>Parkside</strong> ή <strong>Lidl</strong>. Όλα τα εμπορικά σήματα ανήκουν στους κατόχους τους.
            </span>
        </p>
    </div>
</footer>

<script src="script.js"></script>
</body>
</html>
