import puppeteer from "puppeteer-extra";
import StealthPlugin from "puppeteer-extra-plugin-stealth";
import https from "https";

puppeteer.use(StealthPlugin());

function fetchUrl(url, accept = "application/json") {
  return new Promise((resolve, reject) => {
    https.get(url, {
      headers: { "User-Agent": "Mozilla/5.0", Accept: accept },
    }, (res) => {
      let data = "";
      res.on("data", (c) => (data += c));
      res.on("end", () => resolve(data));
    }).on("error", reject);
  });
}

function fetchJson(url) {
  return fetchUrl(url, "application/json").then(JSON.parse);
}

const storeName = (process.argv[2] ?? "").toLowerCase();
const timeout = 30000;

const STORES = {
  "ah": {
    url: "https://www.ah.nl/bonus",
    extract: extractAH,
    scroll: true,
    cookieBtn: '[data-testid="accept-cookies"]',
  },
  "lidl-nl": {
    url: "https://www.lidl.nl/aanbiedingen",
    extract: extractLidlNL,
    scroll: true,
  },
  "aldi-nl": {
    url: "https://www.aldi.nl/aanbiedingen.html",
    extract: extractAldiNL,
    scroll: true,
    cookieBtn: '[data-testid="accept-cookies"]',
  },
  "plus": {
    url: "https://www.plus.nl/aanbiedingen",
    extract: extractPlus,
    scroll: true,
  },
  "aldi-sued": {
    custom: true,
  },
  "lidl-de": {
    custom: true,
  },
};

if (!STORES[storeName]) {
  console.error(`Onbekende winkel: ${storeName}`);
  console.error("Beschikbaar: " + Object.keys(STORES).join(", "));
  process.exit(1);
}

const cfg = STORES[storeName];

// ── AH-specific extractor ──
function extractAH() {
  const cards = document.querySelectorAll('[class*="promotion-card_root"]');
  const products = [];
  const seen = new Set();

  for (const card of cards) {
    const titleEl = card.querySelector('[data-testid="card-title"]');
    const name = titleEl?.textContent?.trim() || card.querySelector("img")?.getAttribute("alt") || "";
    if (!name || name.length < 3) continue;

    const key = name.toLowerCase();
    if (seen.has(key)) continue;
    seen.add(key);

    // Promotion labels: "2 voor 1.19", "1+1 gratis", "€1korting", etc.
    const promoEl = card.querySelector('[class*="promotion-label-base_base"]');
    const promoAria = promoEl?.getAttribute("aria-label") || "";
    const promoText = promoEl?.textContent?.replace(/\s+/g, " ")?.trim() || "";

    // Get all price numbers
    const allText = card.textContent.replace(/\s+/g, " ").trim();
    const priceMatches = allText.match(/([0-9]+[,.][0-9]{2})/g);
    const dateEl = card.querySelector('[data-testid="promotion-card-labels"]');
    const dateText = dateEl?.textContent?.trim() || "";

    // Description e.g. "Los", "per stuk", "per kg"
    const descEl = card.querySelector('[data-testid="card-description"]');
    const description = descEl?.textContent?.trim() || "";

    // Determine prices from the card text
    let price = 0;
    let unitSize = null;
    let unitPrice = null;

    // Try to extract price from aria-label first (most reliable)
    const ariaPrice = promoAria.match(/([0-9]+[,.][0-9]{2})/);
    if (ariaPrice) {
      price = parseFloat(ariaPrice[1].replace(",", "."));
    }

    // If we have a simple price like "voor3.99" or "2 voor1.19" or "€1korting"
    // The actual euro amount in the aria-label is what we want
    // For "2 voor 1.19": price = 1.19
    // For "1+1 gratis": no specific price shown
    // For "€1korting": no base price, just discount
    // For "2e halve prijs": need to calculate

    // Try to find unit info in the name (e.g. "400 gram", "1 kilo", "1.5 liter", "500 ml", "85 gram")
    const unitMatch = name.match(/([0-9]+[.,]?[0-9]*)\s*(gram|g|kg|ml|l|liter|stuk|stuks|pack|zak|fles|blik|kilo)\b/i);
    if (unitMatch) {
      const val = parseFloat(unitMatch[1].replace(",", "."));
      const unit = unitMatch[2].toLowerCase();
      if (unit === "kg" || unit === "kilo") {
        unitSize = `${val} kg`;
        if (price > 0) unitPrice = price / val;
      } else if (unit === "gram" || unit === "g") {
        unitSize = `${val} gram`;
        if (price > 0) unitPrice = (price / val) * 1000;
      } else if (unit === "ml" || unit === "milliliter") {
        unitSize = `${val} ml`;
        if (price > 0) unitPrice = (price / val) * 1000;
      } else if (unit === "l" || unit === "liter") {
        unitSize = `${val} l`;
        if (price > 0) unitPrice = price / val;
      }
    }

    // For combined products like "Alle AH Italiaanse roerbakgroenten", skip if no price
    if (price <= 0 && !name.toLowerCase().includes("alle ")) continue;

    const image = window.__getProductImage(card.querySelector("img"));
    const href = card.getAttribute("href") || "";

    products.push({
      name,
      brand: name.startsWith("AH ") ? "Albert Heijn" : null,
      price,
      description,
      promo_label: promoAria || promoText,
      date_text: dateText,
      unit_size: unitSize,
      unit_price: unitPrice ? Math.round(unitPrice * 100) / 100 : null,
      image,
      url: href ? `https://www.ah.nl${href}` : null,
    });
  }

  return products;
}

