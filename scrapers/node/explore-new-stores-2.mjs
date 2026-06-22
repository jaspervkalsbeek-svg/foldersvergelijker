import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';

puppeteer.use(StealthPlugin());

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

// ── Penny: full product details ──
console.error('═══ PENNY - full details ═══');
try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
  page.setDefaultTimeout(15000);
  await page.goto('https://www.penny.de/angebote', { waitUntil: 'networkidle0', timeout: 20000 });

  const fullHtml = await page.evaluate(() => {
    const articles = document.querySelectorAll('article.offer-tile');
    const results = [];
    for (let i = 0; i < Math.min(articles.length, 3); i++) {
      const el = articles[i];
      results.push(el.outerHTML);
    }
    return results;
  });
  fullHtml.forEach((h, i) => {
    console.error(`\n--- Article ${i} ---`);
    console.error(h);
  });
  
  // Also check total count and data attributes
  const meta = await page.evaluate(() => {
    const arts = document.querySelectorAll('article.offer-tile');
    const names = document.querySelectorAll('.offer-tile__title, [class*="title"]');
    const prices = document.querySelectorAll('.offer-tile__price, [class*="price"]');
    return {
      articleCount: arts.length,
      nameSelectors: [...new Set([...document.querySelectorAll('[class*="title"]')].map(e => e.className))],
      priceSelectors: [...new Set([...document.querySelectorAll('[class*="price"]')].map(e => e.className))],
    };
  });
  console.error(`\nMeta: ${JSON.stringify(meta, null, 2)}`);
  
  await page.close();
} catch(e) { console.error(`Error: ${e.message}`); }

// ── Aldi Süd: full product details ──
console.error('\n═══ ALDI SÜD - full details ═══');
try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
  page.setDefaultTimeout(15000);
  await page.goto('https://www.aldi-sued.de/angebote', { waitUntil: 'networkidle0', timeout: 20000 });

  const fullHtml = await page.evaluate(() => {
    const tiles = document.querySelectorAll('.product-tile');
    const results = [];
    for (let i = 0; i < Math.min(tiles.length, 3); i++) {
      results.push(tiles[i].outerHTML);
    }
    // Also look for price selectors
    const classes = [...document.querySelectorAll('[class*="price"], [class*="Price"], [class*="title"], [class*="Title"]')]
      .map(e => e.className + ' -> ' + e.textContent.trim().slice(0, 50));
    return { html: results, selectors: classes.slice(0, 20) };
  });
  fullHtml.html.forEach((h, i) => {
    console.error(`\n--- Tile ${i} ---`);
    console.error(h.slice(0, 1500));
  });
  console.error(`\nPrice/Title selectors:`);
  fullHtml.selectors.forEach(s => console.error(`  ${s}`));
  
  await page.close();
} catch(e) { console.error(`Error: ${e.message}`); }

// ── Aldi Nord: find actual product tiles ──
console.error('\n═══ ALDI NORD - find products ═══');
try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
  page.setDefaultTimeout(15000);
  await page.goto('https://www.aldi-nord.de/angebote.html', { waitUntil: 'networkidle0', timeout: 20000 });

  // Try to find actual product URLs/tiles
  const info = await page.evaluate(() => {
    // List all links on page
    const links = [...document.querySelectorAll('a[href]')]
      .map(a => ({ href: a.href, text: a.textContent.trim().slice(0, 80) }))
      .filter(a => a.href.includes('produkt') || a.href.includes('angebot') || a.href.includes('aktion'));
    
    // Look for offer tiles structure
    const offerTiles = document.querySelectorAll('.offer-tile, [class*="offer-tile"], [class*="product"]');
    
    return {
      links: links.slice(0, 20),
      offerTileCount: offerTiles.length,
      offerTileClasses: [...new Set([...offerTiles].map(e => e.className))].slice(0, 10),
    };
  });
  console.error(`Info: ${JSON.stringify(info, null, 2)}`);
  
  // Get full page text to understand structure
  const bodyText = await page.evaluate(() => document.body.innerText.slice(0, 2000));
  console.error(`\nPage text:\n${bodyText}`);
  
  await page.close();
} catch(e) { console.error(`Error: ${e.message}`); }

// ── Netto: find actual offer page ──
console.error('\n═══ NETTO - find products ═══');
try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
  page.setDefaultTimeout(15000);
  
  // Try to find the online offers page
  await page.goto('https://www.netto-online.de/angebote/c-N07', { waitUntil: 'networkidle0', timeout: 20000 });
  console.error(`URL: ${page.url()}`);
  
  const bodyText = await page.evaluate(() => document.body.innerText.slice(0, 2000));
  console.error(`Page text:\n${bodyText}`);
  
  // Check for product elements
  const products = await page.evaluate(() => {
    const selectors = ['article', '.product', '.offer', '[class*="product"]', '[class*="offer"]', '.card', '.teaser', 'li'];
    const found = {};
    for (const sel of selectors) {
      const els = document.querySelectorAll(sel);
      if (els.length > 0) {
        const sample = els[0]?.outerHTML?.slice(0, 300) || '';
        found[sel] = { count: els.length, sample };
      }
    }
    return found;
  });
  console.error(`Products: ${JSON.stringify(products, null, 2).slice(0, 2000)}`);
  
  await page.close();
} catch(e) { console.error(`Error: ${e.message}`); }

await browser.close();
