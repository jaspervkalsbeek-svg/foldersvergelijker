import puppeteer from "puppeteer-extra";
import StealthPlugin from "puppeteer-extra-plugin-stealth";
puppeteer.use(StealthPlugin());

const storeKey = process.argv[2] || "dirk";

const urls = {
  "dirk": "https://www.dirk.nl/aanbiedingen",
  "plus": "https://www.plus.nl/aanbiedingen",
  "lidl-nl": "https://www.lidl.nl/aanbiedingen",
  "lidl-de": "https://www.lidl.de/angebote",
  "aldi-de": "https://www.aldi-nord.de/angebote.html",
  "rewe": "https://www.rewe.de/angebote/",
  "netto": "https://www.netto.de/angebote",
};

const url = urls[storeKey];
if (!url) { console.error("Unknown store"); process.exit(1); }

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
  await page.goto(url, { waitUntil: "networkidle2", timeout: 30000 });
  await new Promise(r => setTimeout(r, 3000));

  await page.evaluate(async () => {
    for (let i = 0; i < 5; i++) {
      window.scrollBy(0, 600);
      await new Promise(r => setTimeout(r, 300));
    }
  });
  await new Promise(r => setTimeout(r, 2000));

  const result = await page.evaluate(() => {
    const out = { title: document.title };

    // Unique class names containing product/card/tile/offer
    const clsSet = new Set();
    document.querySelectorAll("*").forEach(el => {
      const cls = el.className;
      if (typeof cls === "string") {
        cls.split(/\s+/).forEach(c => {
          if (/product|card|tile|offer|price|item/.test(c)) clsSet.add(c.slice(0, 100));
        });
      }
    });
    out.relevantClasses = [...clsSet].slice(0, 50);

    // Price text nodes with context
    const prices = [];
    document.querySelectorAll("*").forEach(el => {
      if (el.children.length > 0) return;
      const text = el.textContent.trim();
      // Match € price OR number.price format
      if (/[€€]\s*[0-9]/.test(text) || /[0-9]+[,.]?[0-9]*\s*[€€]/.test(text) || /^[0-9]+[,.]?[0-9]{2}$/.test(text.trim())) {
        const parent = el.parentElement;
        const grandparent = parent?.parentElement;
        prices.push({
          text: text.slice(0, 60),
          tag: el.tagName,
          cls: (el.className+"").slice(0, 80),
          parentCls: (parent?.className+"").slice(0, 80),
          gpCls: (grandparent?.className+"").slice(0, 80),
        });
      }
    });
    out.prices = prices.slice(0, 40);

    // Product images (alt texts)
    const imgs = [];
    document.querySelectorAll("img[alt]").forEach(img => {
      const alt = (img.getAttribute("alt") || "").trim();
      if (alt && alt.length > 3 && alt.length < 150) imgs.push(alt);
    });
    out.imageAlts = imgs.slice(0, 25);

    // H2/H3 texts
    const headings = [];
    document.querySelectorAll("h2, h3, h4").forEach(h => {
      const t = h.textContent.trim();
      if (t.length > 3 && t.length < 120) headings.push({ tag: h.tagName, text: t.slice(0, 80) });
    });
    out.headings = headings.slice(0, 20);

    // Data attributes related to products
    const dataAttrs = new Set();
    document.querySelectorAll("[data-price], [data-product], [data-name], [data-article-id]").forEach(el => {
      el.getAttributeNames().forEach(a => {
        if (a.startsWith("data-")) dataAttrs.add(a);
      });
    });
    out.dataAttrs = [...dataAttrs].slice(0, 20);

    return out;
  });

  console.log(JSON.stringify(result, null, 2));
} catch (err) {
  console.error(`Error: ${err.message}`);
  console.log(JSON.stringify({ error: err.message }));
}

await browser.close();
