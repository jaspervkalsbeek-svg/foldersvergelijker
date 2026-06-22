<?php
header('Content-Type: application/json');
set_time_limit(0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../include/functions.php';

$available = [
    'rewe'    => ['slug' => 'REWE',                'name' => 'REWE'],
    'kaufland'=> ['slug' => 'Kaufland',             'name' => 'Kaufland'],
    'netto'   => ['slug' => 'Netto-Marken-Discount','name' => 'Netto Marken-Discount'],
    'lidl-de' => ['slug' => 'Lidl',                 'name' => 'Lidl'],
];

$storeKey = $_GET['store'] ?? '';
if (!isset($available[$storeKey])) {
    echo json_encode(['error' => 'Onbekende winkel: ' . $storeKey]);
    exit;
}

$info = $available[$storeKey];
$progress = [];

// ── Fetch kaufda.de page ──
$url = 'https://www.kaufda.de/Geschaefte/' . $info['slug'];
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
]);
$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || empty($html)) {
    echo json_encode(['error' => "HTTP $httpCode bij ophalen kaufda.de pagina"]);
    exit;
}

// ── Extract __NEXT_DATA__ ──
if (!preg_match('/__NEXT_DATA__[^>]*>(.*?)<\/script>/s', $html, $m)) {
    echo json_encode(['error' => 'Geen __NEXT_DATA__ gevonden op de pagina']);
    exit;
}

$data = json_decode($m[1], true);
if (!$data) {
    echo json_encode(['error' => 'JSON parse error in __NEXT_DATA__']);
    exit;
}

$pi = $data['props']['pageProps']['pageInformation'] ?? [];
$offers = $pi['offers']['main']['items'] ?? [];
$totalItems = $pi['offers']['main']['totalItems'] ?? 0;

$progress[] = "[kaufda] {$info['name']}: " . count($offers) . " offers getoond (totaal beschikbaar: $totalItems)";

if (empty($offers)) {
    echo json_encode(['error' => 'Geen offers gevonden', 'progress' => $progress]);
    exit;
}

// ── Persist to database ──
$imported = 0;
$error = null;

