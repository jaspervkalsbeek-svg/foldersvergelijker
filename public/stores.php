<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../include/functions.php';

$country = trim($_GET['country'] ?? '');

$where = 'WHERE s.active = 1';
$params = [];
if ($country) {
    $where .= " AND s.country = :country";
    $params[':country'] = $country;
}

$stmt = $pdo->prepare("
    SELECT s.*,
           COUNT(DISTINCT pp.id) as price_count,
           COUNT(DISTINCT p.id) as product_count
    FROM stores s
    LEFT JOIN product_prices pp ON pp.store_id = s.id
    LEFT JOIN products p ON p.id = pp.product_id
    $where
    GROUP BY s.id, s.name, s.country, s.logo, s.website, s.scraper_class, s.active, s.created_at
    ORDER BY s.country, s.name
");
$stmt->execute($params);
$stores = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Winkels – Folders Vergelijker</title>
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
            <a href="stores.php" class="active">Winkels</a>
            <a href="shopping-list.php">Boodschappenlijstje</a>
        </nav>
    </div>
</header>

<main class="container">
    <section class="page-header">
        <h1>Winkels</h1>
        <p>Alle supermarkten die we volgen in Nederland en Duitsland</p>

        <div class="country-filter">
            <a href="stores.php" class="filter-btn <?= !$country ? 'active' : '' ?>">Alle</a>
            <a href="stores.php?country=NL" class="filter-btn <?= $country === 'NL' ? 'active' : '' ?>">🇳🇱 Nederland</a>
            <a href="stores.php?country=DE" class="filter-btn <?= $country === 'DE' ? 'active' : '' ?>">🇩🇪 Duitsland</a>
        </div>
    </section>

    <div class="stores-grid">
        <?php foreach ($stores as $store): ?>
            <a href="index.php?store=<?= (int)$store['id'] ?>" class="store-card" style="border-color: <?= getStoreColor($store['name']) ?>">
                <div class="store-card-header" style="background: <?= getStoreColor($store['name']) ?>">
                    <?= htmlspecialchars($store['name']) ?>
                </div>
                <div class="store-card-body">
                    <span class="store-country"><?= $store['country'] === 'NL' ? '🇳🇱 Nederland' : '🇩🇪 Duitsland' ?></span>
                    <div class="store-stats">
                        <div class="stat">
                            <span class="stat-value"><?= (int)$store['product_count'] ?></span>
                            <span class="stat-label">Producten</span>
                        </div>
                        <div class="stat">
                            <span class="stat-value"><?= (int)$store['price_count'] ?></span>
                            <span class="stat-label">Prijzen</span>
                        </div>
                    </div>
                    <?php if ($store['website']): ?>
                        <span class="store-website"><?= htmlspecialchars(parse_url($store['website'], PHP_URL_HOST)) ?></span>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</main>

<footer class="footer">
    <div class="container">
        <p>Folders Vergelijker - <a href="privacy.php">Privacybeleid</a> - <a href="voorwaarden.php">Voorwaarden</a> - <a href="contact.php">Contact</a></p>
    </div>
</footer>
</body>
</html>
