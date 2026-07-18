# Technisch Ontwerp - Folders Vergelijker

## 1. Architectuur Overzicht

```
┌─────────────────────────────────────────────────────────────────┐
│                        BRONNEN (Externe)                        │
├─────────────────┬─────────────────┬─────────────────────────────┤
│  Puppeteer      │  kaufDA.de      │  AlleFolders GraphQL API    │
│  (NL winkels)   │  (DE winkels)   │  (NL winkels)               │
│  Node.js        │  PHP cURL       │  PHP cURL                   │
└────────┬────────┴────────┬────────┴──────────────┬──────────────┘
         │                 │                       │
         ▼                 ▼                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                     SCRAPERS (Admin Panel)                      │
│  scrape-run.php  │  kaufda-scrape.php  │  allefolders-scrape.php│
│  AJAX JSON       │  AJAX JSON          │  AJAX JSON             │
│  + scrape-store  │  + __NEXT_DATA__    │  + GraphQL             │
│    .mjs (Node)   │  + Bonial API       │  + jafolders.com       │
└────────┬────────┴────────┬────────────┴──────────────┬──────────┘
         │                 │                           │
         ▼                 ▼                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                    DATABASE (MariaDB 10.4)                      │
│  folders_vergelijker                                            │
│  stores │ folders │ categories │ products │ product_prices      │
│  admins │ shopping_lists │ shopping_list_items                  │
└────────┬────────────────────────────────────────┬───────────────┘
         │                                        │
         ▼                                        ▼
┌─────────────────────────────────┐  ┌────────────────────────────┐
│      ADMIN PANEL                │  │     PUBLIEKE SITE          │
│  index, add_product, import,    │  │  index, product, stores,   │
│  overview, scraper UI           │  │  shopping-list, contact    │
└─────────────────────────────────┘  └────────────────────────────┘
```

## 2. Tech Stack

| Component | Technologie | Versie |
|-----------|------------|--------|
| Backend | PHP (XAMPP) | 8.x |
| Database | MariaDB | 10.4.32 |
| Node.js | Node.js | 24.13.0 |
| Browser Automation | Puppeteer Extra + Stealth | 24.x |
| PDF Generatie | FPDF | 1.86 |
| E-mail | PHPMailer (Gmail SMTP) | 6.9.3 |
| Frontend | Vanilla HTML/CSS/JS | - |
| Hosting | XAMPP localhost | - |

## 3. Database Schema

### 3.1 Entiteit-Relatiediagram

```
stores (1)──────< (N) product_prices
stores (1)──────< (N) folders
categories (1)──< (N) products
products (1)────< (N) product_prices
folders (1)─────< (N) product_prices
shopping_lists (1)──< (N) shopping_list_items
```

### 3.2 Tabellen

#### `stores`
| Kolom | Type | constraints |
|-------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| name | VARCHAR(100) | NOT NULL |
| country | ENUM('NL','DE') | NOT NULL |
| logo | VARCHAR(255) | NULL |
| website | VARCHAR(255) | NULL |
| scraper_class | VARCHAR(100) | NULL |
| active | BOOLEAN | DEFAULT TRUE |
| created_at | TIMESTAMP | DEFAULT NOW |

#### `folders`
| Kolom | Type | constraints |
|-------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| store_id | INT | FK -> stores(id) CASCADE |
| title | VARCHAR(255) | NULL |
| start_date | DATE | NULL |
| end_date | DATE | NULL |
| folder_url | VARCHAR(500) | NULL |
| scraped_at | TIMESTAMP | DEFAULT NOW |

#### `categories`
| Kolom | Type | constraints |
|-------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| name | VARCHAR(100) | NOT NULL |
| slug | VARCHAR(100) | UNIQUE, NOT NULL |

15 categorieen: zuivel-eieren, brood-ontbijtgranen, fruit-groente, vlees-vis, diepvries, dranken, snacks-zoetigheid, pasta-rijst, conserven-sauzen, huishouden, persoonlijke-verzorging, drogisterij, baby, huisdier, overig.