// ── Generic product extractor (fallback) ──
function extractGeneric() {
  const d = document;
  const products = [];
  const seen = new Set();

  const selectors = [
    '[class*="product-card"]', '[class*="promotion-card"]', '[class*="product-tile"]',
    '[data-testid="product-card"]', '[data-testid="product"]',
    "article[class*='product']", "article[class*='offer']",
  ];

  let elements = [];
  for (const sel of selectors) {
    const found = d.querySelectorAll(sel);
    if (found.length > 0) { elements = found; break; }
  }

  if (elements.length === 0) {
    elements = d.querySelectorAll('[class*="price"]');
  }

  for (const el of elements) {
    const text = el.textContent?.trim() ?? "";
    if (text.length < 5) continue;

    let name =
      el.querySelector("h2")?.textContent?.trim() ??
      el.querySelector("h3")?.textContent?.trim() ??
      el.querySelector('[class*="title"]')?.textContent?.trim() ??
      el.querySelector("img")?.getAttribute("alt") ??
      "";
    if (!name || name.length < 3) continue;

    let price = 0;
    const priceText =
      el.querySelector('[class*="price"]')?.textContent?.trim() ?? "";
    const priceMatch = priceText.match(/[€€]\s*([0-9]+[.,][0-9]{2})/);
    if (priceMatch) {
      price = parseFloat(priceMatch[1].replace(",", "."));
    } else {
      const fallback = text.match(/[€€]\s*([0-9]+[.,][0-9]{2})/);
      if (fallback) price = parseFloat(fallback[1].replace(",", "."));
    }

    if (price <= 0) continue;

    const imgEl = el.querySelector("img[src]");
    const image = imgEl?.getAttribute("src") ?? null;
    const url = el.querySelector("a")?.getAttribute("href") ?? null;

    const key = name.toLowerCase() + "|" + price;
    if (seen.has(key)) continue;
    seen.add(key);

    products.push({ name, price, image, url });
  }

  return products;
}

