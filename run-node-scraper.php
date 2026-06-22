<?php
/**
 * run-node-scraper.php – Scrapen via Node.js Puppeteer
 *
 * Gebruik:  php run-node-scraper.php [winkelnaam]
 * Voorbeeld: php run-node-scraper.php ah
 *            php run-node-scraper.php          (alles)
 *
 * Dit script:
 * 1. Roept Node.js scrape-store.mjs aan (Puppeteer)
 * 2. Parset de JSON output
 * 3. Slaat producten + prijzen op in de database
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/include/functions.php';

$nodeScript = __DIR__ . '/scrapers/node/scrape-store.mjs';
$storeFilter = $argv[1] ?? null;

$available = [
    'ah'      => ['name' => 'Albert Heijn', 'country' => 'nl'],
    'jumbo'   => ['name' => 'Jumbo',         'country' => 'nl'],
    'lidl-nl' => ['name' => 'Lidl',          'country' => 'nl'],
    'aldi-nl' => ['name' => 'Aldi',          'country' => 'nl'],
    'plus'    => ['name' => 'Plus',          'country' => 'nl'],
    'dirk'    => ['name' => 'Dirk',          'country' => 'nl'],
    'lidl-de' => ['name' => 'Lidl',          'country' => 'de'],
    'aldi-de' => ['name' => 'Aldi',          'country' => 'de'],
    'rewe'    => ['name' => 'Rewe',          'country' => 'de'],
    'edeka'   => ['name' => 'Edeka',         'country' => 'de'],
    'netto'   => ['name' => 'Netto',         'country' => 'de'],
];

$storeKeys = $storeFilter ? [$storeFilter] : array_keys($available);

// Haal winkel ID's uit DB voor fuzzy matching
$dbStores = $pdo->query("SELECT id, name, country FROM stores WHERE active = 1")->fetchAll();
$cats     = $pdo->query("SELECT id, slug FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
$catSlugs = [];
foreach ($cats as $id => $slug) $catSlugs[$slug] = $id;

function matchDbStore(string $name, array $stores): ?array {
    $name = mb_strtolower(trim($name));
    foreach ($stores as $s) {
        $sn = mb_strtolower($s['name']);
        $sc = mb_strtolower($s['country']);
        if ($sn === $name) return $s;
        if (str_contains($sn, $name) || str_contains($name, $sn)) return $s;
    }
    return null;
}

function categorizeProduct(string $name): ?int {
    global $catSlugs;
    $name = mb_strtolower($name);
    $map = [
        'melk' => 'zuivel-eieren', 'kaas' => 'zuivel-eieren', 'yoghurt' => 'zuivel-eieren', 'ei' => 'zuivel-eieren',
        'brood' => 'brood-ontbijtgranen', 'cracker' => 'brood-ontbijtgranen', 'muesli' => 'brood-ontbijtgranen',
        'fruit' => 'fruit-groente', 'groente' => 'fruit-groente', 'appel' => 'fruit-groente', 'banaan' => 'fruit-groente',
        'tomaat' => 'fruit-groente', 'komkommer' => 'fruit-groente', 'sla' => 'fruit-groente', 'aardappel' => 'fruit-groente',
        'vlees' => 'vlees-vis', 'kip' => 'vlees-vis', 'rund' => 'vlees-vis', 'gehakt' => 'vlees-vis',
        'vis' => 'vlees-vis', 'zalm' => 'vlees-vis', 'filet' => 'vlees-vis', 'braadworst' => 'vlees-vis',
        'diepvries' => 'diepvries', 'ijs' => 'diepvries',
        'cola' => 'dranken', 'fris' => 'dranken', 'sap' => 'dranken', 'water' => 'dranken',
        'bier' => 'dranken', 'wijn' => 'dranken', 'drank' => 'dranken',
        'chips' => 'snacks-zoetigheid', 'chocola' => 'snacks-zoetigheid', 'koek' => 'snacks-zoetigheid',
        'snoep' => 'snacks-zoetigheid', 'snack' => 'snacks-zoetigheid',
        'pasta' => 'pasta-rijst', 'spaghetti' => 'pasta-rijst', 'rijst' => 'pasta-rijst',
        'soep' => 'conserven-sauzen', 'saus' => 'conserven-sauzen', 'tomaatblok' => 'conserven-sauzen',
        'was' => 'huishouden', 'schoonmaak' => 'huishouden',
        'shampoo' => 'persoonlijke-verzorging', 'tandpasta' => 'persoonlijke-verzorging',
        'luiers' => 'baby',
        'honden' => 'huisdier', 'katten' => 'huisdier', 'katte' => 'huisdier', 'honden' => 'huisdier',
    ];
    foreach ($map as $keyword => $slug) {
        if (str_contains($name, $keyword)) return $catSlugs[$slug] ?? null;
    }
    return null;
}

echo "============================================\n";
echo "  Node.js Puppeteer Scraper\n";
echo "  " . date('Y-m-d H:i:s') . "\n";
echo "============================================\n\n";

$total = 0;

foreach ($storeKeys as $key) {
    if (!isset($available[$key])) {
        echo "  [!] Onbekende winkel: $key\n";
        continue;
    }

    $storeInfo = $available[$key];
    // Filter DB stores by country first for NL/DE duplicates
    $filteredStores = array_filter($dbStores, fn($s) => strtolower($s['country']) === $storeInfo['country']);
    $storeDb = matchDbStore($storeInfo['name'], $filteredStores ?: $dbStores);
    if (!$storeDb) {
        echo "  [!] Winkel '$key' niet gevonden in database\n";
        continue;
    }

    echo "\n--- {$storeDb['name']} ({$storeDb['country']}) ---\n";
    echo "  Starten van Node.js scraper...\n";

    $cmd = sprintf('node "%s" "%s"', $nodeScript, $key);
    $output = [];
    $returnVar = 0;
    $startTime = microtime(true);

    exec($cmd, $output, $returnVar);

    $elapsed = round(microtime(true) - $startTime, 1);

    if ($returnVar !== 0) {
        echo "  [!] Node.js scraper faalde (code $returnVar)\n";
        // Toon laatste errors uit output
        foreach (array_slice($output, -3) as $line) {
            $line = trim($line);
            if ($line) echo "    $line\n";
        }
        continue;
    }

    // Laatste regel is JSON, of de hele output
    $jsonStr = implode("\n", $output);

    // Zoek naar JSON in output (soms staat er stderr tussen)
    $jsonStart = strpos($jsonStr, '[');
    $jsonEnd   = strrpos($jsonStr, ']');
    if ($jsonStart === false || $jsonEnd === false) {
        echo "  [!] Geen JSON in output gevonden\n";
        continue;
    }
    $jsonStr = substr($jsonStr, $jsonStart, $jsonEnd - $jsonStart + 1);

    $products = json_decode($jsonStr, true);
    if (!$products || !is_array($products)) {
        echo "  [!] JSON parse fout\n";
        continue;
    }

    echo "  [*] $elapsed sec, " . count($products) . " producten\n";

    $imported = 0;
    foreach ($products as $item) {
        $name = $item['name'] ?? '';
        if (empty($name)) continue;

        $brand = $item['brand'] ?? null;
        $price = (float)($item['price'] ?? 0);
        $unitSize = $item['unit_size'] ?? null;
        $unitPrice = $item['unit_price'] ?? null;
        $image = $item['image'] ?? null;

        if ($price <= 0) continue;

        // Auto-calculate unit_price if missing
        if ($unitPrice === null && $unitSize && $price > 0) {
            $sizeStr = str_replace(',', '.', $unitSize);
            if (preg_match('/([0-9.]+)\s*(kg|g|l|ml)/i', $sizeStr, $m)) {
                $qty = (float)$m[1];
                $u = strtolower($m[2]);
                if ($u === 'g') $qty /= 1000;
                if ($u === 'ml') $qty /= 1000;
                if ($qty > 0) $unitPrice = round($price / $qty, 2);
            }
        }

        $catId = categorizeProduct($name);

        // Upsert product
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND (brand = ? OR (brand IS NULL AND ? IS NULL)) LIMIT 1");
        $stmt->execute([$name, $brand, $brand]);
        $existing = $stmt->fetch();

        if ($existing) {
            $pid = (int)$existing['id'];
            $upd = $pdo->prepare("UPDATE products SET category_id = COALESCE(NULLIF(category_id, 0), ?), image_url = COALESCE(NULLIF(image_url, ''), ?) WHERE id = ?");
            $upd->execute([$catId, $image, $pid]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (name, brand, category_id, image_url) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $brand, $catId, $image]);
            $pid = (int)$pdo->lastInsertId();
        }

        // Upsert price
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT id, price FROM product_prices WHERE product_id = ? AND store_id = ? AND DATE(scraped_at) = ? LIMIT 1");
        $stmt->execute([$pid, (int)$storeDb['id'], $today]);
        $row = $stmt->fetch();

        if ($row) {
            if ((float)$row['price'] !== $price) {
                $pdo->prepare("UPDATE product_prices SET price = ?, unit_size = ?, unit_price = ?, scraped_at = NOW() WHERE id = ?")
                    ->execute([$price, $unitSize, $unitPrice, (int)$row['id']]);
            }
        } else {
            $pdo->prepare("INSERT INTO product_prices (product_id, store_id, price, unit_size, unit_price, scraped_at) VALUES (?, ?, ?, ?, ?, NOW())")
                ->execute([$pid, (int)$storeDb['id'], $price, $unitSize, $unitPrice]);
        }

        $imported++;
    }

    echo "  [✓] {$storeDb['name']}: $imported producten opgeslagen\n";
    $total += $imported;
}

echo "\n============================================\n";
echo "  Gereed! Totaal: $total producten\n";
echo "============================================\n";
