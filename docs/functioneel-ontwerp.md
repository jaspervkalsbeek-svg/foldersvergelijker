# Functioneel Ontwerp - Folders Vergelijker

## 1. Projectomschrijving

**Folders Vergelijker** is een publieke website die producten uit Nederlandse en Duitse supermarktfolders verzamelt, vergelijkt en toont waar elk product het goedkoopst is, inclusief prijs per eenheid.

## 2. Doelgroep

- Nederlandse consumenten die boodschappen willen vergelijken
- Prijsbewuste shoppers die over de grens willen kijken (NL <-> DE)
- Gebruikers die een boodschappenlijst willen samenstellen en per e-mail willen ontvangen

## 3. Kernfunctionaliteit

### 3.1 Product Verrijking & Vergelijking
- Automatische verzameling van productprijzen uit supermarktfolders
- Cross-border vergelijking (NL en DE supermarkten)
- Prijs per eenheid (per 100g/kg/L) naast totaalprijs
- Automatische categorisering van producten

### 3.2 Scraping & Data Import
- **Automatisch:** 3 scraper-systemen (Puppeteer, kaufDA.de, AlleFolders API)
- **Handmatig:** CSV/JSON bulk import via admin panel
- **Enkelvoudig:** Handmatig product + prijs toevoegen

### 3.3 Boodschappenlijst
- Live autocomplete op productnamen
- Samenstelling van lijst via web UI
- E-mail versturen met HTML overzicht + PDF bijlage
- PDF bevat winkelprijzen, eenheidsprijzen, en alternatieven

## 4. Gebruikersstromen

### 4.1 Publieke Gebruiker - Product Zoeken

```
Landing page (index.php)
    │
    ├── Zoekbalk: voer productnaam in
    │   └── Live resultaten tonen
    │
    ├── Filters:
    │   ├── Land: NL / DE / Alle
    │   ├── Winkel: dropdown
    │   └── Categorie: dropdown
    │
    ├── Weergave: Grid / List toggle
    │
    └── Product selecteren
        └── Product detail pagina (product.php?id=N)
            ├── Product info (naam, merk, afbeelding, categorie, EAN)
            ├── Laagste prijs box (met winkelnaam + kleur)
            ├── Laagste prijs per eenheid
            ├── Prijsvergelijkingstabel:
            │   ├── Winkel (gekleurde badge)
            │   ├── Land (NL/DE)
            │   ├── Prijs
            │   ├── Verpakking
            │   ├── Prijs/kg
            │   ├── Prijs/100g
            │   └── Datum gezien
            └── Vergelijkbare producten (4 willekeurige uit dezelfde categorie)
```

### 4.2 Publieke Gebruiker - Boodschappenlijst

```
Shopping List pagina (shopping-list.php)
    │
    ├── Product toevoegen:
    │   ├── Typ productnaam (min. 2 tekens)
    │   ├── Live autocomplete (max. 15 resultaten)
    │   └── Klik om toe te voegen
    │
    ├── Lijst beheren:
    │   ├── Producten tonen als tags
    │   └── Verwijderen via X knop
    │
    ├── E-mail adres invoeren
    │
    └── "Verstuur overzicht" knop
        └── AJAX POST naar shopping-list-send.php
            ├── PDF generatie (FPDF):
            │   ├── Donkere achtergrond
            │   ├── "Folders Vergelijker" branding
            │   ├── Per product: winkelbadge, naam, merk, prijs, eenheidsprijs
            │   ├── "Ook:" alternatieve winkelprijzen
            │   └── Checkbox voor afdrukken
            ├── E-mail verzenden (PHPMailer):
            │   ├── HTML body (donker theme)
            │   ├── Productoverzicht met winkelprijzen
            │   └── PDF bijlage ("boodschappenlijstje.pdf")
            └── Succes/foutmelding tonen
```

### 4.3 Publieke Gebruiker - Winkels Bekijken

```
Stores pagina (stores.php)
    │
    ├── Land filter: Alle / NL / DE
    │
    └── Winkel cards:
        ├── Gekleurde header (winkelkleur)
        ├── Land (NL/DE)
        ├── Statistieken (aantal producten, aantal prijzen)
        ├── Website domein
        └── Link naar gefilterde productlisting
```

### 4.4 Admin - Login & Dashboard

