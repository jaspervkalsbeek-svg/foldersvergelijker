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
$cmd = sprintf('node "%s" "%s" 2>&1', $nodeScript, $store);

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
            // Get categories
            $cats = $pdo->query("SELECT id, slug FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
            $catSlugs = [];
            foreach ($cats as $id => $slug) $catSlugs[$slug] = $id;

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

                // Categorize
                $catId = null;
                $nameLower = mb_strtolower($name);
                $catMap = [
                    'melk'=>'zuivel-eieren','kaas'=>'zuivel-eieren','yoghurt'=>'zuivel-eieren','ei'=>'zuivel-eieren',
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
                $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND (brand = ? OR (brand IS NULL AND ? IS NULL)) LIMIT 1");
                $stmt->execute([$name, $brand, $brand]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $pid = (int)$existing['id'];
                    $pdo->prepare("UPDATE products SET category_id = COALESCE(NULLIF(category_id, 0), ?), image_url = COALESCE(NULLIF(image_url, ''), ?) WHERE id = ?")
                        ->execute([$catId, $image, $pid]);
                } else {
                    $pdo->prepare("INSERT INTO products (name, brand, category_id, image_url) VALUES (?, ?, ?, ?)")
                        ->execute([$name, $brand, $catId, $image]);
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

        // Remove old prices for this store (keep newest per product, max 7 days)
        $stmt = $pdo->prepare("DELETE pp FROM product_prices pp
            WHERE pp.store_id = ?
            AND NOT (pp.id IN (
                SELECT keep_id FROM (
                    SELECT MAX(pp2.id) as keep_id FROM product_prices pp2
                    WHERE pp2.store_id = ? GROUP BY pp2.product_id
                ) latest
            ))
            AND pp.scraped_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute([(int)$storeDb['id'], (int)$storeDb['id']]);
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
