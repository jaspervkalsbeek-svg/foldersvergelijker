<?php
require_once __DIR__ . '/config/database.php';

echo "Seed data toevoegen...\n";

$check = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
if ($check > 0) {
    echo "Er staan al $check producten in de database. Wil je opnieuw beginnen? (ja/nee): ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    if ($line !== 'ja') {
        echo "Stoppen.\n";
        exit;
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE product_prices");
    $pdo->exec("TRUNCATE TABLE products");
    $pdo->exec("TRUNCATE TABLE folders");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Data gewist.\n";
}

$stores = $pdo->query("SELECT id, name FROM stores WHERE active = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
$cats   = $pdo->query("SELECT id, slug FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
$catSlugToId = [];
foreach ($cats as $id => $slug) $catSlugToId[$slug] = $id;

$data = [
    // ── Zuivel & eieren ──
    ['name' => 'AH Halfvolle Melk', 'brand' => 'Albert Heijn', 'cat' => 'zuivel-eieren', 'size' => '1L', 'unit' => 1.0,
     'prices' => [['store' => 'Albert Heijn', 'price' => 1.39], ['store' => 'Jumbo', 'price' => 1.45], ['store' => 'Lidl (NL)', 'price' => 1.22], ['store' => 'Aldi (NL)', 'price' => 1.19]]],
    ['name' => 'AH Volle Melk', 'brand' => 'Albert Heijn', 'cat' => 'zuivel-eieren', 'size' => '1L', 'unit' => 1.0,
     'prices' => [['store' => 'Albert Heijn', 'price' => 1.45], ['store' => 'Jumbo', 'price' => 1.49], ['store' => 'Lidl (NL)', 'price' => 1.28], ['store' => 'Dirk', 'price' => 1.35]]],
    ['name' => 'Goudse Kaas Jong 48+', 'brand' => 'Milner', 'cat' => 'zuivel-eieren', 'size' => 'per kg', 'unit' => 1.0,
     'prices' => [['store' => 'Albert Heijn', 'price' => 8.29], ['store' => 'Jumbo', 'price' => 7.99], ['store' => 'Lidl (NL)', 'price' => 6.99], ['store' => 'Aldi (NL)', 'price' => 6.45]]],
    ['name' => 'Platte Yoghurt Vol', 'brand' => 'Arla', 'cat' => 'zuivel-eieren', 'size' => '500g', 'unit' => 0.5,
     'prices' => [['store' => 'Albert Heijn', 'price' => 1.99], ['store' => 'Jumbo', 'price' => 2.05], ['store' => 'Lidl (NL)', 'price' => 1.65], ['store' => 'Plus', 'price' => 1.89]]],
    ['name' => 'Eieren M (10 stuks)', 'brand' => 'AH Basic', 'cat' => 'zuivel-eieren', 'size' => '10 stuks', 'unit' => null,
     'prices' => [['store' => 'Albert Heijn', 'price' => 3.49], ['store' => 'Jumbo', 'price' => 3.29], ['store' => 'Lidl (NL)', 'price' => 2.99], ['store' => 'Aldi (NL)', 'price' => 2.85]]],

    // ── Brood & ontbijtgranen ──
    ['name' => 'Tijgerbrood Wit', 'brand' => 'AH Basic', 'cat' => 'brood-ontbijtgranen', 'size' => 'heel', 'unit' => null,
     'prices' => [['store' => 'Albert Heijn', 'price' => 1.59], ['store' => 'Jumbo', 'price' => 1.55], ['store' => 'Lidl (NL)', 'price' => 1.29], ['store' => 'Aldi (NL)', 'price' => 1.19]]],
    ['name' => 'Volkoren Brood', 'brand' => 'AH Basic', 'cat' => 'brood-ontbijtgranen', 'size' => 'heel', 'unit' => null,
     'prices' => [['store' => 'Albert Heijn', 'price' => 1.69], ['store' => 'Jumbo', 'price' => 1.65], ['store' => 'Lidl (NL)', 'price' => 1.39], ['store' => 'Dirk', 'price' => 1.29]]],
    ['name' => 'Muesli Fruit & Noten', 'brand' => 'AH', 'cat' => 'brood-ontbijtgranen', 'size' => '500g', 'unit' => 0.5,
     'prices' => [['store' => 'Albert Heijn', 'price' => 3.49], ['store' => 'Jumbo', 'price' => 3.29], ['store' => 'Lidl (NL)', 'price' => 2.69], ['store' => 'Aldi (NL)', 'price' => 2.45]]],
    ['name' => 'Crackers Volkoren', 'brand' => 'Bolletje', 'cat' => 'brood-ontbijtgranen', 'size' => '175g', 'unit' => 0.175,
     'prices' => [['store' => 'Albert Heijn', 'price' => 2.29], ['store' => 'Jumbo', 'price' => 2.15], ['store' => 'Lidl (NL)', 'price' => 1.79], ['store' => 'Plus', 'price' => 1.99]]],

    // ── Fruit & groente ──
    ['name' => 'Appels Elstar', 'brand' => null, 'cat' => 'fruit-groente', 'size' => 'per kg', 'unit' => 1.0,
     'prices' => [['store' => 'Albert Heijn', 'price' => 2.39], ['store' => 'Jumbo', 'price' => 2.19], ['store' => 'Lidl (NL)', 'price' => 1.79], ['store' => 'Aldi (NL)', 'price' => 1.69], ['store' => 'Dirk', 'price' => 1.89]]],
    ['name' => 'Bananen', 'brand' => null, 'cat' => 'fruit-groente', 'size' => 'per kg', 'unit' => 1.0,
     'prices' => [['store' => 'Albert Heijn', 'price' => 1.59], ['store' => 'Jumbo', 'price' => 1.49], ['store' => 'Lidl (NL)', 'price' => 1.29], ['store' => 'Aldi (NL)', 'price' => 1.19], ['store' => 'Plus', 'price' => 1.39]]],
    ['name' => 'Trostomaten', 'brand' => null, 'cat' => 'fruit-groente', 'size' => 'per kg', 'unit' => 1.0,
     'prices' => [['store' => 'Albert Heijn', 'price' => 2.19], ['store' => 'Jumbo', 'price' => 1.99], ['store' => 'Lidl (NL)', 'price' => 1.59], ['store' => 'Aldi (NL)', 'price' => 1.49]]],
    ['name' => 'Komkommer', 'brand' => null, 'cat' => 'fruit-groente', 'size' => 'per stuk', 'unit' => null,
     'prices' => [['store' => 'Albert Heijn', 'price' => 1.09], ['store' => 'Jumbo', 'price' => 0.99], ['store' => 'Lidl (NL)', 'price' => 0.79], ['store' => 'Dirk', 'price' => 0.85]]],
    ['name' => 'Aardappelen Kruimig (2kg)', 'brand' => null, 'cat' => 'fruit-groente', 'size' => '2kg', 'unit' => 2.0,
     'prices' => [['store' => 'Albert Heijn', 'price' => 3.19], ['store' => 'Jumbo', 'price' => 2.99], ['store' => 'Lidl (NL)', 'price' => 2.49], ['store' => 'Aldi (NL)', 'price' => 2.29]]],

    // ── Vlees & vis ──
    ['name' => 'Kipfilet (300g)', 'brand' => null, 'cat' => 'vlees-vis', 'size' => '300g', 'unit' => 0.3,
     'prices' => [['store' => 'Albert Heijn', 'price' => 4.69], ['store' => 'Jumbo', 'price' => 4.49], ['store' => 'Lidl (NL)', 'price' => 3.79], ['store' => 'Aldi (NL)', 'price' => 3.49], ['store' => 'Dirk', 'price' => 3.99]]],
    ['name' => 'Rundergehakt (500g)', 'brand' => null, 'cat' => 'vlees-vis', 'size' => '500g', 'unit' => 0.5,
     'prices' => [['store' => 'Albert Heijn', 'price' => 5.49], ['store' => 'Jumbo', 'price' => 5.29], ['store' => 'Lidl (NL)', 'price' => 4.49], ['store' => 'Plus', 'price' => 4.79]]],
    ['name' => 'Zalmfilet (200g)', 'brand' => null, 'cat' => 'vlees-vis', 'size' => '200g', 'unit' => 0.2,
     'prices' => [['store' => 'Albert Heijn', 'price' => 7.99], ['store' => 'Jumbo', 'price' => 7.49], ['store' => 'Lidl (NL)', 'price' => 6.49], ['store' => 'Aldi (NL)', 'price' => 5.99]]],
    ['name' => 'Braadworst 4-pack', 'brand' => 'Unox', 'cat' => 'vlees-vis', 'size' => '300g', 'unit' => 0.3,
     'prices' => [['store' => 'Albert Heijn', 'price' => 3.29], ['store' => 'Jumbo', 'price' => 3.15], ['store' => 'Lidl (NL)', 'price' => 2.59], ['store' => 'Aldi (NL)', 'price' => 2.45]]],

    // ── Dranken ──
    ['name' => 'Coca Cola (1,5L)', 'brand' => 'Coca-Cola', 'cat' => 'dranken', 'size' => '1,5L', 'unit' => 1.5,
     'prices' => [['store' => 'Albert Heijn', 'price' => 2.39], ['store' => 'Jumbo', 'price' => 2.29], ['store' => 'Lidl (NL)', 'price' => 1.79], ['store' => 'Aldi (NL)', 'price' => 1.55], ['store' => 'Dirk', 'price' => 1.89]]],
    ['name' => 'Sinaasappelsap (1L)', 'brand' => 'Appelsientje', 'cat' => 'dranken', 'size' => '1L', 'unit' => 1.0,
     'prices' => [['store' => 'Albert Heijn', 'price' => 2.09], ['store' => 'Jumbo', 'price' => 1.99], ['store' => 'Lidl (NL)', 'price' => 1.65], ['store' => 'Plus', 'price' => 1.79]]],
    ['name' => 'Spa Rood (1,5L)', 'brand' => 'Spa', 'cat' => 'dranken', 'size' => '1,5L', 'unit' => 1.5,
     'prices' => [['store' => 'Albert Heijn', 'price' => 1.49], ['store' => 'Jumbo', 'price' => 1.39], ['store' => 'Lidl (NL)', 'price' => 1.09], ['store' => 'Aldi (NL)', 'price' => 0.99]]],
    ['name' => 'Hertog Jan Pilsener (6x33cl)', 'brand' => 'Hertog Jan', 'cat' => 'dranken', 'size' => '6x33cl (1,98L)', 'unit' => 1.98,
     'prices' => [['store' => 'Albert Heijn', 'price' => 6.99], ['store' => 'Jumbo', 'price' => 6.49], ['store' => 'Lidl (NL)', 'price' => 5.49], ['store' => 'Aldi (NL)', 'price' => 4.99]]],

    // ── Duitse producten ──
    ['name' => 'Vollmilch 3,5% (1L)', 'brand' => 'Milbona', 'cat' => 'zuivel-eieren', 'size' => '1L', 'unit' => 1.0,
     'prices' => [['store' => 'Lidl (DE)', 'price' => 1.09], ['store' => 'Aldi (DE)', 'price' => 1.05], ['store' => 'Rewe', 'price' => 1.19], ['store' => 'Edeka', 'price' => 1.15], ['store' => 'Netto', 'price' => 1.07]]],
    ['name' => 'Gouda Käse jung (200g)', 'brand' => 'Milbona', 'cat' => 'zuivel-eieren', 'size' => '200g', 'unit' => 0.2,
     'prices' => [['store' => 'Lidl (DE)', 'price' => 2.49], ['store' => 'Aldi (DE)', 'price' => 2.29], ['store' => 'Rewe', 'price' => 2.79], ['store' => 'Edeka', 'price' => 2.69]]],
    ['name' => 'Hähnchenbrustfilet (400g)', 'brand' => null, 'cat' => 'vlees-vis', 'size' => '400g', 'unit' => 0.4,
     'prices' => [['store' => 'Lidl (DE)', 'price' => 3.99], ['store' => 'Aldi (DE)', 'price' => 3.79], ['store' => 'Rewe', 'price' => 4.49], ['store' => 'Netto', 'price' => 3.89]]],
    ['name' => 'Coca Cola (1,5L)', 'brand' => 'Coca-Cola', 'cat' => 'dranken', 'size' => '1,5L', 'unit' => 1.5,
     'prices' => [['store' => 'Lidl (DE)', 'price' => 1.49], ['store' => 'Aldi (DE)', 'price' => 1.29], ['store' => 'Rewe', 'price' => 1.79], ['store' => 'Edeka', 'price' => 1.69], ['store' => 'Netto', 'price' => 1.39]]],
    ['name' => 'Bratwurst (4 Stück)', 'brand' => null, 'cat' => 'vlees-vis', 'size' => '400g', 'unit' => 0.4,
     'prices' => [['store' => 'Lidl (DE)', 'price' => 2.49], ['store' => 'Aldi (DE)', 'price' => 2.29], ['store' => 'Rewe', 'price' => 3.19], ['store' => 'Edeka', 'price' => 2.99]]],
    ['name' => 'Apfel (1kg)', 'brand' => null, 'cat' => 'fruit-groente', 'size' => '1kg', 'unit' => 1.0,
     'prices' => [['store' => 'Lidl (DE)', 'price' => 1.99], ['store' => 'Aldi (DE)', 'price' => 1.79], ['store' => 'Rewe', 'price' => 2.49], ['store' => 'Edeka', 'price' => 2.29]]],

    // ── Snacks & zoetigheid ──
    ['name' => "Lay's Naturel Chips (225g)", 'brand' => "Lay's", 'cat' => 'snacks-zoetigheid', 'size' => '225g', 'unit' => 0.225,
     'prices' => [['store' => 'Albert Heijn', 'price' => 2.69], ['store' => 'Jumbo', 'price' => 2.49], ['store' => 'Lidl (NL)', 'price' => 1.99], ['store' => 'Aldi (NL)', 'price' => 1.85]]],
    ['name' => "Tony's Chocolonely Melk", 'brand' => "Tony's", 'cat' => 'snacks-zoetigheid', 'size' => '180g', 'unit' => 0.18,
     'prices' => [['store' => 'Albert Heijn', 'price' => 4.99], ['store' => 'Jumbo', 'price' => 4.79], ['store' => 'Lidl (NL)', 'price' => 3.99], ['store' => 'Dirk', 'price' => 4.29]]],

    // ── Pasta & rijst ──
    ['name' => 'Spaghetti (500g)', 'brand' => 'AH Basic', 'cat' => 'pasta-rijst', 'size' => '500g', 'unit' => 0.5,
     'prices' => [['store' => 'Albert Heijn', 'price' => 0.89], ['store' => 'Jumbo', 'price' => 0.79], ['store' => 'Lidl (NL)', 'price' => 0.59], ['store' => 'Aldi (NL)', 'price' => 0.49]]],
    ['name' => 'Penne (500g)', 'brand' => 'AH Basic', 'cat' => 'pasta-rijst', 'size' => '500g', 'unit' => 0.5,
     'prices' => [['store' => 'Albert Heijn', 'price' => 0.99], ['store' => 'Jumbo', 'price' => 0.89], ['store' => 'Lidl (NL)', 'price' => 0.69], ['store' => 'Plus', 'price' => 0.79]]],
    ['name' => 'Basmatirijst (500g)', 'brand' => 'Lassie', 'cat' => 'pasta-rijst', 'size' => '500g', 'unit' => 0.5,
     'prices' => [['store' => 'Albert Heijn', 'price' => 2.19], ['store' => 'Jumbo', 'price' => 1.99], ['store' => 'Lidl (NL)', 'price' => 1.59], ['store' => 'Aldi (NL)', 'price' => 1.49]]],

    // ── Conserven & sauzen ──
    ['name' => 'Tomatenblokjes (400g)', 'brand' => 'AH Basic', 'cat' => 'conserven-sauzen', 'size' => '400g', 'unit' => 0.4,
     'prices' => [['store' => 'Albert Heijn', 'price' => 0.79], ['store' => 'Jumbo', 'price' => 0.69], ['store' => 'Lidl (NL)', 'price' => 0.49], ['store' => 'Aldi (NL)', 'price' => 0.39]]],
    ['name' => 'Groentensoep (900ml)', 'brand' => 'Unox', 'cat' => 'conserven-sauzen', 'size' => '900ml', 'unit' => 0.9,
     'prices' => [['store' => 'Albert Heijn', 'price' => 1.99], ['store' => 'Jumbo', 'price' => 1.89], ['store' => 'Lidl (NL)', 'price' => 1.49], ['store' => 'Aldi (NL)', 'price' => 1.29]]],
];

$count = 0;
foreach ($data as $item) {
    $catId = $catSlugToId[$item['cat']] ?? null;
    $stmt = $pdo->prepare('INSERT INTO products (name, brand, category_id) VALUES (?, ?, ?)');
    $stmt->execute([$item['name'], $item['brand'], $catId]);
    $productId = (int)$pdo->lastInsertId();

    foreach ($item['prices'] as $priceData) {
        $storeName = $priceData['store'];
        $storeId = null;
        foreach ($stores as $sid => $sname) {
            $sn = mb_strtolower($sname);
            $sm = mb_strtolower($storeName);
            if ($sn === $sm || str_contains($sn, $sm) || str_contains($sm, $sn)) { $storeId = $sid; break; }
        }
        if ($storeId) {
            $price = $priceData['price'];
            $unitSize = $item['size'];
            $unitPrice = $item['unit'] ? round($price / $item['unit'], 2) : null; // prijs per kg/L
            $stmt = $pdo->prepare('INSERT INTO product_prices (product_id, store_id, price, unit_size, unit_price, scraped_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$productId, $storeId, $price, $unitSize, $unitPrice]);
        }
    }
    $count++;
}

echo "$count producten toegevoegd met eenheidsprijzen!\n";

echo "\nSamenvatting per winkel:\n";
$stmt = $pdo->query("SELECT s.name, s.country, COUNT(pp.id) as prices FROM stores s LEFT JOIN product_prices pp ON pp.store_id = s.id GROUP BY s.id, s.name, s.country HAVING prices > 0 ORDER BY s.country, s.name");
while ($row = $stmt->fetch()) printf("  %-20s (%s): %d prijzen\n", $row['name'], $row['country'], $row['prices']);

echo "\nProducten met prijs per eenheid (kg/L):\n";
$stmt = $pdo->query("SELECT p.name, MIN(pp.price) as totaal, MIN(pp.unit_price) as per_kg, pp.unit_size FROM products p JOIN product_prices pp ON pp.product_id = p.id WHERE pp.unit_price IS NOT NULL GROUP BY p.id, p.name, pp.unit_size ORDER BY per_kg ASC LIMIT 10");
while ($row = $stmt->fetch()) printf("  %-30s  €%-5.2f (%s) → €%.2f/kg\n", $row['name'], $row['totaal'], $row['unit_size'], $row['per_kg']);

echo "\nVergelijking Kipfilet vs Hähnchenbrust:\n";
$stmt = $pdo->query("SELECT p.name, pp.price, pp.unit_size, pp.unit_price, s.name as store FROM products p JOIN product_prices pp ON pp.product_id = p.id JOIN stores s ON s.id = pp.store_id WHERE p.name LIKE '%Kipfilet%' OR p.name LIKE '%Hähnchenbrust%' ORDER BY p.name, pp.price");
while ($row = $stmt->fetch()) printf("  %-30s @ %-15s €%-5.2f (%s) = €%.2f/kg\n", $row['name'], $row['store'], $row['price'], $row['unit_size'], $row['unit_price']);
