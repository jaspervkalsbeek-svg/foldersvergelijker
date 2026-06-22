import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
puppeteer.use(StealthPlugin());

const browser = await puppeteer.launch({headless:true,args:['--no-sandbox']});

// Try each food theme URL directly
const themes = [
  'Frischekracher',
  'Gek%C3%BChlte+Produkte',
  'Internationale+K%C3%BCche',
  'Markenartikel',
  'Tiefk%C3%BChlprodukte',
];

for (const theme of themes) {
  console.error(`\n═══ Theme: ${decodeURIComponent(theme)} ═══`);
  try {
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    page.setDefaultTimeout(10000);
    await page.goto(`https://www.aldi-sued.de/angebote/2026-05-22?theme=${theme}`, {waitUntil:'networkidle0', timeout:15000});
    await new Promise(r => setTimeout(r, 1000));

    const info = await page.evaluate(() => {
      const tiles = [...document.querySelectorAll('.product-tile')];
      const samples = tiles.slice(0, 5).map(t => {
        const brand = t.querySelector('.product-tile__brandname p')?.textContent?.trim() || '';
        const name = t.querySelector('.product-tile__name p')?.textContent?.trim() || '';
        const price = t.querySelector('.base-price__regular')?.textContent?.trim() || '';
        return brand + ' ' + name + ' - ' + price;
      });
      return { count: tiles.length, samples, url: location.href };
    });
    console.error(`  URL: ${info.url}`);
    console.error(`  Products: ${info.count}`);
    info.samples.forEach(s => console.error('  - ' + s));
    await page.close();
  } catch(e) { console.error(`  Error: ${e.message?.slice(0,100)}`);
  }
}

await browser.close();