```
Login pagina (login.php)
    │
    ├── Username + wachtwoord
    └── Na succesvolle login → Dashboard (index.php)
        │
        ├── Statistieken:
        │   ├── Totaal producten
        │   ├── Totaal prijzen
        │   └── Actieve winkels met prijzen
        │
        ├── Laagste prijzen (5 goedkoopste)
        │
        ├── Snelstart kaarten:
        │   ├── Product toevoegen
        │   ├── Importeren
        │   └── Overzicht
        │
        └── Scraper Control Panel:
            ├── 3 secties: Puppeteer, kaufDA, AlleFolders
            ├── Per winkel: "Scrapen" knop
            ├── "Alle scannen" knop
            └── Terminal-achtig log venster
```

### 4.5 Admin - Product Beheer

```
Handmatig toevoegen (add_product.php):
    ├── Product naam, merk, categorie (dropdown), EAN
    ├── Dynamische prijsregels:
    │   ├── Winkel dropdown
    │   ├── Prijs invoer
    │   └── +/- knop om regels toe te voegen/verwijderen
    └── Opslaan → upsert in DB

Bulk import (import.php):
    ├── Tabbladen: Upload / Preview / Resultaat
    ├── Drag-and-drop upload
    ├── Ondersteunt CSV en JSON:
    │   ├── CSV: name,brand,category,ean,store,price
    │   └── JSON: array met nested prices[]
    ├── Preview: samenvatting + eerste 10 producten
    └── Resultaat: log met OK/WARN/ERR entries

Overzicht (overview.php):
    ├── Paginated tabel (30/pagina)
    ├── Zoeken op naam of merk
    ├── Kolommen: naam, merk, categorie, aantal winkels, laagste prijs, aanmaakdatum
    └── Link naar publieke detail pagina
```

## 5. Actieve Winkels

### 5.1 Nederland (5)
| Winkel | Scraper | Methode |
|--------|---------|---------|
| Albert Heijn | Puppeteer | ah.nl/bonus |
| Jumbo | AlleFolders | GraphQL API |
| Lidl NL | Puppeteer | lidl.nl/aanbiedingen |
| Aldi NL | Puppeteer | aldi.nl/aanbiedingen |
| Plus | Puppeteer | plus.nl/aanbiedingen |

### 5.2 Duitsland (5)
| Winkel | Scraper | Methode |
|--------|---------|---------|
| Rewe | kaufDA.de | Bonial API |
| Kaufland | kaufDA.de | Bonial API |
| Netto | kaufDA.de | Bonial API |
| Lidl DE | kaufDA.de | Bonial API |
| Aldi Sud | kaufDA.de | Search page + API |

## 6. Pagina's Overzicht

### 6.1 Publieke Pagina's

| Pagina | Bestand | Doel |
|--------|---------|------|
| Homepage | `public/index.php` | Product zoeken, filteren, sorteren |
| Product detail | `public/product.php` | Prijsvergelijking per winkel |
| Winkels | `public/stores.php` | Winkeloverzicht met statistieken |
| Boodschappenlijst | `public/shopping-list.php` | Lijst samenstellen + e-mail |
| Contact | `public/contact.php` | Contact formulier |
| Privacy | `public/privacy.php` | Privacybeleid (wettelijk) |
| Voorwaarden | `public/voorwaarden.php` | Algemene voorwaarden |

### 6.2 Admin Pagina's

| Pagina | Bestand | Doel |
|--------|---------|------|
| Dashboard | `admin/index.php` | Overzicht + scraper besturing |
| Login | `admin/login.php` | Authenticatie |
| Logout | `admin/logout.php` | Veilig uitloggen |
| Product toevoegen | `admin/add_product.php` | Handmatig product + prijzen |
| Importeren | `admin/import.php` | CSV/JSON bulk import |
| Overzicht | `admin/overview.php` | Paginated product tabel |
| Admin aanmaken | `admin/add_admin.php` | Nieuw admin account |

## 7. Functionaliteiten Per Pagina

### 7.1 Homepage (`public/index.php`)
- **Zoekfunctionaliteit:** Tekstinput met live filtering
- **Filters:** Land (NL/DE), Winkel, Categorie
- **Weergave:** Grid/List toggle
- **Sortering:** Op afbeelding, aantal winkels, naam
- **Paginering:** 30 producten per pagina
- **Product cards tonen:**
  - Afbeelding (of placeholder)
  - Winkelbadge (goedkoopste)
  - Naam, merk, beschrijving
  - Categorie
  - Aantal winkels
  - Laagste prijs
  - Prijs per 100g

