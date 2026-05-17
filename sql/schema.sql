-- ============================================
-- MecaBuddy - Schéma de base de données
-- ============================================
-- Ce script crée la base de données et toutes les tables
-- nécessaires au fonctionnement de MecaBuddy.
-- ============================================

-- Création de la base de données
CREATE DATABASE IF NOT EXISTS mecabuddy
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE mecabuddy;

-- ============================================
-- TABLE: vehicles (Véhicules enregistrés)
-- ============================================
-- Stocke les informations des véhicules des utilisateurs
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Identification du véhicule
    license_plate VARCHAR(20) DEFAULT NULL COMMENT 'Plaque d''immatriculation',
    brand VARCHAR(100) NOT NULL COMMENT 'Marque du véhicule',
    model VARCHAR(100) NOT NULL COMMENT 'Modèle du véhicule',
    year YEAR NOT NULL COMMENT 'Année de fabrication',
    
    -- Informations techniques optionnelles
    engine_type VARCHAR(50) DEFAULT NULL COMMENT 'Type de moteur (essence, diesel, hybride, électrique)',
    engine_size VARCHAR(20) DEFAULT NULL COMMENT 'Cylindrée (ex: 1.6L, 2.0L)',
    transmission VARCHAR(50) DEFAULT NULL COMMENT 'Type de transmission (manuelle, automatique)',
    
    -- Métadonnées
    session_id VARCHAR(128) DEFAULT NULL COMMENT 'ID de session pour lier le véhicule',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Index pour optimiser les recherches
    INDEX idx_license_plate (license_plate),
    INDEX idx_brand_model (brand, model),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: tutorials (Tutoriels générés)
-- ============================================
-- Stocke les tutoriels générés pour référence future
CREATE TABLE IF NOT EXISTS tutorials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Lien avec le véhicule
    vehicle_id INT DEFAULT NULL,
    
    -- Contenu du tutoriel
    title VARCHAR(255) NOT NULL COMMENT 'Titre du tutoriel',
    action_type VARCHAR(100) NOT NULL COMMENT 'Type d''action (vidange, plaquettes, etc.)',
    description TEXT COMMENT 'Description générale du tutoriel',
    
    -- Données JSON pour les étapes et la sécurité
    steps JSON NOT NULL COMMENT 'Étapes du tutoriel au format JSON',
    tools_required JSON DEFAULT NULL COMMENT 'Outils nécessaires',
    parts_required JSON DEFAULT NULL COMMENT 'Pièces nécessaires',
    
    -- Informations de sécurité
    danger_level ENUM('none', 'low', 'medium', 'high') DEFAULT 'none' COMMENT 'Niveau de danger global',
    global_warnings JSON DEFAULT NULL COMMENT 'Avertissements globaux',
    
    -- Métadonnées
    estimated_time INT DEFAULT NULL COMMENT 'Temps estimé en minutes',
    difficulty ENUM('facile', 'moyen', 'difficile', 'expert') DEFAULT 'moyen',
    session_id VARCHAR(128) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Contraintes
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    INDEX idx_action_type (action_type),
    INDEX idx_session_id (session_id),
    INDEX idx_vehicle_id (vehicle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: diagnostic_conversations (Historique des conversations)
-- ============================================
-- Stocke l'historique des conversations avec le buddy
CREATE TABLE IF NOT EXISTS diagnostic_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Lien avec le véhicule
    vehicle_id INT DEFAULT NULL,
    
    -- Contenu de la conversation
    user_message TEXT NOT NULL COMMENT 'Message de l''utilisateur',
    buddy_response TEXT NOT NULL COMMENT 'Réponse du buddy',
    
    -- Contexte
    context JSON DEFAULT NULL COMMENT 'Contexte de la conversation (véhicule, historique)',
    
    -- Métadonnées
    session_id VARCHAR(128) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Contraintes
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: vehicle_brands (Marques de véhicules - référentiel)
-- ============================================
-- Référentiel des marques pour les sélecteurs
CREATE TABLE IF NOT EXISTS vehicle_brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    country VARCHAR(100) DEFAULT NULL,
    logo_url VARCHAR(255) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: vehicle_models (Modèles de véhicules - référentiel)
-- ============================================
-- Référentiel des modèles par marque
CREATE TABLE IF NOT EXISTS vehicle_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    year_start YEAR DEFAULT NULL COMMENT 'Année de début de production',
    year_end YEAR DEFAULT NULL COMMENT 'Année de fin de production (NULL si encore produit)',
    is_active BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (brand_id) REFERENCES vehicle_brands(id) ON DELETE CASCADE,
    INDEX idx_brand_id (brand_id),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERTION DES DONNÉES DE RÉFÉRENCE
-- ============================================

-- Marques populaires en France
INSERT INTO vehicle_brands (name, country) VALUES
    ('Renault', 'France'),
    ('Peugeot', 'France'),
    ('Citroën', 'France'),
    ('Volkswagen', 'Allemagne'),
    ('BMW', 'Allemagne'),
    ('Mercedes-Benz', 'Allemagne'),
    ('Audi', 'Allemagne'),
    ('Toyota', 'Japon'),
    ('Honda', 'Japon'),
    ('Nissan', 'Japon'),
    ('Ford', 'États-Unis'),
    ('Fiat', 'Italie'),
    ('Opel', 'Allemagne'),
    ('Hyundai', 'Corée du Sud'),
    ('Kia', 'Corée du Sud'),
    ('Dacia', 'Roumanie'),
    ('Seat', 'Espagne'),
    ('Skoda', 'République Tchèque'),
    ('Volvo', 'Suède'),
    ('Mazda', 'Japon')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Modèles Renault
INSERT INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Clio', 1990, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Mégane', 1995, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Captur', 2013, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Scenic', 1996, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Twingo', 1992, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Kadjar', 2015, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Arkana', 2019, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Renault'), 'Austral', 2022, NULL);

