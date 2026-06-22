import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';

puppeteer.use(StealthPlugin());

const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

// ── Penny: full extraction test ──
console.error('═══ PENNY extraction test ═══');
try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
  page.setDefaultTimeout(15000);
  await page.goto('https://www.penny.de/angebote', { waitUntil: 'networkidle0', timeout: 20000 });

  const products = await page.evaluate(() => {
    const articles = document.querySelectorAll('article.offer-tile');
    return [...articles].map(el => {
      const headline = el.querySelector('.offer-tile__headline');
      const name = headline?.textContent?.trim() || '';
      
      // Get price - primary (blue) bubble has the offer price, but if no blue bubble, use yellow
      const priceEl = el.querySelector('.bubble--lg .bubble__price-value, .bubble--md .bubble__price-value');
      const price = priceEl?.textContent?.trim() || '';
      
      // Get unit price info
      const unitEl = el.querySelector('.offer-tile__unit-price');
      const unitPrice = unitEl?.textContent?.trim() || '';
      
      // Image alt/title as fallback
      const img = el.querySelector('.offer-tile__image');
      const imgAlt = img?.getAttribute('alt') || '';
      const imgTitle = img?.getAttribute('title') || '';
      
      return { name, price, unitPrice, imgAlt, imgTitle };
    }).slice(0, 5);
  });
  
  console.error('Sample products:');
  products.forEach((p, i) => {
    console.error(`\n[${i}] Name: "${p.name}"`);
    console.error(`    Price: "${p.price}"`);
    console.error(`    Unit: "${p.unitPrice}"`);
    console.error(`    Img: alt="${p.imgAlt}" title="${p.imgTitle}"`);
  });
  
  // Count products with prices
  const stats = await page.evaluate(() => {
    const articles = document.querySelectorAll('article.offer-tile');
    const withPrice = [...articles].filter(el => el.querySelector('.bubble__price-value')).length;
    const withName = [...articles].filter(el => el.querySelector('.offer-tile__headline')?.textContent?.trim()).length;
    return { total: articles.length, withPrice, withName };
  });
  console.error(`\nStats: ${JSON.stringify(stats)}`);
  
  await page.close();
} catch(e) { console.error(`Error: ${e.message}`); }

// ── Aldi Nord: find product details within sections ──
console.error('\n═══ ALDI NORD - product tiles within sections ═══');
try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');
  page.setDefaultTimeout(15000);
  await page.goto('https://www.aldi-nord.de/angebote.html', { waitUntil: 'networkidle0', timeout: 20000 });

  const productData = await page.evaluate(() => {
    const tiles = document.querySelectorAll('.product-tile');
    const results = [];
    for (let i = 0; i < Math.min(tiles.length, 10); i++) {
      const t = tiles[i];
      // Look for name, brand, price elements
      const nameEl = t.querySelector('[class*="name"] p, [class*="title"] p, [class*="Name"]');
      const priceEl = t.querySelector('[class*="price"]');
      const brandEl = t.querySelector('[class*="brand"] p, [class*="Brand"] p');
      const unitEl = t.querySelector('[class*="unit"]');
      const sizeEl = t.querySelector('[data-testid*="size"], [class*="size"]');
      
      results.push({
        text: t.textContent.replace(/\s+/g, ' ').trim().slice(0, 200),
        name: nameEl?.textContent?.trim() || '',
        brand: brandEl?.textContent?.trim() || '',
        price: priceEl?.textContent?.trim() || '',
        unit: unitEl?.textContent?.trim() || '',
        size: sizeEl?.textContent?.trim() || '',
        html: t.innerHTML.replace(/\s+/g, ' ').slice(0, 600),
      });
    }
    return results;
  });
  
  productData.forEach((p, i) => {
    console.error(`\n[${i}]`);
    console.error(`  text: ${p.text}`);
    console.error(`  name: "${p.name}"`);
    console.error(`  brand: "${p.brand}"`);
    console.error(`  price: "${p.price}"`);
    console.error(`  unit: "${p.unit}"`);
  });
  
  await page.close();
} catch(e) { console.error(`Error: ${e.message}`); }

await browser.close();
