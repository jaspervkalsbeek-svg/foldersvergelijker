<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Producten overzicht – Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="adminstyle.css">
</head>
<body>
<?php
session_start();
require_once __DIR__ . '/../include/auth.php';
require_once __DIR__ . '/../config/database.php';

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
if ($search) {
    $where = "WHERE p.name LIKE :search OR p.brand LIKE :search";
    $params[':search'] = "%$search%";
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name,
           COUNT(DISTINCT pp.store_id) as store_count,
           MIN(pp.price) as lowest_price,
           GROUP_CONCAT(DISTINCT CONCAT(s.name, ':', pp.price) ORDER BY pp.price ASC SEPARATOR ' | ') as price_list
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_prices pp ON pp.product_id = p.id
    LEFT JOIN stores s ON s.id = pp.store_id
    $where
    GROUP BY p.id, p.name, p.brand, p.category_id, p.ean, p.image_url, p.created_at, c.name
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();
?>
<aside class="sidebar">
    <div class="sidebar-logo">Admin Panel<span>Folders Vergelijker</span></div>
    <div class="nav-label">Folders Vergelijker</div>
    <a href="index.php"       class="nav-item"><span class="icon">🏠</span> Dashboard</a>
    <a href="add_product.php" class="nav-item"><span class="icon">📦</span> Product toevoegen</a>
    <a href="import.php"      class="nav-item"><span class="icon">📥</span> Import CSV/JSON</a>
    <a href="overview.php"    class="nav-item active"><span class="icon">📋</span> Producten overzicht</a>
    <div class="sidebar-footer"><a href="../public/index.php">← Terug naar site</a></div>
</aside>

<main class="main">
    <a href="index.php" class="back">← Terug naar dashboard</a>
    <div class="page-header">
        <h1>Producten overzicht</h1>
        <p><?= $total ?> producten in de database</p>
    </div>
    <div class="section">
        <form method="GET" class="toolbar" style="display:flex;gap:8px;margin-bottom:20px">
            <input type="text" name="search" placeholder="Zoeken op naam of merk..." style="flex:1"
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-edit" style="background:var(--yellow);color:#000;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-weight:600">Zoeken</button>
            <?php if ($search): ?>
                <a href="overview.php" style="color:var(--text-muted);display:flex;align-items:center">Reset</a>
            <?php endif; ?>
        </form>

        <?php if (empty($products)): ?>
            <div class="empty" style="text-align:center;padding:40px;color:var(--text-muted)">Geen producten gevonden.</div>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:1px">Product</th>
                        <th style="text-align:left;padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:1px">Merk</th>
                        <th style="text-align:left;padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:1px">Categorie</th>
                        <th style="text-align:right;padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:1px">Winkels</th>
                        <th style="text-align:right;padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:1px">Laagste prijs</th>
                        <th style="text-align:left;padding:10px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:1px">Toegevoegd</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td style="padding:10px 12px;border-bottom:1px solid var(--border-subtle)">
                            <a href="../public/product.php?id=<?= (int)$p['id'] ?>" style="color:var(--text);text-decoration:none;font-weight:500">
                                <?= htmlspecialchars(truncate_text($p['name'], 50)) ?>
                            </a>
                        </td>
                        <td style="padding:10px 12px;border-bottom:1px solid var(--border-subtle);color:var(--text-dim)"><?= htmlspecialchars($p['brand'] ?? '-') ?></td>
                        <td style="padding:10px 12px;border-bottom:1px solid var(--border-subtle);color:var(--text-dim)"><?= htmlspecialchars($p['cat_name'] ?? '-') ?></td>
                        <td style="padding:10px 12px;border-bottom:1px solid var(--border-subtle);text-align:right"><?= (int)$p['store_count'] ?></td>
                        <td style="padding:10px 12px;border-bottom:1px solid var(--border-subtle);text-align:right;font-weight:600;color:var(--yellow)"><?= $p['lowest_price'] ? '€' . number_format((float)$p['lowest_price'], 2, ',', '.') : '-' ?></td>
                        <td style="padding:10px 12px;border-bottom:1px solid var(--border-subtle);color:var(--text-muted);font-size:.82rem"><?= date('d-m-Y', strtotime($p['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
            <div style="display:flex;justify-content:center;gap:6px;margin-top:20px">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?search=<?= urlencode($search) ?>&page=<?= $i ?>"
                       style="padding:6px 12px;background:<?= $i === $page ? 'var(--yellow-dim)' : 'var(--card)' ?>;border:1px solid var(--border);border-radius:6px;color:<?= $i === $page ? 'var(--yellow)' : 'var(--text-dim)' ?>;text-decoration:none">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
<?php
function truncate_text($text, $len = 50) {
    if (mb_strlen($text) <= $len) return $text;
    return mb_substr($text, 0, $len) . '...';
}
?>
