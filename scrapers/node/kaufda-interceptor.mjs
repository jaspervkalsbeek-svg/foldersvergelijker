import puppeteer from "puppeteer-extra";
import StealthPlugin from "puppeteer-extra-plugin-stealth";
puppeteer.use(StealthPlugin());

async function main() {
  const browser = await puppeteer.launch({
    headless: true,
    args: ["--no-sandbox", "--disable-setuid-sandbox", "--disable-dev-shm-usage", "--window-size=1920,1080"],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1920, height: 1080 });

  // Log ALL network requests  
  const requests = [];
  page.on("request", (req) => {
    const url = req.url();
    if (url.startsWith("http") && !url.includes("favicon") && !url.includes("font") && !url.includes("analytics") && !url.includes("collect")) {
      requests.push({ url, method: req.method(), type: "request" });
    }
  });

  console.error("Navigating...");
  await page.goto("https://www.kaufda.de/Geschaefte/Lidl", { waitUntil: "networkidle2", timeout: 30000 });
  console.error("Page loaded, waiting...");
  await new Promise((r) => setTimeout(r, 5000));

  // Get all text from the page
  const bodyText = await page.evaluate(() => document.body.innerText);
  const firstLines = bodyText.split("\n").filter(l => l.trim()).slice(0, 30);

  // Get all links
  const links = await page.evaluate(() =>
    [...document.querySelectorAll("a[href]")].map(a => ({ href: a.href, text: a.textContent?.trim()?.slice(0, 50) }))
  );

  // Check for product/offer elements
  const offerElements = await page.evaluate(() => {
    const selectors = ['[data-testid*="offer"]', '[data-testid*="Offer"]', '[class*="offer"]', '[class*="card"]', '[class*="tile"]', "article"];
    const results = {};
    for (const sel of selectors) {
      const els = document.querySelectorAll(sel);
      if (els.length > 0) {
        results[sel] = [...els].slice(0, 3).map(el => ({
          outer: el.outerHTML?.slice(0, 300) || "",
          text: el.textContent?.slice(0, 100) || "",
        }));
      }
    }
    return results;
  });

  console.log(JSON.stringify({
    requests: requests.filter(r => !r.url.includes("newrelic") && !r.url.includes("nr-data")),
    bodyPreview: firstLines,
    links: links.filter(l => !l.href.includes("google") && !l.href.includes("facebook") && !l.href.includes("doubleclick") && !l.href.includes("nr-data")),
    offerElements,
  }, null, 2));
  await browser.close();
}

main().catch(console.error);
