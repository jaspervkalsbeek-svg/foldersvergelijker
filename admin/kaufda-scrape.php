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

$available = [
    'rewe'     => ['slug' => 'REWE',                'name' => 'REWE'],
    'kaufland' => ['slug' => 'Kaufland',             'name' => 'Kaufland'],
    'netto'    => ['slug' => 'Netto-Marken-Discount','name' => 'Netto Marken-Discount'],
    'lidl-de'  => ['slug' => 'Lidl',                 'name' => 'Lidl'],
    'aldi-nord'=> ['slug' => 'Aldi-Nord',            'name' => 'Aldi Nord'],
    'aldi-sud' => ['search'=> 'ALDI SÜD',            'name' => 'Aldi Süd'],
    'penny'    => ['slug' => 'Penny-Markt',          'name' => 'Penny'],
    'rossmann' => ['slug' => 'Rossmann',             'name' => 'Rossmann'],
    'dm'       => ['slug' => 'DM',                   'name' => 'DM'],
];

$storeKey = $_GET['store'] ?? '';
if (!isset($available[$storeKey])) {
    echo json_encode(['error' => 'Onbekende winkel: ' . $storeKey]);
    exit;
}

$info = $available[$storeKey];
$progress = [];

// ── Fetch kaufda.de page ──
if (isset($info['search'])) {
    $url = 'https://www.kaufda.de/search?query=' . urlencode($info['search']) . '&lat=50.919128&lng=6.1186928&city=Ubach-Palenberg&zip=52531';
} else {
    $url = 'https://www.kaufda.de/Geschaefte/' . $info['slug'];
}
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
$progress[] = "[kaufda] {$info['name']}: " . count($offers) . " offers in SSR (totaal beschikbaar: $totalItems)";

// ── Extract brochure UUID ──
$brochureUuid = null;
if (isset($info['search'])) {
    // Search page: find brochure via __NEXT_DATA__ searchResults
    $brochures = $pi['searchResults']['contents']['brochures'] ?? [];
    foreach ($brochures as $b) {
        $pubName = $b['content']['publisher']['name'] ?? '';
        if ($pubName === $info['search']) {
            $brochureUuid = $b['content']['id'];
            break;
        }
    }
} elseif (preg_match('/content-media\.bonial\.biz\/([a-f0-9-]{36})\/preview\.jpg\?impolicy=SEO-BROCHURE-BOX-VIEWER/i', $html, $m2)) {
    $brochureUuid = $m2[1];
}

// ── Fetch ALL offers from brochure pages API ──
$pagesOffers = [];
if ($brochureUuid) {
    $progress[] = "[brochure] Brochure UUID: $brochureUuid";
    $apiUrl = "https://content-viewer-be.kaufda.de/v1/brochures/{$brochureUuid}/pages?partner=kaufda_web&lat=50.1169&lng=8.6837";
    $ch2 = curl_init($apiUrl);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    $apiJson = curl_exec($ch2);
    $apiHttp = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if ($apiHttp === 200 && !empty($apiJson)) {
        $apiData = json_decode($apiJson, true);
        if ($apiData && isset($apiData['contents'])) {
            foreach ($apiData['contents'] as $page) {
                if (isset($page['offers'])) {
                    foreach ($page['offers'] as $offer) {
                        $pagesOffers[] = $offer['content'] ?? null;
                    }
                }
            }
            $progress[] = "[brochure] {$info['name']}: " . count($pagesOffers) . " offers uit brochure API";
        }
    } else {
        $progress[] = "[brochure] API gaf HTTP $apiHttp terug";
    }
} else {
    $progress[] = "[brochure] Geen brochure UUID gevonden op de pagina";
}

