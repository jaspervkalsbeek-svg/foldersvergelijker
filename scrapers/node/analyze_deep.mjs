import puppeteer from "puppeteer-extra";
import StealthPlugin from "puppeteer-extra-plugin-stealth";
puppeteer.use(StealthPlugin());

const storeKey = process.argv[2] || "dirk";
if (!storeKey) { console.error("Need store"); process.exit(1); }

const STORE = {
  dirk: "https://www.dirk.nl/aanbiedingen",
  plus: "https://www.plus.nl/aanbiedingen",
  "lidl-nl": "https://www.lidl.nl/aanbiedingen",
  "aldi-nl": "https://www.aldi.nl/aanbiedingen.html",
  rewe: "https://www.rewe.de/angebote/",
  netto: "https://www.netto.de/angebote",
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
    const cards = [];

    // Try multiple card selector strategies
    const strategies = [
      // Dirk
      { name: "offer > *", sel: "div.offer" },
      // Plus
      { name: "plp-item-wrapper", sel: "div.plp-item-wrapper" },
      // Lidl
      { name: "odsc-tile", sel: "div.odsc-tile" },
      // Aldi
      { name: "product-tile", sel: '[class*="product-tile"]' },
      // Rewe
      { name: "cor-offer-renderer-tile", sel: "div.cor-offer-renderer-tile" },
    ];

    for (const s of strategies) {
      const els = document.querySelectorAll(s.sel);
      if (els.length > 0) {
        const card = els[0];
        return {
          strategy: s.name,
          selector: s.sel,
          count: els.length,
          outerHTML: card.outerHTML.slice(0, 8000),
          text: card.textContent.replace(/\s+/g, " ").trim().slice(0, 500),
          tagName: card.tagName,
          classList: [...card.classList],
        };
      }
    }

    // Fallback: try to find anything product-like
    for (const cls of document.querySelectorAll("*")) {
      const c = cls.className;
      if (typeof c === "string" && /product|offer|tile|card/.test(c) && cls.children.length > 2) {
        return {
          strategy: "fallback",
          selector: `.${c.split(/\s+/)[0]}`,
          count: document.querySelectorAll(`.${c.split(/\s+/)[0]}`).length,
          outerHTML: cls.outerHTML.slice(0, 8000),
          text: cls.textContent.replace(/\s+/g, " ").trim().slice(0, 500),
          tagName: cls.tagName,
          classList: [...cls.classList],
        };
      }
    }

    return { strategy: "none", found: false };
  });

  console.log(JSON.stringify(result, null, 2));
} catch (err) {
  console.error(err.message);
  console.log(JSON.stringify({ error: err.message }));
}

await browser.close();