// ── Dirk extractor ──
function extractDirk() {
  const articles = document.querySelectorAll('article[data-product-id]');
  const products = [];
  const seen = new Set();

  for (const art of articles) {
    const titleEl = art.querySelector('a.bottom p.title');
    const name = titleEl?.textContent?.trim() || "";
    if (!name || name.length < 2) continue;

    const key = name.toLowerCase();
    if (seen.has(key)) continue;
    seen.add(key);

    const subtitleEl = art.querySelector('a.bottom span.subtitle');
    const subtitle = subtitleEl?.textContent?.trim() || "";

    // Price: combine euros + cents
    const eurosEl = art.querySelector('.hasEuros.price-large');
    const centsEl = art.querySelector('.price-small');
    let price = 0;
    if (eurosEl && centsEl) {
      price = parseFloat(eurosEl.textContent.trim() + "." + centsEl.textContent.trim());
    }

    // Regular (was) price
    const regPriceEl = art.querySelector('.regular-price span');
    let wasPrice = null;
    if (regPriceEl) {
      const rp = parseFloat(regPriceEl.textContent.trim());
      if (!isNaN(rp)) wasPrice = rp;
    }

    const labelEl = art.querySelector('.label.price-label .description');
    const promoLabel = labelEl?.textContent?.trim() || "";

    const image = window.__getProductImage(art.querySelector('img.main-image') || art.querySelector('img'));
    const linkEl = art.querySelector('a.top[href]');
    const url = linkEl?.getAttribute('href') ?? null;

    const description = subtitle;

    // Extract unit size from subtitle or all text
    let unitSize = null;
    let unitPrice = null;
    const unitMatch = (description + " " + name).match(/\b([0-9]+[.,]?[0-9]*)\s*(cl|gram|g|kg|ml|l|liter|kilo|stuk|stuks|pack|zak|fles|blik|schaal|beker)\b/i);
    if (unitMatch) {
      const val = parseFloat(unitMatch[1].replace(",", "."));
      const unit = unitMatch[2].toLowerCase();
      if (["kg", "kilo"].includes(unit)) {
        unitSize = `${val} kg`;
        if (price > 0) unitPrice = price / val;
      } else if (["gram", "g"].includes(unit)) {
        unitSize = `${val} gram`;
        if (price > 0) unitPrice = (price / val) * 1000;
      } else if (["ml", "cl"].includes(unit)) {
        const factor = unit === "cl" ? 10 : 1;
        unitSize = `${val} ${unit}`;
        if (price > 0) unitPrice = (price / (val * factor)) * 1000;
      } else {
        unitSize = `${val} ${unit}`;
        if (price > 0) unitPrice = price / val;
      }
    }

    products.push({
      name,
      price,
      description,
      promo_label: promoLabel,
      unit_size: unitSize,
      unit_price: unitPrice ? Math.round(unitPrice * 100) / 100 : null,
      image,
      url: url ? `https://www.dirk.nl${url}` : null,
    });
  }

  return products;
}

// ── Rewe extractor ──
function extractRewe() {
  const tiles = document.querySelectorAll('article.cor-offer-renderer-tile');
  const products = [];
  const seen = new Set();

  for (const tile of tiles) {
    const titleLink = tile.querySelector('h3.cor-offer-information__title a');
    const name = titleLink?.textContent?.trim() || "";
    if (!name || name.length < 2) continue;

    const key = name.toLowerCase();
    if (seen.has(key)) continue;
    seen.add(key);

    // Price (German format: "1,99 €")
    const priceEl = tile.querySelector('.cor-offer-price__tag-price');
    let price = 0;
    if (priceEl) {
      const priceText = priceEl.textContent.trim();
      const m = priceText.match(/([0-9]+)[,.]\s*([0-9]{2})/);
      if (m) price = parseFloat(m[1] + "." + m[2]);
    }

    // Build description from additional spans
    const additionalSpans = tile.querySelectorAll('.cor-offer-information__additional');
    const parts = [];
    let unitSize = null;
    let unitPrice = null;

    for (const span of additionalSpans) {
      const text = span.textContent.trim();
      parts.push(text);

      // Parse unit price: "(1 kg = 5,69 €)" or "(1 l = 1,98 €)"
      const upMatch = text.match(/\(1\s*(kg|l)\s*=\s*([0-9]+)[,.]\s*([0-9]{2})\s*€\)/);
      if (upMatch) {
        unitPrice = parseFloat(upMatch[2] + "." + upMatch[3]);
      }

      // Parse size: "je 350-g-Pckg.", "je 500-g-Pckg.", "je 0,5-l-Dose"
      if (!unitSize) {
        const sizeMatch = text.match(/je\s+([0-9]+[.,]?[0-9]*)-?([gmlk]+)/i);
        if (sizeMatch) {
          const val = sizeMatch[1].replace(",", ".");
          unitSize = val + " " + sizeMatch[2];
        }
      }
    }
    const description = parts.filter(Boolean).join(", ");

    // Promo label
    const labelEl = tile.querySelector('.cor-offer-price__tag-label');
    const promoLabel = labelEl?.textContent?.trim() || "";

    // Image (prefer product image over loyalty badge)
    const image = window.__getProductImage(tile.querySelector('.cor-offer-image img') || tile.querySelector('img'));

    products.push({
      name,
      price,
      description,
      promo_label: promoLabel,
      unit_size: unitSize,
      unit_price: unitPrice,
      image,
      url: null,
    });
  }

  return products;
}

