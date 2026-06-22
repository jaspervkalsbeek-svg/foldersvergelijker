import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';

puppeteer.use(StealthPlugin());

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

// ── Penny ──
console.error('═══ PENNY ═══');
try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
  page.setDefaultTimeout(15000);
  await page.goto('https://www.penny.de/angebote', { waitUntil: 'networkidle0', timeout: 20000 });
  
  const sample = await page.evaluate(() => {
    const articles = document.querySelectorAll('article');
    const results = [];
    for (let i = 0; i < Math.min(articles.length, 5); i++) {
      const el = articles[i];
      results.push({
        html: el.innerHTML.replace(/\\s+/g, ' ').slice(0, 500),
        classes: el.className,
        id: el.id,
      });
    }
    return results;
  });
  sample.forEach((s, i) => {
    console.error(`\nArticle ${i}:`);
    console.error(`  class: ${s.classes}`);
    console.error(`  html: ${s.html}`);
  });
  await page.close();
} catch(e) { console.error(`Error: ${e.message}`); }

// ── Aldi Süd ──
console.error('\n═══ ALDI SÜD ═══');
try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
  page.setDefaultTimeout(15000);
  await page.goto('https://www.aldi-sued.de/angebote', { waitUntil: 'networkidle0', timeout: 20000 });
  
  const sample = await page.evaluate(() => {
    const tiles = document.querySelectorAll('.product-tile, [class*="product-tile"]');
    const results = [];
    for (let i = 0; i < Math.min(tiles.length, 3); i++) {
      const el = tiles[i];
      results.push({
        html: el.innerHTML.replace(/\\s+/g, ' ').slice(0, 500),
        classes: el.className,
        text: el.textContent.replace(/\\s+/g, ' ').slice(0, 200),
      });
    }
    return results;
  });
  sample.forEach((s, i) => {
    console.error(`\nTile ${i}:`);
    console.error(`  class: ${s.classes}`);
    console.error(`  text: ${s.text}`);
    console.error(`  html: ${s.html}`);
  });
  await page.close();
} catch(e) { console.error(`Error: ${e.message}`); }

// ── Aldi Nord ──
console.error('\n═══ ALDI NORD ═══');
try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
  page.setDefaultTimeout(15000);
  await page.goto('https://www.aldi-nord.de/angebote.html', { waitUntil: 'networkidle0', timeout: 20000 });
  
  const sample = await page.evaluate(() => {
    const tiles = document.querySelectorAll('.offer-tile, [class*="offer-tile"]');
    const results = [];
    for (let i = 0; i < Math.min(tiles.length, 3); i++) {
      const el = tiles[i];
      results.push({
        html: el.innerHTML.replace(/\\s+/g, ' ').slice(0, 500),
        classes: el.className,
        text: el.textContent.replace(/\\s+/g, ' ').slice(0, 200),
      });
    }
    return results;
  });
  sample.forEach((s, i) => {
    console.error(`\nTile ${i}:`);
    console.error(`  class: ${s.classes}`);
    console.error(`  text: ${s.text}`);
    console.error(`  html: ${s.html}`);
  });
  await page.close();
} catch(e) { console.error(`Error: ${e.message}`); }

// ── Netto ──
console.error('\n═══ NETTO ═══');
try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
  page.setDefaultTimeout(15000);
  await page.goto('https://www.netto-online.de/ueber-netto/Online-Prospekte.chtm?initfb=filialangebote', { waitUntil: 'networkidle0', timeout: 20000 });
  
  const sample = await page.evaluate(() => {
    // Look for any product-like elements
    const selectors = ['article', '.product', '.offer', '[class*="product"]', '[class*="offer"]', 'a[href*="angebot"]', '.teaser', '.card'];
    const results = {};
    for (const sel of selectors) {
      const els = document.querySelectorAll(sel);
      if (els.length > 0) {
        const samples = [];
        for (let i = 0; i < Math.min(els.length, 2); i++) {
          samples.push(els[i].outerHTML.replace(/\\s+/g, ' ').slice(0, 400));
        }
        results[sel] = { count: els.length, samples };
      }
    }
    return results;
  });
  Object.entries(sample).forEach(([sel, data]) => {
    console.error(`\n${sel} (${data.count}):`);
    data.samples.forEach((h, i) => console.error(`  [${i}] ${h}`));
  });
  await page.close();
} catch(e) { console.error(`Error: ${e.message}`); }

await browser.close();
