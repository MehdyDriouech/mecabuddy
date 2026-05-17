<?php
/**
 * MecaBuddy - Mock Database (Faker API)
 * 
 * Ce fichier simule une base de données en utilisant les sessions PHP.
 * Permet de tester l'application sans installer MySQL.
 * 
 * Toutes les données sont stockées en session et persistent
 * tant que la session est active.
 */

require_once __DIR__ . '/config.php';

// Initialise la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * Classe MockDatabase - Simule PDO avec des données en session
 */
class MockDatabase {
    
    /**
     * Initialise les données mockées si elles n'existent pas
     */
    public static function init(): void {
        if (!isset($_SESSION['mock_db'])) {
            $_SESSION['mock_db'] = [
                'vehicles' => [],
                'tutorials' => [],
                'diagnostic_conversations' => [],
                'vehicle_brands' => self::getDefaultBrands(),
                'vehicle_models' => self::getDefaultModels(),
                'auto_increment' => [
                    'vehicles' => 1,
                    'tutorials' => 1,
                    'diagnostic_conversations' => 1
                ]
            ];
        }
    }
    
    /**
     * Retourne les marques par défaut
     */
    private static function getDefaultBrands(): array {
        return [
            ['id' => 1, 'name' => 'Renault', 'country' => 'France', 'category' => 'car', 'is_active' => true],
            ['id' => 2, 'name' => 'Peugeot', 'country' => 'France', 'category' => 'car', 'is_active' => true],
            ['id' => 3, 'name' => 'Citroën', 'country' => 'France', 'category' => 'car', 'is_active' => true],
            ['id' => 4, 'name' => 'Volkswagen', 'country' => 'Allemagne', 'category' => 'car', 'is_active' => true],
            ['id' => 5, 'name' => 'BMW', 'country' => 'Allemagne', 'category' => 'car', 'is_active' => true],
            ['id' => 6, 'name' => 'Mercedes-Benz', 'country' => 'Allemagne', 'category' => 'car', 'is_active' => true],
            ['id' => 7, 'name' => 'Audi', 'country' => 'Allemagne', 'category' => 'car', 'is_active' => true],
            ['id' => 8, 'name' => 'Toyota', 'country' => 'Japon', 'category' => 'car', 'is_active' => true],
            ['id' => 9, 'name' => 'Honda', 'country' => 'Japon', 'category' => 'car', 'is_active' => true],
            ['id' => 10, 'name' => 'Nissan', 'country' => 'Japon', 'category' => 'car', 'is_active' => true],
            ['id' => 11, 'name' => 'Ford', 'country' => 'États-Unis', 'category' => 'car', 'is_active' => true],
            ['id' => 12, 'name' => 'Fiat', 'country' => 'Italie', 'category' => 'car', 'is_active' => true],
            ['id' => 13, 'name' => 'Opel', 'country' => 'Allemagne', 'category' => 'car', 'is_active' => true],
            ['id' => 14, 'name' => 'Hyundai', 'country' => 'Corée du Sud', 'category' => 'car', 'is_active' => true],
            ['id' => 15, 'name' => 'Kia', 'country' => 'Corée du Sud', 'category' => 'car', 'is_active' => true],
            ['id' => 16, 'name' => 'Dacia', 'country' => 'Roumanie', 'category' => 'car', 'is_active' => true],
            ['id' => 17, 'name' => 'Seat', 'country' => 'Espagne', 'category' => 'car', 'is_active' => true],
            ['id' => 18, 'name' => 'Skoda', 'country' => 'République Tchèque', 'category' => 'car', 'is_active' => true],
            ['id' => 19, 'name' => 'Volvo', 'country' => 'Suède', 'category' => 'car', 'is_active' => true],
            ['id' => 20, 'name' => 'Mazda', 'country' => 'Japon', 'category' => 'car', 'is_active' => true]
        ];
    }
    
