import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import https from 'https';

puppeteer.use(StealthPlugin());

function fetchJson(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { 
      headers: { 'User-Agent': 'Mozilla/5.0', 'Accept': 'application/json' } 
    }, (res) => {
      let data = '';
      res.on('data', c => data += c);
      res.on('end', () => {
        try { resolve(JSON.parse(data)); }
        catch(e) { reject(e); }
      });
    }).on('error', reject);
  });
}

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

// ── Step 1: Get current flyer product IDs via Schwarz API ──
console.error('═══ Step 1: Schwarz API → get product IDs ═══');
const flyerData = await fetchJson(
  "https://endpoints.leaflets.schwarz/v4/flyer?flyer_identifier=aktionsprospekt-26-05-2026-30-05-2026-c7c3e1&region_id=0"
);
const flyer = flyerData.flyer;

const allLinks = [];
for (const page of flyer.pages) {
  for (const link of page.links || []) {
    if (link.displayType === 'product' && link.productDetails?.productId) {
      allLinks.push({
        id: link.productDetails.productId,
        title: link.productDetails.title || link.title,
        page: page.number
      });
    }
  }
}
console.error(`Total product links: ${allLinks.length}`);
console.error('Sample IDs:', allLinks.slice(0, 5).map(l => l.id));

// ── Step 2: Call gridboxes API via Puppeteer ──
console.error('\n═══ Step 2: Gridboxes via Puppeteer ═══');
const page = await browser.newPage();
await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
page.setDefaultTimeout(15000);

// First visit Lidl DE to get proper cookies/session
await page.goto('https://www.lidl.de/', { waitUntil: 'networkidle0', timeout: 20000 });
await new Promise(r => setTimeout(r, 2000));

// Get unique product IDs
const uniqueIds = [...new Set(allLinks.slice(0, 20).map(l => l.id))];
console.error(`Testing with ${uniqueIds.length} product IDs: ${uniqueIds.join(',')}`);

// Try the gridboxes API via fetch within the page
const result = await page.evaluate(async (ids) => {
  try {
    const url = `https://www.lidl.de/p/api/gridboxes/DE/de?erpNumbers=${ids.join(',')}`;
    console.error('Fetching:', url);
    const res = await fetch(url);
    const text = await res.text();
    console.error('Response length:', text.length);
    console.error('First 200 chars:', text.slice(0, 200));
    try { return JSON.parse(text); }
    catch(e) { return { parseError: e.message, text: text.slice(0, 500) }; }
  } catch(e) { return { error: e.message }; }
}, uniqueIds);

if (Array.isArray(result)) {
  console.error(`\nGridboxes returned ${result.length} products:`);
  result.slice(0, 10).forEach(p => {
    console.error(`  ${p.title}: €${p.price?.price || '?'} (cat: ${p.category || '?'})`);
  });
} else {
  console.error(`\nGridboxes result:`, JSON.stringify(result).slice(0, 500));
}

// ── Step 3: Try product list API (alternative) ──
console.error('\n═══ Step 3: Try alternative product page ═══');
try {
  const prodPage = await browser.newPage();
  await prodPage.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
  // Try to view one of the flyer product pages on the website
  const firstProduct = allLinks[0];
  // The URLs from flyer links often contain the path
  if (firstProduct?.id) {
    await prodPage.goto(`https://www.lidl.de/p/${firstProduct.id}`, { waitUntil: 'networkidle0', timeout: 15000 });
    console.error(`URL: ${prodPage.url()}`);
    const content = await prodPage.evaluate(() => {
      const priceEl = document.querySelector('[class*="price"], [class*="Price"]');
      return {
        title: document.title,
        price: priceEl?.textContent?.trim() || 'no price found',
        priceHTML: priceEl?.outerHTML?.slice(0, 300) || 'none',
      };
    });
    console.error(`Title: ${content.title}`);
    console.error(`Price: ${content.price}`);
  }
  await prodPage.close();
} catch(e) { console.error(`Error: ${e.message}`); }

await browser.close();