#### `products`
| Kolom | Type | constraints |
|-------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| name | VARCHAR(255) | NOT NULL |
| brand | VARCHAR(100) | NULL |
| category_id | INT | FK -> categories(id) SET NULL |
| ean | VARCHAR(13) | NULL |
| image_url | VARCHAR(500) | NULL |
| description | TEXT | NULL |
| created_at | TIMESTAMP | DEFAULT NOW |

#### `product_prices`
| Kolom | Type | constraints |
|-------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| product_id | INT | FK -> products(id) CASCADE |
| store_id | INT | FK -> stores(id) CASCADE |
| folder_id | INT | FK -> folders(id) SET NULL |
| price | DECIMAL(10,2) | NOT NULL |
| unit_size | VARCHAR(50) | NULL |
| unit_price | DECIMAL(10,2) | NULL |
| scraped_at | TIMESTAMP | DEFAULT NOW |

#### `admins`
| Kolom | Type | constraints |
|-------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| username | VARCHAR | NOT NULL, UNIQUE |
| password | VARCHAR(255) | NOT NULL (bcrypt) |

#### `shopping_lists`
| Kolom | Type | constraints |
|-------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| email | VARCHAR | NOT NULL |
| token | VARCHAR(32) | NOT NULL |
| sent_at | DATETIME | NULL |

#### `shopping_list_items`
| Kolom | Type | constraints |
|-------|------|-------------|
| id | INT AUTO_INCREMENT | PK |
| list_id | INT | FK -> shopping_lists(id) |
| product_name | VARCHAR | NOT NULL |

## 4. Scraper Architectuur

### 4.1 Overzicht Scrapers per Bron

| Bron | Scraper | Bestand | Winkels |
|------|---------|---------|---------|
| Puppeteer | `scrape-run.php` + `scrape-store.mjs` | PHP + Node.js | AH, Lidl NL, Aldi NL, Plus |
| kaufDA.de | `kaufda-scrape.php` | PHP cURL | Rewe, Kaufland, Netto, Lidl DE, Aldi Nord, Aldi Sud, Penny, Rossmann, DM |
| AlleFolders | `allefolders-scrape.php` | PHP cURL | Jumbo |

### 4.2 Puppeteer Scrapers (NL)

**Bestand:** `scrapers/node/scrape-store.mjs` (1030 regels)

**Architectuur:**
- Enkel bestand met store-specifieke extractors
- Elke extractor draait in `page.evaluate()` (browser context)
- Product-JSON naar stdout, voortgang naar stderr
- Gebruikt `puppeteer-extra` + `stealth-plugin` tegen detectie

**Extractiepatronen:**
- **AH:** Cookie accepteren, scrollen, `.bonus-card` elements
- **Lidl NL:** `.product-grid-boxes` na lazy-load scroll
- **Aldi NL:** `.product-tiles` na scroll
- **Plus:** `.plp-item-wrapper` na scroll

**Gemeenschappelijk:**
- `window.__getProductImage()` helper (data-src/src/currentSrc fallback)
- Deduplicatie via `Set` van lowercase productnamen
- Unit price regex: `1 kg = X,XX`, `je 250 g`
- Cookie banner handling via configurable `cookieBtn` selector

### 4.3 kaufDA.de Scraper (DE)

**Bestand:** `admin/kaufda-scrape.php`

**Twee-fasen aanpak:**

1. **SSR Extractie:** `__NEXT_DATA__` uit HTML van `kaufda.de/Geschaefte/{slug}`
   - Limiet: 16 offers per pagina
   - Bevat: title, prices.mainPrice, offerImages, publisherName, validFrom/validUntil

2. **Bonial API:** `content-viewer-be.kaufda.de/v1/brochures/{uuid}/pages`
   - Publieke API, geen authenticatie nodig
   - Retourneert ALLE offers per brochure (185-439 per winkel)
   - Brochure UUID uit `<img>` src op retailer pagina

**Prijsformatten:** `mainPrice`, `deals[].SALES_PRICE`, `priceByBaseUnit`

**Eenheidsprijzen:** Duitse formaten: `1 l = 1,10`, `500 g = 7,98`, multi-pack detectie

