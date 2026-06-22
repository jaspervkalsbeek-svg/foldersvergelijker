import puppeteer from "puppeteer-extra";
import StealthPlugin from "puppeteer-extra-plugin-stealth";
puppeteer.use(StealthPlugin());

const browser = await puppeteer.launch({
  headless: true,
  args: ["--no-sandbox","--disable-setuid-sandbox","--disable-dev-shm-usage","--disable-blink-features=AutomationControlled","--window-size=1920,1080"],
});

const page = await browser.newPage();
await page.setViewport({ width: 1920, height: 1080 });

// ── Approach: Brochure API ──
// Intercept ALL requests to find brochure data APIs
const apiCalls = [];
page.on('response', async (response) => {
  const url = response.url();
  if (url.includes('leaflet') || url.includes('prospekt') || url.includes('schwarz') || url.includes('json') || url.includes('api')) {
    try {
      const text = await response.text();
      apiCalls.push({
        url: url.slice(0, 200),
        type: response.headers()['content-type'] || '',
        status: response.status(),
        size: text.length,
        preview: text.slice(0, 400)
      });
    } catch(e) {}
  }
});

await page.goto("https://www.lidl.de/l/prospekte/aktionsprospekt-26-05-2026-30-05-2026-c7c3e1/ar/0?lf=HHZ", { waitUntil: "networkidle2", timeout: 15000 });
await new Promise(r => setTimeout(r, 5000));

console.error("═══ Brochure API calls ═══");
apiCalls.forEach(c => {
  console.error(`\n[${c.status}] ${c.type}`);
  console.error(`  ${c.url}`);
  if (c.size < 5000) console.error(`  → ${c.preview}`);
  else console.error(`  → ${c.size} bytes (showing first 200): ${c.preview.slice(0, 200)}`);
});

// ── Approach: Check third-party aggregator sites for structured data ──
console.error("\n\n═══ prospektin.com aggregator ═══");
await page.goto("https://prospektin.com/lidl/", { waitUntil: "networkidle2", timeout: 15000 }).catch(() => {});
await new Promise(r => setTimeout(r, 3000));

const prospektin = await page.evaluate(() => {
  const items = [];
  document.querySelectorAll('[class*="product"], [class*="offer"], [class*="item"], [class*="card"], li, article').forEach(el => {
    const text = el.textContent?.trim()?.slice(0, 200);
    if (text && text.includes('€') && text.length > 10 && text.length < 300) {
      items.push(text);
    }
  });
  return items.slice(0, 20);
});
console.error("Prospektin products:", JSON.stringify(prospektin, null, 2));

// ── Approach: Check kimbino.de ──
console.error("\n═══ kimbino.de aggregator ═══");
await page.goto("https://www.kimbino.de/lidl/", { waitUntil: "networkidle2", timeout: 15000 }).catch(() => {});
await new Promise(r => setTimeout(r, 3000));

const kimbino = await page.evaluate(() => {
  const items = [];
  document.querySelectorAll('[class*="product"], [class*="offer"], [class*="item"], [class*="card"], li, article, [class*="deal"]').forEach(el => {
    const text = el.textContent?.trim()?.slice(0, 200);
    if (text && text.includes('€') && text.length > 10 && text.length < 300) {
      items.push(text);
    }
  });
  return items.slice(0, 20);
});
console.error("Kimbino products:", JSON.stringify(kimbino, null, 2));

await browser.close();