// ── Plus extractor ──
function extractPlus() {
  const wrappers = document.querySelectorAll('div.plp-item-wrapper');
  const products = [];
  const seen = new Set();

  for (const w of wrappers) {
    const nameEl = w.querySelector('.plp-item-name span');
    const name = nameEl?.textContent?.trim() || "";
    if (!name || name.length < 2) continue;

    const key = name.toLowerCase();
    if (seen.has(key)) continue;
    seen.add(key);

    // Price integer + decimals (e.g. "3." + "99")
    const intEl = w.querySelector('.product-header-price-integer span');
    const decEl = w.querySelector('.product-header-price-decimals span');
    let price = 0;
    const intText = intEl?.textContent?.trim() || "";
    const decText = decEl?.textContent?.trim() || "";
    if (intText && decText) {
      const combined = intText.replace(/[^0-9]/g, "") + "." + decText;
      const p = parseFloat(combined);
      if (!isNaN(p)) price = p;
    }

    // Promo label
    const promoEl = w.querySelector('.promo-offer-label');
    const promoLabel = promoEl?.textContent?.trim() || "";

    // Image
    const image = window.__getProductImage(w.querySelector('img'));

    // Description from complementary text
    const compEl = w.querySelector('.plp-item-complementary');
    const description = compEl?.textContent?.trim() || "";

    products.push({
      name,
      price,
      description,
      promo_label: promoLabel,
      unit_size: null,
      unit_price: null,
      image,
      url: null,
    });
  }

  return products;
}

// ── Lidl NL extractor ──
function extractLidlNL() {
  const boxes = document.querySelectorAll('div.product-grid-box');
  const products = [];
  const seen = new Set();

  for (const box of boxes) {
    // Name from title element
    const titleEl = box.querySelector('.product-grid-box__title');
    let name = titleEl?.textContent?.trim() || "";

    // Fallback: fulltitle attribute
    if (!name) {
      name = box.getAttribute('fulltitle') || "";
    }

    // Fallback: link text
    if (!name) {
      const linkEl = box.querySelector('a.odsc-tile__link');
      name = linkEl?.textContent?.trim() || "";
    }

    if (!name || name.length < 2) continue;

    const key = name.toLowerCase();
    if (seen.has(key)) continue;
    seen.add(key);

    // Current price
    const priceValEl = box.querySelector('.ods-price__value');
    let price = 0;
    if (priceValEl) {
      const p = parseFloat(priceValEl.textContent.trim());
      if (!isNaN(p)) price = p;
    }

    // Stroked (original) price
    const strokeEl = box.querySelector('.ods-price__stroke-price s, .ods-price__stroke-price');
    let wasPrice = null;
    if (strokeEl) {
      const wp = parseFloat(strokeEl.textContent.trim());
      if (!isNaN(wp)) wasPrice = wp;
    }

    // Extract text content for unit info
    const allText = box.textContent.replace(/\s+/g, " ").trim();

    // Description from all text excluding rendered date
    let description = allText.replace(/Rendered:\s*[0-9-T:.Z]+/g, "").trim();

    // Unit info from text (e.g., "Halve kilo", "500 g", "250 g", "250 ml")
    let unitSize = null;
    // Look for "NUMBER unit" patterns, but NOT date-like patterns (e.g., 25/05, 31/05)
    const unitChunks = allText.split(/[\s,;]+/);
    for (let i = 0; i < unitChunks.length - 1; i++) {
      const numMatch = unitChunks[i].match(/^([0-9]+)$/);
      if (!numMatch) continue;
      const next = unitChunks[i + 1].toLowerCase().replace(/^\.+/, "");
      if (/^(gram|g|kg|ml|cl|l|liter|kilo|stuk|stuks)$/.test(next) && parseInt(numMatch[1]) < 10000) {
        unitSize = numMatch[1] + " " + next;
        break;
      }
    }
    if (!unitSize) {
      const unitMatch = allText.match(/\b([0-9]+)\s*(gram|g|kg|ml|cl|l|liter|kilo|stuk)\b/i);
      if (unitMatch && parseInt(unitMatch[1]) < 10000) {
        unitSize = unitMatch[1] + " " + unitMatch[2].toLowerCase();
      }
    }

    // Image (prefer product image)
    const image = window.__getProductImage(box.querySelector('.odsc-image-gallery img') || box.querySelector('img'));

    // URL
    const linkEl = box.querySelector('a.odsc-tile__link');
    const url = linkEl?.getAttribute('href') ?? null;

    products.push({
      name,
      price,
      description,
      promo_label: wasPrice ? `Was €${wasPrice}` : "",
      unit_size: unitSize,
      unit_price: null,
      image,
      url: url ? `https://www.lidl.nl${url}` : null,
    });
  }

  return products;
}

