import puppeteer from "puppeteer-extra";
import StealthPlugin from "puppeteer-extra-plugin-stealth";
puppeteer.use(StealthPlugin());

const storeKey = process.argv[2] || "plus";
const STORE = {
  plus: "https://www.plus.nl/aanbiedingen",
}[storeKey];

const browser = await puppeteer.launch({
  headless: true,
  args: ["--no-sandbox", "--disable-blink-features=AutomationControlled", "--window-size=1920,1080"],
});

const page = await browser.newPage();
await page.setViewport({ width: 1920, height: 1080 });
await page.evaluateOnNewDocument(() => {
  Object.defineProperty(navigator, "webdriver", { get: () => false });
});

try {
  await page.goto(STORE, { waitUntil: "networkidle2", timeout: 30000 });
  await new Promise(r => setTimeout(r, 3000));

  await page.evaluate(async () => {
    for (let i = 0; i < 5; i++) {
      window.scrollBy(0, 600);
      await new Promise(r => setTimeout(r, 300));
    }
  });
  await new Promise(r => setTimeout(r, 2000));

  const result = await page.evaluate(() => {
    // Find first product-like element
    const strategies = [
      "div.plp-item-wrapper",
      "div.list-item",
      "div.expanded-product-tile",
      "div[class*='plp-item']",
    ];

    for (const sel of strategies) {
      const els = document.querySelectorAll(sel);
      if (els.length > 0) {
        const el = els[0];
        return {
          strategy: sel,
          count: els.length,
          outerHTML: el.outerHTML.slice(0, 10000),
          parentHTML: el.parentElement?.outerHTML?.slice(0, 5000),
        };
      }
    }

    // fallback: find anything with product in the class
    for (const el of document.querySelectorAll("*")) {
      const c = el.className;
      if (typeof c === "string" && /product|offer|item/.test(c) && el.children.length > 1) {
        return {
          strategy: "fallback: " + c.slice(0, 80),
          outerHTML: el.outerHTML.slice(0, 8000),
        };
      }
    }
    return { error: "nothing found" };
  });

  console.log(JSON.stringify(result, null, 2));
} catch (err) {
  console.error(err.message);
  console.log(JSON.stringify({ error: err.message }));
}

await browser.close();
