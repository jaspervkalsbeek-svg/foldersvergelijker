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
            // Get categories
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

                // Get image from hotspot
                $image = null;
                if (isset($item['hotspot']['fileUrl'])) {
                    $image = $item['hotspot']['fileUrl'];
                }

                // Categorize
                $catId = null;
                $nameLower = mb_strtolower($name);
                $catMap = [
                    'melk'=>'zuivel-eieren','kaas'=>'zuivel-eieren','yoghurt'=>'zuivel-eieren',
                    'boter'=>'zuivel-eieren','ei'=>'zuivel-eieren',
                    'brood'=>'brood-ontbijtgranen','cracker'=>'brood-ontbijtgranen','muesli'=>'brood-ontbijtgranen',
                    'fruit'=>'fruit-groente','groente'=>'fruit-groente','appel'=>'fruit-groente','banaan'=>'fruit-groente',
                    'tomaat'=>'fruit-groente','komkommer'=>'fruit-groente','sla'=>'fruit-groente','aardappel'=>'fruit-groente',
                    'vlees'=>'vlees-vis','kip'=>'vlees-vis','rund'=>'vlees-vis','gehakt'=>'vlees-vis',
                    'vis'=>'vlees-vis','zalm'=>'vlees-vis','filet'=>'vlees-vis','braadworst'=>'vlees-vis',
                    'diepvries'=>'diepvries','ijs'=>'diepvries',
                    'cola'=>'dranken','fris'=>'dranken','sap'=>'dranken','water'=>'dranken',
                    'bier'=>'dranken','wijn'=>'dranken','drank'=>'dranken',
                    'chips'=>'snacks-zoetigheid','chocola'=>'snacks-zoetigheid','koek'=>'snacks-zoetigheid',
                    'snoep'=>'snacks-zoetigheid','snack'=>'snacks-zoetigheid',
                    'pasta'=>'pasta-rijst','spaghetti'=>'pasta-rijst','rijst'=>'pasta-rijst',
                    'soep'=>'conserven-sauzen','saus'=>'conserven-sauzen',
                    'was'=>'huishouden','schoonmaak'=>'huishouden',
                    'shampoo'=>'persoonlijke-verzorging','tandpasta'=>'persoonlijke-verzorging',
                    'luiers'=>'baby','honden'=>'huisdier','katten'=>'huisdier',
                ];
                foreach ($catMap as $keyword => $slug) {
                    if (str_contains($nameLower, $keyword)) {
                        $catId = $catSlugs[$slug] ?? null;
                        break;
                    }
                }

                // Upsert product
                $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
                $stmt->execute([$name]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $pid = (int)$existing['id'];
                    $pdo->prepare("UPDATE products SET image_url = COALESCE(NULLIF(image_url, ''), ?) WHERE id = ?")
                        ->execute([$image, $pid]);
                    $pdo->prepare("UPDATE products SET description = COALESCE(NULLIF(description, ''), ?) WHERE id = ? AND (description IS NULL OR description = '')")
                        ->execute([$description, $pid]);
                } else {
                    $pdo->prepare("INSERT INTO products (name, description, category_id, image_url) VALUES (?, ?, ?, ?)")
                        ->execute([$name, $description, $catId, $image]);
                    $pid = (int)$pdo->lastInsertId();
                }

                // Upsert price
                $today = date('Y-m-d');
                $stmt = $pdo->prepare("SELECT id, price FROM product_prices WHERE product_id = ? AND store_id = ? AND DATE(scraped_at) = ? LIMIT 1");
                $stmt->execute([$pid, (int)$storeDb['id'], $today]);
                $row = $stmt->fetch();

                if ($row) {
                    $pdo->prepare("UPDATE product_prices SET price = ?, scraped_at = NOW() WHERE id = ?")
                        ->execute([$price, (int)$row['id']]);
                } else {
                    $pdo->prepare("INSERT INTO product_prices (product_id, store_id, price, scraped_at) VALUES (?, ?, ?, NOW())")
                        ->execute([$pid, (int)$storeDb['id'], $price]);
                }

                $imported++;
            }

            // Remove prices not updated in this scrape
            $stmt = $pdo->prepare("DELETE FROM product_prices WHERE store_id = ? AND scraped_at < ?");
            $stmt->execute([(int)$storeDb['id'], $batch_time]);
            $deleted = $stmt->rowCount();
            if ($deleted > 0) $progress[] = "[cleanup] {$deleted} verouderde prijzen opgeruimd";
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
