## Goal
Bouw een prijsvergelijkingswebsite die producten uit Nederlandse en Duitse supermarktfolder verzamelt, vergelijkt en toont waar elk product het goedkoopst is, inclusief prijs per eenheid.

## Constraints & Preferences
- Nieuwe publieke website los van bestaande admin panel.
- Eigen database `folders_vergelijker` (MariaDB 10.4.32, localhost, root zonder wachtwoord).
- Categorie: supermarkt/boodschappen (NL + DE).
- Datascraping: automatisch (scrapers, Puppeteer, PHP cURL), aangevuld met handmatige import CSV/JSON.
- Prijs per eenheid (per 100g/kg/L) naast totaalprijs.
- Node.js v24.13.0, `puppeteer-extra` + `stealth-plugin` in `scrapers/node/`.
- XAMPP PHP op localhost, projectroot `C:\xampp\htdocs\foldersvergelijker`.
- Verzenden via Gmail SMTP: jasper.v.kalsbeek@gmail.com met app-wachtwoord `epqk nagz zgze lbla`.
- PDF generatie via FPDF v1.86 met cp1252 encoding (iconv) in `lib/fpdf.php`.

## Progress
### Done
- **9 actieve Puppeteer-extractors** – AH (116), Lidl NL (56), Aldi NL (93), Plus (39), Dirk (112), Rewe (43), Penny (58), Aldi Sud (47), Lidl DE (85) – allemaal getest en werkend via PHP pipeline.
- **AlleFolders.nl API scraper** – `admin/allefolders-scrape.php`: PHP cURL naar `POST https://api.jafolders.com/graphql` met header `jafolders-context: allefolders;nl;web;1;1`, pagineert per 50 offers, importeert in DB (upsert per dag). Werkt voor 7 NL winkels, geen Puppeteer nodig.
- **7 NL winkels via AlleFolders API** – Jumbo (187), Vomar (172), Hoogvliet (265), Poiesz (179), Boni (134), Coop (74), DekaMarkt (239).
- **kaufDA.de scraper** – `admin/kaufda-scrape.php`: PHP cURL naar `https://www.kaufda.de/Geschaefte/{slug}`, extracteert `__NEXT_DATA__` JSON, parset offers met productnamen, prijzen, eenheidsprijzen, afbeeldingen, datums. Geen Puppeteer nodig.
- **9 DE winkels via kaufDA.de** – REWE (15), EDEKA (15), Kaufland (16), Netto (15), Aldi Nord (16), Lidl DE (16), Penny (16), Rossmann (15), DM (16). **4 nieuwe winkels** toegevoegd aan DB: Kaufland, Aldi Nord, Rossmann, DM.
- **Admin scraper UI** – `admin/index.php` met 3 secties (Puppeteer, kaufDA.de met `kaufDA` badge, AlleFolders API met `API` badge). Aparte `scrapeKaufda()`, `scrapeAllefolders()`, `scrapeStore()` functies. "Alle scannen" doorloopt alle 3 groepen sequentieel.
- **`admin/scrape-run.php`** – AJAX endpoint voor Puppeteer scrapers.
- **Public page** – `public/index.php` met search, filters, sort, grid/list view, image-first ordering.
- **Shopping list feature** – volledig werkend (UI, autocomplete, email, PDF-billage via Gmail SMTP).
- **PDF generatie** (FPDF, `lib/fpdf.php`) – donkere achtergrond, productkaartjes, `/100ml` detectie, `iconv('UTF-8','CP1252//TRANSLIT')`.
- **Winkelkleuren** – voor alle NL+DE winkels in `include/functions.php` (incl. Aldi Nord, Aldi Sud, Kaufland, Rossmann, DM).
- **`admins`-tabel aangemaakt** – met gebruiker `jasper` / `admin`. `login.php` en `add_admin.php` werken nu.
- **`auth.php` redirect gefixt** – verwijst nu naar `/foldersvergelijker/admin/login.php` i.p.v. `/ow_heroes/admin/login.php`.
- **`s.active = 1` filters toegevoegd** – `public/index.php`, `product.php`, `stores.php`, `shopping-list-search.php` tonen alleen nog producten/winkels van actieve winkels.
- **13 winkels gedeactiveerd (active=0)** – Dirk, Vomar, Hoogvliet, Poiesz, Boni, Coop, DekaMarkt (NL) en Edeka, Aldi Nord, Aldi Süd, Penny, Rossmann, DM (DE).
- **Code opschoning** – Dode OW_heroes admin paginas verwijderd (`add.php`, `add2.php`, `add3.php`, `add_festival.php`), dode PHP scrapers verwijderd (`scrapers/*.php`, vervangen door Node), 28 node analysis scripts verwijderd. Sidebars in `add_admin.php` en `overview.php` consistent gemaakt.

### In Progress
- *(none)*

### Blocked
- **Aldi Sud** – Niet gevonden op kaufda.de; geen werkende slug.
- **Lidl DE images** – Gridboxes API retourneert geen image URLs; flyer data heeft geen product-afbeeldingen.