// ── Aldi NL extractor ──
function extractAldiNL() {
  const tiles = document.querySelectorAll('[class*="product-tile"]');
  const products = [];
  const seen = new Set();

  for (const tile of tiles) {
    const nameEl = tile.querySelector('[class*="product-name"], [class*="product-tile__name"]');
    const name = nameEl?.textContent?.trim() || "";
    if (!name || name.length < 2) continue;

    const key = name.toLowerCase();
    if (seen.has(key)) continue;
    seen.add(key);

    // Price elements
    const priceEl = tile.querySelector('[class*="price"], [class*="tag-current-price"]');
    let price = 0;
    if (priceEl) {
      const text = priceEl.textContent.trim();
      const m = text.match(/([0-9]+)[,.]\s*([0-9]{2})/);
      if (m) price = parseFloat(m[1] + "." + m[2]);
    }

    // Unit price (e.g., "€2.99/kg")
    let unitPrice = null;
    let unitSize = null;
    const unitPriceEl = tile.querySelector('[class*="tag-base-price"], [class*="base-price"]');
    if (unitPriceEl) {
      const upText = unitPriceEl.textContent.trim();
      const upMatch = upText.match(/([0-9]+)[,.]?\s*([0-9]{2})?\s*[€€]\s*\/\s*(kg|l)/i);
      if (upMatch) {
        unitPrice = parseFloat(upMatch[1] + "." + (upMatch[2] || "00"));
      }
    }

    // Image
    const image = window.__getProductImage(tile.querySelector('img'));

    // Promo label
    const promoEl = tile.querySelector('[class*="badge"], [class*="promo"], [class*="tag"]');
    const promoLabel = promoEl?.textContent?.trim() || "";

    products.push({
      name,
      price,
      description: "",
      promo_label: promoLabel,
      unit_size: unitSize,
      unit_price: unitPrice,
      image,
      url: null,
    });
  }

  return products;
}

