<?php
/**
 * run.php – Hoofdscript om alle scrapers te draaien
 *
 * Gebruik: php scrapers/run.php
 * Gebruik: php scrapers/run.php --store="Albert Heijn"  (alleen specifieke winkel)
 */

require_once __DIR__ . '/../config/database.php';

// Laad alle scraper classes
$scraperFiles = glob(__DIR__ . '/*.php');
foreach ($scraperFiles as $file) {
    if (basename($file) !== 'run.php' && basename($file) !== 'ScraperBase.php') {
        require_once $file;
    }
}

echo "============================================\n";
echo "  Folders Vergelijker – Scraper\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "============================================\n\n";

// Haal winkels uit database
$stmt = $pdo->query("SELECT id, name, country, scraper_class FROM stores WHERE active = 1 AND scraper_class IS NOT NULL");
$stores = $stmt->fetchAll();

$storeFilter = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--store=')) {
        $storeFilter = substr($arg, 8);
    }
}

$totalProducts = 0;

foreach ($stores as $store) {
    $className = $store['scraper_class'];

    if ($storeFilter && stripos($store['name'], $storeFilter) === false && stripos($className, $storeFilter) === false) {
        continue;
    }

    if (!class_exists($className)) {
        echo "  [!] Scraper class '$className' voor {$store['name']} niet gevonden\n";
        continue;
    }

    echo "\n--- {$store['name']} ({$store['country']}) ---\n";

    try {
        $scraper = new $className($pdo, (int)$store['id'], $store['name'], $store['country']);
        $products = $scraper->scrape();
        $totalProducts += count($products);
        echo "  [✓] {$store['name']}: " . count($products) . " producten\n";
    } catch (Exception $e) {
        echo "  [✗] Fout bij {$store['name']}: " . $e->getMessage() . "\n";
    }
}

echo "\n============================================\n";
echo "  Gereed! Totaal: $totalProducts producten\n";
echo "============================================\n";

// Toon een samenvatting van het aantal producten per winkel
echo "\nSamenvatting per winkel:\n";
$stmt = $pdo->query("
    SELECT s.name, s.country, COUNT(pp.id) as total_prices
    FROM stores s
    LEFT JOIN product_prices pp ON pp.store_id = s.id
    WHERE s.active = 1
    GROUP BY s.id, s.name, s.country
    ORDER BY s.country, s.name
");
$summary = $stmt->fetchAll();

foreach ($summary as $row) {
    printf("  %-20s (%s): %d prijzen\n", $row['name'], $row['country'], $row['total_prices']);
}
