<?php
abstract class ScraperBase
{
    protected $pdo;
    protected $storeId;
    protected $storeName;
    protected $country;
    protected $curlTimeout = 30;
    protected $userAgent  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';

    public function __construct(PDO $pdo, int $storeId, string $storeName, string $country)
    {
        $this->pdo       = $pdo;
        $this->storeId   = $storeId;
        $this->storeName = $storeName;
        $this->country   = $country;
    }

    abstract public function scrape(): array;

    protected function fetchUrl(string $url, array $headers = []): ?string
    {
        // Probeer eerst Node.js Puppeteer (JS-rendered HTML)
        $html = $this->fetchViaPuppeteer($url);
        if ($html !== null) {
            echo "  [*] Opgehaald via Puppeteer\n";
            return $html;
        }

        // Fallback: curl
        echo "  [*] Fallback naar curl...\n";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $this->curlTimeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_HTTPHEADER     => array_merge([
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: nl-NL,nl;q=0.9,de;q=0.8,en;q=0.7',
            ], $headers),
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $body === false) {
            echo "  [!] HTTP $httpCode voor $url\n";
            return null;
        }
        return $body;
    }

    private function fetchViaPuppeteer(string $url): ?string
    {
        $nodeScript = __DIR__ . '/node/fetch-html.mjs';
        if (!file_exists($nodeScript)) return null;

        $waitFor = '';
        // Bepaal wacht-selector o.b.v. de store
        if (str_contains($url, 'ah.nl'))         $waitFor = '--wait "[data-testid=\"product-card\"]"';
        elseif (str_contains($url, 'jumbo.com'))  $waitFor = '--wait "[data-testid=\"product\"]"';
        elseif (str_contains($url, 'lidl.'))      $waitFor = '--wait ".product"';
        elseif (str_contains($url, 'aldi.'))      $waitFor = '--wait ".mod-article-tile"';
        elseif (str_contains($url, 'plus.'))      $waitFor = '--wait ".offer"';
        elseif (str_contains($url, 'dirk.'))      $waitFor = '--wait ".product"';
        elseif (str_contains($url, 'rewe.'))      $waitFor = '--wait "[data-testid=\"product\"]"';
        elseif (str_contains($url, 'edeka.'))     $waitFor = '--wait ".teaser"';
        elseif (str_contains($url, 'netto.'))     $waitFor = '--wait ".product"';

        $cmd = sprintf(
            'node "%s" "%s" %s --timeout 25000 2>NUL',
            $nodeScript,
            $url,
            $waitFor
        );

        $output = [];
        $returnVar = 0;
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0 || empty($output)) return null;

        return implode("\n", $output);
    }

    protected function saveProduct(string $name, ?string $brand, ?int $categoryId, ?string $ean, ?string $imageUrl): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM products WHERE name = ? AND (brand = ? OR brand IS NULL) LIMIT 1');
        $stmt->execute([$name, $brand]);
        $existing = $stmt->fetch();

        if ($existing) {
            $productId = (int)$existing['id'];
            if ($imageUrl) {
                $upd = $this->pdo->prepare('UPDATE products SET image_url = COALESCE(NULLIF(image_url, ""), ?) WHERE id = ?');
                $upd->execute([$imageUrl, $productId]);
            }
            return $productId;
        }

        $stmt = $this->pdo->prepare('INSERT INTO products (name, brand, category_id, ean, image_url) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $brand, $categoryId, $ean, $imageUrl]);
        return (int)$this->pdo->lastInsertId();
    }

    protected function savePrice(int $productId, float $price, ?string $unitSize, ?float $unitPrice, ?int $folderId = null): void
    {
        $today = date('Y-m-d');

        $existing = $this->pdo->prepare('SELECT id, price FROM product_prices WHERE product_id = ? AND store_id = ? AND DATE(scraped_at) = ? LIMIT 1');
        $existing->execute([$productId, $this->storeId, $today]);
        $row = $existing->fetch();

        if ($row) {
            if ((float)$row['price'] !== $price) {
                $upd = $this->pdo->prepare('UPDATE product_prices SET price = ?, unit_size = ?, unit_price = ?, folder_id = ?, scraped_at = NOW() WHERE id = ?');
                $upd->execute([$price, $unitSize, $unitPrice, $folderId, (int)$row['id']]);
            }
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO product_prices (product_id, store_id, folder_id, price, unit_size, unit_price) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$productId, $this->storeId, $folderId, $price, $unitSize, $unitPrice]);
        }
    }

    protected function saveFolder(string $title, string $startDate, string $endDate, string $url): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM folders WHERE store_id = ? AND folder_url = ? LIMIT 1');
        $stmt->execute([$this->storeId, $url]);
        $existing = $stmt->fetch();

        if ($existing) {
            $upd = $this->pdo->prepare('UPDATE folders SET start_date = ?, end_date = ?, scraped_at = NOW() WHERE id = ?');
            $upd->execute([$startDate, $endDate, (int)$existing['id']]);
            return (int)$existing['id'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO folders (store_id, title, start_date, end_date, folder_url) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$this->storeId, $title, $startDate, $endDate, $url]);
        return (int)$this->pdo->lastInsertId();
    }

    protected function findCategoryId(string $keyword): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM categories WHERE name LIKE ? OR slug LIKE ? LIMIT 1');
        $stmt->execute(["%$keyword%", "%$keyword%"]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }
}