// ── Penny extractor ──
function extractPenny() {
  const articles = document.querySelectorAll('article.offer-tile');
  const products = [];
  const seen = new Set();

  for (const art of articles) {
    const nameEl = art.querySelector('.offer-tile__headline');
    const name = nameEl?.textContent?.trim() || "";
    if (!name || name.length < 2) continue;

    const key = name.toLowerCase();
    if (seen.has(key)) continue;
    seen.add(key);

    // Price: prefer non-app (secondary) offer price, fall back to primary
    const secondaryPriceEl = art.querySelector('[data-bubble-secondary] .bubble__price-value');
    const primaryPriceEl = art.querySelector('.bubble:not(.bubble--secondary) .bubble__price-value');
    const priceText = (secondaryPriceEl?.textContent?.trim() || primaryPriceEl?.textContent?.trim() || "").replace(/\*/g, "");
    let price = 0;
    const pm = priceText.match(/([0-9]+)[.,]\s*([0-9]{2})/);
    if (pm) price = parseFloat(pm[1] + "." + pm[2]);

    if (price <= 0) continue;

    // Unit price text: "je 250 g (1 kg = 4.76)" or "je kg"
    const unitTextEl = art.querySelector('.offer-tile__unit-price');
    const unitText = unitTextEl?.textContent?.trim() || "";

    // For dual-price items, extract the "ohne App" part
    let unitForParsing = unitText;
    const ohneAppMatch = unitText.match(/ohne App:\s*(.+)$/i);
    if (ohneAppMatch) {
      unitForParsing = ohneAppMatch[1];
    } else {
      // Remove "mit App: ... ;" prefix
      unitForParsing = unitText.replace(/^mit App:\s*[^;]+;\s*/i, "");
    }

    // Parse unit size: "je 250 g", "je 0,75 l", "je 1.000 g", "je 1,5-kg-Packung"
    let unitSize = null;
    const sizeMatch = unitForParsing.match(/je\s+([0-9]+[.,]?[0-9]*)\s*-?\s*(g|kg|ml|l|liter)/i);
    if (sizeMatch) {
      let val = sizeMatch[1].replace(",", ".");
      if (val.match(/^\d{1,3}(\.\d{3})+$/)) {
        val = val.replace(/\./g, "");
      }
      unitSize = val + " " + sizeMatch[2].toLowerCase();
    }

    // Parse unit price: "1 kg = 8.88"
    let unitPrice = null;
    const upMatch = unitForParsing.match(/1\s*(kg|l)\s*=\s*([0-9]+)[.,]?\s*([0-9]{2})/i);
    if (upMatch) {
      unitPrice = parseFloat(upMatch[2] + "." + (upMatch[3] || "00"));
    }

    // Image
    const image = window.__getProductImage(art.querySelector('.offer-tile__image') || art.querySelector('img'));

    // Promo badge (e.g. "-54%", "Preisknaller")
    const badgeEl = art.querySelector('.offer-tile__badges .badge:last-child');
    const promoLabel = badgeEl?.textContent?.trim() || "";

    products.push({
      name,
      price,
      description: unitForParsing.trim(),
      promo_label: promoLabel,
      unit_size: unitSize,
      unit_price: unitPrice,
      image,
      url: null,
    });
  }

  return products;
}

// ── Aldi Süd custom flow ──
const ALDI_SUED_FOOD_THEMES = [
  "Frischekracher", "Gekühlte Produkte", "Internationale Küche",
  "Markenartikel", "Tiefkühlprodukte",
];

