import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
puppeteer.use(StealthPlugin());

const browser = await puppeteer.launch({headless:true,args:['--no-sandbox']});
const page = await browser.newPage();
await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
await page.goto('https://www.aldi-sued.de/angebote', {waitUntil:'networkidle0', timeout:20000});

const info = await page.evaluate(() => {
  const links = [...document.querySelectorAll('a[href*="/angebote/20"]')];
  const dates = [...new Set(links.map(l => {
    const m = l.href.match(/\/angebote\/([0-9-]{10})/);
    return m ? {date: m[1], text: l.textContent.trim().slice(0,60), href: l.href} : null;
  }).filter(Boolean))];
  
  const sections = [...document.querySelectorAll('[class*="product-teaser-list"]')].map(el => {
    const h = el.querySelector('h2, h3, [class*="title"], [class*="headline"]');
    return h?.textContent?.trim() || '';
  }).filter(Boolean);
  
  // Get all theme links
  const themeLinks = [...document.querySelectorAll('a[href*="theme="]')].map(a => ({
    href: a.href,
    text: a.textContent.trim().slice(0,60),
  }));
  
  return { dates, sections, themeLinks };
});

console.error('Dates found:');
info.dates.forEach(d => console.error(`  ${d.date} - "${d.text}"`));
console.error('\nSections:', info.sections);
console.error('\nTheme links:');
info.themeLinks.forEach(t => console.error(`  ${t.text} -> ${t.href}`));

await browser.close();
