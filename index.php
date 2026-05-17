<?php
$csvFile = 'Parkside.csv';
$products = [];
$defaultImage = 'Media/Default/parkside.png';
$sidebarMenu = [];

if (file_exists($csvFile)) {
    ini_set('auto_detect_line_endings', true);
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 0, ",");
        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
            if (empty($data) || !isset($data[0]) || empty(trim($data[0]))) {
                continue;
            }
            
            $title     = trim($data[0], " \t\n\r\0\x0B\"");
            $rawPrice  = isset($data[1]) ? trim($data[1], " \t\n\r\0\x0B\"") : '0';
            $date      = isset($data[2]) ? trim($data[2], " \t\n\r\0\x0B\"") : '';
            $desc      = isset($data[3]) ? trim($data[3], "\"") : '';
            $specs     = isset($data[4]) ? trim($data[4], "\"") : '';
            $imagePath = isset($data[5]) ? trim($data[5], " \t\n\r\0\x0B\"") : '';
            
            if (empty($imagePath)) {
                $finalImage = $defaultImage;
            } elseif (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
                $finalImage = $imagePath;
            } elseif (!file_exists($imagePath)) {
                $finalImage = $defaultImage;
            } else {
                $finalImage = $imagePath;
            }
            
            $mainCat   = isset($data[6]) && !empty(trim($data[6])) ? trim($data[6], " \t\n\r\0\x0B\"") : 'Γενικά';
            $secondCat = isset($data[7]) && !empty(trim($data[7])) ? trim($data[7], " \t\n\r\0\x0B\"") : '';
            
            if (!empty($mainCat)) {
                if (!isset($sidebarMenu[$mainCat])) {
                    $sidebarMenu[$mainCat] = [];
                }
                if (!empty($secondCat) && !in_array($secondCat, $sidebarMenu[$mainCat])) {
                    $sidebarMenu[$mainCat][] = $secondCat;
                }
            }

            $priceHistory = isset($data[10]) && !empty(trim($data[10])) ? trim($data[10], " \t\n\r\0\x0B\"") : null;
            $lidlPlus     = isset($data[11]) && !empty(trim($data[11])) ? trim($data[11], " \t\n\r\0\x0B\"") : null;
            $youtube      = isset($data[12]) && !empty(trim($data[12])) ? trim($data[12], " \t\n\r\0\x0B\"") : null;
            
            $products[] = [
                'title'          => $title,
                'price'          => $rawPrice,
                'date'           => $date,
                'description'    => $desc,
                'tech_specs'     => $specs,
                'image'          => $finalImage,
                'main_category'  => $mainCat,
                'second_category'=> $secondCat,
                'price_history'  => $priceHistory,
                'lidl_plus'      => $lidlPlus,
                'youtube'        => $youtube
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
    <title>Parkside Κατάλογος Προϊόντων</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header class="main-header">
        <div class="header-content">
            <div class="logo-area">
                <h1><span>PARKSIDE</span> Fan Catalog</h1>
            </div>
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Αναζήτηση προϊόντος...">
            </div>
        </div>
    </header>

    <div class="full-screen-layout">
        
        <aside class="left-sidebar">
            
            <div class="filter-panel">
                <h3>Κατηγορίες</h3>
                <div class="menu-tree">
                    <button class="filter-btn active" data-category="all" data-type="main">Όλα τα προϊόντα</button>
                    
                    <?php foreach ($sidebarMenu as $mainCatName => $subCats): ?>
                        <div class="menu-item-group">
                            <button class="filter-btn main-cat-toggle" data-category="<?php echo htmlspecialchars($mainCatName); ?>" data-type="main">
                                <?php echo htmlspecialchars($mainCatName); ?>
                                <?php if(!empty($subCats)): ?><span class="arrow">▼</span><?php endif; ?>
                            </button>
                            
                            <?php if(!empty($subCats)): ?>
                                <div class="sub-menu">
                                    <?php foreach ($subCats as $subName): ?>
                                        <button class="filter-btn sub-cat-btn" data-parent="<?php echo htmlspecialchars($mainCatName); ?>" data-category="<?php echo htmlspecialchars($subName); ?>" data-type="second">
                                            — <?php echo htmlspecialchars($subName); ?>
                                        </button>
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
                    <?php 
                        $jsPrice = floatval(str_replace(['€', ' ', ','], ['', '', '.'], $product['price']));
                        $jsLidlPrice = $product['lidl_plus'] ? floatval(str_replace(['€', ' ', ','], ['', '', '.'], $product['lidl_plus'])) : null;
                        $activeFilterPrice = $jsLidlPrice ?? $jsPrice;
                    ?>
                    <article class="product-card" 
                         data-category="<?php echo htmlspecialchars($product['main_category']); ?>"
                         data-second="<?php echo htmlspecialchars($product['second_category']); ?>"
                         data-price="<?php echo $activeFilterPrice; ?>"
                         data-product-json="<?php echo htmlspecialchars(json_encode([
                            'title' => $product['title'],
                            'price' => $product['price'],
                            'lidl_plus' => $product['lidl_plus'],
                            'description' => $product['description'],
                            'tech_specs' => $product['tech_specs'],
                            'image' => $product['image'],
                            'main_category' => $product['main_category'],
                            'second_category' => $product['second_category'],
                            'price_history' => $product['price_history'],
                            'youtube' => $product['youtube']
                         ])); ?>">
                        
                        <div class="card-img-holder">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" loading="lazy">
                        </div>
                        
                        <div class="card-body-holder">
                            <h3 class="card-product-title"><?php echo htmlspecialchars($product['title']); ?></h3>
                            <div class="card-price-block">
                                <?php if ($product['lidl_plus']): ?>
                                    <p class="card-price sale-active">
                                        <span class="was-price"><?php echo htmlspecialchars($product['price']); ?> €</span> 
                                        <span class="now-price"><?php echo htmlspecialchars($product['lidl_plus']); ?> €</span>
                                    </p>
                                    <span class="l-plus-badge">💳 Lidl Plus</span>
                                <?php else: ?>
                                    <p class="card-price"><?php echo htmlspecialchars($product['price']); ?> €</p>
                                <?php endif; ?>
                            </div>
                            <div class="card-btn-action">Δείτε Περισσότερα &rarr;</div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </main>

    </div>

    <div id="productModal" class="custom-modal-backdrop" aria-hidden="true">
        <div class="custom-modal-window">
            <button class="custom-modal-close">&times;</button>
            <div class="custom-modal-wrapper">
                <div class="custom-modal-media">
                    <img id="modalImage" src="" alt="">
                </div>
                <div class="custom-modal-info">
                    <div class="custom-modal-crumbs" id="modalBreadcrumbs"></div>
                    <h2 id="modalTitle"></h2>
                    <div class="custom-modal-pricing">
                        <p id="modalPrice" class="modal-price-output"></p>
                        <p id="modalLidlBadge" class="l-plus-badge" style="display:none;">💳 Lidl Plus</p>
                    </div>
                    <p id="modalDescription" class="modal-body-description"></p>
                    <div id="modalPriceHistory" class="modal-extra-section" style="display:none;">
                        <h4>Ιστορικό Τιμών</h4>
                        <div id="modalHistoryTags" class="modal-tags-container"></div>
                    </div>
                    <div id="modalSpecs" class="modal-extra-section" style="display:none;">
                        <h4>Τεχνικά Χαρακτηριστικά</h4>
                        <ul id="modalSpecsList"></ul>
                    </div>
                    <div id="modalYoutube" class="modal-extra-section" style="display:none;">
                        <a id="modalYoutubeLink" href="" target="_blank" rel="noopener">📺 Δείτε το Video στο YouTube</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>