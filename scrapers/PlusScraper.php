<?php
require_once __DIR__ . '/ScraperBase.php';

class PlusScraper extends ScraperBase
{
    public function scrape(): array
    {
        $products = [];
        echo "  Scrapen van folders van PLUS...\n";

        $html = $this->fetchUrl('https://www.plus.nl/aanbiedingen');
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
                    'brand'    => $node['brand'] ?? null,
                    'image'    => $node['image'] ?? $node['productImage'] ?? null,
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
        echo "  [*] " . count($items) . " producten gevonden\n";
        foreach ($items as $item) {
            $name = $item['name'] ?? '';
            if (empty($name)) continue;
            $price = is_numeric($item['price']) ? (float)$item['price'] : 0;

            $category = $this->categorizeProduct($name);
            $catId = $category ? $this->findCategoryId($category) : null;
            $pid = $this->saveProduct($name, null, $catId, null, null);
            if ($price > 0) $this->savePrice($pid, $price, null, null);

            $products[] = ['name' => $name, 'price' => $price, 'store' => 'PLUS', 'category' => $category];
        }
        return $products;
    }

    private function scrapeFallback(string $html, array &$products): array
    {
        echo "  [*] Fallback: HTML parsing...\n";

        preg_match_all('/<div[^>]*class="[^"]*offer[^"]*"[^>]*>(.*?)<\/div>/s', $html, $offers);
        foreach ($offers[1] as $offer) {
            $name = '';
            if (preg_match('/<h[23][^>]*>(.*?)<\/h[23]>/s', $offer, $m)) {
                $name = trim(strip_tags($m[1]));
            }
            if (empty($name)) continue;

            $price = 0;
            if (preg_match('/€\s*([0-9]+[.,][0-9]{2})/', $offer, $m)) {
                $price = (float)str_replace(',', '.', str_replace('.', '', $m[1]));
            }

            $category = $this->categorizeProduct($name);
            $catId = $category ? $this->findCategoryId($category) : null;
            $pid = $this->saveProduct($name, null, $catId, null, null);
            if ($price > 0) $this->savePrice($pid, $price, null, null);

            $products[] = ['name' => $name, 'price' => $price, 'store' => 'PLUS', 'category' => $category];
        }

        echo "  [*] " . count($products) . " producten gevonden via fallback\n";
        return $products;
    }

    private function categorizeProduct(string $name): ?string
    {
        $name = mb_strtolower($name);
        $map = [
            'melk' => 'zuivel-eieren', 'kaas' => 'zuivel-eieren', 'yoghurt' => 'zuivel-eieren',
            'brood' => 'brood-ontbijtgranen',
            'fruit' => 'fruit-groente', 'groente' => 'fruit-groente',
            'vlees' => 'vlees-vis', 'kip' => 'vlees-vis', 'gehakt' => 'vlees-vis',
            'vis' => 'vlees-vis',
            'diepvries' => 'diepvries',
            'cola' => 'dranken', 'fris' => 'dranken', 'sap' => 'dranken', 'bier' => 'dranken',
            'chips' => 'snacks-zoetigheid', 'chocola' => 'snacks-zoetigheid', 'koek' => 'snacks-zoetigheid',
            'pasta' => 'pasta-rijst', 'rijst' => 'pasta-rijst',
            'soep' => 'conserven-sauzen',
        ];
        foreach ($map as $keyword => $slug) {
            if (str_contains($name, $keyword)) return $slug;
        }
        return null;
    }
}
