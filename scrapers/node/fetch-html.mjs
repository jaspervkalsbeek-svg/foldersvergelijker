/**
 * fetch-html.mjs – Haalt gerenderde HTML op via Puppeteer
 *
 * Gebruik: node fetch-html.mjs <url> [--wait <selector>] [--timeout <ms>]
 *
 * Output: de volledige HTML (na JS-uitvoering) naar stdout.
 * Fouten  gaan naar stderr.
 */

import puppeteer from 'puppeteer';

const url     = process.argv[2];
const waitFor = parseArg('--wait');
const timeout = parseInt(parseArg('--timeout') ?? '15000', 10);

if (!url) {
  console.error('Gebruik: node fetch-html.mjs <url> [--wait .selector] [--timeout 15000]');
  process.exit(1);
}

function parseArg(name) {
  const idx = process.argv.indexOf(name);
  return idx >= 0 ? process.argv[idx + 1] : null;
}

let browser;
try {
  browser = await puppeteer.launch({
    headless: true,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-web-security',
      '--disable-features=IsolateOrigins,site-per-process',
      '--window-size=1920,1080',
    ],
  });

  const page = await browser.newPage();

  // Echte browser fingerprint
  await page.setUserAgent(
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36'
  );
  await page.setExtraHTTPHeaders({
    'Accept-Language': 'nl-NL,nl;q=0.9,de;q=0.8,en;q=0.7',
  });
  await page.setViewport({ width: 1920, height: 1080 });

  // Blokkeer overbodige resources (sneller)
  await page.setRequestInterception(true);
  page.on('request', (req) => {
    const type = req.resourceType();
    if (['image', 'stylesheet', 'font', 'media'].includes(type)) {
      req.abort();
    } else {
      req.continue();
    }
  });

  const response = await page.goto(url, {
    waitUntil: 'networkidle2',
    timeout,
  });

  if (!response || !response.ok()) {
    console.error(`HTTP ${response?.status() ?? 'timeout'} voor ${url}`);
    process.exit(1);
  }

  // Wacht optioneel op een CSS selector
  if (waitFor) {
    try {
      await page.waitForSelector(waitFor, { timeout: 5000 });
    } catch {
      // niet erg, sommige paginas hebben 't niet
    }
  }

  // Kleine extra wachttijd voor laatste rendering
  await new Promise((r) => setTimeout(r, 1000));

  const html = await page.content();
  console.log(html);
} catch (err) {
  console.error(`Fout: ${err.message}`);
  process.exit(1);
} finally {
  if (browser) await browser.close();
}
