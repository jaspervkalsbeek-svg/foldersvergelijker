<?php
/**
 * CLI import script voor Folders Vergelijker
 *
 * Voorbeelden:
 *   php import.php bestand.csv
 *   php import.php bestand.json
 *   php import.php --format=csv data.txt
 *   php import.php --help
 */

require_once __DIR__ . '/config/database.php';

// ── Helpers ──
function logMsg(string $msg, string $type = 'INFO'): void {
    $colors = ['INFO' => "\033[36m", 'OK' => "\033[32m", 'WARN' => "\033[33m", 'ERR' => "\033[31m"];
    $c = $colors[$type] ?? "\033[0m";
    echo "  {$c}[{$type}]\033[0m {$msg}\n";
}

// ── Store + category lookup ──
$storeList = $pdo->query("SELECT id, name FROM stores WHERE active = 1")->fetchAll(PDO::FETCH_ASSOC);
$catList   = $pdo->query("SELECT id, name, slug FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

function storeId(string $name, array $stores): ?int {
    $name = mb_strtolower(trim($name));
    foreach ($stores as $s) {
        $sn = mb_strtolower($s['name']);
        if ($sn === $name || str_contains($sn, $name) || str_contains($name, $sn)) return (int)$s['id'];
    }
    return null;
}

function catId(string $slugOrName, array $cats): ?int {
    $input = mb_strtolower(trim($slugOrName));
    foreach ($cats as $c) {
        if (mb_strtolower($c['slug']) === $input || mb_strtolower($c['name']) === $input) return (int)$c['id'];
    }
    return null;
}

// ── Parsers ──
function parseCSVFile(string $path): array {
    $content = file_get_contents($path);
    $lines = explode("\n", $content);
    if (empty($lines)) return [];
    $header = str_getcsv(array_shift($lines));
    $header = array_map('trim', $header);
    $products = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        $row = str_getcsv($line);
        $row = array_pad($row, count($header), '');
        $data = array_combine($header, $row);

        $name  = trim($data['name'] ?? $data['product'] ?? '');
        if (empty($name)) continue;

        $store = trim($data['store'] ?? $data['store_name'] ?? $data['winkel'] ?? '');
        $price = (float)str_replace(',', '.', str_replace('€', '', trim($data['price'] ?? $data['prijs'] ?? '0')));

        if (empty($store) || $price <= 0) {
            $products[$name] = [
                'name'     => $name,
                'brand'    => trim($data['brand'] ?? $data['merk'] ?? ''),
                'category' => trim($data['category'] ?? $data['categorie'] ?? $data['cat'] ?? ''),
                'ean'      => trim($data['ean'] ?? ''),
                'prices'   => $products[$name]['prices'] ?? [],
            ];
            continue;
        }

        if (!isset($products[$name])) {
            $products[$name] = [
                'name'     => $name,
                'brand'    => trim($data['brand'] ?? $data['merk'] ?? ''),
                'category' => trim($data['category'] ?? $data['categorie'] ?? $data['cat'] ?? ''),
                'ean'      => trim($data['ean'] ?? ''),
                'prices'   => [],
            ];
        }
        $unitSize = trim($data['unit_size'] ?? $data['verpakking'] ?? $data['size'] ?? '');
        $unitPrice = $data['unit_price'] ?? $data['prijs_per_kg'] ?? $data['price_per_kg'] ?? '';
        $unitPrice = $unitPrice !== '' ? (float)str_replace(',', '.', str_replace('€', '', trim($unitPrice))) : null;
        $products[$name]['prices'][] = ['store' => $store, 'price' => $price, 'unit_size' => $unitSize ?: null, 'unit_price' => $unitPrice];
    }

    return array_values($products);
}

function parseJSONFile(string $path): array {
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    if (!$data) return [];
    if (isset($data['name'])) $data = [$data];

    $products = [];
    foreach ($data as $item) {
        $name = trim($item['name'] ?? $item['product'] ?? '');
        if (empty($name)) continue;

        $prices = [];
        foreach ($item['prices'] ?? $item['prijzen'] ?? [] as $p) {
            $sn = trim($p['store'] ?? $p['winkel'] ?? $p['store_name'] ?? '');
            $pr = (float)str_replace(',', '.', str_replace('€', '', trim($p['price'] ?? $p['prijs'] ?? '0')));
            $us = trim($p['unit_size'] ?? $p['verpakking'] ?? $p['size'] ?? '');
            $up = $p['unit_price'] ?? $p['prijs_per_kg'] ?? $p['price_per_kg'] ?? '';
            $up = $up !== '' ? (float)str_replace(',', '.', str_replace('€', '', trim($up))) : null;
            if ($sn && $pr > 0) $prices[] = ['store' => $sn, 'price' => $pr, 'unit_size' => $us ?: null, 'unit_price' => $up];
        }

        $products[] = [
            'name'     => $name,
            'brand'    => trim($item['brand'] ?? $item['merk'] ?? ''),
            'category' => trim($item['category'] ?? $item['categorie'] ?? $item['cat'] ?? ''),
            'ean'      => trim($item['ean'] ?? ''),
            'prices'   => $prices,
        ];
    }
    return $products;
}

