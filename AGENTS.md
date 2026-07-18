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
- Verzenden via Gmail SMTP: jasper.v.kalsbeek@gmail.com met app-wachtwoord in `config/smtp.php`.
- PDF generatie via FPDF v1.86 met cp1252 encoding (iconv) in `lib/fpdf.php`.

## Vibe Coding Regels
- **Geen color gradients** — gebruik effen kleuren, geen overgangen van kleur naar kleur.
- **Geen em dashes (—)** — gebruik gewoon een koppelteken of punt.
- **Altijd mobile-first** — elke pagina moet er goed uitzien op mobiel, niet alleen op desktop.
- **Geen badges op de landing page** — vermijd "Nieuw", "Early Access", "BETA" etc.
- **Footer met juridische pagina's** — in het Nederlands, passend bij de website (privacybeleid, voorwaarden, contact).

## Security Regels
- **Logout moet volledig zijn** — Bij uitloggen: sessie token verwijderen, cookies wissen, localStorage + sessionStorage legen, token server-side invalidaten. Gebruik `admin/logout.php` als standaard logout-pagina.
- **IDOR preventie** — Verifieer dat de ingelogde gebruiker alleen toegang heeft tot zijn eigen data. Gebruik nooit object-ID's direct uit de URL zonder controle.

## Progress
### Done
- **content-viewer-be.kaufda.de brochure API ontdekt** – Publieke Bonial API zonder auth. Retourneert ALLE offers per brochure (geen 16-offer limiet). Geeft 185-439 offers per DE store.
- **8 DE stores draaien op brochure API** – Rewe (177), Kaufland (381), Netto (354), Lidl DE (433), Aldi Nord (244), Aldi Süd (170), Penny (239), Rossmann (222) — allemaal geïmporteerd via `admin/kaufda-scrape.php`. DM (DE): UUID gevonden maar 0 offers.
- **kaufDA.de scraper** – `admin/kaufda-scrape.php`: PHP cURL naar `https://www.kaufda.de/Geschaefte/{slug}`, extracteert brochure UUID uit HTML, roept `content-viewer-be.kaufda.de` pages API aan. Val terug op `__NEXT_DATA__` SSR als geen UUID.
- **AlleFolders.nl API scraper** – `admin/allefolders-scrape.php`: PHP cURL naar `POST https://api.jafolders.com/graphql`, 7 NL winkels.
- **Admin scraper UI** – `admin/index.php` met 3 secties (Puppeteer, kaufDA.de met `kaufDA` badge, AlleFolders API met `API` badge).
- **Public page** – `public/index.php` met search, filters, sort, grid/list view.
- **Shopping list feature** – volledig werkend (UI, autocomplete, email, PDF-billage via Gmail SMTP).
- **PDF generatie** (FPDF, `lib/fpdf.php`) – donkere achtergrond, productkaartjes, `/100ml` detectie, `iconv('UTF-8','CP1252//TRANSLIT')`.
- **Winkelkleuren** – voor alle NL+DE winkels in `include/functions.php`.
- **`admins`-tabel** – gebruiker `jasper` / `admin`.
- **10 actieve winkels** – NL: AH, Aldi, Plus, Lidl, Jumbo. DE: Rewe, Kaufland, Netto, Lidl, Aldi Süd.**Code opschoning** – OW_heroes paginas, dode PHP scrapers, 28 node analysis scripts verwijderd.

### In Progress
- *(none)*

### Blocked
- **Aldi Sud** – Werkt via search page i.p.v. `/Geschaefte/` slug. `__NEXT_DATA__` searchResults.contents.brochures[] bevat brochure UUID.
- **Lidl DE images** – Gridboxes API retourneert geen image URLs; flyer data heeft geen product-afbeeldingen.

## Key Decisions
- **kaufDA.de als Duitse bron** – Productdata server-side in `__NEXT_DATA__` op retailer-pagina's (`/Geschaefte/{SLUG}`). Biedt productnamen, prijzen, eenheidsprijzen, afbeeldingen. 16 offers per pagina SSR.
- **content-viewer-be.kaufda.de** – **Publieke Bonial API** (geen auth) die ALLE productdata per brochure retourneert via `/v1/brochures/{uuid}/pages`. Brochure UUID staat in w-brochureViewer img src op retailer pagina. Geeft 439+ offers per brochure (i.p.v. 16 SSR limiet).
- **AlleFolders API als NL-supplement** – Voor NL winkels die anders niet scrapeable waren. Geen eenheidsprijzen uit de API.
- **iconv voor FPDF** – `utf8_decode` gaf fouten met Euro-teken, iconv zet correct om naar cp1252.
- **x80 voor Euro teken in FPDF** – cp1252 heeft Euro op positie 0x80.
- **Alleen lokaal relevante winkels actief** – Alleen AH, Aldi NL, Plus, Lidl NL, Jumbo (NL) en Rewe, Kaufland, Netto, Lidl DE zijn actief.

## Next Steps
1. **Extra NL supermarkten** – Eventueel via AlleFolders API: Nettorama, Jan Linders, Spar.

## Critical Context
- **KaufDA.de:** Next.js SSR; data in `<script id="__NEXT_DATA__" type="application/json">`. Structuur: `data.props.pageProps.pageInformation.offers.main.items[]` met `title`, `prices.mainPrice`/`priceByBaseUnit`, `offerImages.url.normal`, `publisherName`, `validFrom`/`validUntil`. Slugs: `REWE`, `Edeka`, `Kaufland`, `Netto-Marken-Discount`, `Aldi-Nord`, `Lidl`, `Penny-Markt`, `Rossmann`, `DM`. Volledige brochure data via `content-viewer-be.kaufda.de/v1/brochures/{uuid}/pages`.
- **AlleFolders GraphQL API:** `POST https://api.jafolders.com/graphql` header `jafolders-context: allefolders;nl;web;1;1`. Self-documenting schema via introspection query.
- **Database:** `folders_vergelijker` op localhost (MariaDB 10.4.32), user root, geen wachtwoord. **DB stats:** 5219 producten, 4129 prijzen, 5 actieve NL winkels (AH, Aldi, Plus, Lidl, Jumbo), 5 actieve DE winkels (Rewe, Kaufland, Netto, Lidl, Aldi Süd). `admins`-tabel met user `jasper`.
- **Node.js:** v24.13.0, `puppeteer-extra` + `stealth-plugin` in `scrapers/node/`.
- **XAMPP:** PHP op localhost, projectroot `C:\xampp\htdocs\foldersvergelijker`.
- **Gmail SMTP:** `jasper.v.kalsbeek@gmail.com` / app-wachtwoord in `config/smtp.php`.
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
