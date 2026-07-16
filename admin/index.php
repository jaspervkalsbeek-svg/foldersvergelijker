<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="adminstyle.css">
</head>
<body>
<?php
session_start();
include_once '../include/db.php';
require_once '../include/auth.php';

// ── Stats ──
$productCount = $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$priceCount   = $pdo->query('SELECT COUNT(*) FROM product_prices')->fetchColumn();
$storeCount   = $pdo->query('SELECT COUNT(*) FROM stores WHERE active = 1')->fetchColumn();
$storeWithPrices = $pdo->query('SELECT COUNT(DISTINCT store_id) FROM product_prices')->fetchColumn();

// Store scraped-at timestamps + product counts
$storeStats = $pdo->query("
    SELECT s.id, s.name, s.country,
           (SELECT COUNT(*) FROM product_prices pp WHERE pp.store_id = s.id) as price_count,
           (SELECT MAX(scraped_at) FROM product_prices pp WHERE pp.store_id = s.id) as last_scraped
    FROM stores s WHERE s.active = 1
    ORDER BY s.country, s.name
")->fetchAll();

$cheapestItems = $pdo->query("
    SELECT p.name, MIN(pp.price) as price, s.name as store
    FROM products p
    JOIN product_prices pp ON pp.product_id = p.id
    JOIN stores s ON s.id = pp.store_id
    GROUP BY p.id
    ORDER BY pp.price ASC
    LIMIT 5
")->fetchAll();
?>

<aside class="sidebar">
    <div class="sidebar-logo">
        Admin Panel
        <span>Folders Vergelijker</span>
    </div>

    <div class="nav-label">Folders Vergelijker</div>
    <a href="index.php"       class="nav-item active"><span class="icon">🏠</span> Dashboard</a>
    <a href="add_product.php" class="nav-item"><span class="icon">📦</span> Product toevoegen</a>
    <a href="import.php"      class="nav-item"><span class="icon">📥</span> Import CSV/JSON</a>
    <a href="overview.php"    class="nav-item"><span class="icon">📋</span> Producten overzicht</a>

    <div class="sidebar-footer">
        <a href="../public/index.php">← Terug naar site</a>
    </div>
</aside>

<main class="main">
    <div class="page-header">
        <h1>Folders Vergelijker</h1>
        <p>Beheer producten, prijzen en imports</p>
    </div>

    <div class="section-title">Database overzicht</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $productCount ?></div>
            <div class="stat-label">Producten</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $priceCount ?></div>
            <div class="stat-label">Prijzen</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $storeWithPrices ?>/<?= $storeCount ?></div>
            <div class="stat-label">Winkels (actief/met prijzen)</div>
        </div>
    </div>

    <?php if (!empty($cheapestItems)): ?>
    <div class="section-title">Laagste prijzen</div>
    <div style="background:var(--card);border:1px solid var(--border-subtle);border-radius:16px;padding:20px;margin-bottom:32px">
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr>
                    <th style="text-align:left;padding:8px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Product</th>
                    <th style="text-align:left;padding:8px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Winkel</th>
                    <th style="text-align:right;padding:8px 12px;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Prijs</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cheapestItems as $item): ?>
                <tr>
                    <td style="padding:8px 12px;border-bottom:1px solid var(--border-subtle)"><?= htmlspecialchars($item['name']) ?></td>
                    <td style="padding:8px 12px;border-bottom:1px solid var(--border-subtle);color:var(--text-dim)"><?= htmlspecialchars($item['store']) ?></td>
                    <td style="padding:8px 12px;border-bottom:1px solid var(--border-subtle);text-align:right;font-weight:600">€<?= number_format((float)$item['price'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="section-title">Producten beheren</div>
    <div class="cards-grid">
        <a href="add_product.php" class="card">
            <div class="card-icon">📦</div>
            <div class="card-title">Product toevoegen</div>
            <div class="card-desc">Voeg handmatig een product toe met prijzen voor een of meerdere winkels.</div>
            <div class="card-action">Toevoegen →</div>
        </a>
        <a href="import.php" class="card">
            <div class="card-icon">📥</div>
            <div class="card-title">Import CSV/JSON</div>
            <div class="card-desc">Importeer producten en prijzen in bulk via CSV of JSON bestand.</div>
            <div class="card-action">Importeren →</div>
        </a>
        <a href="overview.php" class="card">
            <div class="card-icon">📋</div>
            <div class="card-title">Producten overzicht</div>
            <div class="card-desc">Bekijk alle producten, zoek, filter en zie prijzen per winkel.</div>
            <div class="card-action">Bekijken →</div>
        </a>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         Scraper Control Panel
         ═══════════════════════════════════════════════════════ -->
    <div class="section-title">Scraper</div>

    <div id="scrapeResult" class="section scrape-result" style="display:none">
        <div class="section-title">Resultaat</div>
        <pre id="scrapeLog" class="scrape-log"></pre>
    </div>

    <div class="store-grid">
        <div class="store-card scrape-all-card" data-store="_all">
            <div class="store-card-body">
                <div class="store-card-icon">⚡</div>
                <div class="store-card-info">
                    <div class="store-card-name">Alle winkels</div>
                    <div class="store-card-count"><?= $productCount ?> producten / <?= $priceCount ?> prijzen</div>
                </div>
            </div>
            <button class="scrape-btn scrape-btn-all" onclick="scrapeAll()">Alle scannen</button>
        </div>

        <?php
        $puppeteerStores = [
            'ah'       => ['name' => 'Albert Heijn', 'country' => 'NL'],
            'lidl-nl'  => ['name' => 'Lidl',          'country' => 'NL'],
            'aldi-nl'  => ['name' => 'Aldi',          'country' => 'NL'],
            'plus'     => ['name' => 'Plus',          'country' => 'NL'],
        ];
        $allefoldersStores = [
            'jumbo'      => ['name' => 'Jumbo',       'country' => 'NL'],
        ];
        $kaufdaStores = [
            'rewe'      => ['name' => 'Rewe',                'country' => 'DE'],
            'kaufland'  => ['name' => 'Kaufland',            'country' => 'DE'],
            'netto'     => ['name' => 'Netto',               'country' => 'DE'],
            'lidl-de'   => ['name' => 'Lidl',                'country' => 'DE'],
            'aldi-nord' => ['name' => 'Aldi Nord',           'country' => 'DE'],
            'aldi-sud'  => ['name' => 'Aldi Süd',            'country' => 'DE'],
        ];
        $allAvailable = array_merge($puppeteerStores, $allefoldersStores, $kaufdaStores);

        function renderStoreCard($s, $storeKey, $source) {
            $lastScraped = $s['last_scraped'] ? date('d-m-Y H:i', strtotime($s['last_scraped'])) : 'nooit';
            $isFresh = $s['last_scraped'] && strtotime($s['last_scraped']) > strtotime('-1 day');
            $scrapeFunc = "scrapeStore";
            $badge = '';
            if ($source === 'allefolders') {
                $scrapeFunc = "scrapeAllefolders";
                $badge = '<span class="api-badge">API</span>';
            } elseif ($source === 'kaufda') {
                $scrapeFunc = "scrapeKaufda";
                $badge = '<span class="api-badge">kaufDA</span>';
            }
        ?>
        <div class="store-card" data-store="<?= $storeKey ?>" data-source="<?= $source ?>">
            <div class="store-card-body">
                <div class="store-card-icon"><?= $s['country'] === 'NL' ? '🇳🇱' : '🇩🇪' ?></div>
                <div class="store-card-info">
                    <div class="store-card-name"><?= htmlspecialchars($s['name']) ?> <?= $badge ?></div>
                    <div class="store-card-count"><?= (int)$s['price_count'] ?> prijzen</div>
                    <div class="store-card-last <?= $isFresh ? 'fresh' : 'stale' ?>">
                        Laatst: <?= $lastScraped ?>
                    </div>
                </div>
            </div>
            <button class="scrape-btn" onclick="<?= $scrapeFunc ?>('<?= $storeKey ?>', this)">Scrapen</button>
        </div>
        <?php
        }

        // Puppeteer stores
        foreach ($storeStats as $s):
            $storeKey = null;
            foreach ($puppeteerStores as $k => $v) {
                if ($v['name'] === $s['name'] && $v['country'] === $s['country']) {
                    $storeKey = $k; break;
                }
            }
            if (!$storeKey) continue;
            renderStoreCard($s, $storeKey, 'puppeteer');
        endforeach;
        ?>

        <div style="grid-column:1/-1;height:1px;background:var(--border-subtle);margin:8px 0"></div>
        <div style="grid-column:1/-1;font-size:.72rem;color:var(--text-muted);letter-spacing:1px;text-transform:uppercase;padding:4px 0">kaufDA.de (DE)</div>

        <?php
        // kaufDA.de stores
        foreach ($storeStats as $s):
            $storeKey = null;
            foreach ($kaufdaStores as $k => $v) {
                if ($v['name'] === $s['name'] && $v['country'] === $s['country']) {
                    $storeKey = $k; break;
                }
            }
            if (!$storeKey) continue;
            renderStoreCard($s, $storeKey, 'kaufda');
        endforeach;
        ?>

        <div style="grid-column:1/-1;height:1px;background:var(--border-subtle);margin:8px 0"></div>

        <?php
        // AlleFolders API stores
        foreach ($storeStats as $s):
            $storeKey = null;
            foreach ($allefoldersStores as $k => $v) {
                if ($v['name'] === $s['name'] && $v['country'] === $s['country']) {
                    $storeKey = $k; break;
                }
            }
            if (!$storeKey) continue;
            renderStoreCard($s, $storeKey, 'allefolders');
        endforeach;
        ?>
    </div>
</main>

<script>
const STORE_ORDER = ['ah','lidl-nl','aldi-nl','plus'];
const AF_STORE_ORDER = ['jumbo'];
const KAUF_STORE_ORDER = ['rewe','kaufland','netto','lidl-de','aldi-nord','aldi-sud'];
const STORE_NAMES = {
    'ah':'Albert Heijn','lidl-nl':'Lidl (NL)','aldi-nl':'Aldi (NL)',
    'plus':'Plus','rewe':'Rewe','lidl-de':'Lidl (DE)',
    'jumbo':'Jumbo','kaufland':'Kaufland','netto':'Netto',
    'aldi-nord':'Aldi Nord','aldi-sud':'Aldi Süd'
};

const resultDiv = document.getElementById('scrapeResult');
const logPre   = document.getElementById('scrapeLog');

function log(msg) {
    logPre.textContent += msg + '\n';
}

function disableAll(v) {
    document.querySelectorAll('.store-card button').forEach(b => b.disabled = v);
}

function setBtnText(btn, txt) {
    if (btn) btn.textContent = txt;
}

function scrapeStore(key, btn) {
    resultDiv.style.display = 'block';
    logPre.textContent = '';

    disableAll(true);
    setBtnText(btn, '⏳ Bezig...');

    scrapeOne(key, 'scrape-run.php').then(() => {
        disableAll(false);
        setBtnText(btn, 'Scrapen');
    });
}

function scrapeAllefolders(key, btn) {
    resultDiv.style.display = 'block';
    logPre.textContent = '';

    disableAll(true);
    setBtnText(btn, '⏳ Bezig...');

    scrapeOne(key, 'allefolders-scrape.php').then(() => {
        disableAll(false);
        setBtnText(btn, 'Scrapen');
    });
}

function scrapeKaufda(key, btn) {
    resultDiv.style.display = 'block';
    logPre.textContent = '';

    disableAll(true);
    setBtnText(btn, '⏳ Bezig...');

    scrapeOne(key, 'kaufda-scrape.php').then(() => {
        disableAll(false);
        setBtnText(btn, 'Scrapen');
    });
}

async function scrapeAll() {
    disableAll(true);
    log('─── Puppeteer winkels ───');
    for (const k of STORE_ORDER) {
        const card = document.querySelector(`.store-card[data-store="${k}"]`);
        const b = card ? card.querySelector('button') : null;
        if (b) b.textContent = '⏳ Bezig...';
        await scrapeOne(k, 'scrape-run.php');
        if (b) b.textContent = 'Scrapen';
    }
    log('');
    log('─── kaufDA.de winkels ───');
    for (const k of KAUF_STORE_ORDER) {
        const card = document.querySelector(`.store-card[data-store="${k}"]`);
        const b = card ? card.querySelector('button') : null;
        if (b) b.textContent = '⏳ Bezig...';
        await scrapeOne(k, 'kaufda-scrape.php');
        if (b) b.textContent = 'Scrapen';
    }
    log('');
    log('─── AlleFolders API winkels ───');
    for (const k of AF_STORE_ORDER) {
        const card = document.querySelector(`.store-card[data-store="${k}"]`);
        const b = card ? card.querySelector('button') : null;
        if (b) b.textContent = '⏳ Bezig...';
        await scrapeOne(k, 'allefolders-scrape.php');
        if (b) b.textContent = 'Scrapen';
    }
    disableAll(false);
    log('');
    log('─── Alle winkels voltooid ───');
}

async function scrapeOne(key, endpoint) {
    const name = STORE_NAMES[key] || key;
    log(`─── ${name} ───`);

    try {
        const res = await fetch(`${endpoint}?store=${key}`);
        const data = await res.json();

        if (data.progress) {
            data.progress.forEach(p => log('  ' + p));
        }

        if (data.success) {
            log(`  [✓] ${data.count} producten, ${data.imported} in DB`);
        } else {
            log(`  [!] Fout: ${data.error || 'onbekend'}`);
        }
        if (data.error) {
            log(`  [!] ${data.error}`);
        }
    } catch (err) {
        log(`  [!] Netwerkfout: ${err.message}`);
    }

    return;
}
</script>

</body>
</html>