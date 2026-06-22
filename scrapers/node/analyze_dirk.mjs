import puppeteer from "puppeteer-extra";
import StealthPlugin from "puppeteer-extra-plugin-stealth";
puppeteer.use(StealthPlugin());

const storeKey = process.argv[2] || "dirk";

const STORE = {
  dirk: "https://www.dirk.nl/aanbiedingen",
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
    // Find a product price element, then walk up to find the full card
    const firstPrice = document.querySelector('div.offer');
    if (!firstPrice) return { error: "no price" };

    // Walk up 5 levels and save HTML
    let el = firstPrice;
    const chain = [];
    for (let i = 0; i < 5; i++) {
      if (!el.parentElement) break;
      el = el.parentElement;
      chain.push({
        level: i + 1,
        tag: el.tagName,
        cls: el.className?.slice(0, 120) || "",
        children: el.children.length,
        text: el.textContent.replace(/\s+/g, " ").trim().slice(0, 200),
      });
    }

    // Also grab the full product tile from its parent chain
    const grandparent = firstPrice.parentElement?.parentElement?.parentElement;
    if (grandparent) {
      return {
        cardHTML: grandparent.outerHTML.slice(0, 10000),
        chain
      };
    }

    return { chain, html: firstPrice.parentElement?.outerHTML?.slice(0, 2000) };
  });

  console.log(JSON.stringify(result, null, 2));
} catch (err) {
  console.error(err.message);
  console.log(JSON.stringify({ error: err.message }));
}

await browser.close();
