CREATE DATABASE IF NOT EXISTS folders_vergelijker
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE folders_vergelijker;

CREATE TABLE stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    country ENUM('NL','DE') NOT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    scraper_class VARCHAR(100) DEFAULT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    folder_url VARCHAR(500) DEFAULT NULL,
    scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    brand VARCHAR(100) DEFAULT NULL,
    category_id INT DEFAULT NULL,
    ean VARCHAR(13) DEFAULT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE product_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    store_id INT NOT NULL,
    folder_id INT DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    unit_size VARCHAR(50) DEFAULT NULL,
    unit_price DECIMAL(10,2) DEFAULT NULL,
    scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL
);

INSERT INTO stores (name, country, website, scraper_class) VALUES
('Albert Heijn', 'NL', 'https://www.ah.nl', 'AHScraper'),
('Jumbo', 'NL', 'https://www.jumbo.com', 'JumboScraper'),
('Lidl', 'NL', 'https://www.lidl.nl', 'LidlNLScraper'),
('Aldi', 'NL', 'https://www.aldi.nl', 'AldiNLScraper'),
('Plus', 'NL', 'https://www.plus.nl', 'PlusScraper'),
('Dirk', 'NL', 'https://www.dirk.nl', 'DirkScraper'),
('Lidl', 'DE', 'https://www.lidl.de', 'LidlDEScraper'),
('Aldi', 'DE', 'https://www.aldi.de', 'AldiDEScraper'),
('Rewe', 'DE', 'https://www.rewe.de', 'ReweScraper'),
('Edeka', 'DE', 'https://www.edeka.de', 'EdekaScraper'),
('Netto', 'DE', 'https://www.netto.de', 'NettoScraper');

INSERT INTO categories (name, slug) VALUES
('Zuivel & eieren', 'zuivel-eieren'),
('Brood & ontbijtgranen', 'brood-ontbijtgranen'),
('Fruit & groente', 'fruit-groente'),
('Vlees & vis', 'vlees-vis'),
('Diepvries', 'diepvries'),
('Dranken', 'dranken'),
('Snacks & zoetigheid', 'snacks-zoetigheid'),
('Pasta & rijst', 'pasta-rijst'),
('Conserven & sauzen', 'conserven-sauzen'),
('Huishouden', 'huishouden'),
('Persoonlijke verzorging', 'persoonlijke-verzorging'),
('Drogisterij', 'drogisterij'),
('Baby', 'baby'),
('Huisdier', 'huisdier'),
('Overig', 'overig');