try {
    // Map kaufda publisherName -> DB store name
    $dbNameMap = [
        'REWE'                 => 'Rewe',
        'EDEKA'                => 'Edeka',
        'Kaufland'             => 'Kaufland',
        'Netto Marken-Discount'=> 'Netto',
        'ALDI Nord'            => 'Aldi Nord',
        'Lidl'                 => 'Lidl',
        'Penny'                => 'Penny',
        'Rossmann'             => 'Rossmann',
        'dm-drogerie markt'    => 'DM',
    ];

    $pubName = $offers[0]['publisherName'] ?? '';
    $targetDbName = $dbNameMap[$pubName] ?? $pubName;

    // Find or create DB store
    $stmt = $pdo->prepare("SELECT id, name FROM stores WHERE name = ? AND country = 'DE' LIMIT 1");
    $stmt->execute([$targetDbName]);
    $storeDb = $stmt->fetch();

    if (!$storeDb) {
        // Auto-create store
        $pdo->prepare("INSERT INTO stores (name, country, active) VALUES (?, 'DE', 1)")
            ->execute([$targetDbName]);
        $storeDb = ['id' => (string)$pdo->lastInsertId(), 'name' => $targetDbName];
        $progress[] = "[db] Winkel '{$targetDbName}' aangemaakt in database";
    }

    $storeId = (int)$storeDb['id'];

    // Get categories
    $cats = $pdo->query("SELECT id, slug FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
    $catSlugs = [];
    foreach ($cats as $id => $slug) $catSlugs[$slug] = $id;

    // Category map (DE keywords)
    $catMap = [
        'milch' => 'zuivel-eieren', 'käse' => 'zuivel-eieren', 'joghurt' => 'zuivel-eieren', 'ei' => 'zuivel-eieren',
        'brot' => 'brood-ontbijtgranen', 'müsli' => 'brood-ontbijtgranen',
        'obst' => 'fruit-groente', 'gemüse' => 'fruit-groente', 'apfel' => 'fruit-groente', 'banane' => 'fruit-groente',
        'tomate' => 'fruit-groente', 'gurke' => 'fruit-groente', 'salat' => 'fruit-groente', 'kartoffel' => 'fruit-groente',
        'fleisch' => 'vlees-vis', 'hähnchen' => 'vlees-vis', 'rind' => 'vlees-vis', 'hack' => 'vlees-vis',
        'fisch' => 'vlees-vis', 'lachs' => 'vlees-vis', 'wurst' => 'vlees-vis',
        'tiefkühl' => 'diepvries', 'eis' => 'diepvries',
        'cola' => 'dranken', 'wasser' => 'dranken', 'saft' => 'dranken', 'bier' => 'dranken', 'wein' => 'dranken',
        'chips' => 'snacks-zoetigheid', 'schokolade' => 'snacks-zoetigheid', 'kekse' => 'snacks-zoetigheid',
        'bonbon' => 'snacks-zoetigheid', 'snack' => 'snacks-zoetigheid',
        'pasta' => 'pasta-rijst', 'spaghetti' => 'pasta-rijst', 'reis' => 'pasta-rijst',
        'suppe' => 'conserven-sauzen', 'sauce' => 'conserven-sauzen',
        'shampoo' => 'persoonlijke-verzorging', 'zahnpasta' => 'persoonlijke-verzorging',
        'windeln' => 'baby', 'hund' => 'huisdier', 'katze' => 'huisdier',
    ];

    foreach ($offers as $item) {
        $title = $item['title'] ?? '';
        if (empty($title)) continue;

        $price = null;
        if (isset($item['prices']['mainPrice']) && $item['prices']['mainPrice'] !== null) {
            $price = (float)$item['prices']['mainPrice'];
        }
        if ($price === null || $price <= 0) continue;

        $brand = $item['brand'] ?? null;
        $description = $item['description'] ?? null;

        // Image
        $image = null;
        if (isset($item['offerImages']['url']['normal'])) {
            $image = $item['offerImages']['url']['normal'];
        } elseif (isset($item['offerImages']['url']['large'])) {
            $image = $item['offerImages']['url']['large'];
        } elseif (isset($item['offerImages']['url']['thumbnail'])) {
            $image = $item['offerImages']['url']['thumbnail'];
        }

        // Unit price from "1 l = 5.43" or "1 kg = 9.31" format
        $unitPrice = null;
        $unitSize = null;
        if (!empty($item['prices']['priceByBaseUnit'])) {
            $baseStr = $item['prices']['priceByBaseUnit'];
            if (preg_match('/([0-9.,]+)\s*(kg|g|l|ml|stk)\s*=\s*([0-9.,]+)/i', $baseStr, $m2)) {
                $qty = (float)str_replace(',', '.', $m2[1]);
                $unitSize = $qty . ' ' . strtolower($m2[2]);
                $unitPrice = (float)str_replace(',', '.', $m2[3]);
            }
        }

        // Date validity
        $validFrom = null;
        $validUntil = null;
        if (!empty($item['validFrom'])) {
            $ts = strtotime($item['validFrom']);
            if ($ts) $validFrom = date('Y-m-d', $ts);
        }
        if (!empty($item['validUntil'])) {
            $ts = strtotime($item['validUntil']);
            if ($ts) $validUntil = date('Y-m-d', $ts);
        }

        // Categorize
        $catId = null;
        $nameLower = mb_strtolower($title);
        foreach ($catMap as $keyword => $slug) {
            if (str_contains($nameLower, $keyword)) {
                $catId = $catSlugs[$slug] ?? null;
                break;
            }
        }

        // Upsert product
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND (brand = ? OR (brand IS NULL AND ? IS NULL)) LIMIT 1");
        $stmt->execute([$title, $brand, $brand]);
        $existing = $stmt->fetch();

        if ($existing) {
            $pid = (int)$existing['id'];
            $pdo->prepare("UPDATE products SET category_id = COALESCE(NULLIF(category_id, 0), ?), image_url = COALESCE(NULLIF(image_url, ''), ?) WHERE id = ?")
                ->execute([$catId, $image, $pid]);
            // Update description if empty
            $pdo->prepare("UPDATE products SET description = COALESCE(NULLIF(description, ''), ?) WHERE id = ? AND (description IS NULL OR description = '')")
                ->execute([$description, $pid]);
        } else {
            $pdo->prepare("INSERT INTO products (name, brand, description, category_id, image_url) VALUES (?, ?, ?, ?, ?)")
                ->execute([$title, $brand, $description, $catId, $image]);
            $pid = (int)$pdo->lastInsertId();
        }

        // Upsert price
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT id, price FROM product_prices WHERE product_id = ? AND store_id = ? AND DATE(scraped_at) = ? LIMIT 1");
        $stmt->execute([$pid, $storeId, $today]);
        $row = $stmt->fetch();

        if ($row) {
            if ((float)$row['price'] !== $price) {
                $pdo->prepare("UPDATE product_prices SET price = ?, unit_size = ?, unit_price = ?, scraped_at = NOW() WHERE id = ?")
                    ->execute([$price, $unitSize, $unitPrice, (int)$row['id']]);
            }
        } else {
            $pdo->prepare("INSERT INTO product_prices (product_id, store_id, price, unit_size, unit_price, scraped_at) VALUES (?, ?, ?, ?, ?, NOW())")
                ->execute([$pid, $storeId, $price, $unitSize, $unitPrice]);
        }

        $imported++;
    }

    // Remove old prices for this store
    $stmt = $pdo->prepare("DELETE pp FROM product_prices pp
        WHERE pp.store_id = ?
        AND NOT (pp.id IN (
            SELECT keep_id FROM (
                SELECT MAX(pp2.id) as keep_id FROM product_prices pp2
                WHERE pp2.store_id = ? GROUP BY pp2.product_id
            ) latest
        ))
        AND pp.scraped_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute([$storeId, $storeId]);
    $deleted = $stmt->rowCount();
    if ($deleted > 0) $progress[] = "[cleanup] {$deleted} verouderde prijzen opgeruimd";

} catch (Exception $e) {
    $error = 'DB fout: ' . $e->getMessage();
}

echo json_encode([
    'store'    => $storeKey,
    'name'     => $info['name'],
    'success'  => $error === null,
    'progress' => $progress,
    'count'    => count($offers),
    'imported' => $imported,
    'error'    => $error,
]);
