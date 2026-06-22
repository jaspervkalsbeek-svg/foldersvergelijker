import https from "https";

function fetchJson(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { 
      headers: { 'User-Agent': 'Mozilla/5.0', 'Accept': 'application/json' } 
    }, (res) => {
      let data = '';
      res.on('data', c => data += c);
      res.on('end', () => {
        try { resolve(JSON.parse(data)); }
        catch(e) { reject(e); }
      });
    }).on('error', reject);
  });
}

// ── Step 1: Get flyer identifiers from the brochure listing page ──
// The page https://www.lidl.de/c/online-prospekte/s10005610 lists current flyers
// But the Schwarz API might have a discovery endpoint
// Let me try common patterns

console.error("═══ Step 1: Discover current flyer ═══\n");

// Try to find flyer listing via the product pages API
// Also check if we can use "latest-leaflet" format
const identifiers = [
  "aktionsprospekt-26-05-2026-30-05-2026-c7c3e1",
  "aktionsprospekt-01-06-2026-06-06-2026-dfd9ee",
  "aktionsprospekt-08-06-2026-13-06-2026-cc781b",
];

for (const id of identifiers) {
  try {
    const data = await fetchJson(`https://endpoints.leaflets.schwarz/v4/flyer?flyer_identifier=${id}&region_id=0`);
    if (data.success && data.flyer) {
      const f = data.flyer;
      console.error(`\n${id}:`);
      console.error(`  Title: ${f.title}`);
      console.error(`  Dates: ${f.startDate} → ${f.endDate}`);
      console.error(`  Pages: ${f.pages?.length || 0}`);
      console.error(`  Status: ${f.status}`);
      console.error(`  Category: ${f.category} / ${f.subcategory}`);
    }
  } catch(e) {}
}

// ── Step 2: Extract product IDs from flyer pages ──
console.error("\n═══ Step 2: Extract products from current flyer ═══\n");

const flyerData = await fetchJson(
  "https://endpoints.leaflets.schwarz/v4/flyer?flyer_identifier=aktionsprospekt-26-05-2026-30-05-2026-c7c3e1&region_id=0"
);
const flyer = flyerData.flyer;

// Collect all product links from all pages
const productIds = [];
const productLinks = [];

for (const page of flyer.pages) {
  for (const link of page.links || []) {
    if (link.displayType === 'product' && link.productDetails?.productId) {
      productIds.push(link.productDetails.productId);
      productLinks.push({
        id: link.productDetails.productId,
        title: link.productDetails.title,
        page: page.number,
      });
    }
  }
}

console.error(`Total product links: ${productLinks.length}`);
console.error(`Unique product IDs: ${[...new Set(productIds)].length}`);

// Show sample products by page
const byPage = {};
for (const pl of productLinks) {
  if (!byPage[pl.page]) byPage[pl.page] = [];
  if (byPage[pl.page].length < 3) byPage[pl.page].push(pl.title);
}
console.error("\nProducts per page (showing max 3 per page):");
Object.entries(byPage).slice(0, 20).forEach(([page, titles]) => {
  console.error(`  Page ${page}: ${titles.join(' | ')}`);
});

// ── Step 3: Fetch prices via gridboxes API ──
console.error("\n═══ Step 3: Fetch prices ═══\n");

const uniqueIds = [...new Set(productIds)];
const batches = [];
for (let i = 0; i < uniqueIds.length; i += 50) {
  batches.push(uniqueIds.slice(i, i + 50));
}

let priceData = [];
const MAX_BATCHES = 3; // Just fetch first 3 batches for demo
for (let b = 0; b < Math.min(batches.length, MAX_BATCHES); b++) {
  const ids = batches[b];
  console.error(`Fetching batch ${b + 1}/${Math.min(batches.length, MAX_BATCHES)} (${ids.length} products)...`);
  try {
    const data = await fetchJson(`https://www.lidl.de/p/api/gridboxes/DE/de?erpNumbers=${ids.join(',')}`);
    if (Array.isArray(data)) {
      priceData = priceData.concat(data);
      data.slice(0, 3).forEach(p => {
        const price = p.price?.price || '?';
        const oldPrice = p.price?.oldPrice || p.price?.recommendedPrice || null;
        const disc = p.price?.discount?.percentageDiscount || null;
        console.error(`  ${p.title?.slice(0, 50)}: €${price}${oldPrice ? ` (was €${oldPrice})` : ''}${disc ? ` -${disc}%` : ''}`);
      });
    }
  } catch(e) {
    console.error(`  Batch ${b + 1} failed: ${e.message}`);
  }
}

console.error(`\nTotal products with prices: ${priceData.length}`);
const foodProducts = priceData.filter(p => p.category?.toLowerCase().includes('lebensmittel') || p.category?.toLowerCase().includes('getraenk') || p.category?.toLowerCase().includes('frisch'));
console.error(`Food/grocery products: ${foodProducts.length}`);

// Show distinct categories
const cats = [...new Set(priceData.map(p => p.category?.split('/')[1]).filter(Boolean))];
console.error(`Categories: ${cats.join(', ')}`);
