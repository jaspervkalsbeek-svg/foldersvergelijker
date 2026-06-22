<?php
require_once __DIR__ . '/../config/database.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

$stmt = $pdo->prepare("SELECT DISTINCT p.name FROM products p
    JOIN product_prices pp ON pp.product_id = p.id
    WHERE p.name LIKE ? ORDER BY p.name LIMIT 15");
$stmt->execute(["%$q%"]);
$results = $stmt->fetchAll(PDO::FETCH_COLUMN);

header('Content-Type: application/json');
echo json_encode($results);
