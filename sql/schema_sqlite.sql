-- ============================================
-- MecaBuddy - Schéma SQLite (POC)
-- Traduit depuis sql/schema.sql
-- ============================================

PRAGMA foreign_keys = ON;

-- ============================================
-- TABLE: vehicles (Véhicules enregistrés)
-- ============================================
CREATE TABLE IF NOT EXISTS vehicles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    license_plate VARCHAR(20) DEFAULT NULL,
    brand VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    year INTEGER NOT NULL,
    engine_type VARCHAR(50) DEFAULT NULL,
    engine_size VARCHAR(20) DEFAULT NULL,
    transmission VARCHAR(50) DEFAULT NULL,
    session_id VARCHAR(128) DEFAULT NULL,
    is_active INTEGER DEFAULT 0 CHECK (is_active IN (0, 1)),
    slot INTEGER DEFAULT NULL CHECK (slot IS NULL OR slot IN (1, 2, 3)),
    demo_user_id INTEGER DEFAULT NULL,
    is_demo_seed INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_license_plate ON vehicles(license_plate);
CREATE INDEX IF NOT EXISTS idx_brand_model ON vehicles(brand, model);
CREATE INDEX IF NOT EXISTS idx_session_id ON vehicles(session_id);
CREATE INDEX IF NOT EXISTS idx_vehicles_demo_user_id ON vehicles(demo_user_id);

CREATE TRIGGER IF NOT EXISTS trg_vehicles_updated_at
AFTER UPDATE ON vehicles
FOR EACH ROW
WHEN NEW.updated_at = OLD.updated_at
BEGIN
    UPDATE vehicles SET updated_at = datetime('now') WHERE id = OLD.id;
END;

-- ============================================
-- TABLE: tutorials (Tutoriels générés)
-- ============================================
CREATE TABLE IF NOT EXISTS tutorials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    action_type VARCHAR(100) NOT NULL,
    description TEXT,
    steps TEXT NOT NULL,
    tools_required TEXT DEFAULT NULL,
    parts_required TEXT DEFAULT NULL,
    danger_level TEXT DEFAULT 'none' CHECK (danger_level IN ('none', 'low', 'medium', 'high')),
    global_warnings TEXT DEFAULT NULL,
    estimated_time INTEGER DEFAULT NULL,
    difficulty TEXT DEFAULT 'moyen' CHECK (difficulty IN ('facile', 'moyen', 'difficile', 'expert')),
    session_id VARCHAR(128) DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_action_type ON tutorials(action_type);
CREATE INDEX IF NOT EXISTS idx_session_id_tutorials ON tutorials(session_id);
CREATE INDEX IF NOT EXISTS idx_vehicle_id ON tutorials(vehicle_id);

-- ============================================
-- TABLE: diagnostic_conversations
-- ============================================
CREATE TABLE IF NOT EXISTS diagnostic_conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vehicle_id INTEGER DEFAULT NULL,
    user_message TEXT NOT NULL,
    buddy_response TEXT NOT NULL,
    context TEXT DEFAULT NULL,
    session_id VARCHAR(128) DEFAULT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_session_id_diag ON diagnostic_conversations(session_id);
CREATE INDEX IF NOT EXISTS idx_created_at ON diagnostic_conversations(created_at);

-- ============================================
-- TABLE: vehicle_brands
-- ============================================
CREATE TABLE IF NOT EXISTS vehicle_brands (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    country VARCHAR(100) DEFAULT NULL,
    logo_url VARCHAR(255) DEFAULT NULL,
    is_active INTEGER DEFAULT 1,
    category TEXT DEFAULT 'car' CHECK (category IN ('car', 'moto'))
);

CREATE INDEX IF NOT EXISTS idx_vb_name ON vehicle_brands(name);
CREATE INDEX IF NOT EXISTS idx_vb_category ON vehicle_brands(category);

-- ============================================
-- TABLE: vehicle_models
-- ============================================
CREATE TABLE IF NOT EXISTS vehicle_models (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    brand_id INTEGER NOT NULL,
    name VARCHAR(100) NOT NULL,
    year_start INTEGER DEFAULT NULL,
    year_end INTEGER DEFAULT NULL,
    is_active INTEGER DEFAULT 1,
    FOREIGN KEY (brand_id) REFERENCES vehicle_brands(id) ON DELETE CASCADE,
    UNIQUE (brand_id, name)
);

CREATE INDEX IF NOT EXISTS idx_brand_id ON vehicle_models(brand_id);
CREATE INDEX IF NOT EXISTS idx_name_models ON vehicle_models(name);