### 7.2 Product Detail (`public/product.php`)
- **Product info:** Afbeelding, naam, merk, categorie, EAN
- **Laagste prijs box:** Prijs + winkelnaam (gekleurd)
- **Laagste prijs per eenheid:** Per 100g/L
- **Prijsvergelijkingstabel:**
  - Winkel (gekleurde badge)
  - Land (NL/DE)
  - Prijs
  - Verpakking
  - Prijs/kg
  - Prijs/100g
  - Datum gezien
  - Goedkoopste rij gemarkeerd
- **Vergelijkbare producten:** 4 willekeurige uit dezelfde categorie

### 7.3 Boodschappenlijst (`public/shopping-list.php`)
- **Autocomplete:** Live zoeken op productnamen (min. 2 tekens, max. 15 resultaten)
- **Lijst beheren:** Producten als tags, verwijderen via X
- **E-mail:** Input voor e-mailadres
- **Verzenden:** AJAX POST, toont spinner + resultaat
- **PDF:** Donkere achtergrond, productkaarten, winkelprijzen, alternatieven

### 7.4 Dashboard (`admin/index.php`)
- **Statistieken:** Totaal producten, prijzen, actieve winkels
- **Laagste prijzen:** 5 goedkoopste producten
- **Snelstart:** Cards naar andere admin pagina's
- **Scraper Control Panel:**
  - 3 secties met store cards
  - Per winkel "Scrapen" knop
  - "Alle scannen" doorloopt alle groepen
  - Terminal-achtig log met voortgang

## 8. Design Principes

### 8.1 Visueel
- **Donker thema:** `#0d0d0d` achtergrond, `#ffd600` geel accent
- **Mobile-first:** Responsive design, single-column op <768px
- **Geen gradients:** Alleen effen kleuren
- **Winkelkleuren:** Elke winkel heeft eigen brand kleur
- **Inter font family**

### 8.2 Gebruiksvriendelijkheid
- **Eenvoudig zoeken:** Direct op de homepage
- **Filters:** Land, winkel, categorie
- **Grid/List toggle:** Gebruiker kiest weergave
- **Paginering:** Niet overweldigend grote lijsten
- **Autocomplete:** Snelle product toevoeging aan lijst

## 9. External Bronnen

### 9.1 kaufDA.de (Bonial)
- **Type:** Publieke API (geen authenticatie)
- **Data:** Productnamen, prijzen, eenheidsprijzen, afbeeldingen
- **Scope:** ALLE offers per brochure (185-439 per winkel)
- **URL:** `content-viewer-be.kaufda.de/v1/brochures/{uuid}/pages`

### 9.2 AlleFolders (jafolders.com)
- **Type:** GraphQL API
- **Data:** Productnamen, prijzen, hotspot afbeeldingen
- **Beperking:** Geen eenheidsprijzen
- **Header:** `jafolders-context: allefolders;nl;web;1;1`

### 9.3 Supermarkt Websites (Puppeteer)
- **Type:** Browser automatie
- **Data:** Productnamen, prijzen, afbeeldingen, eenheidsprijzen
- **Detectie:** `puppeteer-extra` + `stealth-plugin`
- **Beperking:** Afhankelijk van DOM structuur wijzigingen

## 10. Bekende Beperkingen

1. **Geen real-time prijzen:** Data is snapshot-based (scrape-moment)
2. **Geen product matching cross-winkel:** Zelfde product kan verschillende namen hebben
3. **Geen EAN-based matching:** Matching is op naam + merk (fuzzy)
4. **Geen caching:** Elke page load queryt de database
5. **Geen PWA/offline:** Alleen beschikbaar met internetverbinding
6. **Geen meerdere talen:** Alleen Nederlands
7. **Geen user accounts:** Alleen admin accounts
8. **Geen wishlist/alerts:** Niet gewenst in huidige scope

## 11. Toekomstige Uitbreidingen (Nice-to-have)

1. **Extra NL supermarkten:** Nettorama, Jan Linders, Spar (via AlleFolders API)
2. **Product matching:** Fuzzy matching op naam + gewicht + merk
3. **Prijs geschiedenis:** Tracking van prijswijzigingen over tijd
4. **Prijs alerts:** Notificatie bij prijsdaling
5. **Favorieten:** Opslaan van favoriete producten
6. **API voor mobiele app:** REST API voor externe clients
7. **SEO optimalisatie:** Meta tags, structured data, sitemap
8. **Analytics:** Page views, zoektermen, populairste producten
