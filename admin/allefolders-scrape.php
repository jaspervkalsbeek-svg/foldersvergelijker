<?php
header('Content-Type: application/json');
set_time_limit(0);

session_start();
if (empty($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../include/functions.php';

// ── Available stores from allefolders.nl ──
$available = [
    'jumbo'       => ['name' => 'Jumbo',       'slug' => 'jumbo'],
];

$storeKey = $_GET['store'] ?? '';
if (!isset($available[$storeKey])) {
    echo json_encode(['error' => 'Onbekende winkel: ' . $storeKey]);
    exit;
}

$info = $available[$storeKey];
$apiUrl = 'https://api.jafolders.com/graphql';
$progress = [];

function afQuery($apiUrl, $query) {
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'jafolders-context: allefolders;nl;web;1;1',
        ],
        CURLOPT_POSTFIELDS => json_encode(['query' => $query]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) return null;
    $data = json_decode($result, true);
    return $data['data'] ?? null;
}

// ── Fetch all offers via pagination ──
$allOffers = [];
$offset = 0;
$limit = 50;

do {
    $query = 'query { offers(offers: {shopSlug: "' . $info['slug'] . '"}, pagination: {limit: ' . $limit . ', offset: ' . $offset . '}) {';
    $query .= ' id name description discountPercent priceAfterDiscount priceBeforeDiscount activeFrom expireAfter';
    $query .= ' shop { name slug }';
    $query .= ' hotspot { ... on HotspotProductEntity { fileUrl(version: ORIGINAL) } ... on HotspotImageEntity { fileUrl(version: ORIGINAL) } }';
    $query .= ' } }';

    $data = afQuery($apiUrl, $query);
    $offers = $data['offers'] ?? [];

    if (empty($offers)) break;

    $allOffers = array_merge($allOffers, $offers);
    $offset += $limit;
    $progress[] = '[api] ' . count($offers) . ' offers opgehaald (offset ' . ($offset - $limit) . ')';
} while (count($offers) >= $limit);

$progress[] = '[api] Totaal ' . count($allOffers) . ' offers voor ' . $info['name'];

// ── Persist to database ──
$imported = 0;
$error = null;

if (count($allOffers) > 0) {
    try {
        // Find DB store by name
        $stmt = $pdo->prepare("SELECT id, name FROM stores WHERE active = 1 AND name = ? AND country = 'NL'");
        $stmt->execute([$info['name']]);
        $storeDb = $stmt->fetch();

        if (!$storeDb) {
            $error = 'Store niet gevonden in database (activeert eerst de winkel)';
        } else {
            $cats = $pdo->query("SELECT id, slug FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
            $catSlugs = [];
            foreach ($cats as $id => $slug) $catSlugs[$slug] = $id;

            $batch_time = date('Y-m-d H:i:s');
            foreach ($allOffers as $item) {
                $name = $item['name'] ?? '';
                if (empty($name)) continue;

                $price = null;
                if (isset($item['priceAfterDiscount']) && $item['priceAfterDiscount'] !== null) {
                    $price = (float)$item['priceAfterDiscount'];
                } elseif (isset($item['priceBeforeDiscount']) && $item['priceBeforeDiscount'] !== null) {
                    $price = (float)$item['priceBeforeDiscount'];
                }
                if ($price === null || $price <= 0) continue;

                $description = $item['description'] ?? null;
                $image = null;
                if (isset($item['hotspot']['fileUrl'])) {
                    $image = $item['hotspot']['fileUrl'];
                }

                $catId = categorizeProduct($name, $catSlugs);
                $pid = upsertProduct($pdo, $name, null, $description, $catId, $image);
                upsertPrice($pdo, $pid, (int)$storeDb['id'], $price);
                $imported++;
            }

            // Remove prices not updated in this scrape
            $stmt = $pdo->prepare("DELETE FROM product_prices WHERE store_id = ? AND scraped_at < ?");
            $stmt->execute([(int)$storeDb['id'], $batch_time]);
            $deleted = $stmt->rowCount();
            if ($deleted > 0) $progress[] = "[cleanup] {$deleted} verouderde prijzen opgeruimd";

            // Global cleanup: remove all prices older than 7 days
            $globalDeleted = cleanupOldPrices($pdo, 7);
            if ($globalDeleted > 0) $progress[] = "[cleanup] {$globalDeleted} oude prijzen (>7 dagen) verwijderd";
        }
    } catch (Exception $e) {
        $error = 'DB fout: ' . $e->getMessage();
    }
} else {
    $error = 'Geen offers gevonden van de API';
}

echo json_encode([
    'store'    => $storeKey,
    'name'     => $info['name'],
    'success'  => $error === null,
    'progress' => $progress,
    'count'    => count($allOffers),
    'imported' => $imported,
    'error'    => $error,
]);
