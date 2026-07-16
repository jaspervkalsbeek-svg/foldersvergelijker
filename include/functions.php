<?php
function formatPrice(float $price): string
{
    return '€ ' . number_format($price, 2, ',', '.');
}

function truncateText(string $text, int $length = 60): string
{
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '...';
}

function getProductLink(int $id): string
{
    return 'product.php?id=' . $id;
}

function getStoreLogo(string $name): string
{
    $logos = [
        'Albert Heijn' => 'ah.svg',
        'Jumbo'        => 'jumbo.svg',
        'Lidl'         => 'lidl.svg',
        'Aldi'         => 'aldi.svg',
        'Plus'         => 'plus.svg',
        'Dirk'         => 'dirk.svg',
        'Rewe'         => 'rewe.svg',
        'Edeka'        => 'edeka.svg',
        'Netto'        => 'netto.svg',
    ];

    $nameLower = mb_strtolower($name);
    foreach ($logos as $storeName => $filename) {
        if (str_contains($nameLower, mb_strtolower($storeName))) {
            return 'assets/logos/' . $filename;
        }
    }

    return 'assets/logos/default.svg';
}

function getStoreColor(string $name): string
{
    $colors = [
        'Albert Heijn' => '#00a1e4',
        'Jumbo'        => '#e2001a',
        'Lidl'         => '#0050aa',
        'Aldi Nord'    => '#002b5e',
        'Aldi Süd'     => '#0050aa',
        'Aldi'         => '#002b5e',
        'Plus'         => '#f39200',
        'Dirk'         => '#00843d',
        'Rewe'         => '#e30613',
        'Edeka'        => '#142c8a',
        'Netto'        => '#d71920',
        'Vomar'        => '#e6007e',
        'Hoogvliet'    => '#00843d',
        'Poiesz'       => '#003d7a',
        'Boni'         => '#e2001a',
        'Coop'         => '#00843d',
        'DekaMarkt'    => '#003399',
        'Kaufland'     => '#e30613',
        'Rossmann'     => '#c8102e',
        'DM'           => '#e3000f',
    ];

    $nameLower = mb_strtolower($name);
    foreach ($colors as $storeName => $color) {
        if (str_contains($nameLower, mb_strtolower($storeName))) {
            return $color;
        }
    }

    return '#666';
}

function formatUnitPrice(?float $unitPrice, ?string $unitSize): string
{
    if ($unitPrice === null || $unitPrice <= 0) return '';
    if (!$unitSize) return '€ ' . number_format($unitPrice, 2, ',', '.') . '/kg';

    $unitLower = mb_strtolower($unitSize);
    if (str_contains($unitLower, 'l') && !str_contains($unitLower, 'cl')) {
        return '€ ' . number_format($unitPrice, 2, ',', '.') . '/L';
    }
    return '€ ' . number_format($unitPrice, 2, ',', '.') . '/kg';
}

function formatUnitPrice100g(?float $unitPrice): string
{
    if ($unitPrice === null || $unitPrice <= 0) return '';
    $per100g = $unitPrice / 10;
    return '€ ' . number_format($per100g, 2, ',', '.') . '/100g';
}

