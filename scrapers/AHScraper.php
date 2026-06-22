<?php
require_once __DIR__ . '/ScraperBase.php';

class AHScraper extends ScraperBase
{
    public function scrape(): array
    {
        $products = [];
        echo "  Scrapen van folders van Albert Heijn...\n";

        $html = $this->fetchUrl('https://www.ah.nl/bonus');
        if (!$html) return $products;

        preg_match_all('/<script[^>]*id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html, $matches);

        if (empty($matches[1][0])) {
            echo "  [!] Geen JSON data gevonden op AH pagina\n";
            return $this->scrapeFallback($html);
        }

        $json = json_decode($matches[1][0], true);
        if (!$json) {
            echo "  [!] JSON parse fout\n";
            return $products;
        }

        $bonusItems = $this->extractBonusItems($json);
        echo "  [*] " . count($bonusItems) . " bonusproducten gevonden\n";

        foreach ($bonusItems as $item) {
            $name = $item['name'] ?? '';
            if (empty($name)) continue;

            $brand = $item['brand'] ?? null;
            $price = $item['price'] ?? 0;
            $unitSize = $item['unitSize'] ?? null;
            $unitPrice = $item['unitPrice'] ?? null;
            $imageUrl = $item['image'] ?? null;

            $category = $this->categorizeProduct($name);
            $catId = $category ? $this->findCategoryId($category) : null;

            $pid = $this->saveProduct($name, $brand, $catId, null, $imageUrl);
            if ($price > 0) {
                $this->savePrice($pid, (float)$price, $unitSize, $unitPrice !== null ? (float)$unitPrice : null);
            }

            $products[] = [
                'name'      => $name,
                'brand'     => $brand,
                'price'     => (float)$price,
                'store'     => 'Albert Heijn',
                'unit_size' => $unitSize,
                'unit_price'=> $unitPrice,
                'category'  => $category,
            ];
        }

        return $products;
    }

    private function extractBonusItems(array $json): array
    {
        $items = [];

        $navigate = function ($node) use (&$navigate, &$items) {
            if (!is_array($node)) return;

            if (isset($node['bonusPrice']) || isset($node['price'])) {
                $item = [];
                $item['name'] = $node['name'] ?? $node['title'] ?? '';
                $item['brand'] = $node['brand'] ?? null;
                $item['price'] = $node['bonusPrice']['price'] ?? $node['price']['now'] ?? $node['price'] ?? 0;
                $item['unitSize'] = $node['unitSize'] ?? null;
                $item['unitPrice'] = $node['price']['unitPrice'] ?? null;
                $item['image'] = $node['images'][0]['url'] ?? $node['image']['url'] ?? null;
                if (!empty($item['name'])) $items[] = $item;
            }

            foreach ($node as $value) {
                if (is_array($value)) $navigate($value);
            }
        };

        $navigate($json);
        return $items;
    }

    private function scrapeFallback(string $html): array
    {
        echo "  [*] Fallback: zoeken naar product elementen in HTML...\n";
        $products = [];

        preg_match_all('/<article[^>]*class="[^"]*product[^"]*"[^>]*>(.*?)<\/article>/s', $html, $articles);
        foreach ($articles[1] as $article) {
            $name = '';
            if (preg_match('/<span[^>]*class="[^"]*product-name[^"]*"[^>]*>(.*?)<\/span>/s', $article, $m)) {
                $name = trim(strip_tags($m[1]));
            } elseif (preg_match('/<h[23][^>]*>(.*?)<\/h[23]>/s', $article, $m)) {
                $name = trim(strip_tags($m[1]));
            }

            if (empty($name)) continue;

            $price = 0;
            if (preg_match('/<span[^>]*class="[^"]*price[^"]*"[^>]*>€?\s*([0-9,]+(?:[.,][0-9]{2})?)/', $article, $m)) {
                $price = (float)str_replace(',', '.', str_replace('.', '', $m[1]));
            }

            $category = $this->categorizeProduct($name);
            $catId = $category ? $this->findCategoryId($category) : null;
            $pid = $this->saveProduct($name, null, $catId, null, null);

            if ($price > 0) {
                $this->savePrice($pid, $price, null, null);
            }

            $products[] = ['name' => $name, 'price' => $price, 'store' => 'Albert Heijn', 'category' => $category];
        }

        echo "  [*] " . count($products) . " producten gevonden via fallback\n";
        return $products;
    }

    private function categorizeProduct(string $name): ?string
    {
        $name = mb_strtolower($name);
        $map = [
            'zuivel'       => 'zuivel-eieren',  'melk'     => 'zuivel-eieren',
            'kaas'         => 'zuivel-eieren',   'yoghurt'  => 'zuivel-eieren',
            'brood'        => 'brood-ontbijtgranen', 'crackers' => 'brood-ontbijtgranen',
            'muesli'       => 'brood-ontbijtgranen', 'haver'   => 'brood-ontbijtgranen',
            'fruit'        => 'fruit-groente',   'appel'    => 'fruit-groente',
            'banaan'       => 'fruit-groente',   'sinaasappel'=> 'fruit-groente',
            'druiven'      => 'fruit-groente',   'tomaat'   => 'fruit-groente',
            'komkommer'    => 'fruit-groente',   'sla'      => 'fruit-groente',
            'aardappel'    => 'fruit-groente',   'ui'       => 'fruit-groente',
            'vlees'        => 'vlees-vis',       'kip'      => 'vlees-vis',
            'rundvlees'    => 'vlees-vis',       'varken'   => 'vlees-vis',
            'gehakt'       => 'vlees-vis',       'biefstuk' => 'vlees-vis',
            'vis'          => 'vlees-vis',       'zalm'     => 'vlees-vis',
            'diepvries'    => 'diepvries',       'ijs'      => 'diepvries',
            'cola'         => 'dranken',         'frisdrank'=> 'dranken',
            'sap'          => 'dranken',         'water'    => 'dranken',
            'bier'         => 'dranken',         'wijn'     => 'dranken',
            'chips'        => 'snacks-zoetigheid', 'chocolade'=> 'snacks-zoetigheid',
            'koek'         => 'snacks-zoetigheid', 'snoep'   => 'snacks-zoetigheid',
            'pasta'        => 'pasta-rijst',     'spaghetti'=> 'pasta-rijst',
            'rijst'        => 'pasta-rijst',     'macaroni' => 'pasta-rijst',
            'soep'         => 'conserven-sauzen', 'saus'    => 'conserven-sauzen',
            'was'          => 'huishouden',      'schoonmaak'=> 'huishouden',
            'shampoo'      => 'persoonlijke-verzorging', 'tandpasta'=> 'persoonlijke-verzorging',
            'deodorant'    => 'persoonlijke-verzorging',
            'luiers'       => 'baby',
            'honden'       => 'huisdier',        'katten'   => 'huisdier',
            'katte'        => 'huisdier',        'honden'   => 'huisdier',
        ];

        foreach ($map as $keyword => $slug) {
            if (str_contains($name, $keyword)) return $slug;
        }

        return null;
    }
}