-- Modèles Peugeot
INSERT INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Peugeot'), '208', 2012, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Peugeot'), '308', 2007, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Peugeot'), '3008', 2009, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Peugeot'), '5008', 2009, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Peugeot'), '2008', 2013, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Peugeot'), '508', 2010, NULL);

-- Modèles Citroën
INSERT INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Citroën'), 'C3', 2002, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Citroën'), 'C4', 2004, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Citroën'), 'C5 Aircross', 2018, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Citroën'), 'Berlingo', 1996, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Citroën'), 'C3 Aircross', 2017, NULL);

-- Modèles Volkswagen
INSERT INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'Golf', 1974, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'Polo', 1975, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'Tiguan', 2007, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'Passat', 1973, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'T-Roc', 2017, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'ID.3', 2020, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Volkswagen'), 'ID.4', 2020, NULL);

-- Modèles Dacia
INSERT INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Dacia'), 'Sandero', 2007, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Dacia'), 'Duster', 2010, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Dacia'), 'Logan', 2004, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Dacia'), 'Spring', 2021, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Dacia'), 'Jogger', 2021, NULL);

-- Modèles Toyota
INSERT INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Toyota'), 'Yaris', 1999, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Toyota'), 'Corolla', 1966, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Toyota'), 'RAV4', 1994, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Toyota'), 'C-HR', 2016, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Toyota'), 'Aygo', 2005, NULL);

-- Modèles BMW
INSERT INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'BMW'), 'Série 1', 2004, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'BMW'), 'Série 3', 1975, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'BMW'), 'Série 5', 1972, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'BMW'), 'X1', 2009, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'BMW'), 'X3', 2003, NULL);

-- Modèles Mercedes
INSERT INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Mercedes-Benz'), 'Classe A', 1997, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Mercedes-Benz'), 'Classe C', 1993, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Mercedes-Benz'), 'Classe E', 1993, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Mercedes-Benz'), 'GLA', 2013, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Mercedes-Benz'), 'GLC', 2015, NULL);

-- Modèles Audi
INSERT INTO vehicle_models (brand_id, name, year_start, year_end) VALUES
    ((SELECT id FROM vehicle_brands WHERE name = 'Audi'), 'A1', 2010, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Audi'), 'A3', 1996, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Audi'), 'A4', 1994, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Audi'), 'Q3', 2011, NULL),
    ((SELECT id FROM vehicle_brands WHERE name = 'Audi'), 'Q5', 2008, NULL);

-- ============================================
-- FIN DU SCRIPT
-- ============================================

