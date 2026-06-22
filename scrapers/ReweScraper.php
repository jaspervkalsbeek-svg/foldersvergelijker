<?php
require_once __DIR__ . '/ScraperBase.php';

class ReweScraper extends ScraperBase
{
    public function scrape(): array
    {
        $products = [];
        echo "  Scrapen van folders van Rewe...\n";

        $html = $this->fetchUrl('https://www.rewe.de/angebote/');
        if (!$html) return $products;

        preg_match_all('/<script[^>]*id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches);

        if (!empty($matches[1][0])) {
            $json = json_decode($matches[1][0], true);
            if ($json) {
                $items = $this->extractItems($json);
                return $this->processItems($items, $products);
            }
        }

        return $this->scrapeFallback($html, $products);
    }

    private function extractItems(array $json): array
    {
        $items = [];
        $walk = function ($node) use (&$walk, &$items) {
            if (!is_array($node)) return;
            if (isset($node['productName']) || isset($node['name'])) {
                $items[] = [
                    'name'     => $node['productName'] ?? $node['name'] ?? '',
                    'price'    => $node['price'] ?? $node['productPrice'] ?? 0,
                    'brand'    => $node['brand'] ?? $node['marke'] ?? null,
                    'image'    => $node['image'] ?? null,
                    'unitSize' => $node['unitSize'] ?? null,
                ];
            }
            foreach ($node as $v) if (is_array($v)) $walk($v);
        };
        $walk($json);
        return $items;
    }

    private function processItems(array $items, array &$products): array
    {
        echo "  [*] " . count($items) . " Produkte gefunden\n";
        foreach ($items as $item) {
            $name = $item['name'] ?? '';
            if (empty($name)) continue;
            $price = is_numeric($item['price']) ? (float)$item['price'] : 0;

            $category = $this->categorizeProduct($name);
            $catId = $category ? $this->findCategoryId($category) : null;
            $pid = $this->saveProduct($name, null, $catId, null, null);
            if ($price > 0) $this->savePrice($pid, $price, null, null);

            $products[] = ['name' => $name, 'price' => $price, 'store' => 'Rewe', 'category' => $category];
        }
        return $products;
    }

    private function scrapeFallback(string $html, array &$products): array
    {
        echo "  [*] Fallback: HTML-Parsing...\n";
        preg_match_all('/<div[^>]*data-testid="[^"]*product[^"]*"[^>]*>(.*?)<\/div>/s', $html, $divs);
        foreach ($divs[1] as $div) {
            $name = '';
            if (preg_match('/<span[^>]*class="[^"]*title[^"]*"[^>]*>(.*?)<\/span>/s', $div, $m)) {
                $name = trim(strip_tags($m[1]));
            }
            if (empty($name)) continue;

            $price = 0;
            if (preg_match('/€\s*([0-9]+[.,][0-9]{2})/', $div, $m)) {
                $price = (float)str_replace(',', '.', str_replace('.', '', $m[1]));
            }

            $category = $this->categorizeProduct($name);
            $catId = $category ? $this->findCategoryId($category) : null;
            $pid = $this->saveProduct($name, null, $catId, null, null);
            if ($price > 0) $this->savePrice($pid, $price, null, null);

            $products[] = ['name' => $name, 'price' => $price, 'store' => 'Rewe', 'category' => $category];
        }

        echo "  [*] " . count($products) . " Produkte via Fallback gefunden\n";
        return $products;
    }

    private function categorizeProduct(string $name): ?string
    {
        $name = mb_strtolower($name);
        $map = [
            'milch' => 'zuivel-eieren', 'käse' => 'zuivel-eieren',
            'brot' => 'brood-ontbijtgranen', 'brötchen' => 'brood-ontbijtgranen',
            'obst' => 'fruit-groente', 'gemüse' => 'fruit-groente',
            'fleisch' => 'vlees-vis', 'hähnchen' => 'vlees-vis',
            'fisch' => 'vlees-vis',
            'tiefkühl' => 'diepvries',
            'wasser' => 'dranken', 'saft' => 'dranken', 'bier' => 'dranken',
            'nudel' => 'pasta-rijst', 'reis' => 'pasta-rijst',
        ];
        foreach ($map as $keyword => $slug) {
            if (str_contains($name, $keyword)) return $slug;
        }
        return null;
    }
}
