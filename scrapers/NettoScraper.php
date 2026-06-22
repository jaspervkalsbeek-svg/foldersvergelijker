<?php
require_once __DIR__ . '/ScraperBase.php';

class NettoScraper extends ScraperBase
{
    public function scrape(): array
    {
        $products = [];
        echo "  Scrapen van folders van Netto...\n";

        $html = $this->fetchUrl('https://www.netto.de/angebote');
        if (!$html) return $products;

        preg_match_all('/<div[^>]*class="[^"]*offers[^"]*"[^>]*>(.*?)<\/div>/s', $html, $offers);

        preg_match_all('/<div[^>]*class="[^"]*product[^"]*"[^>]*>(.*?)<\/div>/s', $html, $products_div);
        $divs = !empty($products_div[1]) ? $products_div[1] : [];

        foreach ($divs as $div) {
            $name = '';
            if (preg_match('/<span[^>]*class="[^"]*name[^"]*"[^>]*>(.*?)<\/span>/s', $div, $m)) {
                $name = trim(strip_tags($m[1]));
            } elseif (preg_match('/<h[23][^>]*>(.*?)<\/h[23]>/s', $div, $m)) {
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

            $products[] = ['name' => $name, 'price' => $price, 'store' => 'Netto', 'category' => $category];
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
            'fisch' => 'vlees-vis',
            'tiefkühl' => 'diepvries',
            'wasser' => 'dranken', 'bier' => 'dranken',
        ];
        foreach ($map as $keyword => $slug) {
            if (str_contains($name, $keyword)) return $slug;
        }
        return null;
    }
}
