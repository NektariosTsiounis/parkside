<?php
$csvFile = 'Parkside.csv';
$products = [];
$defaultImage = 'Media/Default/parkside.png';
$sidebarMenu = [];

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

function resolveImage($raw, $default) {
    $raw = trim($raw, " \t\n\r\0\x0B\"");
    if (empty($raw)) return $default;
    if (strpos($raw, 'http://') === 0 || strpos($raw, 'https://') === 0) return $raw;
    $path = 'Products/' . $raw . '.webp';
    return file_exists($path) ? $path : $default;
}

if (file_exists($csvFile)) {
    ini_set('auto_detect_line_endings', true);
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 0, ",");
        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
            if (empty($data) || !isset($data[0]) || empty(trim($data[0]))) continue;

            $title         = trim($data[0], " \t\n\r\0\x0B\"");
            $rawPriceField = isset($data[1]) ? trim($data[1], " \t\n\r\0\x0B\"") : '0';
            $parsedPrice   = parseRawPrice($rawPriceField);
            $price         = $parsedPrice['price'];
            $rawDateField  = isset($data[2]) ? trim($data[2], " \t\n\r\0\x0B\"") : '';
            $displayDate   = !empty($rawDateField) ? formatDate($rawDateField) : formatDate($parsedPrice['date']);
            $desc          = isset($data[3]) ? trim($data[3], "\"") : '';
            $specs         = isset($data[4]) ? trim($data[4], "\"") : '';

            $finalImage  = resolveImage(isset($data[5])  ? $data[5]  : '', $defaultImage);
            $secondImage = resolveImage(isset($data[13]) ? $data[13] : '', '');

            $mainCat   = isset($data[6]) && !empty(trim($data[6])) ? trim($data[6], " \t\n\r\0\x0B\"") : 'Γενικά';
            $secondCat = isset($data[7]) && !empty(trim($data[7])) ? trim($data[7], " \t\n\r\0\x0B\"") : '';
            $thirdCat  = isset($data[8]) && !empty(trim($data[8])) ? trim($data[8], " \t\n\r\0\x0B\"") : '';
            $fourthCat = isset($data[9]) && !empty(trim($data[9])) ? trim($data[9], " \t\n\r\0\x0B\"") : '';

            if (!isset($sidebarMenu[$mainCat])) $sidebarMenu[$mainCat] = ['_subs' => [], '_deepsubs' => []];
            if (!empty($secondCat) && !in_array($secondCat, $sidebarMenu[$mainCat]['_subs']))
                $sidebarMenu[$mainCat]['_subs'][] = $secondCat;
            if (!empty($secondCat)) {
                if (!isset($sidebarMenu[$mainCat]['_deepsubs'][$secondCat]))
                    $sidebarMenu[$mainCat]['_deepsubs'][$secondCat] = [];
                if (!empty($thirdCat) && !in_array($thirdCat, $sidebarMenu[$mainCat]['_deepsubs'][$secondCat]))
                    $sidebarMenu[$mainCat]['_deepsubs'][$secondCat][] = $thirdCat;
                if (!empty($fourthCat) && !in_array($fourthCat, $sidebarMenu[$mainCat]['_deepsubs'][$secondCat]))
                    $sidebarMenu[$mainCat]['_deepsubs'][$secondCat][] = $fourthCat;
            }

            $priceHistory = isset($data[10]) && !empty(trim($data[10])) ? trim($data[10], " \t\n\r\0\x0B\"") : null;
            $lidlRaw      = isset($data[11]) && !empty(trim($data[11])) ? trim($data[11], " \t\n\r\0\x0B\"") : null;

            $lidlCurrentPrice = null;
            if ($lidlRaw) {
                $firstEntry = explode('|', $lidlRaw)[0];
                if (strpos($firstEntry, '>') !== false) {
                    $sides = explode('>', $firstEntry, 2);
                    $lidlPart = explode('_', $sides[1])[0];
                    $lidlCurrentPrice = trim($lidlPart);
                } else {
                    $lidlCurrentPrice = parseRawPrice($firstEntry)['price'];
                }
            }

            $youtube = isset($data[12]) && !empty(trim($data[12])) ? trim($data[12], " \t\n\r\0\x0B\"") : null;

            $jsPrice           = floatval($price);
            $jsLidlPrice       = $lidlCurrentPrice ? floatval($lidlCurrentPrice) : null;
            $activeFilterPrice = $jsLidlPrice ?? $jsPrice;
            $allCats           = array_values(array_filter([$mainCat, $secondCat, $thirdCat, $fourthCat]));

            $products[] = [
                'title'           => $title,
                'price'           => $price,
                'date'            => $displayDate,
                'description'     => $desc,
                'tech_specs'      => $specs,
                'image'           => $finalImage,
                'second_image'    => $secondImage,
                'main_category'   => $mainCat,
                'second_category' => $secondCat,
                'third_category'  => $thirdCat,
                'fourth_category' => $fourthCat,
                'price_history'   => $priceHistory,
                'lidl_plus'       => $lidlCurrentPrice,
                'lidl_raw'        => $lidlRaw,
                'youtube'         => $youtube,
                'js_price'        => $activeFilterPrice,
                'all_cats'        => $allCats,
            ];
        }
        fclose($handle);
    }
}
ksort($sidebarMenu);
?>
<!DOCTYPE html>
<html lang="el">
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
        <div class="search-bar"><input type="text" id="searchInput" placeholder="Αναζήτηση προϊόντος..."></div>
    </div>
