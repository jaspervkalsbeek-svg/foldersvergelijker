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
  await page.goto("https://www.netto.de/angebote", { waitUntil: "networkidle2", timeout: 30000 });
  await new Promise(r => setTimeout(r, 3000));

  await page.evaluate(async () => {
    for (let i = 0; i < 5; i++) {
      window.scrollBy(0, 600);
      await new Promise(r => setTimeout(r, 300));
    }
  });
  await new Promise(r => setTimeout(r, 2000));

  const result = await page.evaluate(() => {
    // Analyze the offers section
    const offers = [];
    document.querySelectorAll("h3, h4").forEach(h => {
      const t = h.textContent.trim();
      if (t && t.length < 100 && t.length > 1) {
        offers.push({
          tag: h.tagName,
          text: t.slice(0, 80),
          cls: h.className?.slice(0, 60),
          parentCls: h.parentElement?.className?.slice(0, 80),
          prevTag: h.previousElementSibling?.tagName,
          prevText: h.previousElementSibling?.textContent?.trim()?.slice(0, 60),
          nextTag: h.nextElementSibling?.tagName,
          nextText: h.nextElementSibling?.textContent?.trim()?.slice(0, 60),
        });
      }
    });

    // Also look for price patterns
    const priceEls = [];
    document.querySelectorAll("*").forEach(el => {
      if (el.children.length > 0) return;
      const t = el.textContent.trim();
      if (/^[0-9]+[,.][0-9]{2}$/.test(t) || /^[0-9]+[,.][0-9]{2}\s*[€€*]/.test(t)) {
        priceEls.push({
          text: t.slice(0, 30),
          tag: el.tagName,
          cls: el.className?.slice(0, 60),
          parentCls: el.parentElement?.className?.slice(0, 60),
        });
      }
    });

    return {
      headings: offers.slice(0, 30),
      priceElements: priceEls.slice(0, 20),
    };
  });

  console.log(JSON.stringify(result, null, 2));
} catch (err) {
  console.error(err.message);
  console.log(JSON.stringify({ error: err.message }));
}

await browser.close();
