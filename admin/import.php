<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import – Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="adminstyle.css">
    <style>
        .import-format { background:rgba(255,255,255,.03); border:1px solid var(--border-subtle); border-radius:10px; padding:16px; margin-bottom:16px }
        .import-format code { display:block; white-space:pre; font-size:.8rem; color:var(--text-dim); line-height:1.6; padding:8px; background:rgba(0,0,0,.3); border-radius:6px; overflow-x:auto }
        .import-format h4 { font-size:.85rem; font-weight:600; margin-bottom:8px; color:var(--yellow); text-transform:uppercase; letter-spacing:.5px }
        .tab-bar { display:flex; gap:0; margin-bottom:24px; border-bottom:1px solid var(--border) }
        .tab-btn { padding:10px 24px; background:none; border:none; border-bottom:2px solid transparent; color:var(--text-dim); cursor:pointer; font-family:inherit; font-size:.9rem; transition:all var(--transition) }
        .tab-btn:hover { color:var(--text) }
        .tab-btn.active { color:var(--yellow); border-bottom-color:var(--yellow) }
        .tab-content { display:none }
        .tab-content.active { display:block }
        .log { background:rgba(0,0,0,.4); border-radius:8px; padding:14px; margin-top:16px; font-family:monospace; font-size:.82rem; max-height:300px; overflow-y:auto }
        .log .ok { color:#81c784 }
        .log .warn { color:#ffd54f }
        .log .err { color:#ef9a9a }
        .drop-zone { border:2px dashed var(--border); border-radius:var(--radius); padding:40px; text-align:center; cursor:pointer; transition:all var(--transition); margin-bottom:16px }
        .drop-zone:hover, .drop-zone.dragover { border-color:var(--yellow); background:var(--yellow-dim) }
        .drop-zone p { color:var(--text-muted); margin-bottom:8px }
        .drop-zone .icon { font-size:2rem; margin-bottom:8px }
        .preview-table { width:100%; border-collapse:collapse; margin:16px 0; font-size:.85rem }
        .preview-table th { background:var(--surface); padding:8px 10px; text-align:left; border-bottom:1px solid var(--border); color:var(--text-muted); font-size:.72rem; text-transform:uppercase }
        .preview-table td { padding:8px 10px; border-bottom:1px solid var(--border-subtle); color:var(--text-dim) }
        .summary { display:flex; gap:20px; margin-bottom:20px }
        .summary-card { flex:1; background:var(--card); border:1px solid var(--border); border-radius:var(--radius-sm); padding:16px; text-align:center }
        .summary-card .num { font-size:1.6rem; font-weight:700; color:var(--yellow) }
        .summary-card .lbl { font-size:.78rem; color:var(--text-muted); margin-top:4px }
        .inline-flex { display:flex; gap:12px; align-items:center; flex-wrap:wrap }
    </style>
</head>
<body>
<?php
session_start();
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../include/functions.php';

$log     = [];
$preview = null;
$step    = $_GET['step'] ?? 'upload';
$format  = 'csv';

// ── Store + category lookup ──
$storeList = $pdo->query("SELECT id, name FROM stores WHERE active = 1")->fetchAll(PDO::FETCH_ASSOC);
$catList   = $pdo->query("SELECT id, name, slug FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// ── Fuzzy store match ──
function matchStore(string $name, array $stores): ?int {
    $name = mb_strtolower(trim($name));
    foreach ($stores as $s) {
        $sn = mb_strtolower($s['name']);
        if ($sn === $name || str_contains($sn, $name) || str_contains($name, $sn)) return (int)$s['id'];
    }
    return null;
}

// ── Fuzzy category match ──
function matchCategory(string $slugOrName, array $cats): ?int {
    $input = mb_strtolower(trim($slugOrName));
    foreach ($cats as $c) {
        if (mb_strtolower($c['slug']) === $input || mb_strtolower($c['name']) === $input) return (int)$c['id'];
    }
    return null;
}

// ── Parse CSV ──
 function parseCSV(string $content): array {
    $lines = explode("\n", $content);
    if (empty($lines)) return [];
    $header = str_getcsv(array_shift($lines));
    $header = array_map('trim', $header);
    $products = [];
    $current  = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        $row = str_getcsv($line);
        $row = array_pad($row, count($header), '');
        $data = array_combine($header, $row);

        $name = trim($data['name'] ?? $data['product'] ?? '');
        if (empty($name)) continue;

        $store = trim($data['store'] ?? $data['store_name'] ?? $data['winkel'] ?? '');
        $price = (float)str_replace(',', '.', str_replace('€', '', trim($data['price'] ?? $data['prijs'] ?? '0')));

        // If no store/price on this row, it's a product-only row
        if (empty($store) || $price <= 0) {
            $current = [
                'name'     => $name,
                'brand'    => trim($data['brand'] ?? $data['merk'] ?? ''),
                'category' => trim($data['category'] ?? $data['categorie'] ?? $data['cat'] ?? ''),
                'ean'      => trim($data['ean'] ?? ''),
                'prices'   => [],
            ];
            $products[$name] = $current;
            continue;
        }

        // If we already have this product, add price
        if (isset($products[$name])) {
            $unitSize = trim($data['unit_size'] ?? $data['verpakking'] ?? $data['size'] ?? '');
            $unitPrice = $data['unit_price'] ?? $data['prijs_per_kg'] ?? '';
            $unitPrice = $unitPrice !== '' ? (float)str_replace(',', '.', str_replace('€', '', trim($unitPrice))) : null;
            $products[$name]['prices'][] = ['store' => $store, 'price' => $price, 'unit_size' => $unitSize ?: null, 'unit_price' => $unitPrice];
        } else {
            $products[$name] = [
                'name'     => $name,
                'brand'    => trim($data['brand'] ?? $data['merk'] ?? ''),
                'category' => trim($data['category'] ?? $data['categorie'] ?? $data['cat'] ?? ''),
                'ean'      => trim($data['ean'] ?? ''),
                'prices'   => [],
            ];
        }
    }

    return array_values($products);
 }

function parseJSON(string $content): array {
    $data = json_decode($content, true);
    if (!$data) return [];

    // Normalize: if single object, wrap in array
    if (isset($data['name'])) $data = [$data];

    $products = [];
    foreach ($data as $item) {
        $name = trim($item['name'] ?? $item['product'] ?? '');
        if (empty($name)) continue;

        $prices = [];
        foreach ($item['prices'] ?? $item['prijzen'] ?? [] as $p) {
            $storeName = trim($p['store'] ?? $p['winkel'] ?? $p['store_name'] ?? '');
            $price     = (float)str_replace(',', '.', str_replace('€', '', trim($p['price'] ?? $p['prijs'] ?? '0')));
            $unitSize  = trim($p['unit_size'] ?? $p['verpakking'] ?? $p['size'] ?? '');
            $unitPrice = $p['unit_price'] ?? $p['prijs_per_kg'] ?? '';
            $unitPrice = $unitPrice !== '' ? (float)str_replace(',', '.', str_replace('€', '', trim($unitPrice))) : null;
            if ($storeName && $price > 0) {
                $prices[] = ['store' => $storeName, 'price' => $price, 'unit_size' => $unitSize ?: null, 'unit_price' => $unitPrice];
            }
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

// ── Import logic ──
function doImport(array $products, PDO $pdo, array $stores, array $cats, array &$log): int {
    $imported = 0;
    foreach ($products as $prod) {
        $name = $prod['name'];
        $brand = $prod['brand'] ?: null;
        $slug  = $prod['category'];
        $ean   = $prod['ean'] ?: null;

        $catId = null;
        if ($slug) $catId = matchCategory($slug, $cats);

        // Check if product exists (by exact name, or by name + brand)
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND (brand = ? OR (brand IS NULL AND ? IS NULL)) LIMIT 1");
        $stmt->execute([$name, $brand, $brand]);
        $existing = $stmt->fetch();

        if ($existing) {
            $productId = (int)$existing['id'];
            // Update category/ean if empty
            $upd = $pdo->prepare("UPDATE products SET category_id = COALESCE(NULLIF(category_id, 0), ?), ean = COALESCE(NULLIF(ean, ''), ?) WHERE id = ?");
            $upd->execute([$catId, $ean, $productId]);
            $log[] = ['type' => 'ok', 'msg' => "Bijgewerkt: $name (ID $productId)"];
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (name, brand, category_id, ean) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $brand, $catId, $ean]);
            $productId = (int)$pdo->lastInsertId();
            $log[] = ['type' => 'ok', 'msg' => "Nieuw product: $name (ID $productId)"];
        }

        // Insert prices
        foreach ($prod['prices'] as $pr) {
            $storeId = matchStore($pr['store'], $stores);
            if (!$storeId) {
                $log[] = ['type' => 'warn', 'msg' => "Winkel niet gevonden: '{$pr['store']}' voor '$name'"];
                continue;
            }
            $price = (float)$pr['price'];
            $unitSize = $pr['unit_size'] ?? null;
            $unitPrice = $pr['unit_price'] ?? null;
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

            // Upsert price (same product + store + date)
            $today = date('Y-m-d');
            $stmt = $pdo->prepare("SELECT id, price FROM product_prices WHERE product_id = ? AND store_id = ? AND DATE(scraped_at) = ? LIMIT 1");
            $stmt->execute([$productId, $storeId, $today]);
            $row = $stmt->fetch();

            if ($row) {
                if ((float)$row['price'] !== $price) {
                    $upd = $pdo->prepare("UPDATE product_prices SET price = ?, unit_size = ?, unit_price = ?, scraped_at = NOW() WHERE id = ?");
                    $upd->execute([$price, $unitSize, $unitPrice, (int)$row['id']]);
                    $log[] = ['type' => 'ok', 'msg' => "Prijs geüpdatet: $name @ {$pr['store']} = €$price"];
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO product_prices (product_id, store_id, price, unit_size, unit_price, scraped_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$productId, $storeId, $price, $unitSize, $unitPrice]);
                $log[] = ['type' => 'ok', 'msg' => "Prijs toegevoegd: $name @ {$pr['store']} = €$price"];
            }
        }
        $imported++;
    }
    return $imported;
}

// ── Handle form submissions ──
$result = null;
$activeTab = 'upload';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $format = $_POST['format'] ?? 'csv';

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $content = file_get_contents($_FILES['file']['tmp_name']);
        $activeTab = 'preview';
    } elseif (!empty($_POST['raw'])) {
        $content = $_POST['raw'];
        $activeTab = 'preview';
    } else {
        $log[] = ['type' => 'err', 'msg' => 'Geen bestand of data ontvangen.'];
        $activeTab = 'upload';
    }

    if (!empty($content)) {
        $products = ($format === 'json') ? parseJSON($content) : parseCSV($content);

        if (empty($products)) {
            $log[] = ['type' => 'err', 'msg' => 'Geen producten gevonden in de data. Controleer het formaat.'];
            $activeTab = 'upload';
        } elseif (isset($_POST['confirm'])) {
            $count = doImport($products, $pdo, $storeList, $catList, $log);
            $result = "$count producten geïmporteerd!";
            $activeTab = 'result';
        } else {
            $preview = $products;
            $activeTab = 'preview';
            $log[] = ['type' => 'ok', 'msg' => count($products) . ' producten gevonden. Controleer de preview en klik op "Bevestig import".'];
        }
    }
}
?>
<aside class="sidebar">
    <div class="sidebar-logo">Admin Panel<span>Folders Vergelijker</span></div>
    <div class="nav-label">Beheer</div>
    <a href="index.php" class="nav-item"><span class="icon">🏠</span> Dashboard</a>
    <a href="add_product.php" class="nav-item"><span class="icon">📦</span> Product toevoegen</a>
    <a href="import.php" class="nav-item active"><span class="icon">📥</span> Import CSV/JSON</a>
    <a href="overview.php" class="nav-item"><span class="icon">📋</span> Producten overzicht</a>
    <div class="sidebar-footer"><a href="../public/index.php">← Terug naar site</a></div>
</aside>

<main class="main">
    <a href="index.php" class="back">← Terug naar dashboard</a>
    <div class="page-header">
        <h1>Import</h1>
        <p>Importeer producten en prijzen via CSV of JSON</p>
    </div>

    <?php if ($result): ?>
        <div class="alert success"><?= htmlspecialchars($result) ?></div>
    <?php endif; ?>

    <div class="tab-bar">
        <button class="tab-btn <?= $activeTab === 'upload' ? 'active' : '' ?>" onclick="switchTab('upload')">Upload</button>
        <button class="tab-btn <?= $activeTab === 'preview' ? 'active' : '' ?>" onclick="switchTab('preview')">Preview</button>
        <button class="tab-btn <?= $activeTab === 'result' ? 'active' : '' ?>" onclick="switchTab('result')">Resultaat</button>
    </div>

    <!-- Upload tab -->
    <div class="tab-content <?= $activeTab === 'upload' ? 'active' : '' ?>" id="tab-upload">
        <div class="section">
            <div class="section-title">CSV Formaat</div>
            <div class="import-format">
                <h4>Voorbeeld CSV</h4>
                <code>name,brand,category,ean,store,price
AH Halfvolle Melk,Albert Heijn,zuivel-eieren,,Albert Heijn,1.39
AH Halfvolle Melk,,,,"Jumbo",1.45
,,,,"Lidl",1.22
Banaan,,fruit-groente,,Albert Heijn,1.59
Banaan,,,,Jumbo,1.49</code>
                <p style="margin-top:8px;font-size:.82rem;color:var(--text-muted)">
                    Eén rij per product (zonder winkel/prijs) óf één rij per product+prijs.
                    Herhaal dezelfde productnaam om meerdere prijzen toe te voegen.
                </p>
            </div>
        </div>

        <div class="section">
            <div class="section-title">JSON Formaat</div>
            <div class="import-format">
                <h4>Voorbeeld JSON</h4>
                <code>[
  {
    "name": "AH Halfvolle Melk",
    "brand": "Albert Heijn",
    "category": "zuivel-eieren",
    "ean": "",
    "prices": [
      { "store": "Albert Heijn", "price": 1.39 },
      { "store": "Jumbo", "price": 1.45 },
      { "store": "Lidl", "price": 1.22 }
    ]
  }
]</code>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Importeer data</div>
            <form method="POST" enctype="multipart/form-data">
                <div class="field">
                    <label>Formaat</label>
                    <select name="format" id="formatSelect" onchange="updateDropZone()">
                        <option value="csv" <?= $format === 'csv' ? 'selected' : '' ?>>CSV</option>
                        <option value="json" <?= $format === 'json' ? 'selected' : '' ?>>JSON</option>
                    </select>
                </div>

                <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                    <div class="icon">📄</div>
                    <p><strong>Klik</strong> of <strong>sleep</strong> een bestand hierheen</p>
                    <p style="font-size:.82rem;color:var(--text-muted)" id="dropZoneHint">CSV of JSON bestand</p>
                    <input type="file" name="file" id="fileInput" accept=".csv,.json,.txt" style="display:none" onchange="handleFile(this)">
                </div>

                <div class="field">
                    <label>Of plak de data hier</label>
                    <textarea name="raw" rows="6" placeholder="Plak CSV of JSON data..." style="font-family:monospace;font-size:.85rem"><?= htmlspecialchars($_POST['raw'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="submit-btn">Voorbeeld bekijken →</button>
            </form>
        </div>
    </div>

    <!-- Preview tab -->
    <div class="tab-content <?= $activeTab === 'preview' ? 'active' : '' ?>" id="tab-preview">
        <?php if ($preview): ?>
            <div class="summary">
                <div class="summary-card">
                    <div class="num"><?= count($preview) ?></div>
                    <div class="lbl">Producten</div>
                </div>
                <div class="summary-card">
                    <div class="num"><?= array_sum(array_map(fn($p) => count($p['prices']), $preview)) ?></div>
                    <div class="lbl">Prijzen</div>
                </div>
                <div class="summary-card">
                    <?php
                    $stores = [];
                    foreach ($preview as $p) foreach ($p['prices'] as $pr) $stores[$pr['store']] = true;
                    ?>
                    <div class="num"><?= count($stores) ?></div>
                    <div class="lbl">Winkels</div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Voorbeeld (max 10 producten)</div>
                <table class="preview-table">
                    <thead><tr>
                        <th>Product</th><th>Merk</th><th>Categorie</th><th>Winkels</th><th>Prijzen</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach (array_slice($preview, 0, 10) as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                            <td><?= htmlspecialchars($p['brand'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($p['category'] ?: '-') ?></td>
                            <td><?= implode(', ', array_map(fn($pr) => $pr['store'], $p['prices'])) ?></td>
                            <td><?= implode(', ', array_map(fn($pr) => '€' . number_format($pr['price'], 2, ',', '.'), $p['prices'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($preview) > 10): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--text-muted)">... en nog <?= count($preview) - 10 ?> producten</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <form method="POST" style="margin-top:16px">
                    <input type="hidden" name="format" value="<?= htmlspecialchars($format) ?>">
                    <input type="hidden" name="raw" value="<?= htmlspecialchars($_POST['raw'] ?? '') ?>">
                    <?php if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK): ?>
                        <input type="hidden" name="file_imported" value="1">
                    <?php endif; ?>
                    <input type="hidden" name="confirm" value="1">
                    <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:12px">
                        Na bevestiging worden alle <?= count($preview) ?> producten met prijzen geïmporteerd.
                        Bestaande producten worden bijgewerkt, dubbele prijzen overslagen.
                    </p>
                    <button type="submit" class="submit-btn" style="background:#4caf50;color:#fff">✓ Import bevestigen</button>
                </form>
            </div>
        <?php else: ?>
            <div class="empty" style="text-align:center;padding:40px;color:var(--text-muted)">
                Geen data om te previewen. Upload eerst een bestand.
            </div>
        <?php endif; ?>
    </div>

    <!-- Result tab -->
    <div class="tab-content <?= $activeTab === 'result' ? 'active' : '' ?>" id="tab-result">
        <div class="section">
            <div class="section-title">Import logboek</div>
            <div class="log" id="log">
                <?php foreach ($log as $entry): ?>
                    <div class="<?= $entry['type'] ?>">[<?= strtoupper($entry['type']) ?>] <?= htmlspecialchars($entry['msg']) ?></div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:16px;display:flex;gap:12px">
                <a href="import.php" class="submit-btn" style="text-decoration:none;text-align:center">Nog een import</a>
                <a href="overview.php" class="submit-btn" style="background:var(--text-dim);text-decoration:none;text-align:center">Bekijk producten</a>
            </div>
        </div>
    </div>
</main>

<script>
function switchTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    document.querySelector(`.tab-btn[onclick*="${name}"]`).classList.add('active');
}

function updateDropZone() {
    const fmt = document.getElementById('formatSelect').value;
    document.getElementById('dropZoneHint').textContent = fmt.toUpperCase() + ' bestand';
    document.getElementById('fileInput').accept = fmt === 'csv' ? '.csv,.txt' : '.json,.txt';
}

function handleFile(input) {
    if (input.files.length > 0) {
        document.getElementById('dropZone').classList.add('dragover');
        document.querySelector('#dropZone p:first-of-type').textContent = input.files[0].name;
        input.form.submit();
    }
}

// Drag & drop
const dz = document.getElementById('dropZone');
['dragenter', 'dragover'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('dragover'); }));
['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('dragover'); }));
dz.addEventListener('drop', e => {
    const file = e.dataTransfer.files[0];
    if (file) {
        document.getElementById('fileInput').files = e.dataTransfer.files;
        dz.querySelector('p:first-of-type').textContent = file.name;
        document.getElementById('fileInput').form.submit();
    }
});
</script>
</body>
</html>
