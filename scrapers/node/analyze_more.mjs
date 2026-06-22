import puppeteer from "puppeteer-extra";
import StealthPlugin from "puppeteer-extra-plugin-stealth";
puppeteer.use(StealthPlugin());

const storeKey = process.argv[2] || "rewe";
const STORE = {
  rewe: "https://www.rewe.de/angebote/",
  "lidl-nl": "https://www.lidl.nl/aanbiedingen",
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
    let sel, items;
    if (location.host.includes("rewe")) {
      sel = ".cor-offer-renderer-tile";
      items = document.querySelectorAll(sel);
    } else if (location.host.includes("lidl")) {
      sel = ".product-grid-box";
      items = document.querySelectorAll(sel);
    } else if (location.host.includes("netto")) {
      sel = "h4"; // fallback
      items = [{ length: 0 }];
    } else {
      sel = "none";
      items = [];
    }

    const cards = [];
    for (let i = 0; i < Math.min(items.length, 6); i++) {
      const el = items[i];
      cards.push({
        html: el.outerHTML.slice(0, 3000),
        text: el.textContent.replace(/\s+/g, " ").trim().slice(0, 300),
      });
    }

    // If Netto, analyze H3+H4 pairs
    if (location.host.includes("netto")) {
      const h3s = document.querySelectorAll("h3");
      const h4s = document.querySelectorAll("h4");
      const pairs = [];
      for (let i = 0; i < Math.min(h3s.length, h4s.length, 10); i++) {
        pairs.push({
          h3: h3s[i]?.textContent?.trim()?.slice(0, 80),
          h4: h4s[i]?.textContent?.trim()?.slice(0, 80),
          h3Class: h3s[i]?.className?.slice(0, 60),
          h4Class: h4s[i]?.className?.slice(0, 60),
        });
      }
      return {
        strategy: sel,
        count: items.length,
        cards: [],
        nettoPairs: pairs,
        nettoH3Styles: h3s[0]?.getAttribute("style")?.slice(0, 200),
      };
    }

    return { strategy: sel, count: items.length, cards };
  });

  console.log(JSON.stringify(result, null, 2));
} catch (err) {
  console.error(err.message);
  console.log(JSON.stringify({ error: err.message }));
}

await browser.close();
