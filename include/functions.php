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
