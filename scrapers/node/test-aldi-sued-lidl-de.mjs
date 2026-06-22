import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
puppeteer.use(StealthPlugin());

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

// ── Aldi Süd: full product extraction test ──
console.error('═══ ALDI SÜD - full extraction ═══');
try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
  page.setDefaultTimeout(15000);
  await page.goto('https://www.aldi-sued.de/angebote/2026-05-22?theme=Frischekracher', { waitUntil: 'networkidle0', timeout: 20000 });
  await new Promise(r => setTimeout(r, 1000));

  const products = await page.evaluate(() => {
    const tiles = document.querySelectorAll('.product-tile');
    return [...tiles].map(t => {
      const brand = t.querySelector('.product-tile__brandname p')?.textContent?.trim() || '';
      const name = t.querySelector('.product-tile__name p')?.textContent?.trim() || '';
      const fullName = (brand ? brand + ' ' : '') + name;
      
      const priceEl = t.querySelector('.base-price--product-tile .base-price__regular');
      const priceText = priceEl?.textContent?.trim() || '';
      const pm = priceText.match(/([0-9]+)[,.]?\s*([0-9]{2})?\s*[€]/);
      let price = 0;
      if (pm) {
        if (pm[2]) price = parseFloat(pm[1] + '.' + pm[2]);
        else price = parseFloat(pm[1]);
      }
      
      const img = t.querySelector('img')?.getAttribute('src') || null;
      
      // Check for comparison/unit price
      const compEl = t.querySelector('[class*="comparison"], [class*="base-price"], [class*="unit"]');
      const compText = compEl?.textContent?.trim() || '';
      
      return { name: fullName, price, compText, image: img };
    });
  });
  
  console.error(`Found ${products.length} products:`);
  products.forEach((p, i) => {
    console.error(`  [${i}] ${p.name} - €${p.price} (${p.compText})`);
  });
  
  await page.close();
} catch(e) { console.error(`Error: ${e.message}`); }

// ── Lidl DE: try gridboxes via Puppeteer ──
console.error('\n═══ LIDL DE - gridboxes via Puppeteer ═══');
try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
  page.setDefaultTimeout(15000);
  
  // Set up request interception to capture responses
  await page.setRequestInterception(true);
  const responses = [];
  page.on('response', async (response) => {
    if (response.url().includes('gridboxes')) {
      try {
        const json = await response.json();
        responses.push(json);
      } catch (e) {}
    }
  });
  
  page.on('request', (request) => {
    request.continue();
  });
  
  // Navigate to Lidl DE (need to be on their domain for the API to work)
  await page.goto('https://www.lidl.de/', { waitUntil: 'networkidle0', timeout: 20000 });
  
  // Now call the gridboxes API within the page context
  const gridData = await page.evaluate(async () => {
    try {
      const res = await fetch('https://www.lidl.de/p/api/gridboxes/DE/de?erpNumbers=40531038,44344357,27481402');
      return await res.json();
    } catch(e) { return { error: e.message }; }
  });
  
  if (Array.isArray(gridData)) {
    console.error(`Got ${gridData.length} products from gridboxes:`);
    gridData.slice(0, 5).forEach(p => {
      console.error(`  ${p.title}: €${p.price?.price} (was €${p.price?.oldPrice || 'n/a'})`);
    });
  } else {
    console.error(`gridboxes result:`, JSON.stringify(gridData).slice(0, 200));
  }
  
  await page.close();
} catch(e) { console.error(`Error: ${e.message}`); }

await browser.close();
