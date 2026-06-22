import puppeteer from "puppeteer-extra";
import StealthPlugin from "puppeteer-extra-plugin-stealth";
import https from "https";

puppeteer.use(StealthPlugin());

// ── Approach 1: Search API on lidl.de ──
async function searchLidlDe(query) {
  return new Promise((resolve, reject) => {
    const url = `https://www.lidl.de/q/${encodeURIComponent(query)}`;
    https.get(url, { headers: { 'User-Agent': 'Mozilla/5.0' } }, (res) => {
      let data = '';
      res.on('data', c => data += c);
      res.on('end', () => resolve({ status: res.statusCode, url: res.responseUrl || url, length: data.length }));
    }).on('error', reject);
  });
}

// ── Approach 2: Direct API call ──
async function callApi(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { headers: { 'User-Agent': 'Mozilla/5.0', 'Accept': 'application/json' } }, (res) => {
      let data = '';
      res.on('data', c => data += c);
      res.on('end', () => {
        try { resolve(JSON.parse(data)); }
        catch(e) { resolve({ error: 'parse failed', raw: data.slice(0, 200) }); }
      });
    }).on('error', reject);
  });
}

console.error("═══ Direct HTTP tests ═══\n");

// Try the search page for grocery queries
for (const q of ['milch', 'brot', 'eier', 'butter', 'kaese', 'obst', 'gemuese', 'joghurt']) {
  const result = await searchLidlDe(q);
  console.error(`Search "${q}": status=${result.status}, size=${result.length}b, url=${result.url?.slice(0, 80)}`);
}

// Try the gridboxes API directly with known grocery product IDs from search results
// First, try to find some via a different API endpoint
console.error("\n═══ Trying product API ═══");

// Try getting product data from a search-like endpoint
// The search endpoint
const searchResults = await callApi('https://www.lidl.de/q/milch?format=json');
console.error("Search /milch json:", JSON.stringify(searchResults).slice(0, 300));

// ── Approach 3: Puppeteer with search ──
console.error("\n═══ Puppeteer search approach ═══");
const browser = await puppeteer.launch({
  headless: true,
  args: ["--no-sandbox","--disable-setuid-sandbox","--disable-dev-shm-usage","--disable-blink-features=AutomationControlled","--window-size=1920,1080"],
});
const page = await browser.newPage();
await page.setViewport({ width: 1920, height: 1080 });

// Intercept responses
let gridboxCalls = [];
page.on('response', async (response) => {
  const url = response.url();
  if (url.includes('gridboxes')) {
    try {
      const text = await response.text();
      const data = JSON.parse(text);
      const cats = [...new Set(data.map(p => p.category?.split('/')[1]).filter(Boolean))];
      gridboxCalls.push({ url: url.slice(0, 100), count: data.length, categories: cats, hasFood: cats.some(c => c?.toLowerCase().includes('lebensmittel') || c?.toLowerCase().includes('getraenk') || c?.toLowerCase().includes('frisch')) });
    } catch(e) {}
  }
});

// Go to search result page for a grocery term
await page.goto("https://www.lidl.de/q/milch", { waitUntil: "networkidle2", timeout: 15000 });
await new Promise(r => setTimeout(r, 3000));
console.error("Search 'milch' URL:", page.url());

const milchProducts = await page.evaluate(() => {
  const items = [];
  document.querySelectorAll('[class*="product"], [data-product-id], article, [class*="grid-box"], [class*="tile"]').forEach(el => {
    const text = el.textContent?.trim()?.slice(0, 150);
    if (text && (text.includes('€') || text.includes('EUR'))) {
      const name = el.querySelector('h2, h3, [class*="title"], [class*="name"]')?.textContent?.trim() || text.slice(0, 60);
      items.push({ name: name.slice(0, 60), text: text.slice(0, 100) });
    }
  });
  return items.slice(0, 10);
});
console.error("Milch search results:", JSON.stringify(milchProducts, null, 2));

// Check API responses from the search page
console.error("\nGridbox API calls from search page:");
gridboxCalls.forEach(c => {
  console.error(`  ${c.count} items, cats: [${c.categories.join(', ')}], food: ${c.hasFood}`);
});

// ── Approach 4: Brochure viewer ──
console.error("\n═══ Brochure viewer ═══");
await page.goto("https://www.lidl.de/l/prospekte/aktionsprospekt-26-05-2026-30-05-2026-c7c3e1/ar/0?lf=HHZ", { waitUntil: "networkidle2", timeout: 15000 }).catch(() => {});
await new Promise(r => setTimeout(r, 3000));
console.error("Brochure URL:", page.url());

const brochureInfo = await page.evaluate(() => {
  return {
    title: document.title,
    hasImages: document.querySelectorAll('img[src*="prospekt"], img[src*="leaflet"]').length,
    hasCanvas: document.querySelectorAll('canvas').length,
    scripts: [...document.querySelectorAll('script')].map(s => ({ id: s.id, src: (s.src || '').slice(0, 80), len: (s.textContent || '').length })).slice(0, 5),
    text: document.body?.textContent?.trim()?.slice(0, 500) || '',
  };
});
console.error("Brochure info:", JSON.stringify(brochureInfo, null, 2));

// ── Approach 5: kaufda.de (aggregator) ──
console.error("\n═══ kaufda.de aggregator ═══");
await page.goto("https://www.kaufda.de/insights/lidl-prospekt-ab-26-05-2026-macht-euch-bereit-fuer-die-wm/", { waitUntil: "networkidle2", timeout: 15000 }).catch(() => {});
await new Promise(r => setTimeout(r, 3000));

const kaufdaProducts = await page.evaluate(() => {
  const items = [];
  // Look for product listings in the article
  const ps = document.querySelectorAll('p, li, h2, h3');
  ps.forEach(p => {
    const text = p.textContent?.trim() || '';
    if (text.includes('€') && text.length > 5 && text.length < 200) {
      items.push(text.slice(0, 150));
    }
  });
  return items.slice(0, 20);
});
console.error("kaufda.de product mentions:", JSON.stringify(kaufdaProducts, null, 2));

await browser.close();