    /**
     * Retourne les modèles par défaut
     */
    private static function getDefaultModels(): array {
        return [
            // Renault (brand_id = 1)
            ['id' => 1, 'brand_id' => 1, 'name' => 'Clio', 'year_start' => 1990, 'year_end' => null, 'is_active' => true],
            ['id' => 2, 'brand_id' => 1, 'name' => 'Mégane', 'year_start' => 1995, 'year_end' => null, 'is_active' => true],
            ['id' => 3, 'brand_id' => 1, 'name' => 'Captur', 'year_start' => 2013, 'year_end' => null, 'is_active' => true],
            ['id' => 4, 'brand_id' => 1, 'name' => 'Scenic', 'year_start' => 1996, 'year_end' => null, 'is_active' => true],
            ['id' => 5, 'brand_id' => 1, 'name' => 'Twingo', 'year_start' => 1992, 'year_end' => null, 'is_active' => true],
            ['id' => 6, 'brand_id' => 1, 'name' => 'Kadjar', 'year_start' => 2015, 'year_end' => null, 'is_active' => true],
            ['id' => 7, 'brand_id' => 1, 'name' => 'Arkana', 'year_start' => 2019, 'year_end' => null, 'is_active' => true],
            ['id' => 8, 'brand_id' => 1, 'name' => 'Austral', 'year_start' => 2022, 'year_end' => null, 'is_active' => true],
            
            // Peugeot (brand_id = 2)
            ['id' => 9, 'brand_id' => 2, 'name' => '208', 'year_start' => 2012, 'year_end' => null, 'is_active' => true],
            ['id' => 10, 'brand_id' => 2, 'name' => '308', 'year_start' => 2007, 'year_end' => null, 'is_active' => true],
            ['id' => 11, 'brand_id' => 2, 'name' => '3008', 'year_start' => 2009, 'year_end' => null, 'is_active' => true],
            ['id' => 12, 'brand_id' => 2, 'name' => '5008', 'year_start' => 2009, 'year_end' => null, 'is_active' => true],
            ['id' => 13, 'brand_id' => 2, 'name' => '2008', 'year_start' => 2013, 'year_end' => null, 'is_active' => true],
            ['id' => 14, 'brand_id' => 2, 'name' => '508', 'year_start' => 2010, 'year_end' => null, 'is_active' => true],
            
            // Citroën (brand_id = 3)
            ['id' => 15, 'brand_id' => 3, 'name' => 'C3', 'year_start' => 2002, 'year_end' => null, 'is_active' => true],
            ['id' => 16, 'brand_id' => 3, 'name' => 'C4', 'year_start' => 2004, 'year_end' => null, 'is_active' => true],
            ['id' => 17, 'brand_id' => 3, 'name' => 'C5 Aircross', 'year_start' => 2018, 'year_end' => null, 'is_active' => true],
            ['id' => 18, 'brand_id' => 3, 'name' => 'Berlingo', 'year_start' => 1996, 'year_end' => null, 'is_active' => true],
            ['id' => 19, 'brand_id' => 3, 'name' => 'C3 Aircross', 'year_start' => 2017, 'year_end' => null, 'is_active' => true],
            
            // Volkswagen (brand_id = 4)
            ['id' => 20, 'brand_id' => 4, 'name' => 'Golf', 'year_start' => 1974, 'year_end' => null, 'is_active' => true],
            ['id' => 21, 'brand_id' => 4, 'name' => 'Polo', 'year_start' => 1975, 'year_end' => null, 'is_active' => true],
            ['id' => 22, 'brand_id' => 4, 'name' => 'Tiguan', 'year_start' => 2007, 'year_end' => null, 'is_active' => true],
            ['id' => 23, 'brand_id' => 4, 'name' => 'Passat', 'year_start' => 1973, 'year_end' => null, 'is_active' => true],
            ['id' => 24, 'brand_id' => 4, 'name' => 'T-Roc', 'year_start' => 2017, 'year_end' => null, 'is_active' => true],
            
            // BMW (brand_id = 5)
            ['id' => 25, 'brand_id' => 5, 'name' => 'Série 1', 'year_start' => 2004, 'year_end' => null, 'is_active' => true],
            ['id' => 26, 'brand_id' => 5, 'name' => 'Série 3', 'year_start' => 1975, 'year_end' => null, 'is_active' => true],
            ['id' => 27, 'brand_id' => 5, 'name' => 'Série 5', 'year_start' => 1972, 'year_end' => null, 'is_active' => true],
            ['id' => 28, 'brand_id' => 5, 'name' => 'X1', 'year_start' => 2009, 'year_end' => null, 'is_active' => true],
            ['id' => 29, 'brand_id' => 5, 'name' => 'X3', 'year_start' => 2003, 'year_end' => null, 'is_active' => true],
            
            // Mercedes-Benz (brand_id = 6)
            ['id' => 30, 'brand_id' => 6, 'name' => 'Classe A', 'year_start' => 1997, 'year_end' => null, 'is_active' => true],
            ['id' => 31, 'brand_id' => 6, 'name' => 'Classe C', 'year_start' => 1993, 'year_end' => null, 'is_active' => true],
            ['id' => 32, 'brand_id' => 6, 'name' => 'Classe E', 'year_start' => 1993, 'year_end' => null, 'is_active' => true],
            ['id' => 33, 'brand_id' => 6, 'name' => 'GLA', 'year_start' => 2013, 'year_end' => null, 'is_active' => true],
            ['id' => 34, 'brand_id' => 6, 'name' => 'GLC', 'year_start' => 2015, 'year_end' => null, 'is_active' => true],
            
            // Audi (brand_id = 7)
            ['id' => 35, 'brand_id' => 7, 'name' => 'A1', 'year_start' => 2010, 'year_end' => null, 'is_active' => true],
            ['id' => 36, 'brand_id' => 7, 'name' => 'A3', 'year_start' => 1996, 'year_end' => null, 'is_active' => true],
            ['id' => 37, 'brand_id' => 7, 'name' => 'A4', 'year_start' => 1994, 'year_end' => null, 'is_active' => true],
            ['id' => 38, 'brand_id' => 7, 'name' => 'Q3', 'year_start' => 2011, 'year_end' => null, 'is_active' => true],
            ['id' => 39, 'brand_id' => 7, 'name' => 'Q5', 'year_start' => 2008, 'year_end' => null, 'is_active' => true],
            
            // Toyota (brand_id = 8)
            ['id' => 40, 'brand_id' => 8, 'name' => 'Yaris', 'year_start' => 1999, 'year_end' => null, 'is_active' => true],
            ['id' => 41, 'brand_id' => 8, 'name' => 'Corolla', 'year_start' => 1966, 'year_end' => null, 'is_active' => true],
            ['id' => 42, 'brand_id' => 8, 'name' => 'RAV4', 'year_start' => 1994, 'year_end' => null, 'is_active' => true],
            ['id' => 43, 'brand_id' => 8, 'name' => 'C-HR', 'year_start' => 2016, 'year_end' => null, 'is_active' => true],
            ['id' => 44, 'brand_id' => 8, 'name' => 'Aygo', 'year_start' => 2005, 'year_end' => null, 'is_active' => true],
            
            // Dacia (brand_id = 16)
            ['id' => 45, 'brand_id' => 16, 'name' => 'Sandero', 'year_start' => 2007, 'year_end' => null, 'is_active' => true],
            ['id' => 46, 'brand_id' => 16, 'name' => 'Duster', 'year_start' => 2010, 'year_end' => null, 'is_active' => true],
            ['id' => 47, 'brand_id' => 16, 'name' => 'Logan', 'year_start' => 2004, 'year_end' => null, 'is_active' => true],
            ['id' => 48, 'brand_id' => 16, 'name' => 'Spring', 'year_start' => 2021, 'year_end' => null, 'is_active' => true],
            ['id' => 49, 'brand_id' => 16, 'name' => 'Jogger', 'year_start' => 2021, 'year_end' => null, 'is_active' => true],
        ];
    }
    
