<?php
require_once __DIR__ . '/ScraperBase.php';

class JumboScraper extends ScraperBase
{
    public function scrape(): array
    {
        $products = [];
        echo "  Scrapen van folders van Jumbo...\n";

        $html = $this->fetchUrl('https://www.jumbo.com/aanbiedingen');
        if (!$html) return $products;

        preg_match_all('/<script[^>]*id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches);

        if (!empty($matches[1][0])) {
            $json = json_decode($matches[1][0], true);
            if ($json) {
                $items = $this->extractItems($json);
                echo "  [*] " . count($items) . " producten gevonden\n";

                foreach ($items as $item) {
                    $name = $item['name'] ?? '';
                    if (empty($name)) continue;

                    $price = $item['price'] ?? 0;
                    $brand = $item['brand'] ?? null;
                    $imageUrl = $item['image'] ?? null;
                    $unitSize = $item['unitSize'] ?? null;

                    $category = $this->categorizeProduct($name);
                    $catId = $category ? $this->findCategoryId($category) : null;

                    $pid = $this->saveProduct($name, $brand, $catId, null, $imageUrl);
                    if ($price > 0) {
                        $this->savePrice($pid, (float)$price, $unitSize, null);
                    }

                    $products[] = [
                        'name'      => $name,
                        'brand'     => $brand,
                        'price'     => (float)$price,
                        'store'     => 'Jumbo',
                        'unit_size' => $unitSize,
                        'category'  => $category,
                    ];
                }
                return $products;
            }
        }

        return $this->scrapeFallback($html);
    }

    private function extractItems(array $json): array
    {
        $items = [];
        $walk = function ($node) use (&$walk, &$items) {
            if (!is_array($node)) return;
            if (isset($node['product']) && isset($node['product']['title'])) {
                $p = $node['product'];
                $items[] = [
                    'name'     => $p['title'] ?? '',
                    'brand'    => $p['brand'] ?? null,
                    'price'    => $p['price'] ?? $node['price'] ?? 0,
                    'unitSize' => $p['unitSize'] ?? null,
                    'image'    => $p['image'] ?? $p['imageUrl'] ?? null,
                ];
            }
            foreach ($node as $v) if (is_array($v)) $walk($v);
        };
        $walk($json);
        return $items;
    }

    private function scrapeFallback(string $html): array
    {
        echo "  [*] Fallback: HTML parsing...\n";
        $products = [];

        preg_match_all('/<div[^>]*class="[^"]*product-card[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/s', $html, $cards);
        foreach ($cards[1] as $card) {
            $name = '';
            if (preg_match('/<h[23][^>]*class="[^"]*title[^"]*"[^>]*>(.*?)<\/h[23]>/s', $card, $m)) {
                $name = trim(strip_tags($m[1]));
            }
            if (empty($name)) continue;

            $price = 0;
            if (preg_match('/€\s*([0-9]+[.,][0-9]{2})/', $card, $m)) {
                $price = (float)str_replace(',', '.', str_replace('.', '', $m[1]));
            }

            $category = $this->categorizeProduct($name);
            $catId = $category ? $this->findCategoryId($category) : null;
            $pid = $this->saveProduct($name, null, $catId, null, null);
            if ($price > 0) $this->savePrice($pid, $price, null, null);

            $products[] = ['name' => $name, 'price' => $price, 'store' => 'Jumbo', 'category' => $category];
        }

        echo "  [*] " . count($products) . " producten gevonden\n";
        return $products;
    }

    private function categorizeProduct(string $name): ?string
    {
        $name = mb_strtolower($name);
        $map = [
            'zuivel' => 'zuivel-eieren', 'melk' => 'zuivel-eieren', 'kaas' => 'zuivel-eieren',
            'brood' => 'brood-ontbijtgranen', 'crackers' => 'brood-ontbijtgranen',
            'fruit' => 'fruit-groente', 'groente' => 'fruit-groente', 'sla' => 'fruit-groente',
            'tomaat' => 'fruit-groente', 'komkommer' => 'fruit-groente', 'appel' => 'fruit-groente',
            'banaan' => 'fruit-groente', 'druiven' => 'fruit-groente', 'aardappel' => 'fruit-groente',
            'vlees' => 'vlees-vis', 'kip' => 'vlees-vis', 'rund' => 'vlees-vis', 'gehakt' => 'vlees-vis',
            'vis' => 'vlees-vis', 'zalm' => 'vlees-vis',
            'diepvries' => 'diepvries', 'diepvries' => 'diepvries', 'ijs' => 'diepvries',
            'cola' => 'dranken', 'fris' => 'dranken', 'sap' => 'dranken', 'water' => 'dranken',
            'bier' => 'dranken', 'wijn' => 'dranken',
            'chips' => 'snacks-zoetigheid', 'chocola' => 'snacks-zoetigheid', 'koek' => 'snacks-zoetigheid',
            'pasta' => 'pasta-rijst', 'rijst' => 'pasta-rijst', 'macaroni' => 'pasta-rijst',
            'soep' => 'conserven-sauzen', 'saus' => 'conserven-sauzen',
        ];
        foreach ($map as $keyword => $slug) {
            if (str_contains($name, $keyword)) return $slug;
        }
        return null;
    }
}
