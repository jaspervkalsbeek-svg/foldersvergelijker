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

// ── 1. Fetch the current flyer data ──
console.error("═══ Fetching flyer data ═══\n");
const flyerData = await fetchJson(
  "https://endpoints.leaflets.schwarz/v4/flyer?flyer_identifier=aktionsprospekt-26-05-2026-30-05-2026-c7c3e1&region_id=0&region_code=0"
);

console.error("Top-level keys:", Object.keys(flyerData));
console.error("Flyer info:", JSON.stringify(flyerData.flyer_info, null, 2));
console.error("Number of flyer entries:", flyerData.flyer?.length || 0);

if (flyerData.flyer && flyerData.flyer.length > 0) {
  const flyer = flyerData.flyer[0];
  console.error("\n═══ First flyer structure ═══");
  console.error("Keys:", Object.keys(flyer));
  
  if (flyer.pages) {
    console.error(`\nPages: ${flyer.pages.length}`);
    const firstPage = flyer.pages[0];
    console.error("First page keys:", Object.keys(firstPage));
    console.error("First page:", JSON.stringify(firstPage, null, 2).slice(0, 1000));
    
    // Check if pages have products/items
    let totalItems = 0;
    let hasItems = false;
    for (const page of flyer.pages) {
      if (page.items || page.products || page.offers) {
        hasItems = true;
        const items = page.items || page.products || page.offers;
        totalItems += items.length;
        if (totalItems <= 5) {
          console.error(`\nPage ${flyer.pages.indexOf(page) + 1} items:`);
          console.error(JSON.stringify(items[0], null, 2));
        }
      }
    }
    if (hasItems) {
      console.error(`\nTotal items across all pages: ${totalItems}`);
    } else {
      console.error("\nNo items/products found on pages - checking page content structure");
      // Check what's on each page
      for (const page of flyer.pages.slice(0, 3)) {
        console.error(`\nPage ${flyer.pages.indexOf(page) + 1} content:\n`, JSON.stringify(page).slice(0, 500));
      }
    }
  }
}

// Try to list available flyers  
console.error("\n\n═══ Try to list flyers ═══");
const regionsApi = await fetchJson("https://endpoints.leaflets.schwarz/v4/regions").catch(() => null);
if (regionsApi) {
  console.error("Regions response:", JSON.stringify(regionsApi).slice(0, 500));
} else {
  console.error("Regions API not accessible directly");
}

// Check if there's a flyer list endpoint
const flyersApi = await fetchJson("https://endpoints.leaflets.schwarz/v4/flyers?client=lidl&country=DE").catch(() => null);
if (flyersApi) {
  console.error("Flyers list:", JSON.stringify(flyersApi).slice(0, 1000));
} else {
  console.error("Flyers list API not accessible");
}

// Try to get the current/active flyer list
const currentFlyers = await fetchJson("https://endpoints.leaflets.schwarz/v4/flyers?client=lidl&country=DE&status=active").catch(() => null);
if (currentFlyers) {
  console.error("\nActive flyers:", JSON.stringify(currentFlyers).slice(0, 2000));
} else {
  console.error("Active flyers API not accessible");
}
