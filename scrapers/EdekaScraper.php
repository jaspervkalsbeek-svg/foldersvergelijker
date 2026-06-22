<?php
require_once __DIR__ . '/ScraperBase.php';

class EdekaScraper extends ScraperBase
{
    public function scrape(): array
    {
        $products = [];
        echo "  Scrapen van folders van Edeka...\n";

        $html = $this->fetchUrl('https://www.edeka.de/angebote.jsp');
        if (!$html) return $products;

        preg_match_all('/<div[^>]*class="[^"]*teaser[^"]*"[^>]*>(.*?)<\/div>/s', $html, $teasers);
        foreach ($teasers[1] as $teaser) {
            $name = '';
            if (preg_match('/<h[23][^>]*>(.*?)<\/h[23]>/s', $teaser, $m)) {
                $name = trim(strip_tags($m[1]));
            }
            if (empty($name)) continue;

            $price = 0;
            if (preg_match('/€\s*([0-9]+[.,][0-9]{2})/', $teaser, $m)) {
                $price = (float)str_replace(',', '.', str_replace('.', '', $m[1]));
            }

            $category = $this->categorizeProduct($name);
            $catId = $category ? $this->findCategoryId($category) : null;
            $pid = $this->saveProduct($name, null, $catId, null, null);
            if ($price > 0) $this->savePrice($pid, $price, null, null);

            $products[] = ['name' => $name, 'price' => $price, 'store' => 'Edeka', 'category' => $category];
        }

        echo "  [*] " . count($products) . " Produkte gefunden\n";
        return $products;
    }

    private function categorizeProduct(string $name): ?string
    {
        $name = mb_strtolower($name);
        $map = [
            'milch' => 'zuivel-eieren', 'käse' => 'zuivel-eieren',
            'brot' => 'brood-ontbijtgranen',
            'obst' => 'fruit-groente', 'gemüse' => 'fruit-groente',
            'fleisch' => 'vlees-vis',
            'fisch' => 'vlees-vis', 'lachs' => 'vlees-vis',
            'tiefkühl' => 'diepvries',
            'wasser' => 'dranken', 'bier' => 'dranken',
        ];
        foreach ($map as $keyword => $slug) {
            if (str_contains($name, $keyword)) return $slug;
        }
        return null;
    }
}
