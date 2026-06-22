import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
puppeteer.use(StealthPlugin());

const browser = await puppeteer.launch({headless:true,args:['--no-sandbox']});
const page = await browser.newPage();
await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
page.setDefaultTimeout(15000);
await page.goto('https://www.aldi-sued.de/angebote', {waitUntil:'networkidle0', timeout:20000});
await new Promise(r => setTimeout(r, 2000));

const data = await page.evaluate(() => {
  const tiles = [...document.querySelectorAll('.product-tile')];
  
  // Find sections/themes containing product tiles
  const sections = [...document.querySelectorAll('[class*="grid"], section, div[class]')]
    .filter(el => el.querySelector('.product-tile') && el.children.length > 1)
    .map(el => {
      const heading = el.querySelector('h2, h3, [class*="title"], [class*="heading"], [class*="headline"]');
      return {
        heading: heading?.textContent?.trim() || '(no heading)',
        count: el.querySelectorAll('.product-tile').length,
        class: el.className.slice(0, 80),
      };
    });
  
  // De-duplicate by heading
  const seenHeadings = new Set();
  const uniqueSections = sections.filter(s => {
    if (seenHeadings.has(s.heading)) return false;
    seenHeadings.add(s.heading);
    return true;
  });
  
  const productSamples = tiles.slice(0, 20).map(t => {
    const brand = t.querySelector('.product-tile__brandname p')?.textContent?.trim() || '';
    const name = t.querySelector('.product-tile__name p')?.textContent?.trim() || '';
    const price = t.querySelector('.base-price__regular')?.textContent?.trim() || '';
    const img = t.querySelector('img')?.getAttribute('alt') || '';
    return { brand, name, price, img };
  });
  
  return { totalTiles: tiles.length, sections: uniqueSections, productSamples };
});

console.error('Total product tiles:', data.totalTiles);
console.error('\nSections/themes:');
data.sections.forEach((s, i) => {
  console.error(`  [${i}] "${s.heading}" - ${s.count} products (${s.class})`);
});
console.error('\nFirst 20 products:');
data.productSamples.forEach((p,i) => {
  const isFood = /frische|kühl|tiefkühl|lebensmittel|international|marken|obst|gemüse|milch|fleisch|brot|aktion|frucht|saft|marmelade|schokolade|käse|joghurt|butter|ei/i.test(p.name + ' ' + p.brand + ' ' + p.img);
  console.error(`  [${i}] ${isFood ? '🍎' : '🔧'} ${p.brand} ${p.name} - ${p.price} (${p.img.slice(0,60)})`);
});

await browser.close();