## Key Decisions
- **kaufDA.de als Duitse bron** – Productdata server-side in `__NEXT_DATA__` op retailer-pagina's (`/Geschaefte/{SLUG}`). Biedt productnamen, prijzen, eenheidsprijzen, afbeeldingen. 16 offers per pagina SSR (meer via client-side JS).
- **AlleFolders API als NL-supplement** – Voor NL winkels die anders niet scrapeable waren. Geen eenheidsprijzen uit de API.
- **iconv voor FPDF** – `utf8_decode` gaf fouten met Euro-teken, iconv zet correct om naar cp1252.
- **x80 voor Euro teken in FPDF** – cp1252 heeft Euro op positie 0x80.
- **Alleen lokaal relevante winkels actief** – Alleen AH, Aldi NL, Plus, Lidl NL, Jumbo (NL) en Rewe, Kaufland, Netto, Lidl DE zijn actief.

## Next Steps
1. **Flyer identifier dynamisch maken** – Lidl DE gebruikt nu hardcoded `aktionsprospekt-26-05-2026-30-05-2026-c7c3e1`; automatisch de actuele vinden via brochure-pagina.
2. **Extra NL supermarkten** – Eventueel via AlleFolders API: Nettorama, Jan Linders, Spar.
3. **kaufDA client-side pagination** – 855+ offers bij Kaufland maar alleen 16 SSR; mogelijk client-side API om alle offers op te halen.

## Critical Context
- **KaufDA.de:** Next.js SSR; data in `<script id="__NEXT_DATA__" type="application/json">`. Structuur: `data.props.pageProps.pageInformation.offers.main.items[]` met `title`, `prices.mainPrice`/`priceByBaseUnit`, `offerImages.url.normal`, `publisherName`, `validFrom`/`validUntil`. Slugs: `REWE`, `Edeka`, `Kaufland`, `Netto-Marken-Discount`, `Aldi-Nord`, `Lidl`, `Penny-Markt`, `Rossmann`, `DM`.
- **AlleFolders GraphQL API:** `POST https://api.jafolders.com/graphql` header `jafolders-context: allefolders;nl;web;1;1`. Self-documenting schema via introspection query.
- **Database:** `folders_vergelijker` op localhost (MariaDB 10.4.32), user root, geen wachtwoord. **DB stats:** 2085 producten, 2792 prijzen, 5 actieve NL winkels (AH, Aldi, Plus, Lidl, Jumbo), 4 actieve DE winkels (Rewe, Kaufland, Netto, Lidl). `admins`-tabel met user `jasper`.
- **Node.js:** v24.13.0, `puppeteer-extra` + `stealth-plugin` in `scrapers/node/`.
- **XAMPP:** PHP op localhost, projectroot `C:\xampp\htdocs\foldersvergelijker`.
- **Gmail SMTP:** `jasper.v.kalsbeek@gmail.com` / app-wachtwoord `epqk nagz zgze lbla`.
- **PHPMailer v6.9.3** – `lib/PHPMailer.php`, `lib/SMTP.php`, `lib/Exception.php`.
- **FPDF v1.86** – `lib/fpdf.php`, `lib/font/` (cp1252 core fonts).
- **`auth.php`** – staat in `include/auth.php`, redirect naar `/foldersvergelijker/admin/login.php`. Inclusie in `index.php`, `add_admin.php`, `add_product.php`, `overview.php`, `import.php`. AJAX endpoints (`scrape-run.php`, `kaufda-scrape.php`, `allefolders-scrape.php`) hebben eigen session check (401 JSON).

## Relevant Files
- `C:\xampp\htdocs\foldersvergelijker\admin\kaufda-scrape.php` – kaufDA.de scraper (PHP cURL, __NEXT_DATA__ extractie, DB import).
- `C:\xampp\htdocs\foldersvergelijker\admin\allefolders-scrape.php` – AlleFolders GraphQL API scraper.
- `C:\xampp\htdocs\foldersvergelijker\admin\index.php` – Dashboard met AJAX scraper UI voor 3 groepen (Puppeteer, kaufDA, AlleFolders), "Alle scannen" doorloopt alle groepen.
- `C:\xampp\htdocs\foldersvergelijker\admin\scrape-run.php` – AJAX endpoint voor Puppeteer scrapers.
- `C:\xampp\htdocs\foldersvergelijker\admin\adminstyle.css` – `.api-badge` styling.
- `C:\xampp\htdocs\foldersvergelijker\include\functions.php` – Helpers (`formatPrice`, `getStoreColor` voor alle winkels, `formatUnitPrice`, `truncateText`).
- `C:\xampp\htdocs\foldersvergelijker\scrapers\node\scrape-store.mjs` – Hoofdscript met 9 extractors.
- `C:\xampp\htdocs\foldersvergelijker\public\index.php` – Publieke productenpagina.
- `C:\xampp\htdocs\foldersvergelijker\public\product.php` – Product detailpagina.
- `C:\xampp\htdocs\foldersvergelijker\public\shopping-list.php` – Shopping list UI.
- `C:\xampp\htdocs\foldersvergelijker\lib\fpdf.php` – FPDF v1.86.
- `C:\xampp\htdocs\foldersvergelijker\admin\login.php` – Admin login.
- `C:\xampp\htdocs\foldersvergelijker\admin\add_admin.php` – Nieuwe admin aanmaken.
- `C:\xampp\htdocs\foldersvergelijker\include\auth.php` – Auth guard, redirect naar login.