// Use brochure API offers if available, else fall back to SSR
if (!empty($pagesOffers)) {
    $offers = $pagesOffers;
} elseif (empty($offers)) {
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
        'ALDI SÜD'             => 'Aldi Süd',
        'Lidl'                 => 'Lidl',
        'Penny'                => 'Penny',
        'Rossmann'             => 'Rossmann',
        'dm-drogerie markt'    => 'DM',
    ];

    $pubName = '';
    if (isset($offers[0]['publisherName'])) {
        $pubName = $offers[0]['publisherName'];
    } elseif (isset($offers[0]['publisher']['name'])) {
        $pubName = $offers[0]['publisher']['name'];
    }
    $targetDbName = $dbNameMap[$pubName] ?? $pubName;

    // Find or create DB store
    $stmt = $pdo->prepare("SELECT id, name FROM stores WHERE name = ? AND country = 'DE' LIMIT 1");
    $stmt->execute([$targetDbName]);
    $storeDb = $stmt->fetch();

    if (!$storeDb) {
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

    $batch_time = date('Y-m-d H:i:s');
    foreach ($offers as $item) {
        // Normalize data for both SSR and brochure API format
        $title = $item['title'] ?? ($item['products'][0]['name'] ?? '');
        if (empty($title)) continue;

        $price = null;
        if (isset($item['prices']['mainPrice'])) {
            $price = (float)$item['prices']['mainPrice'];
        } elseif (isset($item['deals'])) {
            foreach ($item['deals'] as $deal) {
                if (($deal['type'] ?? '') === 'SALES_PRICE' && isset($deal['min']) && $deal['min'] > 0) {
                    $price = (float)$deal['min'];
                    break;
                }
            }
        }
        if ($price === null || $price <= 0) continue;

        $brand = $item['brand'] ?? ($item['products'][0]['brandName'] ?? null);
        $description = $item['description'] ?? null;
        if (!$description && isset($item['products'][0]['description'])) {
            $parts = array_column($item['products'][0]['description'], 'paragraph');
            $description = implode(', ', $parts);
        }

        // Image
        $image = $item['image'] ?? null;
        if (!$image && isset($item['offerImages']['url']['normal'])) {
            $image = $item['offerImages']['url']['normal'];
        } elseif (!$image && isset($item['offerImages']['url']['large'])) {
            $image = $item['offerImages']['url']['large'];
        } elseif (!$image && isset($item['offerImages']['url']['thumbnail'])) {
            $image = $item['offerImages']['url']['thumbnail'];
        } elseif (!$image && isset($item['products'][0]['images'][0]['url'])) {
            $image = $item['products'][0]['images'][0]['url'];
        }

        // Unit price
        $unitPrice = null;
        $unitSize = null;
        $baseStr = $item['prices']['priceByBaseUnit'] ?? '';
        if (empty($baseStr) && isset($item['deals'])) {
            foreach ($item['deals'] as $deal) {
                if (!empty($deal['priceByBaseUnit'])) {
                    $baseStr = $deal['priceByBaseUnit'];
                    break;
                }
            }
        }
        if (!empty($baseStr)) {
            // Format A: "1 l = 1,10" or "500 g = 7,98"
            if (preg_match('/([0-9.,]+)\s*(kg|g|l|ml|stk)\s*=\s*([0-9.,]+)/i', $baseStr, $m2)) {
                $qty = (float)str_replace(',', '.', $m2[1]);
                $unitSize = $qty . ' ' . strtolower($m2[2]);
                $unitPrice = (float)str_replace(',', '.', $m2[3]);
            }
            // Format B: "(1.10 / l)" or "(4.47 - 6.58 / kg)" — Netto
            if ($unitPrice === null && preg_match('/\(([0-9.,]+)\s*(?:[–\-]\s*[0-9.,]+\s*)?\/\s*(kg|l)\)/i', $baseStr, $m2)) {
                $unitPrice = (float)str_replace(',', '.', $m2[1]);
                $unitSize = '1 ' . strtolower($m2[2]);
            }
        }

        // Fallback: extract size from product title or description
        $fallbackSources = array_filter([$title, $description]);
        foreach ($fallbackSources as $source) {
            if ($unitPrice !== null) break;
            // Multi-pack: "6x0,5 L" -> 3 L
            if (preg_match('/([0-9]+[.,]?[0-9]*)\s*x\s*([0-9]+[.,]?[0-9]*)\s*(l|ml|g|kg)\b/i', $source, $m)) {
                $multi = (int)str_replace(',', '.', $m[1]);
                $single = (float)str_replace(',', '.', $m[2]);
                $totalQty = $multi * $single;
                $unit = strtolower($m[3]);
                $unitSize = $totalQty . ' ' . $unit;
                if ($price > 0) {
                    $divisor = ($unit === 'g' || $unit === 'ml') ? 1000 : 1;
                    $unitPrice = round($price / ($totalQty / $divisor), 2);
                }
            }
            // Single item: "1,5 L", "200 g", "1-kg-Schale", "1-l-Pckg."
            if ($unitPrice === null && preg_match('/([0-9]+[.,]?[0-9]*)\s*-?\s*(l|ml|kg|g)\b/i', $source, $m)) {
                $qty = (float)str_replace(',', '.', $m[1]);
                $unit = strtolower($m[2]);
                $unitSize = $qty . ' ' . $unit;
                if ($price > 0 && $qty > 0) {
                    $divisor = ($unit === 'g' || $unit === 'ml') ? 1000 : 1;
                    $unitPrice = round($price / ($qty / $divisor), 2);
                }
            }
        }

        // Date validity
        $validFrom = null;
        $validUntil = null;
        if (!empty($item['validFrom'])) {
            $ts = strtotime($item['validFrom']);
            if ($ts) $validFrom = date('Y-m-d', $ts);
        } elseif (isset($item['publicationProfiles'][0]['validity']['startDate'])) {
            $ts = strtotime($item['publicationProfiles'][0]['validity']['startDate']);
            if ($ts) $validFrom = date('Y-m-d', $ts);
        }
        if (!empty($item['validUntil'])) {
            $ts = strtotime($item['validUntil']);
            if ($ts) $validUntil = date('Y-m-d', $ts);
        } elseif (isset($item['publicationProfiles'][0]['validity']['endDate'])) {
            $ts = strtotime($item['publicationProfiles'][0]['validity']['endDate']);
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
            $pdo->prepare("UPDATE product_prices SET price = ?, unit_size = ?, unit_price = ?, scraped_at = NOW() WHERE id = ?")
                ->execute([$price, $unitSize, $unitPrice, (int)$row['id']]);
        } else {
            $pdo->prepare("INSERT INTO product_prices (product_id, store_id, price, unit_size, unit_price, scraped_at) VALUES (?, ?, ?, ?, ?, NOW())")
                ->execute([$pid, $storeId, $price, $unitSize, $unitPrice]);
        }

        $imported++;
    }

    // Remove prices not updated in this scrape
    $stmt = $pdo->prepare("DELETE FROM product_prices WHERE store_id = ? AND scraped_at < ?");
    $stmt->execute([$storeId, $batch_time]);
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