-- ============================================
-- TABLE: engine_types (motorisations de référence)
-- ============================================
CREATE TABLE IF NOT EXISTS engine_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    model_id INTEGER NOT NULL,
    label TEXT NOT NULL,
    fuel_type TEXT NOT NULL CHECK (fuel_type IN ('essence', 'diesel', 'hybride', 'electrique', 'gpl')),
    displacement TEXT DEFAULT NULL,
    power_hp INTEGER DEFAULT NULL,
    year_start INTEGER DEFAULT NULL,
    year_end INTEGER DEFAULT NULL,
    FOREIGN KEY (model_id) REFERENCES vehicle_models(id) ON DELETE CASCADE,
    UNIQUE (model_id, label)
);

CREATE INDEX IF NOT EXISTS idx_et_model_id ON engine_types(model_id);

-- ============================================
-- DONNÉES DE RÉFÉRENCE (marques / modèles)
-- ============================================

INSERT OR IGNORE INTO vehicle_brands (name, country, category) VALUES
    ('Renault', 'France', 'car'),
    ('Peugeot', 'France', 'car'),
    ('Citroën', 'France', 'car'),
    ('Volkswagen', 'Allemagne', 'car'),
    ('BMW', 'Allemagne', 'car'),
    ('Mercedes-Benz', 'Allemagne', 'car'),
    ('Audi', 'Allemagne', 'car'),
    ('Toyota', 'Japon', 'car'),
    ('Honda', 'Japon', 'car'),
    ('Nissan', 'Japon', 'car'),
    ('Ford', 'États-Unis', 'car'),
    ('Fiat', 'Italie', 'car'),
    ('Opel', 'Allemagne', 'car'),
    ('Hyundai', 'Corée du Sud', 'car'),
    ('Kia', 'Corée du Sud', 'car'),
    ('Dacia', 'Roumanie', 'car'),
    ('Seat', 'Espagne', 'car'),
    ('Skoda', 'République Tchèque', 'car'),
    ('Volvo', 'Suède', 'car'),
    ('Mazda', 'Japon', 'car');

INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Clio', 1990, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Mégane', 1995, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Captur', 2013, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Scenic', 1996, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Twingo', 1992, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Kadjar', 2015, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Arkana', 2019, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Austral', 2022, NULL);

INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Peugeot'), '208', 2012, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Peugeot'), '308', 2007, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Peugeot'), '3008', 2009, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Peugeot'), '5008', 2009, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Peugeot'), '2008', 2013, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Peugeot'), '508', 2010, NULL);

INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Citroën'), 'C3', 2002, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Citroën'), 'C4', 2004, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Citroën'), 'C5 Aircross', 2018, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Citroën'), 'Berlingo', 1996, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Citroën'), 'C3 Aircross', 2017, NULL);

INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'Golf', 1974, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'Polo', 1975, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'Tiguan', 2007, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'Passat', 1973, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'T-Roc', 2017, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'ID.3', 2020, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'ID.4', 2020, NULL);

INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Dacia'), 'Sandero', 2007, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Dacia'), 'Duster', 2010, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Dacia'), 'Logan', 2004, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Dacia'), 'Spring', 2021, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Dacia'), 'Jogger', 2021, NULL);

INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Toyota'), 'Yaris', 1999, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Toyota'), 'Corolla', 1966, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Toyota'), 'RAV4', 1994, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Toyota'), 'C-HR', 2016, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Toyota'), 'Aygo', 2005, NULL);

INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'BMW'), 'Série 1', 2004, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'BMW'), 'Série 3', 1975, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'BMW'), 'Série 5', 1972, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'BMW'), 'X1', 2009, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'BMW'), 'X3', 2003, NULL);

INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Mercedes-Benz'), 'Classe A', 1997, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Mercedes-Benz'), 'Classe C', 1993, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Mercedes-Benz'), 'Classe E', 1993, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Mercedes-Benz'), 'GLA', 2013, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Mercedes-Benz'), 'GLC', 2015, NULL);

INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Audi'), 'A1', 2010, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Audi'), 'A3', 1996, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Audi'), 'A4', 1994, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Audi'), 'Q3', 2011, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Audi'), 'Q5', 2008, NULL);

-- ============================================
-- TABLE: settings
-- ============================================
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TEXT DEFAULT (datetime('now'))
);

INSERT OR IGNORE INTO settings (key, value) VALUES
    ('plate_lookup_enabled', 'false'),
    ('plate_lookup_api_key', ''),
    ('plate_lookup_provider', 'apiplaqueimmatriculation'),
    ('llm_fallback_enabled', 'false'),
    ('demo_mode', 'false'),
    ('serper_api_key', ''),
    ('llm_providers', '[{"id":"local_gemma","name":"Gemma4 local","type":"ollama","base_url":"http://localhost:11434","model":"gemma4:26b","active":true}]');

-- Extension catalogue véhicules (marques / modèles / motorisations)
-- Voir sql/seed_vehicle_catalog.sql — appliqué par initSQLite() après le schéma de base.
