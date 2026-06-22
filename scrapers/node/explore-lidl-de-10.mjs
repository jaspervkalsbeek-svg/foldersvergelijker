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
        catch(e) { reject(new Error(`JSON parse error: ${e.message} at ${url}`)); }
      });
    }).on('error', reject);
  });
}

// Try various flyer types
const attempts = [
  // Preisführer / grocery flyers
  { type: 'preisfuehrer-26-05-2026', base: 'preisfuehrer' },
  { type: 'lebensmittel-26-05-2026', base: 'lebensmittel' },
  { type: 'angebote-26-05-2026', base: 'angebote' },
  { type: 'wochenangebote-25-05-2026', base: 'wochenangebote' },
  { type: 'preis-aktion-26-05-2026', base: 'preis-aktion' },
  { type: 'tiefpreis-26-05-2026', base: 'tiefpreis' },
];

console.error("═══ Trying Schwarz API discovery ═══\n");

// First, let's see if the API supports listing flyers or categories
async function discoverFlyers() {
  // Try the homepage/latest approach
  const endpoints = [
    'https://endpoints.leaflets.schwarz/v4/flyer?region_id=0&limit=20',
    'https://endpoints.leaflets.schwarz/v4/categories?region_id=0',
    'https://endpoints.leaflets.schwarz/v4/current?region_id=0',
  ];
  
  for (const ep of endpoints) {
    try {
      const data = await fetchJson(ep);
      console.error(`\n${ep}:`);
      console.error(JSON.stringify(data).slice(0, 500));
    } catch(e) {
      console.error(`  Failed: ${e.message.slice(0, 100)}`);
    }
  }
}

await discoverFlyers();

// Also try to find the flyer listing page in HTML
// The brochure listing page might tell us about grocery flyers
console.error("\n═══ Checking brochure listing page ═══\n");
const cheerio = await import('cheerio').catch(() => null);

// Try to get flyer identifiers from the API using another approach
// Maybe the Schwarz API has a search/flyers endpoint
async function searchFlyers() {
  const searchUrls = [
    'https://endpoints.leaflets.schwarz/v4/flyer?flyer_identifier=preisfuehrer&region_id=0',
    'https://endpoints.leaflets.schwarz/v4/flyer?flyer_identifier=preis-aktion-24&region_id=0',
    'https://endpoints.leaflets.schwarz/v4/flyer?region_id=0&category=lebensmittel',
    'https://endpoints.leaflets.schwarz/v4/flyer?region_id=0&subcategory=lebensmittel',
    'https://endpoints.leaflets.schwarz/v4/flyers?region_id=0',
    'https://endpoints.leaflets.schwarz/v4/search/flyers?region_id=0',
  ];
  
  for (const url of searchUrls) {
    try {
      const data = await fetchJson(url);
      if (data) {
        console.error(`\n${url}:`);
        console.error(JSON.stringify(data).slice(0, 800));
      }
    } catch(e) {
      // ignore 404s
    }
  }
}

await searchFlyers();
