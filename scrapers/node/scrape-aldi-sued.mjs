import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
puppeteer.use(StealthPlugin());

const FOOD_THEMES = ['Frischekracher', 'Gekühlte Produkte', 'Internationale Küche', 'Markenartikel', 'Tiefkühlprodukte'];
const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox'] });

try {
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
  page.setDefaultTimeout(15000);
  
  // Discover dates and themes from the main page
  await page.goto('https://www.aldi-sued.de/angebote', { waitUntil: 'networkidle0', timeout: 20000 });
  
  const themeUrls = await page.evaluate((foodThemes) => {
    const links = [...document.querySelectorAll('a[href*="/angebote/"][href*="theme="]')];
    return [...new Set(links
      .map(l => {
        const href = l.href;
        const themeMatch = href.match(/theme=(.+)$/);
        const theme = themeMatch ? decodeURIComponent(themeMatch[1]) : '';
        return foodThemes.includes(theme) ? href : null;
      })
      .filter(Boolean))]
      .slice(0, 10); // Max 10 theme pages
  }, FOOD_THEMES);
  
  console.error(`Found ${themeUrls.length} food theme pages to scrape`);
  
  const allProducts = [];
  const seen = new Set();
  
  for (const url of themeUrls) {
    console.error(`  Scraping: ${url}`);
    try {
      await page.goto(url, { waitUntil: 'networkidle0', timeout: 15000 });
      await new Promise(r => setTimeout(r, 1000));
      
      const products = await page.evaluate(() => {
        const tiles = document.querySelectorAll('.product-tile');
        return [...tiles].map(t => {
          const brand = t.querySelector('.product-tile__brandname p')?.textContent?.trim() || '';
          const name = t.querySelector('.product-tile__name p')?.textContent?.trim() || '';
          const fullName = (brand + ' ' + name).trim();
          
          const priceText = t.querySelector('.base-price--product-tile .base-price__regular')?.textContent?.trim() || '';
          const pm = priceText.match(/([0-9]+)[,.]?\s*([0-9]{2})?(?:\s*[€])/);
          let price = 0;
          if (pm) {
            if (pm[2]) price = parseFloat(pm[1] + '.' + pm[2]);
            else price = parseFloat(pm[1]);
          }
          
          const compEl = t.querySelector('[class*="comparison"], [class*="base-price"], [class*="unit"]');
          const description = compEl?.textContent?.trim() || '';
          
          const img = t.querySelector('img')?.getAttribute('src') || null;
          
          return { name: fullName, price, description, image: img };
        }).filter(p => p.name && p.price > 0);
      });
      
      for (const p of products) {
        const key = p.name.toLowerCase();
        if (!seen.has(key)) {
          seen.add(key);
          allProducts.push(p);
        }
      }
    } catch(e) {
      console.error(`    Error: ${e.message?.slice(0, 100)}`);
    }
  }
  
  console.log(JSON.stringify(allProducts));
  console.error(`Total: ${allProducts.length} unique products`);
  
} finally {
  await browser.close();
}
