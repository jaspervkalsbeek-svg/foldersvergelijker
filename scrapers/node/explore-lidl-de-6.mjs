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

const d = result.data;
console.error("=== FLYER RESPONSE ===");
console.error("success:", d.success);
console.error("message:", d.message);
console.error("numberOfEntries:", d.numberOfEntries);
console.error("numberOfWarnings:", d.warnings?.length);
console.error("has flyer array:", Array.isArray(d.flyer));
console.error("flyer length:", d.flyer?.length || 0);

// Show full structure (first 3000 chars of raw)
console.error("\n=== FULL RAW (first 3000 chars) ===");
console.error(result.raw.slice(0, 3000));

// Show without truncation - the flyer array content
if (d.flyer && d.flyer.length > 0) {
  console.error("\n=== FLYER[0] KEYS ===", Object.keys(d.flyer[0]));
} else {
  // Maybe data is somewhere else in the response
  const allKeys = Object.keys(d);
  console.error("\nAll response keys:", allKeys);
  for (const key of allKeys) {
    if (key !== 'flyer' && key !== 'warnings') {
      console.error(`\n${key}:`, typeof d[key], Array.isArray(d[key]) ? `array[${d[key].length}]` : '');
      if (typeof d[key] === 'object' && d[key] !== null && !Array.isArray(d[key])) {
        console.error('  subkeys:', Object.keys(d[key]));
      }
    }
  }
}
