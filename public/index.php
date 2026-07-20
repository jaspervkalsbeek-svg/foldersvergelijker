<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../include/functions.php';

$search  = trim($_GET['search'] ?? '');
$storeId = (int)($_GET['store'] ?? 0);
$catSlug = trim($_GET['cat'] ?? '');
$country = trim($_GET['country'] ?? 'NL');
$sort    = trim($_GET['sort'] ?? 'price_asc');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;

$where    = [];
$params   = [];

$where[] = "s.active = 1";

if ($search) {
    $where[] = "p.name LIKE :search";
    $params[':search'] = "%$search%";
}

if ($storeId) {
    $where[] = "pp.store_id = :store_id";
    $params[':store_id'] = $storeId;
}

if ($catSlug) {
    $where[] = "c.slug = :cat_slug";
    $params[':cat_slug'] = $catSlug;
}

if ($country) {
    $where[] = "s.country = :country";
    $params[':country'] = $country;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Haal unieke producten op met hun laagste prijs per product
$sql = "SELECT p.id, p.name, p.brand, p.description, p.image_url,
               MIN(pp.price) as lowest_price,
               (SELECT pp2.store_id FROM product_prices pp2 WHERE pp2.product_id = p.id ORDER BY pp2.price ASC LIMIT 1) as cheapest_store_id,
               (SELECT s2.name FROM stores s2 WHERE s2.id = cheapest_store_id) as cheapest_store_name,
               (SELECT pp3.unit_price FROM product_prices pp3 WHERE pp3.product_id = p.id ORDER BY pp3.price ASC LIMIT 1) as cheapest_unit_price,
               (SELECT pp4.unit_size FROM product_prices pp4 WHERE pp4.product_id = p.id ORDER BY pp4.price ASC LIMIT 1) as cheapest_unit_size,
               c.slug as cat_slug,
               COUNT(DISTINCT pp.store_id) as store_count,
               COUNT(pp.id) as price_count
        FROM products p
        JOIN product_prices pp ON pp.product_id = p.id
        JOIN stores s ON s.id = pp.store_id
        LEFT JOIN categories c ON c.id = p.category_id
        $whereClause
        GROUP BY p.id, p.name, p.brand, p.image_url, c.slug
        ORDER BY (p.image_url IS NOT NULL AND p.image_url != '') DESC, store_count DESC, p.name ASC";

$countSql = "SELECT COUNT(DISTINCT p.id)
             FROM products p
             JOIN product_prices pp ON pp.product_id = p.id
             JOIN stores s ON s.id = pp.store_id
             LEFT JOIN categories c ON c.id = p.category_id
             $whereClause";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProducts / $perPage));

$sql .= " LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Haal winkels op voor filter
$stores = $pdo->query("SELECT id, name, country FROM stores WHERE active = 1 ORDER BY country, name")->fetchAll();

// Haal categorieën op
$categories = $pdo->query("SELECT slug, name FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folders Vergelijker – Producten vergelijken</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=20260720">
</head>
<body>
<header class="header">
    <div class="container">
        <a href="index.php" class="logo">Folders<span>Vergelijker</span></a>
        <nav class="nav">
            <a href="index.php">Home</a>
            <a href="stores.php">Winkels</a>
            <a href="shopping-list.php">Boodschappenlijstje</a>
        </nav>
    </div>
</header>

<main class="container">
    <section class="hero">
        <h1>Vind het goedkoopste product</h1>
        <p>Vergelijk <?= number_format($totalProducts, 0, ',', '.') ?> producten uit Nederland &amp; Duitsland en bespaar op je boodschappen</p>

        <form class="search-form" method="GET" action="">
            <div class="search-row">
                <input type="text" name="search" class="search-input"
                       placeholder="Zoek naar producten..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn">Zoeken</button>
            </div>
            <div class="filter-row">
                <select name="country" onchange="this.form.submit()">
                    <option value="">Alle landen</option>
                    <option value="NL" <?= $country === 'NL' ? 'selected' : '' ?>>Nederland</option>
                    <option value="DE" <?= $country === 'DE' ? 'selected' : '' ?>>Duitsland</option>
                </select>
                <select name="store" onchange="this.form.submit()">
                    <option value="">Alle winkels</option>
                    <?php foreach ($stores as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $storeId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?> (<?= $s['country'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="cat" onchange="this.form.submit()">
                    <option value="">Alle categorieën</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c['slug']) ?>" <?= $catSlug === $c['slug'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </section>

    <section>
        <div class="result-header">
            <span class="result-count"><?= $totalProducts ?> producten gevonden</span>
            <div class="view-controls">
                <button class="view-btn active" data-view="grid" title="Rasterweergave">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                </button>
                <button class="view-btn" data-view="list" title="Lijstweergave">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y1="6"/><line x1="8" y1="12" x2="21" y1="12"/><line x1="8" y1="18" x2="21" y1="18"/><line x1="3" y1="6" x2="3.01" y1="6"/><line x1="3" y1="12" x2="3.01" y1="12"/><line x1="3" y1="18" x2="3.01" y1="18"/></svg>
                </button>
            </div>
        </div>

        <?php if (empty($products)): ?>
            <div class="empty">
                <p>Geen producten gevonden.</p>
                <?php if ($search || $storeId || $catSlug || $country): ?>
                    <p><a href="index.php">Wis alle filters</a></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="product-grid" id="productGrid">
                <?php foreach ($products as $product):
                    $cheapestStore = $product['cheapest_store_name'] ?? '';
                    $storeColor = getStoreColor($cheapestStore);
                ?>
                    <a href="product.php?id=<?= (int)$product['id'] ?>" class="product-card">
                        <div class="product-image">
                            <?php if ($product['image_url']): ?>
                                <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="product-image-placeholder">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="1.5"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                                </div>
                            <?php endif; ?>
                            <div class="product-store-badge" style="background:<?= $storeColor ?>">
                                <?= htmlspecialchars($cheapestStore) ?>
                            </div>
                        </div>
                        <div class="product-info">
                            <h3><?= htmlspecialchars(truncateText($product['name'], 60)) ?></h3>
                            <?php if ($product['brand']): ?>
                                <span class="product-brand"><?= htmlspecialchars($product['brand']) ?></span>
                            <?php endif; ?>
                            <?php if ($product['description']): ?>
                                <span class="product-desc"><?= htmlspecialchars(truncateText($product['description'], 80)) ?></span>
                            <?php endif; ?>
                            <div class="product-meta">
                                <?php if ($product['cat_slug']): ?>
                                    <span class="product-category"><?= htmlspecialchars(getCategoryName($product['cat_slug'])) ?></span>
                                <?php endif; ?>
                                <span class="product-stores"><?= (int)$product['store_count'] ?> winkel(s)</span>
                            </div>
                            <div class="product-price-row">
                                <span class="product-price"><?= formatPrice((float)$product['lowest_price']) ?></span>
                                <span class="product-cheapest-label">Laagste prijs</span>
                            </div>
                            <?php if ($product['cheapest_unit_price']): ?>
                                <div class="product-unit-price">
                                    <?= formatUnitPrice100g((float)$product['cheapest_unit_price']) ?>
                                    <span class="unit-size">(<?= htmlspecialchars($product['cheapest_unit_size'] ?? '') ?>)</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $queryParams = array_filter(['search' => $search, 'store' => $storeId, 'cat' => $catSlug, 'country' => $country]);
                    $queryString = http_build_query($queryParams);
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="?<?= $queryString ?>&page=<?= $page - 1 ?>" class="page-link">← Vorige</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?<?= $queryString ?>&page=<?= $i ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= $queryString ?>&page=<?= $page + 1 ?>" class="page-link">Volgende →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<footer class="footer">
    <div class="container">
        <p>Folders Vergelijker - <a href="privacy.php">Privacybeleid</a> - <a href="voorwaarden.php">Voorwaarden</a> - <a href="contact.php">Contact</a></p>
    </div>
</footer>

<script>
document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const view = this.dataset.view;
        const grid = document.getElementById('productGrid');
        if (view === 'list') {
            grid.classList.add('list-view');
        } else {
            grid.classList.remove('list-view');
        }
    });
});
</script>
</body>
</html>
