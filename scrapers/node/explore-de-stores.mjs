import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';

puppeteer.use(StealthPlugin());

const stores = [
  { key: 'aldi-sued', url: 'https://www.aldi-sued.de/angebote' },
  { key: 'aldi-nord', url: 'https://www.aldi-nord.de/angebote' },
  { key: 'netto', url: 'https://www.netto-online.de/angebote' },
  { key: 'edeka', url: 'https://www.edeka.de/angebote.jsp' },
  { key: 'kaufland', url: 'https://www.kaufland.de/angebote' },
  { key: 'penny', url: 'https://www.penny.de/angebote' },
];

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

for (const store of stores) {
  console.error(`\n═══ ${store.key} ═══`);
  try {
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
    page.setDefaultTimeout(15000);
    const resp = await page.goto(store.url, { waitUntil: 'networkidle0', timeout: 20000 });
    const status = resp?.status() || 'no response';
    console.error(`Status: ${status}, URL: ${page.url()}`);
    
    const title = await page.title();
    console.error(`Title: ${title?.slice(0, 100)}`);
    
    // Look for product-like elements
    const productCount = await page.evaluate(() => {
      const selectors = [
        'article', '.product-card', '.offer-tile', '.product-tile',
        '[data-product-id]', '.product', '.offer', '.card',
        'li[class*="product"]', 'div[class*="product"]',
        'a[href*="angebot"]', 'a[href*="product"]',
      ];
      for (const sel of selectors) {
        const el = document.querySelectorAll(sel);
        if (el.length > 0) return `${sel}: ${el.length}`;
      }
      return 'no product elements found';
    });
    console.error(`Products: ${productCount}`);
    
    await page.close();
  } catch (e) {
    console.error(`Error: ${e.message?.slice(0, 100)}`);
  }
}

await browser.close();
console.error('\nDone.');
