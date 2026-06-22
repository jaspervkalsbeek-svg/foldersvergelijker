import puppeteer from "puppeteer-extra";
import StealthPlugin from "puppeteer-extra-plugin-stealth";
puppeteer.use(StealthPlugin());

const storeKey = process.argv[2] || "aldi-nl";

const urls = {
  "aldi-nl": "https://www.aldi.nl/aanbiedingen.html",
  "plus": "https://www.plus.nl/aanbiedingen",
  "dirk": "https://www.dirk.nl/aanbiedingen",
  "lidl-nl": "https://www.lidl.nl/aanbiedingen",
  "lidl-de": "https://www.lidl.de/angebote",
  "aldi-de": "https://www.aldi.de/angebote.html",
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
  console.error(`[${storeKey}] Loading ${url}...`);
  await page.goto(url, { waitUntil: "networkidle2", timeout: 30000 });
  await new Promise(r => setTimeout(r, 3000));

  // Scroll down a bit to trigger lazy loading
  await page.evaluate(async () => {
    for (let i = 0; i < 5; i++) {
      window.scrollBy(0, 600);
      await new Promise(r => setTimeout(r, 300));
    }
  });
  await new Promise(r => setTimeout(r, 2000));

  const result = await page.evaluate(() => {
    const out = {};

    // All data-testid values
    const testids = {};
    document.querySelectorAll("[data-testid]").forEach(el => {
      const id = el.getAttribute("data-testid");
      testids[id] = (testids[id] || 0) + 1;
    });
    out.testids = testids;

    // Elements containing "€" or "EUR" or price patterns
    const priceEls = [];
    const allEls = document.querySelectorAll("*");
    for (const el of allEls) {
      if (el.children.length > 0) continue;
      const text = el.textContent.trim();
      if (/[€€]\s*[0-9]/.test(text) || /[0-9]+[.,][0-9]{2}\s*[€€]/.test(text)) {
        priceEls.push({
          tag: el.tagName,
          cls: (el.className+"").slice(0, 100),
          text: text.slice(0, 80),
          parentTag: el.parentElement?.tagName || "",
          parentCls: (el.parentElement?.className+"").slice(0, 80)
        });
      }
    }
    out.priceElements = priceEls.slice(0, 30);

    // Image alt texts (product names)
    const imgAlts = [];
    document.querySelectorAll("img[alt]").forEach(img => {
      const alt = (img.getAttribute("alt") || "").trim();
      if (alt && alt.length > 5 && alt.length < 150) {
        imgAlts.push(alt);
      }
    });
    out.imageAlts = imgAlts.slice(0, 30);

    // H2/H3 texts (often product names)
    const headings = [];
    document.querySelectorAll("h2, h3, h4").forEach(h => {
      const text = h.textContent.trim();
      if (text && text.length > 3 && text.length < 120) {
        headings.push({ tag: h.tagName, text });
      }
    });
    out.headings = headings.slice(0, 20);

    // Any element with card-like class name
    const cardClasses = new Set();
    document.querySelectorAll("[class*='card'], [class*='tile'], [class*='product'], [class*='offer'], [class*='article']").forEach(el => {
      const cls = el.className;
      if (typeof cls === "string") {
        cls.split(/\s+/).forEach(c => {
          if (c.includes("card") || c.includes("tile") || c.includes("product") || c.includes("offer")) {
            cardClasses.add(c.slice(0, 80));
          }
        });
      }
    });
    out.cardClasses = [...cardClasses].slice(0, 30);

    return out;
  });

  console.log(JSON.stringify(result, null, 2));
} catch (err) {
  console.error(`Error: ${err.message}`);
  console.log(JSON.stringify({ error: err.message }));
}

await browser.close();
