import https from "https";

function fetchJson(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { 
      headers: { 'User-Agent': 'Mozilla/5.0', 'Accept': 'application/json' } 
    }, (res) => {
      let data = '';
      res.on('data', c => data += c);
      res.on('end', () => {
        try { resolve({ data: JSON.parse(data), raw: data }); }
        catch(e) { reject(e); }
      });
    }).on('error', reject);
  });
}

const result = await fetchJson(
  "https://endpoints.leaflets.schwarz/v4/flyer?flyer_identifier=aktionsprospekt-26-05-2026-30-05-2026-c7c3e1&region_id=0&region_code=0"
);

const flyer = result.data.flyer;
console.error("=== FLYER OBJECT ===");
console.error("Keys:", Object.keys(flyer));

// Check for pages
if (flyer.pages) {
  console.error(`\nPages: ${flyer.pages.length}`);
  const firstPage = flyer.pages[0];
  console.error("First page keys:", Object.keys(firstPage));
  
  // Find items/products on pages
  let totalItems = 0;
  for (const page of flyer.pages.slice(0, 3)) {
    console.error(`\n--- Page ${flyer.pages.indexOf(page) + 1} ---`);
    console.error("Keys:", Object.keys(page));
    
    // Check for items/products/offers in the page
    for (const key of Object.keys(page)) {
      const val = page[key];
      if (Array.isArray(val) && val.length > 0) {
        console.error(`  ${key}: array[${val.length}]`);
        // Show first item structure
        if (typeof val[0] === 'object') {
          console.error(`  First item keys:`, Object.keys(val[0]));

          // Show complete first item
          console.error(`  First item:`, JSON.stringify(val[0]).slice(0, 500));
        }
      }
    }
  }
} else {
  console.error("\nNo pages found in flyer!");
  // Check for pages or similar fields
  for (const key of Object.keys(flyer)) {
    const val = flyer[key];
    if (Array.isArray(val)) {
      console.error(`${key}: array[${val.length}]`);
      if (val.length > 0 && typeof val[0] === 'object') {
        console.error('  First item:', JSON.stringify(val[0]).slice(0, 300));
      }
    }
  }
}

// Check if there's a product list somewhere
console.error("\n=== Looking for products/items ===");
function findArrays(obj, path = 'root', depth = 0) {
  if (depth > 3) return;
  if (typeof obj !== 'object' || obj === null) return;
  
  for (const key of Object.keys(obj)) {
    const val = obj[key];
    if (Array.isArray(val) && val.length > 0 && typeof val[0] === 'object') {
      console.error(`${path}.${key}: array[${val.length}]`);
      if (depth < 2) console.error('  sample:', JSON.stringify(val[0]).slice(0, 300));
    } else if (typeof val === 'object' && val !== null) {
      findArrays(val, `${path}.${key}`, depth + 1);
    }
  }
}
findArrays(flyer);

// Output the full raw JSON for analysis (first 8000 chars)
console.error("\n=== FULL RAW (6000 chars) ===");
const start = result.raw.indexOf('"pdfUrl"');
console.error(result.raw.slice(start, start + 6000));
