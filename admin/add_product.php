<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product toevoegen – Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="adminstyle.css">
    <style>
        .store-price-row { display: flex; gap: 12px; align-items: center; margin-bottom: 8px; }
        .store-price-row select { flex: 1; }
        .store-price-row input[type="number"] { width: 120px; }
        .store-price-row .remove-btn { background: none; border: none; color: #f44336; cursor: pointer; font-size: 1.2rem; }
        .add-store-btn { width: 100%; padding: 10px; background: var(--yellow-dim); border: 1px dashed rgba(255,214,0,0.3); border-radius: 10px; color: var(--yellow); cursor: pointer; font-family: 'Inter', sans-serif; font-size: .9rem; transition: all var(--transition); }
        .add-store-btn:hover { background: rgba(255,214,0,0.1); border-color: var(--yellow); }
    </style>
</head>
<body>
<?php
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../config/database.php';

$success = '';
$error   = '';

$stores = $pdo->query("SELECT id, name, country FROM stores WHERE active = 1 ORDER BY country, name")->fetchAll();
$cats   = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $brand    = trim($_POST['brand'] ?? '');
    $catId    = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $ean      = trim($_POST['ean'] ?? '');
    $prices   = $_POST['prices'] ?? [];

    if (!$name) {
        $error = 'Productnaam is verplicht.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO products (name, brand, category_id, ean) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $brand ?: null, $catId, $ean ?: null]);
            $productId = (int)$pdo->lastInsertId();

            $priceCount = 0;
            foreach ($prices as $p) {
                $storeId = (int)($p['store_id'] ?? 0);
                $price   = (float)($p['price'] ?? 0);
                if ($storeId && $price > 0) {
                    $stmt = $pdo->prepare('INSERT INTO product_prices (product_id, store_id, price, scraped_at) VALUES (?, ?, ?, NOW())');
                    $stmt->execute([$productId, $storeId, $price]);
                    $priceCount++;
                }
            }

            $success = "Product <strong>" . htmlspecialchars($name) . "</strong> toegevoegd met $priceCount prijzen!";
        } catch (PDOException $e) {
            $error = 'Fout: ' . $e->getMessage();
        }
    }
}
?>
<aside class="sidebar">
    <div class="sidebar-logo">Admin Panel<span>Folders Vergelijker</span></div>
    <div class="nav-label">Beheer</div>
    <a href="index.php" class="nav-item"><span class="icon">🏠</span> Dashboard</a>
    <a href="add_product.php" class="nav-item active"><span class="icon">📦</span> Product toevoegen</a>
    <a href="import.php" class="nav-item"><span class="icon">📥</span> Import CSV/JSON</a>
    <a href="overview.php" class="nav-item"><span class="icon">📋</span> Producten overzicht</a>
    <div class="sidebar-footer"><a href="../public/index.php">← Terug naar site</a></div>
</aside>

<main class="main">
    <a href="index.php" class="back">← Terug naar dashboard</a>
    <div class="page-header">
        <h1>Product toevoegen</h1>
        <p>Voeg handmatig een product met prijzen toe</p>
    </div>
    <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
        <div class="section">
            <div class="section-title">Product gegevens</div>
            <div class="field">
                <label>Productnaam *</label>
                <input type="text" name="name" placeholder="Bijv. AH Halfvolle Melk" required
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="row">
                <div class="field">
                    <label>Merk</label>
                    <input type="text" name="brand" placeholder="Bijv. Albert Heijn"
                           value="<?= htmlspecialchars($_POST['brand'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Categorie</label>
                    <select name="category_id">
                        <option value="">-- Selecteer --</option>
                        <?php foreach ($cats as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= (($_POST['category_id'] ?? '') == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field">
                <label>EAN (optioneel)</label>
                <input type="text" name="ean" placeholder="8712345678901" maxlength="13"
                       value="<?= htmlspecialchars($_POST['ean'] ?? '') ?>">
            </div>
        </div>

        <div class="section">
            <div class="section-title">Prijzen per winkel</div>
            <div id="prices-container">
                <?php if (!empty($_POST['prices'])): ?>
                    <?php foreach ($_POST['prices'] as $i => $p): ?>
                    <div class="store-price-row">
                        <select name="prices[<?= $i ?>][store_id]">
                            <option value="">-- Kies winkel --</option>
                            <?php foreach ($stores as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ((int)($p['store_id'] ?? 0) === $s['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['name']) ?> (<?= $s['country'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="prices[<?= $i ?>][price]" placeholder="Prijs" step="0.01" min="0"
                               value="<?= htmlspecialchars($p['price'] ?? '') ?>">
                        <button type="button" class="remove-btn" onclick="this.parentElement.remove()">✕</button>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="store-price-row">
                        <select name="prices[0][store_id]">
                            <option value="">-- Kies winkel --</option>
                            <?php foreach ($stores as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['country'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="prices[0][price]" placeholder="Prijs" step="0.01" min="0">
                        <button type="button" class="remove-btn" onclick="this.parentElement.remove()">✕</button>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="add-store-btn" onclick="addStoreRow()">+ Winkelprijs toevoegen</button>
        </div>

        <button type="submit" class="submit-btn">Product opslaan →</button>
    </form>
</main>

<script>
let priceIndex = <?= max(1, count($_POST['prices'] ?? [1])) ?>;

function addStoreRow() {
    const i = priceIndex++;
    const html = `
    <div class="store-price-row">
        <select name="prices[${i}][store_id]">
            <option value="">-- Kies winkel --</option>
            <?php foreach ($stores as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['country'] ?>)</option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="prices[${i}][price]" placeholder="Prijs" step="0.01" min="0">
        <button type="button" class="remove-btn" onclick="this.parentElement.remove()">✕</button>
    </div>`;
    document.getElementById('prices-container').insertAdjacentHTML('beforeend', html);
}
</script>
</body>
</html>