</header>

<div class="full-screen-layout">

    <aside class="left-sidebar">
        <div class="filter-panel">
            <h3>Κατηγορίες</h3>
            <div class="menu-tree">
                <button class="filter-btn active" data-category="all" data-type="main">Όλα τα προϊόντα</button>
                <?php foreach ($sidebarMenu as $mainCatName => $catData): ?>
                <div class="menu-item-group">
                    <button class="filter-btn main-cat-toggle"
                        data-category="<?= htmlspecialchars($mainCatName) ?>"
                        data-type="main">
                        <?= htmlspecialchars($mainCatName) ?>
                        <?php if (!empty($catData['_subs'])): ?><span class="arrow">▼</span><?php endif; ?>
                    </button>
                    <?php if (!empty($catData['_subs'])): ?>
                    <div class="sub-menu">
                        <?php foreach ($catData['_subs'] as $subName): ?>
                        <?php $deepSubs = $catData['_deepsubs'][$subName] ?? []; ?>
                        <div class="sub-item-group">
                            <button class="filter-btn sub-cat-btn"
                                data-parent="<?= htmlspecialchars($mainCatName) ?>"
                                data-category="<?= htmlspecialchars($subName) ?>"
                                data-type="second">
                                — <?= htmlspecialchars($subName) ?>
                                <?php if (!empty($deepSubs)): ?><span class="arrow-deep">▼</span><?php endif; ?>
                            </button>
                            <?php if (!empty($deepSubs)): ?>
                            <div class="deep-sub-menu">
                                <?php foreach ($deepSubs as $deepName): ?>
                                <button class="filter-btn deep-cat-btn"
                                    data-parent="<?= htmlspecialchars($mainCatName) ?>"
                                    data-second="<?= htmlspecialchars($subName) ?>"
                                    data-category="<?= htmlspecialchars($deepName) ?>"
                                    data-type="deep">
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
            <h3>Εύρος Τιμής</h3>
            <div class="price-inputs-flex">
                <div class="input-container"><input type="number" id="minPrice" placeholder="Από" min="0"><span class="currency">€</span></div>
                <div class="dash">—</div>
                <div class="input-container"><input type="number" id="maxPrice" placeholder="Έως" min="0"><span class="currency">€</span></div>
            </div>
        </div>
    </aside>

    <main class="products-view-zone">
        <div class="modern-products-grid" id="productsGrid">
            <?php foreach ($products as $product): ?>
            <article class="product-card"
                data-category="<?= htmlspecialchars($product['main_category']) ?>"
                data-second="<?= htmlspecialchars($product['second_category']) ?>"
                data-third="<?= htmlspecialchars($product['third_category']) ?>"
                data-fourth="<?= htmlspecialchars($product['fourth_category']) ?>"
                data-all-categories="<?= htmlspecialchars(json_encode($product['all_cats'])) ?>"
                data-price="<?= $product['js_price'] ?>"
                data-product-json="<?= htmlspecialchars(json_encode([
                    'title'           => $product['title'],
                    'price'           => $product['price'],
                    'date'            => $product['date'],
                    'lidl_plus'       => $product['lidl_plus'],
                    'lidl_raw'        => $product['lidl_raw'],
                    'description'     => $product['description'],
                    'tech_specs'      => $product['tech_specs'],
                    'image'           => $product['image'],
                    'second_image'    => $product['second_image'],
                    'main_category'   => $product['main_category'],
                    'second_category' => $product['second_category'],
                    'third_category'  => $product['third_category'],
                    'fourth_category' => $product['fourth_category'],
                    'price_history'   => $product['price_history'],
                    'youtube'         => $product['youtube'],
                ])) ?>">

                <div class="card-img-holder">
                    <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['title']) ?>" loading="lazy">
                </div>
                <div class="card-body-holder">
                    <h3 class="card-product-title"><?= htmlspecialchars($product['title']) ?></h3>
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
                    <div class="card-btn-action">Δείτε Περισσότερα &rarr;</div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </main>