**Aldi Sud:** Werkt via search page i.p.v. `/Geschaefte/` slug. `__NEXT_DATA__` searchResults.contents.brochures[] bevat brochure UUID.

### 4.4 AlleFolders GraphQL API (NL)

**Bestand:** `admin/allefolders-scrape.php`

- Endpoint: `POST https://api.jafolders.com/graphql`
- Header: `jafolders-context: allefolders;nl;web;1;1`
- Paginatie: 50 offers per request
- **Geen eenheidsprijzen** beschikbaar via deze API

## 5. Database Connectie

**Bestand:** `config/database.php`

```php
$pdo = new PDO(
    'mysql:host=localhost;dbname=folders_vergelijker;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);
```

## 6. Core Functions (`include/functions.php`)

| Functie | Doel |
|---------|------|
| `formatPrice(float)` | Formatteer als "EUR X,XX" |
| `truncateText(string, int)` | Truncate met "..." |
| `getProductLink(int)` | Retourneert "product.php?id=N" |
| `getStoreLogo(string)` | Store name -> SVG pad |
| `getStoreColor(string)` | Store name -> brand hex kleur (20 winkels) |
| `formatUnitPrice(?float, ?string)` | Formatteer als "EUR X,XX/kg" of "/L" |
| `formatUnitPrice100g(?float)` | Formatteer als "EUR X,XX/100g" |
| `getCategoryMap(string)` | NL+DE keyword -> categorie mapping |
| `categorizeProduct(string, array, string)` | Auto-categoriseer op keyword match |
| `upsertProduct(PDO, ...)` | Insert of update product (match op name+brand) |
| `upsertPrice(PDO, ...)` | Insert of update price (zelfde product+store+dag = update) |
| `getCategoryName(string)` | Slug -> display naam |

## 7. API Endpoints

### 7.1 Admin AJAX Endpoints

| Endpoint | Methode | Auth | Doel |
|----------|---------|------|------|
| `admin/scrape-run.php?store=X` | GET | Session | Voer Puppeteer scraper uit |
| `admin/kaufda-scrape.php?store=X` | GET | Session | Voer kaufDA scraper uit |
| `admin/allefolders-scrape.php?store=X` | GET | Session | Voer AlleFolders scraper uit |

**Antwoordformaat:** `{ store, success, progress[], count, imported, error }`

### 7.2 Publieke AJAX Endpoints

| Endpoint | Methode | Auth | Doel |
|----------|---------|------|------|
| `public/shopping-list-search.php?q=X` | GET | Geen | Product naam autocomplete |
| `public/shopping-list-send.php` | POST | Geen | Genereer PDF + verstuur e-mail |

## 8. E-mail Configuratie

**Provider:** Gmail SMTP
- Server: `smtp.gmail.com:587` (STARTTLS)
- Afzender: `jasper.v.kalsbeek@gmail.com`
- Authenticatie: App-wachtwoord

**Gebruik:**
1. Shopping list e-mail (HTML + PDF bijlage via FPDF)
2. Contact formulier e-mail

## 9. PDF Generatie

**Bibliotheek:** FPDF v1.86 (`lib/fpdf.php`)

**Besturing:** `ShoppingListPdf` class in `public/shopping-list-send.php`

**Kenmerken:**
- Donkere achtergrond (matcht website theme)
- Productkaarten met winkelbadge (gekleurd), land-tag, naam, merk, prijs, eenheidsprijs
- "Ook:" sectie met alternatieve winkelprijzen
- `/100ml` detectie voor vloeistoffen
- `iconv('UTF-8', 'CP1252//TRANSLIT')` voor juiste encoding
- x80 voor Euro-teken in cp1252

## 10. Beveiliging

### 10.1 Authenticatie
- Session-gebaseerd admin panel
- `include/auth.php` als auth guard
- `password_hash()` / `password_verify()` voor wachtwoorden
- Session ID regeneratie na login

### 10.2 Logout
- `$_SESSION` array wissen
- Session cookie verwijderen
- `session_destroy()` + `session_regenerate_id(true)`
- `localStorage` + `sessionStorage` legen via JS
- Server-side token invalidatie

