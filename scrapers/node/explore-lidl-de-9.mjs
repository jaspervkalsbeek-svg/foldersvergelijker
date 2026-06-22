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

const flyerData = await fetchJson(
  "https://endpoints.leaflets.schwarz/v4/flyer?flyer_identifier=aktionsprospekt-26-05-2026-30-05-2026-c7c3e1&region_id=0"
);
const flyer = flyerData.flyer;

// Show ALL pages with their products
console.error("═══ All products per page ═══\n");
for (const page of flyer.pages) {
  const productLinks = (page.links || []).filter(l => l.displayType === 'product');
  if (productLinks.length > 0) {
    const titles = productLinks.map(l => l.productDetails?.title || l.title).join(' | ');
    console.error(`Page ${page.number}: ${titles}`);
  }
}

// Count food vs non-food based on keywords
console.error("\n═══ Analysis ═══\n");
const allLinks = [];
for (const page of flyer.pages) {
  for (const link of page.links || []) {
    if (link.displayType === 'product' && link.productDetails?.productId) {
      allLinks.push({
        id: link.productDetails.productId,
        title: (link.productDetails.title || link.title || '').toLowerCase(),
        page: page.number
      });
    }
  }
}

console.error(`Total products: ${allLinks.length}`);

// Categorize by keywords
const foodKeywords = ['back', 'brot', 'brötchen', 'milch', 'käse', 'joghurt', 'butter', 'ei', 'fleisch', 'wurst', 'fisch', 'obst', 'gemüse', 'salat', 'saft', 'wasser', 'cola', 'limo', 'bier', 'wein', 'schnaps', 'getränk', 'nudel', 'reis', 'mehl', 'zucker', 'kartoffel', 'tomate', 'gurke', 'apfel', 'banane', 'joghurt', 'quark', 'sahne', 'öl', 'essig', 'konserve', 'schokolade', 'bonbon', 'kuchen', 'torte', 'marmelade', 'honig', 'müsli', 'cornflakes', 'tee', 'kaffee', 'kakao', 'gewürz', 'kräuter', 'sauce', 'suppe', 'eis', 'tiefkühl', 'fertiggericht', 'ketchup', 'senf', 'majo', 'konserve', 'dose', 'packung', 'bio', 'vegan', 'vegetarisch', 'frucht', 'beere', 'pfirsich', 'birne', 'pflaume', 'zwiebel', 'knoblauch', 'paprika', 'zitrone', 'orange', 'traube', 'melone', 'ananas', 'mango', 'avocado'];

const drinkKeywords = ['wasser', 'saft', 'cola', 'limo', 'fanta', 'sprite', 'bier', 'wein', 'sekt', 'champagner', 'vodka', 'whisky', 'rum', 'gin', 'liqueur', 'likör', 'cocktail', 'schnaps', 'getränk', 'energy', 'red bull', 'monster', 'mate', 'schorle', 'spirituose', 'prosecco'];

const foodProducts = allLinks.filter(p => foodKeywords.some(k => p.title.includes(k)));
const drinkProducts = allLinks.filter(p => drinkKeywords.some(k => p.title.includes(k)));

console.error(`Food-related products: ${foodProducts.length}`);
console.error(`Drink/alcohol products: ${drinkProducts.length}`);

if (foodProducts.length > 0) {
  console.error("\nFood items:");
  foodProducts.forEach(p => console.error(`  Page ${p.page}: ${p.title}`));
}

if (drinkProducts.length > 0) {
  console.error("\nDrink items:");
  drinkProducts.forEach(p => console.error(`  Page ${p.page}: ${p.title}`));
}

// Show topics (these indicate what's in the flyer)
console.error("\n═══════════════════════════════════════");
console.error("Topics in flyer:");
flyer.topics?.forEach((t, i) => {
  console.error(`  ${i+1}. ${t.title || t.name || JSON.stringify(t).slice(0, 100)}`);
});