// ── Import ──
function doImport(array $products, PDO $pdo, array $stores, array $cats): int {
    $imported = 0;
    foreach ($products as $prod) {
        $name  = $prod['name'];
        $brand = $prod['brand'] ?: null;
        $slug  = $prod['category'];
        $ean   = $prod['ean'] ?: null;
        $catId = $slug ? catId($slug, $cats) : null;

        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND (brand = ? OR (brand IS NULL AND ? IS NULL)) LIMIT 1");
        $stmt->execute([$name, $brand, $brand]);
        $existing = $stmt->fetch();

        if ($existing) {
            $pid = (int)$existing['id'];
            $upd = $pdo->prepare("UPDATE products SET category_id = COALESCE(NULLIF(category_id, 0), ?), ean = COALESCE(NULLIF(ean, ''), ?) WHERE id = ?");
            $upd->execute([$catId, $ean, $pid]);
            logMsg("Bijgewerkt: $name", 'OK');
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (name, brand, category_id, ean) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $brand, $catId, $ean]);
            $pid = (int)$pdo->lastInsertId();
            logMsg("Nieuw product: $name", 'OK');
        }

        foreach ($prod['prices'] as $pr) {
            $sid = storeId($pr['store'], $stores);
            if (!$sid) { logMsg("Winkel niet gevonden: '{$pr['store']}' voor '$name'", 'WARN'); continue; }
            $price = (float)$pr['price'];
            $today = date('Y-m-d');

            $unitSize  = $pr['unit_size'] ?? null;
            $unitPrice = $pr['unit_price'] ?? null;
            if ($unitPrice === null && $unitSize && $price > 0) {
                // Auto-calculate: parse size like "300g" -> 0.3 kg, "1,5L" -> 1.5 L
                $sizeStr = str_replace(',', '.', $unitSize);
                if (preg_match('/([0-9.]+)\s*(kg|g|l|ml)/i', $sizeStr, $m)) {
                    $qty = (float)$m[1];
                    $unit = strtolower($m[2]);
                    if ($unit === 'g') $qty /= 1000;
                    if ($unit === 'ml') $qty /= 1000;
                    if ($qty > 0) $unitPrice = round($price / $qty, 2);
                }
            }

            $stmt = $pdo->prepare("SELECT id, price FROM product_prices WHERE product_id = ? AND store_id = ? AND DATE(scraped_at) = ? LIMIT 1");
            $stmt->execute([$pid, $sid, $today]);
            $row = $stmt->fetch();

            if ($row) {
                if ((float)$row['price'] !== $price) {
                    $pdo->prepare("UPDATE product_prices SET price = ?, unit_size = ?, unit_price = ?, scraped_at = NOW() WHERE id = ?")->execute([$price, $unitSize, $unitPrice, (int)$row['id']]);
                    logMsg("Prijs update: $name @ {$pr['store']} = €$price", 'OK');
                }
            } else {
                $pdo->prepare("INSERT INTO product_prices (product_id, store_id, price, unit_size, unit_price, scraped_at) VALUES (?, ?, ?, ?, ?, NOW())")->execute([$pid, $sid, $price, $unitSize, $unitPrice]);
                logMsg("Prijs toegevoegd: $name @ {$pr['store']} = €$price", 'OK');
            }
        }
        $imported++;
    }
    return $imported;
}

// ── Main ──
$help = <<<HELP
\033[1mFolders Vergelijker – Import\033[0m

\033[33mGebruik:\033[0m
  php import.php <bestand>              Auto-detect (csv/json o.b.v. extensie)
  php import.php --format=csv <file>    Forceer CSV
  php import.php --format=json <file>   Forceer JSON
  php import.php --help                 Dit help scherm

\033[33mVoorbeelden:\033[0m
  php import.php data.csv
  php import.php --format=json data.json
  php import.php voorbeeld.csv

\033[33mCSV Formaat:\033[0m
  name,brand,category,ean,store,price
  Productnaam,Merk,categorie-slug,,Winkelnaam,1.99

\033[33mJSON Formaat:\033[0m
  [{"name":"...","brand":"...","category":"...","prices":[{"store":"...","price":1.99}]}]

HELP;

$args = $argv ?? [];
if (in_array('--help', $args) || in_array('-h', $args) || count($args) < 2) {
    echo $help;
    exit;
}

$format = 'auto';
$file   = null;

foreach ($args as $i => $arg) {
    if ($arg === '--format=csv') $format = 'csv';
    elseif ($arg === '--format=json') $format = 'json';
    elseif (str_starts_with($arg, '--format=')) $format = substr($arg, 9);
    elseif ($i > 0 && !str_starts_with($arg, '-')) $file = $arg;
}

if (!$file) {
    logMsg("Geef een bestand op.", 'ERR');
    echo $help;
    exit(1);
}

if (!file_exists($file)) {
    logMsg("Bestand niet gevonden: $file", 'ERR');
    exit(1);
}

if ($format === 'auto') {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'csv' || $ext === 'txt') $format = 'csv';
    elseif ($ext === 'json') $format = 'json';
    else { logMsg("Onbekend formaat voor .$ext. Gebruik --format=csv of --format=json", 'ERR'); exit(1); }
}

echo "\n";
echo "  ╔═══════════════════════════════════════╗\n";
echo "  ║   Folders Vergelijker – Import        ║\n";
echo "  ╚═══════════════════════════════════════╝\n\n";
echo "  Bestand: " . basename($file) . "\n";
echo "  Formaat: " . strtoupper($format) . "\n\n";

$products = ($format === 'json') ? parseJSONFile($file) : parseCSVFile($file);

if (empty($products)) {
    logMsg("Geen producten gevonden.", 'ERR');
    exit(1);
}

$totalPrices = array_sum(array_map(fn($p) => count($p['prices']), $products));
echo "  Producten: " . count($products) . "\n";
echo "  Prijzen:   $totalPrices\n\n";

echo "  ── Import starten? (y/n): ";
$handle = fopen("php://stdin", "r");
$answer = trim(fgets($handle));
if (mb_strtolower($answer) !== 'y') {
    logMsg("Geannuleerd.", 'WARN');
    exit;
}

echo "\n";
$count = doImport($products, $pdo, $storeList, $catList);
echo "\n  ── Gereed! $count producten geïmporteerd.\n\n";
