import puppeteer from "puppeteer-extra";
import StealthPlugin from "puppeteer-extra-plugin-stealth";
puppeteer.use(StealthPlugin());

const stores = [
  { key: "jumbo",    url: "https://www.jumbo.com/aanbiedingen" },
  { key: "aldi-nl",  url: "https://www.aldi.nl/aanbiedingen.html" },
  { key: "plus",     url: "https://www.plus.nl/aanbiedingen" },
  { key: "dirk",     url: "https://www.dirk.nl/aanbiedingen" },
  { key: "lidl-nl",  url: "https://www.lidl.nl/aanbiedingen" },
  { key: "lidl-de",  url: "https://www.lidl.de/angebote" },
  { key: "aldi-de",  url: "https://www.aldi.de/angebote.html" },
  { key: "rewe",     url: "https://www.rewe.de/angebote/" },
  { key: "edeka",    url: "https://www.edeka.de/angebote.jsp" },
  { key: "netto",    url: "https://www.netto.de/angebote" },
];

const browser = await puppeteer.launch({
  headless: true,
  args: ["--no-sandbox", "--disable-blink-features=AutomationControlled", "--window-size=1920,1080"],
});

for (const store of stores) {
  const page = await browser.newPage();
  await page.setViewport({ width: 1920, height: 1080 });
  await page.evaluateOnNewDocument(() => {
    Object.defineProperty(navigator, "webdriver", { get: () => false });
  });

  try {
    console.error(`\n[${store.key}] Testing ${store.url}...`);
    const resp = await page.goto(store.url, { waitUntil: "networkidle2", timeout: 25000 });

    const result = await page.evaluate(() => {
      const text = document.body?.textContent || "";
      return {
        title: document.title,
        status: "ok",
        bodyLen: text.length,
        preview: text.slice(0, 200).replace(/\s+/g, " ").trim(),
        linkCount: document.querySelectorAll("a").length,
        imgCount: document.querySelectorAll("img").length,
        h2Count: document.querySelectorAll("h2").length,
        h3Count: document.querySelectorAll("h3").length,
        productSelectors: [
          ...document.querySelectorAll('[class*="product-card"], [class*="promotion-card"], [class*="product-tile"], [data-testid="product-card"], [data-testid="product"], article, [class*="price"]')
        ].length,
      };
    });

    console.log(JSON.stringify({ store: store.key, url: store.url, ...result }));
  } catch (err) {
    console.log(JSON.stringify({ store: store.key, url: store.url, error: err.message }));
  }

  await page.close();
}

await browser.close();