function getCategoryMap(string $lang = 'NL'): array
{
    static $maps = [];
    if ($maps) return $maps[$lang] ?? $maps['NL'];
    $maps['NL'] = [
        'melk'=>'zuivel-eieren','kaas'=>'zuivel-eieren','yoghurt'=>'zuivel-eieren','ei'=>'zuivel-eieren',
        'brood'=>'brood-ontbijtgranen','cracker'=>'brood-ontbijtgranen','muesli'=>'brood-ontbijtgranen',
        'fruit'=>'fruit-groente','groente'=>'fruit-groente','appel'=>'fruit-groente','banaan'=>'fruit-groente',
        'tomaat'=>'fruit-groente','komkommer'=>'fruit-groente','sla'=>'fruit-groente','aardappel'=>'fruit-groente',
        'vlees'=>'vlees-vis','kip'=>'vlees-vis','rund'=>'vlees-vis','gehakt'=>'vlees-vis',
        'vis'=>'vlees-vis','zalm'=>'vlees-vis','filet'=>'vlees-vis',
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
    $maps['DE'] = [
        'milch'=>'zuivel-eieren','käse'=>'zuivel-eieren','joghurt'=>'zuivel-eieren','ei'=>'zuivel-eieren',
        'brot'=>'brood-ontbijtgranen','müsli'=>'brood-ontbijtgranen',
        'obst'=>'fruit-groente','gemüse'=>'fruit-groente','apfel'=>'fruit-groente','banane'=>'fruit-groente',
        'tomate'=>'fruit-groente','gurke'=>'fruit-groente','salat'=>'fruit-groente','kartoffel'=>'fruit-groente',
        'fleisch'=>'vlees-vis','hähnchen'=>'vlees-vis','rind'=>'vlees-vis','hack'=>'vlees-vis',
        'fisch'=>'vlees-vis','lachs'=>'vlees-vis','wurst'=>'vlees-vis',
        'tiefkühl'=>'diepvries','eis'=>'diepvries',
        'cola'=>'dranken','wasser'=>'dranken','saft'=>'dranken','bier'=>'dranken','wein'=>'dranken',
        'chips'=>'snacks-zoetigheid','schokolade'=>'snacks-zoetigheid','kekse'=>'snacks-zoetigheid',
        'bonbon'=>'snacks-zoetigheid','snack'=>'snacks-zoetigheid',
        'pasta'=>'pasta-rijst','spaghetti'=>'pasta-rijst','reis'=>'pasta-rijst',
        'suppe'=>'conserven-sauzen','sauce'=>'conserven-sauzen',
        'shampoo'=>'persoonlijke-verzorging','zahnpasta'=>'persoonlijke-verzorging',
        'windeln'=>'baby','hund'=>'huisdier','katze'=>'huisdier',
    ];
    return $maps[$lang] ?? $maps['NL'];
}

function categorizeProduct(string $name, array $catSlugs, string $lang = 'NL'): ?int
{
    $map = getCategoryMap($lang);
    $nameLower = mb_strtolower($name);
    foreach ($map as $keyword => $slug) {
        if (str_contains($nameLower, $keyword)) return $catSlugs[$slug] ?? null;
    }
    return null;
}

function upsertProduct(PDO $pdo, string $name, ?string $brand, ?string $description, ?int $categoryId, ?string $image): int
{
    if ($brand !== null) {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? AND (brand = ? OR (brand IS NULL AND ? IS NULL)) LIMIT 1");
        $stmt->execute([$name, $brand, $brand]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
    }
    $existing = $stmt->fetch();

    if ($existing) {
        $pid = (int)$existing['id'];
        $pdo->prepare("UPDATE products SET category_id = COALESCE(NULLIF(category_id, 0), ?), image_url = COALESCE(NULLIF(image_url, ''), ?) WHERE id = ?")
            ->execute([$categoryId, $image, $pid]);
        if ($description) {
            $pdo->prepare("UPDATE products SET description = COALESCE(NULLIF(description, ''), ?) WHERE id = ? AND (description IS NULL OR description = '')")
                ->execute([$description, $pid]);
        }
    } else {
        $pdo->prepare("INSERT INTO products (name, brand, description, category_id, image_url) VALUES (?, ?, ?, ?, ?)")
            ->execute([$name, $brand, $description, $categoryId, $image]);
        $pid = (int)$pdo->lastInsertId();
    }
    return $pid;
}

function upsertPrice(PDO $pdo, int $productId, int $storeId, float $price, ?string $unitSize = null, ?float $unitPrice = null): void
{
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT id FROM product_prices WHERE product_id = ? AND store_id = ? AND DATE(scraped_at) = ? LIMIT 1");
    $stmt->execute([$productId, $storeId, $today]);
    $row = $stmt->fetch();

    if ($row) {
        $pdo->prepare("UPDATE product_prices SET price = ?, unit_size = ?, unit_price = ?, scraped_at = NOW() WHERE id = ?")
            ->execute([$price, $unitSize, $unitPrice, (int)$row['id']]);
    } else {
        $pdo->prepare("INSERT INTO product_prices (product_id, store_id, price, unit_size, unit_price, scraped_at) VALUES (?, ?, ?, ?, ?, NOW())")
            ->execute([$productId, $storeId, $price, $unitSize, $unitPrice]);
    }
}

function getCategoryName(string $slug): string
{
    $names = [
        'zuivel-eieren' => 'Zuivel & eieren',
        'brood-ontbijtgranen' => 'Brood & ontbijtgranen',
        'fruit-groente' => 'Fruit & groente',
        'vlees-vis' => 'Vlees & vis',
        'diepvries' => 'Diepvries',
        'dranken' => 'Dranken',
        'snacks-zoetigheid' => 'Snacks & zoetigheid',
        'pasta-rijst' => 'Pasta & rijst',
        'conserven-sauzen' => 'Conserven & sauzen',
        'huishouden' => 'Huishouden',
        'persoonlijke-verzorging' => 'Persoonlijke verzorging',
        'drogisterij' => 'Drogisterij',
        'baby' => 'Baby',
        'huisdier' => 'Huisdier',
        'overig' => 'Overig',
    ];

    return $names[$slug] ?? $slug;
}