    /**
     * Récupère toutes les marques actives.
     *
     * @param string|null $category 'car' | 'moto' | null (toutes)
     */
    public static function getBrands(?string $category = null): array {
        self::init();
        return array_values(array_filter(
            $_SESSION['mock_db']['vehicle_brands'],
            static function ($b) use ($category) {
                if (empty($b['is_active'])) {
                    return false;
                }
                if ($category === null) {
                    return true;
                }
                $cat = $b['category'] ?? 'car';
                return $cat === $category;
            }
        ));
    }
    
    /**
     * Récupère les modèles d'une marque
     */
    public static function getModels(int $brandId): array {
        self::init();
        return array_values(array_filter(
            $_SESSION['mock_db']['vehicle_models'],
            fn($m) => $m['brand_id'] == $brandId && $m['is_active']
        ));
    }
    
    /**
     * Récupère un véhicule par ID
     */
    public static function getVehicle(int $id): ?array {
        self::init();
        foreach ($_SESSION['mock_db']['vehicles'] as $vehicle) {
            if ($vehicle['id'] == $id) {
                return $vehicle;
            }
        }
        return null;
    }
    
    /**
     * Sauvegarde un véhicule
     */
    public static function saveVehicle(array $data): int {
        self::init();
        $id = $_SESSION['mock_db']['auto_increment']['vehicles']++;
        
        $vehicle = [
            'id' => $id,
            'license_plate' => $data['license_plate'] ?? null,
            'brand' => $data['brand'],
            'model' => $data['model'],
            'year' => (int) $data['year'],
            'engine_type' => $data['engine_type'] ?? null,
            'engine_size' => $data['engine_size'] ?? null,
            'transmission' => $data['transmission'] ?? null,
            'session_id' => session_id(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $_SESSION['mock_db']['vehicles'][] = $vehicle;
        return $id;
    }
    
    /**
     * Récupère un tutoriel par ID
     */
    public static function getTutorial(int $id): ?array {
        self::init();
        foreach ($_SESSION['mock_db']['tutorials'] as $tutorial) {
            if ($tutorial['id'] == $id) {
                return $tutorial;
            }
        }
        return null;
    }
    
    /**
     * Sauvegarde un tutoriel
     */
    public static function saveTutorial(array $data): int {
        self::init();
        $id = $_SESSION['mock_db']['auto_increment']['tutorials']++;
        
        $tutorial = [
            'id' => $id,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'title' => $data['title'],
            'action_type' => $data['action_type'],
            'description' => $data['description'],
            'steps' => $data['steps'],
            'tools_required' => $data['tools_required'] ?? [],
            'parts_required' => $data['parts_required'] ?? [],
            'danger_level' => $data['danger_level'] ?? 'none',
            'global_warnings' => $data['global_warnings'] ?? [],
            'estimated_time' => $data['estimated_time'] ?? null,
            'difficulty' => $data['difficulty'] ?? 'moyen',
            'session_id' => session_id(),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $_SESSION['mock_db']['tutorials'][] = $tutorial;
        return $id;
    }
    
    /**
     * Liste les tutoriels récents de la session
     */
    public static function listTutorials(int $limit = 10): array {
        self::init();
        $sessionId = session_id();
        
        $tutorials = array_filter(
            $_SESSION['mock_db']['tutorials'],
            fn($t) => $t['session_id'] === $sessionId
        );
        
        // Tri par date décroissante
        usort($tutorials, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        
        // Ajoute les infos véhicule
        return array_map(function($t) {
            $vehicle = $t['vehicle_id'] ? self::getVehicle($t['vehicle_id']) : null;
            return [
                'id' => $t['id'],
                'title' => $t['title'],
                'action_type' => $t['action_type'],
                'difficulty' => $t['difficulty'],
                'estimated_time' => $t['estimated_time'],
                'danger_level' => $t['danger_level'],
                'created_at' => $t['created_at'],
                'brand' => $vehicle['brand'] ?? null,
                'model' => $vehicle['model'] ?? null
            ];
        }, array_slice($tutorials, 0, $limit));
    }
    
    /**
     * Sauvegarde une conversation diagnostic
     */
    public static function saveConversation(array $data): int {
        self::init();
        $id = $_SESSION['mock_db']['auto_increment']['diagnostic_conversations']++;
        
        $conversation = [
            'id' => $id,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'user_message' => $data['user_message'],
            'buddy_response' => $data['buddy_response'],
            'context' => $data['context'] ?? null,
            'session_id' => session_id(),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $_SESSION['mock_db']['diagnostic_conversations'][] = $conversation;
        return $id;
    }
    
    /**
     * Récupère l'historique des conversations
     */
    public static function getConversationHistory(int $limit = 20): array {
        self::init();
        $sessionId = session_id();
        
        $conversations = array_filter(
            $_SESSION['mock_db']['diagnostic_conversations'],
            fn($c) => $c['session_id'] === $sessionId
        );
        
        // Tri par date croissante
        usort($conversations, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));
        
        return array_map(fn($c) => [
            'id' => $c['id'],
            'user_message' => $c['user_message'],
            'buddy_response' => $c['buddy_response'],
            'created_at' => $c['created_at']
        ], array_slice($conversations, 0, $limit));
    }
    
    /**
     * Efface l'historique des conversations
     */
    public static function clearConversations(): int {
        self::init();
        $sessionId = session_id();
        
        $before = count($_SESSION['mock_db']['diagnostic_conversations']);
        
        $_SESSION['mock_db']['diagnostic_conversations'] = array_filter(
            $_SESSION['mock_db']['diagnostic_conversations'],
            fn($c) => $c['session_id'] !== $sessionId
        );
        
        return $before - count($_SESSION['mock_db']['diagnostic_conversations']);
    }
    
    /**
     * Réinitialise complètement la base de données mock
     */
    public static function reset(): void {
        unset($_SESSION['mock_db']);
        self::init();
    }
}

// Initialise automatiquement
MockDatabase::init();

