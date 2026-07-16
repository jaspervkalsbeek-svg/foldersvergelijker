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
    'ah'       => ['name' => 'Albert Heijn', 'country' => 'NL'],
    'lidl-nl'  => ['name' => 'Lidl',          'country' => 'NL'],
    'aldi-nl'  => ['name' => 'Aldi',          'country' => 'NL'],
    'plus'     => ['name' => 'Plus',          'country' => 'NL'],
];

$store = $_GET['store'] ?? '';
if (!isset($available[$store])) {
    echo json_encode(['error' => 'Onbekende winkel: ' . $store]);
    exit;
}

$info = $available[$store];
$nodeScript = realpath(__DIR__ . '/../scrapers/node/scrape-store.mjs');
$flyerArg = $store === 'lidl-de' ? ' aktionsprospekt-13-07-2026-18-07-2026-4ff4e5' : '';
$cmd = sprintf('node "%s" "%s"%s 2>&1', $nodeScript, $store, $flyerArg);

exec($cmd, $out, $rc);

$progress = [];
$products = null;

foreach ($out as $line) {
    $t = trim($line);
    if (!$t) continue;
    if ($t[0] === '[' && !str_starts_with($t, '[{')) {
        $progress[] = $t;
    } elseif (str_starts_with($t, '[{')) {
        $products = json_decode($t, true);
    }
}

// ── Persist to database ──
$imported = 0;
$error = null;

if ($rc === 0 && is_array($products) && count($products) > 0) {
    try {
        // Find DB store matching by name + country
        $stmt = $pdo->prepare("SELECT id, name, country FROM stores WHERE active = 1");
        $stmt->execute();
        $dbStores = $stmt->fetchAll();

        $filtered = array_filter($dbStores, fn($s) => strtolower($s['country']) === strtolower($info['country']));
        $storeDb = null;
        foreach ($filtered ?: $dbStores as $s) {
            if (strtolower($s['name']) === strtolower($info['name'])) {
                $storeDb = $s; break;
            }
        }
        if (!$storeDb) {
            foreach (($filtered ?: $dbStores) as $s) {
                $sn = strtolower($s['name']);
                $mn = strtolower($info['name']);
                if (str_contains($sn, $mn) || str_contains($mn, $sn)) {
                    $storeDb = $s; break;
                }
            }
        }

        if ($storeDb) {
            $cats = $pdo->query("SELECT id, slug FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
            $catSlugs = [];
            foreach ($cats as $id => $slug) $catSlugs[$slug] = $id;

            $batch_time = date('Y-m-d H:i:s');
            foreach ($products as $item) {
                $name = $item['name'] ?? '';
                if (empty($name)) continue;
                $brand = $item['brand'] ?? null;
                $price = (float)($item['price'] ?? 0);
                $unitSize = $item['unit_size'] ?? null;
                $unitPrice = $item['unit_price'] ?? null;
                $image = $item['image'] ?? null;
                if ($price <= 0) continue;

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

                $catId = categorizeProduct($name, $catSlugs);
                $pid = upsertProduct($pdo, $name, $brand, null, $catId, $image);
                upsertPrice($pdo, $pid, (int)$storeDb['id'], $price, $unitSize, $unitPrice);
                $imported++;
        }

        // Remove prices not updated in this scrape
        $stmt = $pdo->prepare("DELETE FROM product_prices WHERE store_id = ? AND scraped_at < ?");
        $stmt->execute([(int)$storeDb['id'], $batch_time]);
        $deleted = $stmt->rowCount();
        if ($deleted > 0) $progress[] = "[cleanup] {$deleted} verouderde prijzen opgeruimd";
    } else {
        $error = 'Store niet gevonden in database';
    }
    } catch (Exception $e) {
        $error = 'DB fout: ' . $e->getMessage();
    }
}

echo json_encode([
    'store'    => $store,
    'name'     => $info['name'],
    'country'  => $info['country'],
    'success'  => $rc === 0,
    'exitCode' => $rc,
    'progress' => $progress,
    'count'    => is_array($products) ? count($products) : 0,
    'imported' => $imported,
    'error'    => $error,
    'products' => $products,
]);
