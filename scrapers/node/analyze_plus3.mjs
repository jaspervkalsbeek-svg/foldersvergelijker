import puppeteer from "puppeteer-extra";
import StealthPlugin from "puppeteer-extra-plugin-stealth";
puppeteer.use(StealthPlugin());

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
  await page.goto("https://www.plus.nl/aanbiedingen", { waitUntil: "networkidle2", timeout: 30000 });
  await new Promise(r => setTimeout(r, 3000));

  await page.evaluate(async () => {
    for (let i = 0; i < 8; i++) {
      window.scrollBy(0, 800);
      await new Promise(r => setTimeout(r, 400));
    }
  });
  await new Promise(r => setTimeout(r, 3000));

  const result = await page.evaluate(() => {
    const wrappers = document.querySelectorAll("div.plp-item-wrapper");
    const cards = [];
    // Skip first 5 (they're promos), analyze next 8
    for (let i = 5; i < Math.min(wrappers.length, 13); i++) {
      const w = wrappers[i];
      const name = w.querySelector(".plp-item-name")?.textContent?.trim() || "";
      const priceInteger = w.querySelector(".product-header-price-integer span")?.textContent?.trim() || "";
      const priceDecimals = w.querySelector(".product-header-price-decimals span")?.textContent?.trim() || "";
      const pricePrevious = w.querySelector(".product-header-price-previous")?.textContent?.trim() || "";
      const img = w.querySelector("img[src]")?.getAttribute("src") || "";
      const imgAlt = w.querySelector("img[alt]")?.getAttribute("alt") || "";
      const promo = w.querySelector(".promo-offer-label")?.textContent?.trim() || "";
      cards.push({
        name,
        priceInteger,
        priceDecimals,
        pricePrevious,
        img: img.slice(0, 120),
        imgAlt,
        promo,
        htmlSnippet: w.querySelector(".plp-item-price")?.outerHTML?.slice(0, 800),
      });
    }
    return cards;
  });

  console.log(JSON.stringify(result, null, 2));
} catch (err) {
  console.error(err.message);
  console.log(JSON.stringify({ error: err.message }));
}

await browser.close();