### 10.3 AJAX Auth Check
- Scrapers controleren `$_SESSION['admin_id']`
- Retourneren 401 JSON bij ongeautoriseerde toegang

### 10.4 Bekende Issues
- **Geen CSRF tokens** op formulieren
- **Geen rate limiting** op publieke endpoints
- **Geen input sanitization** op sommige plekken (SQL injection via PDO prepared statements is wel afgedekt)
- **IDOR risico:** `product.php?id=N` zonder ownership check
- **Credentials hardcoded** in `shopping-list-send.php` en `contact.php`

## 11. Bekende Bugs

1. **Missing `include/db.php`** - `admin/login.php` en `admin/add_admin.php` refereren naar niet-bestaand bestand
2. **3 tabellen ontbreken in `setup.sql`** - `admins`, `shopping_lists`, `shopping_list_items`
3. **`products.description` kolom ontbreekt** in `setup.sql` maar wordt gebruikt in code
4. **Geen migratiesysteem** - Schema wijzigingen ad-hoc via ALTER TABLE

## 12. Bestandsstructuur

```
foldersvergelijker/
├── config/database.php           # DB connectie
├── include/
│   ├── auth.php                  # Auth guard
│   ├── functions.php             # Core helpers
│   └── footer.html               # STALE (niet gebruikt)
├── lib/
│   ├── fpdf.php                  # PDF generatie
│   ├── PHPMailer.php             # E-mail
│   ├── SMTP.php                  # SMTP transport
│   ├── Exception.php             # PHPMailer exceptions
│   └── font/                     # FPDF core fonts (cp1252)
├── admin/
│   ├── index.php                 # Dashboard + scraper UI
│   ├── login.php                 # Admin login
│   ├── logout.php                # Secure logout
│   ├── add_admin.php             # Admin aanmaken
│   ├── add_product.php           # Handmatig product toevoegen
│   ├── import.php                # CSV/JSON bulk import
│   ├── overview.php              # Paginated product overzicht
│   ├── scrape-run.php            # AJAX: Puppeteer scrapers
│   ├── kaufda-scrape.php         # AJAX: kaufDA.de scraper
│   ├── allefolders-scrape.php    # AJAX: AlleFolders API
│   └── adminstyle.css            # Admin dark theme
├── public/
│   ├── index.php                 # Product listing (search, filters)
│   ├── product.php               # Product detail + prijsvergelijking
│   ├── stores.php                # Winkel overzicht
│   ├── shopping-list.php         # Boodschappenlijst UI
│   ├── shopping-list-search.php  # AJAX: autocomplete
│   ├── shopping-list-send.php    # AJAX: PDF + e-mail
│   ├── contact.php               # Contact formulier
│   ├── privacy.php               # Privacybeleid
│   ├── voorwaarden.php           # Algemene voorwaarden
│   └── style.css                 # Publieke dark theme
├── scrapers/node/
│   ├── scrape-store.mjs          # Puppeteer scraper (9 extractors)
│   └── package.json              # Node.js dependencies
├── assets/logos/                 # Winkel logo's
├── setup.sql                     # Database schema + seed data
└── AGENTS.md                     # Project context
```

## 13. Datastromen

### 13.1 Scraping -> Database
```
Externe bron -> Scraper (PHP/Node) -> JSON parsing -> upsertProduct() + upsertPrice() -> MariaDB
```

### 13.2 Database -> Publieke Site
```
MariaDB -> PDO queries (JOIN products + prices + stores + categories) -> PHP rendering -> HTML
```

### 13.3 Shopping List Flow
```
Autocomplete (AJAX) -> Product selectie -> E-mail input -> POST -> PDF generatie (FPDF) -> SMTP (PHPMailer) -> Inbox
```

### 13.4 Cleanup Strategie
Bij elke scraping-sessie worden oude prijzen voor die winkel verwijderd (niet bijgewerkt in deze batch). Dit zorgt voor automatische archivering van verlopen aanbiedingen.