async function runAldiSued(browser) {
  const page = await browser.newPage();
  await page.setUserAgent("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
  page.setDefaultTimeout(15000);
  await page.evaluateOnNewDocument(() => {
    window.__getProductImage = function(img) {
      if (!img) return null;
      const ok = (s) => s && s.length > 8 && !s.startsWith('data:') && !s.startsWith('blob:') && !s.startsWith('[[') && !s.match(/^[\s{}/\\]/);
      let s = img.getAttribute('data-src');
      if (ok(s)) return s;
      s = img.getAttribute('src');
      if (ok(s)) return s;
      try { s = img.currentSrc; } catch(e) {}
      if (ok(s)) return s;
      return null;
    };
  });

  // Discover theme URLs from main page
  await page.goto("https://www.aldi-sued.de/angebote", { waitUntil: "networkidle2", timeout });
  await new Promise((r) => setTimeout(r, 2000));

  const themeUrls = await page.evaluate((foodThemes) => {
    const links = [...document.querySelectorAll('a[href*="/angebote/"][href*="theme="]')];
    return [...new Set(links
      .map((l) => {
        const m = l.href.match(/theme=(.+)$/);
        const theme = m ? decodeURIComponent(m[1]) : "";
        return foodThemes.includes(theme) ? l.href : null;
      })
      .filter(Boolean))]
      .slice(0, 12);
  }, ALDI_SUED_FOOD_THEMES);

  console.error(`[aldi-sued] ${themeUrls.length} food-theme paginas gevonden`);

  const allProducts = [];
  const seen = new Set();

  for (const url of themeUrls) {
    console.error(`[aldi-sued] Bezoek: ${url}`);
    try {
      await page.goto(url, { waitUntil: "networkidle2", timeout: 15000 });
      await new Promise((r) => setTimeout(r, 1000));

      const products = await page.evaluate(() => {
        const tiles = document.querySelectorAll(".product-tile");
        return [...tiles].map((t) => {
          const brand = t.querySelector(".product-tile__brandname p")?.textContent?.trim() || "";
          const name = t.querySelector(".product-tile__name p")?.textContent?.trim() || "";
          const fullName = (brand + " " + name).trim();

          const priceText = t.querySelector(".base-price--product-tile .base-price__regular")?.textContent?.trim() || "";
          const pm = priceText.match(/([0-9]+)[,.]?\s*([0-9]{2})?(?:\s*[€])/);
          let price = 0;
          if (pm) {
            if (pm[2]) price = parseFloat(pm[1] + "." + pm[2]);
            else price = parseFloat(pm[1]);
          }

          // Parse unit/comparison price from description
          const compEl = t.querySelector('[class*="comparison"], [class*="base-price"], [class*="unit"]');
          const compText = compEl?.textContent?.trim() || "";
          let unitSize = null;
          let unitPrice = null;
          const sizeMatch = compText.match(/([0-9]+[.,]?[0-9]*)\s*(kg|l|g|ml)/);
          if (sizeMatch) {
            unitSize = sizeMatch[1].replace(",", ".") + " " + sizeMatch[2].toLowerCase();
          }
          const upMatch = compText.match(/([0-9]+)[,.]?\s*([0-9]{2})?\s*[€]\s*\/\s*1\s*(kg|l)/);
          if (upMatch) {
            unitPrice = parseFloat(upMatch[1] + "." + (upMatch[2] || "00"));
          }

          const img = window.__getProductImage(t.querySelector("img"));

          return { name: fullName, price, description: compText, unit_size: unitSize, unit_price: unitPrice, image: img, url: null };
        }).filter((p) => p.name && p.price > 0);
      });

      for (const p of products) {
        const key = p.name.toLowerCase();
        if (!seen.has(key)) {
          seen.add(key);
          allProducts.push(p);
        }
      }
    } catch (e) {
      console.error(`[aldi-sued] Fout bij ${url}: ${e.message?.slice(0, 100)}`);
    }
  }

  await page.close();
  console.log(JSON.stringify(allProducts));
  console.error(`[aldi-sued] ${allProducts.length} unieke producten gevonden`);
}

// ── Lidl DE custom flow (Schwarz API + gridboxes + flyer page images) ──

// ponytail: flyer ID als CLI arg (argv[3]), hardcoded fallback; www.lidl.de is unreachable from this network
const LIDL_DE_FLYER_ID = process.argv[3] || "aktionsprospekt-13-07-2026-18-07-2026-4ff4e5";

async function runLidlDE(browser) {
  console.error(`[lidl-de] Flyer: ${LIDL_DE_FLYER_ID}`);

  // Step 1: Get flyer products via Schwarz API
  console.error("[lidl-de] Ophalen flyer data via Schwarz API...");
  let flyerData;
  try {
    flyerData = await fetchJson(
      `https://endpoints.leaflets.schwarz/v4/flyer?flyer_identifier=${LIDL_DE_FLYER_ID}&region_id=0`
    );
  } catch (e) {
    console.error(`[lidl-de] Schwarz API fout: ${e.message}`);
    process.exit(1);
  }

  const flyer = flyerData.flyer;
  const allLinks = [];
  for (const p of flyer.pages || []) {
    for (const link of p.links || []) {
      if (link.displayType === "product" && link.productDetails?.productId) {
        allLinks.push({
          id: link.productDetails.productId,
          title: link.productDetails.title || link.title || "",
        });
      }
    }
  }

  const uniqueIds = [...new Set(allLinks.map((l) => l.id))];
  console.error(`[lidl-de] ${allLinks.length} producten, ${uniqueIds.length} unieke IDs in flyer`);

  if (uniqueIds.length === 0) {
    console.error("[lidl-de] Geen product IDs gevonden in flyer");
    process.exit(1);
  }

  // Step 2: Get prices via gridboxes API (browser session cookies)
  console.error("[lidl-de] Prijzen ophalen via gridboxes API...");
  const page = await browser.newPage();
  await page.setUserAgent("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
  page.setDefaultTimeout(15000);

  // Visit Lidl DE first for session
  await page.goto("https://www.lidl.de/", { waitUntil: "networkidle2", timeout });
  await new Promise((r) => setTimeout(r, 2000));

  // Extract cookies and pass to Node fetch
  const cookies = await page.cookies();
  const cookieStr = cookies.map(c => `${c.name}=${c.value}`).join("; ");
  await page.close();

  const products = [];
  const seen = new Set();

  for (let i = 0; i < uniqueIds.length; i += 50) {
    const batch = uniqueIds.slice(i, i + 50);
    console.error(`[lidl-de] Gridboxes batch ${Math.floor(i / 50) + 1}/${Math.ceil(uniqueIds.length / 50)}...`);

    try {
      const url = `https://www.lidl.de/p/api/gridboxes/DE/de?erpNumbers=${batch.join(",")}`;
      const res = await fetch(url, {
        headers: { Cookie: cookieStr, "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" },
      });
      if (!res.ok) { console.error(`[lidl-de] Gridboxes HTTP ${res.status}`); continue; }
      const data = await res.json();

      if (Array.isArray(data)) {
        for (const item of data) {
          const name = item.title || "";
          if (!name) continue;
          const key = name.toLowerCase();
          if (seen.has(key)) continue;
          seen.add(key);

          const price = parseFloat(item.price?.price) || 0;
          if (price <= 0) continue;

          const category = item.category || "";
          const unitPrice = item.price?.basePrice || null;

          products.push({
            name,
            price,
            description: category,
            promo_label: "",
            unit_size: null,
            unit_price: unitPrice ? parseFloat(unitPrice) : null,
            image: null,
            url: null,
          });
        }
      }
    } catch (e) {
      console.error(`[lidl-de] Gridboxes batch fout: ${e.message?.slice(0, 100)}`);
    }
  }

  console.log(JSON.stringify(products));
  console.error(`[lidl-de] ${products.length} producten gevonden`);
}

// ── Main ──
let browser;
try {
  // ── Custom flows for complex stores ──
  if (storeName === "aldi-sued") {
    browser = await puppeteer.launch({
      headless: true,
      args: ["--no-sandbox", "--disable-setuid-sandbox", "--disable-dev-shm-usage"],
    });
    await runAldiSued(browser);
    process.exit(0);
  }

  if (storeName === "lidl-de") {
    browser = await puppeteer.launch({
      headless: true,
      args: ["--no-sandbox", "--disable-setuid-sandbox", "--disable-dev-shm-usage"],
    });
    await runLidlDE(browser);
    process.exit(0);
  }

  // ── Standard flow ──
  browser = await puppeteer.launch({
    headless: true,
    args: [
      "--no-sandbox",
      "--disable-setuid-sandbox",
      "--disable-dev-shm-usage",
      "--disable-blink-features=AutomationControlled",
      "--window-size=1920,1080",
    ],
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1920, height: 1080 });
  await page.evaluateOnNewDocument(() => {
    Object.defineProperty(navigator, "webdriver", { get: () => false });
    window.__getProductImage = function(img) {
      if (!img) return null;
      const ok = (s) => s && s.length > 8 && !s.startsWith('data:') && !s.startsWith('blob:') && !s.startsWith('[[') && !s.match(/^[\s{}/\\]/);
      let s = img.getAttribute('data-src');
      if (ok(s)) return s;
      s = img.getAttribute('src');
      if (ok(s)) return s;
      try { s = img.currentSrc; } catch(e) {}
      if (ok(s)) return s;
      return null;
    };
  });

  console.error(`[${storeName}] Laden van ${cfg.url}...`);

  await page.goto(cfg.url, { waitUntil: "networkidle2", timeout });
  await new Promise((r) => setTimeout(r, 3000));

  // Accept cookie banner if store specifies a selector
  if (cfg.cookieBtn) {
    try {
      const acceptBtn = await page.waitForSelector(cfg.cookieBtn, { timeout: 3000 });
      if (acceptBtn) await acceptBtn.click();
      await new Promise((r) => setTimeout(r, 2000));
    } catch (e) {}
  }

  // Scroll to load lazy products
  if (cfg.scroll) {
    const scrollCount = storeName === "ah" ? 15 : 8;
    await page.evaluate(async (count) => {
      for (let i = 0; i < count; i++) {
        window.scrollBy(0, 800);
        await new Promise((r) => setTimeout(r, 400));
      }
    }, scrollCount);
    await new Promise((r) => setTimeout(r, 2000));
  }

  const products = await page.evaluate(cfg.extract);
  console.log(JSON.stringify(products));
  console.error(`[${storeName}] ${products.length} producten gevonden`);
} catch (err) {
  console.error(`[${storeName}] Fout: ${err.message}`);
  process.exit(1);
} finally {
  if (browser) await browser.close();
}