</div>

<!-- ══ Modal ══ -->
<div id="productModal" class="custom-modal-backdrop" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="custom-modal-window">
        <button class="custom-modal-close" aria-label="Κλείσιμο">&times;</button>

        <div class="custom-modal-wrapper">

            <!-- LEFT: image -->
            <div class="custom-modal-media">
                <img id="modalImage" src="" alt="" loading="lazy">
                <div id="modalThumbs" class="modal-thumbs" style="display:none;">
                    <img id="modalThumb1" src="" alt="Εικόνα 1" class="modal-thumb active" data-idx="0">
                    <img id="modalThumb2" src="" alt="Εικόνα 2" class="modal-thumb" data-idx="1">
                </div>
            </div>

            <!-- RIGHT: info -->
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
                    <h4>💳 Ιστορικό Lidl Plus</h4>
                    <div id="modalLidlHistoryTags" class="modal-tags-container"></div>
                </div>

                <div id="modalPriceHistory" class="modal-extra-section" style="display:none;">
                    <h4>Ιστορικό Τιμών</h4>
                    <div id="modalHistoryTags" class="modal-tags-container"></div>
                </div>

                <div id="modalSpecs" class="modal-extra-section" style="display:none;">
                    <h4>Τεχνικά Χαρακτηριστικά</h4>
                    <ul id="modalSpecsList"></ul>
                </div>

                <div id="modalYoutube" class="modal-extra-section" style="display:none;">
                    <h4>Video</h4>
                    <a id="modalYoutubeLink" href="" target="_blank" rel="noopener">📺 Δείτε το Video στο YouTube</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="site-footer">
    <div class="footer-disclaimer">
        <span class="disclaimer-icon">⚠️</span>
        <p>
            <strong>Ανεξάρτητος Κατάλογος.</strong>
            Αυτός ο κατάλογος δημιουργήθηκε και συντηρείται από ιδιώτη χωρίς καμία σχέση, συνεργασία ή χορηγία από την <strong>Parkside</strong>, τη <strong>Lidl</strong> ή οποιαδήποτε θυγατρική τους εταιρεία.
            Όλα τα εμπορικά σήματα, λογότυπα και ονόματα προϊόντων ανήκουν στους αντίστοιχους κατόχους τους.
            Οι τιμές και οι πληροφορίες ενδέχεται να μην είναι πάντα ενημερωμένες — πάντα επαληθεύετε στο κατάστημα.
        </p>
    </div>
</footer>

<script src="script.js"></script>
</body>
</html>
