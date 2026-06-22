<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../include/functions.php';

$productId = (int)($_GET['id'] ?? 0);
if (!$productId) {
    header('Location: index.php');
    exit;
}

// Product ophalen
$stmt = $pdo->prepare("SELECT p.*, c.name as cat_name, c.slug as cat_slug
                       FROM products p
                       LEFT JOIN categories c ON c.id = p.category_id
                       WHERE p.id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit;
}

// Prijzen ophalen
$stmt = $pdo->prepare("
    SELECT pp.price, pp.unit_size, pp.unit_price, pp.scraped_at,
           s.name as store_name, s.country
    FROM product_prices pp
    JOIN stores s ON s.id = pp.store_id AND s.active = 1
    WHERE pp.product_id = ?
    ORDER BY pp.unit_price IS NULL, pp.unit_price ASC, pp.price ASC
");
$stmt->execute([$productId]);
$prices = $stmt->fetchAll();

// Vergelijkbare producten
$stmt = $pdo->prepare("
    SELECT p.id, p.name, MIN(pp.price) as lowest_price,
           (SELECT s2.name FROM stores s2 JOIN product_prices pp2 ON pp2.store_id = s2.id WHERE pp2.product_id = p.id AND s2.active = 1 ORDER BY pp2.price ASC LIMIT 1) as cheapest_store
    FROM products p
    JOIN product_prices pp ON pp.product_id = p.id
    JOIN stores s ON s.id = pp.store_id AND s.active = 1
    WHERE p.category_id = ? AND p.id != ?
    GROUP BY p.id, p.name
    ORDER BY RAND()
    LIMIT 4
");
$stmt->execute([$product['category_id'], $productId]);
$similar = $stmt->fetchAll();

$cheapestPrice = $prices[0]['price'] ?? 0;
$cheapestStore = $prices[0]['store_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> – Folders Vergelijker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="header">
    <div class="container">
        <a href="index.php" class="logo">Folders<span>Vergelijker</span></a>
        <nav class="nav">
            <a href="index.php">Home</a>
            <a href="stores.php">Winkels</a>
        </nav>
    </div>
</header>

<main class="container">
    <a href="index.php" class="back-link">← Terug naar overzicht</a>

    <div class="product-detail">
        <div class="detail-image">
            <?php if ($product['image_url']): ?>
                <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
            <?php else: ?>
                <div class="detail-image-placeholder">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="1.5"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                </div>
            <?php endif; ?>
        </div>

        <div class="detail-info">
            <h1><?= htmlspecialchars($product['name']) ?></h1>
            <?php if ($product['brand']): ?>
                <p class="detail-brand">Merk: <?= htmlspecialchars($product['brand']) ?></p>
            <?php endif; ?>
            <?php if ($product['cat_name']): ?>
                <p class="detail-category">Categorie: <?= htmlspecialchars($product['cat_name']) ?></p>
            <?php endif; ?>
            <?php if ($product['ean']): ?>
                <p class="detail-ean">EAN: <?= htmlspecialchars($product['ean']) ?></p>
            <?php endif; ?>

            <div class="detail-cheapest">
                <span class="cheapest-label">Laagste prijs</span>
                <span class="cheapest-price"><?= formatPrice((float)$cheapestPrice) ?></span>
                <span class="cheapest-store" style="color: <?= getStoreColor($cheapestStore) ?>">
                    bij <?= htmlspecialchars($cheapestStore) ?>
                </span>
                <?php
                $cheapestUnitPrice = null;
                $cheapestUnitStore = '';
                $cheapestUnitSize = '';
                foreach ($prices as $p) {
                    if ($p['unit_price'] !== null && ($cheapestUnitPrice === null || (float)$p['unit_price'] < $cheapestUnitPrice)) {
                        $cheapestUnitPrice = (float)$p['unit_price'];
                        $cheapestUnitStore = $p['store_name'];
                        $cheapestUnitSize = $p['unit_size'] ?? '';
                    }
                }
                ?>
                <?php if ($cheapestUnitPrice !== null): ?>
                    <div class="cheapest-unit">
                        <span class="cheapest-unit-label">Laagste prijs per eenheid</span>
                        <span class="cheapest-unit-value"><?= formatUnitPrice100g($cheapestUnitPrice) ?></span>
                        <span class="cheapest-unit-store" style="color: <?= getStoreColor($cheapestUnitStore) ?>">
                            bij <?= htmlspecialchars($cheapestUnitStore) ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <section class="price-comparison">
        <h2>Prijsvergelijking</h2>
        <div class="price-table-wrapper">
            <table class="price-table">
                <thead>
                    <tr>
                        <th>Winkel</th>
                        <th>Land</th>
                        <th>Prijs</th>
                        <th>Verpakking</th>
                        <th>Prijs/kg of /L</th>
                        <th>Prijs/100g</th>
                        <th>Laatst gezien</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prices as $i => $p):
                        $isCheapest = $i === 0;
                        $isCheapestUnit = $p['unit_price'] !== null && (!isset($cheapestUnitPrice) || (float)$p['unit_price'] < $cheapestUnitPrice);
                        if ($p['unit_price'] !== null && (!isset($cheapestUnitPrice) || (float)$p['unit_price'] < $cheapestUnitPrice)) $cheapestUnitPrice = (float)$p['unit_price'];
                    ?>
                        <tr class="<?= $isCheapest ? 'cheapest-row' : '' ?>">
                            <td>
                                <span class="store-badge" style="background:<?= getStoreColor($p['store_name']) ?>">
                                    <?= htmlspecialchars($p['store_name']) ?>
                                </span>
                            </td>
                            <td><?= $p['country'] ?></td>
                            <td class="price-cell"><?= formatPrice((float)$p['price']) ?></td>
                            <td class="unit-cell"><?= htmlspecialchars($p['unit_size'] ?? '-') ?></td>
                            <td class="unit-price-cell <?= ($p['unit_price'] !== null && $p['unit_price'] == $cheapestUnitPrice && $cheapestUnitPrice > 0) ? 'best-unit' : '' ?>">
                                <?= $p['unit_price'] ? formatUnitPrice((float)$p['unit_price'], $p['unit_size']) : '-' ?>
                            </td>
                            <td class="unit-price-cell <?= ($p['unit_price'] !== null && $p['unit_price'] == $cheapestUnitPrice && $cheapestUnitPrice > 0) ? 'best-unit' : '' ?>">
                                <?= $p['unit_price'] ? formatUnitPrice100g((float)$p['unit_price']) : '-' ?>
                            </td>
                            <td class="date-cell"><?= date('d-m-Y', strtotime($p['scraped_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if (!empty($similar)): ?>
    <section class="similar-products">
        <h2>Vergelijkbare producten</h2>
        <div class="product-grid">
            <?php foreach ($similar as $s):
                $sStore = $s['cheapest_store'] ?? '';
            ?>
                <a href="product.php?id=<?= (int)$s['id'] ?>" class="product-card">
                    <div class="product-image">
                        <div class="product-image-placeholder">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="1.5"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                        </div>
                    </div>
                    <div class="product-info">
                        <h3><?= htmlspecialchars(truncateText($s['name'], 50)) ?></h3>
                        <div class="product-price-row">
                            <span class="product-price"><?= formatPrice((float)$s['lowest_price']) ?></span>
                            <?php if ($sStore): ?>
                                <span class="product-cheapest-label"><?= htmlspecialchars($sStore) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>

<footer class="footer">
    <div class="container">
        <p>Folders Vergelijker – Vergelijk prijzen uit Nederlandse en Duitse supermarkten</p>
    </div>
</footer>
</body>
</html>
