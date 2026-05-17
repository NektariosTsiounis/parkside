<?php
/**
 * Parkside Tool Catalog
 *
 * Three CSV sources joined by ID:
 *   main.csv    → ID, Price, Date_of_price, LidlPlus_Price, Price_History,
 *                  LidlPlus_Price_History, Image_URL, other_Image_URL, Youtube
 *   Greek.csv   → ID, Title, Description, Tech_Specs,
 *                  Main_Category, Second_Category, Third_Category, Forth_Category
 *   English.csv → ID, Title_EN, Description_EN, Tech_Specs_EN,
 *                  Main_Category_EN, Second_Category_EN, Third_Category_EN, Forth_Category_EN
 */

$defaultImage = 'Media/Default/parkside.png';
$products     = [];
$sidebarMenu  = [];

// ---------- helpers ----------

function cleanCell($v) {
    return trim($v ?? '', " \t\n\r\0\x0B\"");
}

function formatDate($raw) {
    $raw = trim($raw);
    if (empty($raw)) return '';
    foreach (['d/n/Y', 'n/d/Y', 'd/m/Y', 'j/n/Y', 'n/j/Y'] as $fmt) {
        $d = DateTime::createFromFormat($fmt, $raw);
        if ($d) return $d->format('d/m/Y');
    }
    return $raw;
}

/**
 * Resolve image filename from main.csv Image_URL column.
 * The column stores a descriptive text name (e.g. "Ντουζιέρα ηλιακή").
 * Images are stored in Products/<name>.webp (or .jpg / .jpeg / .png).
 */
function resolveImage($rawName, $default) {
    $name = cleanCell($rawName);
    if (empty($name)) return $default;
    // If it's already a full URL return as-is
    if (strpos($name, 'http://') === 0 || strpos($name, 'https://') === 0) return $name;
    // Search Products/ folder for any common image extension
    foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
        $path = 'Products/' . $name . '.' . $ext;
        if (file_exists($path)) return $path;
    }
    // Legacy fallback
    foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
        $path = 'Media/Products/' . $name . '.' . $ext;
        if (file_exists($path)) return $path;
    }
    return $default;
}

function parseLidlHistory($raw) {
    // Returns current Lidl+ price (first entry) or null
    $raw = cleanCell($raw);
    if (empty($raw)) return null;
    $first = explode('|', $raw)[0];
    if (strpos($first, '>') !== false) {
        $sides = explode('>', $first, 2);
        $val   = explode('_', $sides[1])[0];
    } else {
        $val = explode('_', $first)[0];
    }
    $val = str_replace(',', '.', trim($val));
    return is_numeric($val) ? $val : null;
}

// ---------- read CSV files by ID ----------

function readCsvById($file) {
    $rows = [];
    if (!file_exists($file)) return $rows;
    $h = fopen($file, 'r');
    // Strip BOM from first line
    $header = fgetcsv($h, 0, ',');
    while (($row = fgetcsv($h, 0, ',')) !== false) {
        if (empty($row) || !isset($row[0])) continue;
        $idRaw = trim($row[0], " \t\n\r\0\x0B\"");
        $id    = (int) $idRaw;
        if ($id > 0) $rows[$id] = $row;
    }
    fclose($h);
    return $rows;
}

$mainRows    = readCsvById('main.csv');    // [id => row]
$greekRows   = readCsvById('Greek.csv');   // [id => row]
$englishRows = readCsvById('English.csv'); // [id => row]

// ---------- build products list ----------
// Greek.csv is the master — iterate its IDs

foreach ($greekRows as $id => $gr) {
    $title = cleanCell($gr[1] ?? '');
    if (empty($title)) continue;

    $m  = $mainRows[$id]    ?? [];
    $en = $englishRows[$id] ?? [];

    // --- Greek fields ---
    $desc    = cleanCell($gr[2] ?? '');
    $specs   = cleanCell($gr[3] ?? '');
    $mainCat = cleanCell($gr[4] ?? '') ?: 'Γενικά';
    $secCat  = cleanCell($gr[5] ?? '');
    $thrCat  = cleanCell($gr[6] ?? '');
    $forCat  = cleanCell($gr[7] ?? '');

    // --- English fields ---
    $titleEN   = cleanCell($en[1] ?? '');
    $descEN    = cleanCell($en[2] ?? '');
    $specsEN   = cleanCell($en[3] ?? '');
    $mainCatEN = cleanCell($en[4] ?? '') ?: 'General';
    $secCatEN  = cleanCell($en[5] ?? '');
    $thrCatEN  = cleanCell($en[6] ?? '');
    $forCatEN  = cleanCell($en[7] ?? '');

    // --- main.csv fields ---
    // main.csv columns: ID[0] Price[1] Date[2] LidlPlus[3] PriceHistory[4] LidlHistory[5] Image[6] OtherImage[7] Youtube[8]
    $rawPrice     = cleanCell($m[1] ?? '');
    $price        = str_replace(',', '.', $rawPrice);
    $displayDate  = formatDate(cleanCell($m[2] ?? ''));
    $lidlPlus     = cleanCell($m[3] ?? '') ?: null;
    $priceHistory = cleanCell($m[4] ?? '') ?: null;
    $lidlHistory  = cleanCell($m[5] ?? '') ?: null;
    $imageRaw     = cleanCell($m[6] ?? '');
    $image2Raw    = cleanCell($m[7] ?? '');
    $youtube      = cleanCell($m[8] ?? '') ?: null;

    $finalImage  = resolveImage($imageRaw,  $defaultImage);
    $secondImage = resolveImage($image2Raw, '');

    // Effective price for filtering (prefer Lidl+ price)
    $lidlPlus      = ($lidlPlus && is_numeric(str_replace(',','.',$lidlPlus)))
                     ? str_replace(',','.',$lidlPlus) : null;
    $filterPrice   = floatval($lidlPlus ?? $price);

    $allCats = array_values(array_filter([$mainCat, $secCat, $thrCat, $forCat]));

    // --- sidebar menu ---
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
            $sidebarMenu[$mainCat]['_en_subs'][] = $secCatEN ?: $secCat;
        }
        if (!isset($sidebarMenu[$mainCat]['_deepsubs'][$secCat])) {
            $sidebarMenu[$mainCat]['_deepsubs'][$secCat]    = [];
            $sidebarMenu[$mainCat]['_en_deepsubs'][$secCat] = [];
        }
        foreach ([[$thrCat, $thrCatEN], [$forCat, $forCatEN]] as [$deep, $deepEN]) {
            if (!empty($deep) && !in_array($deep, $sidebarMenu[$mainCat]['_deepsubs'][$secCat])) {
                $sidebarMenu[$mainCat]['_deepsubs'][$secCat][]    = $deep;
                $sidebarMenu[$mainCat]['_en_deepsubs'][$secCat][] = $deepEN ?: $deep;
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
        'lidl_plus'          => $lidlPlus,
        'lidl_history'       => $lidlHistory,
        'youtube'            => $youtube,
        'filter_price'       => $filterPrice,
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
                data-price="<?= $product['filter_price'] ?>"
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
                    'lidl_history'       => $product['lidl_history'],
                    'youtube'            => $product['youtube'],
                ])) ?>">

                <div class="card-img-holder">
                    <img src="<?= htmlspecialchars($product['image']) ?>"
                         alt="<?= htmlspecialchars($product['title']) ?>"
                         loading="lazy" width="300" height="300">
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
