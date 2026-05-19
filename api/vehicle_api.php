<?php
/**
 * MecaBuddy - API Véhicule
 * 
 * Endpoints pour la gestion des véhicules :
 * - GET  ?action=current    → Récupère le véhicule courant de la session
 * - GET  ?action=brands|getBrands&category=car|moto → Liste des marques
 * - GET  ?action=models|getModels&brand_id=X → Liste des modèles pour une marque
 * - GET  ?action=getEngines&model_id=X → Motorisations de référence
 * - POST ?action=save|saveVehicle → Enregistre un véhicule (garage, max 12)
 * - GET  ?action=garage     → Liste véhicules session + slots actifs
 * - POST ?action=set_active → Active/désactive un véhicule sur un slot (1–3)
 * - POST ?action=delete_vehicle → Supprime un véhicule du garage
 * - GET  ?action=current&slot=1|2|3 → Véhicule actif du slot (défaut: 1)
 * - POST ?action=lookup     → Simule une recherche par plaque d'immatriculation
 * 
 * Supporte le mode MOCK (sans base de données MySQL)
 */

// Configuration des headers pour API JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Chargement des dépendances
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/vehicle_scope.php';

// Initialisation de la session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * Fonction pour envoyer une réponse JSON
 */
function sendResponse(array $data, int $statusCode = 200): void {
    // Ajoute un indicateur de mode mock
    if (USE_MOCK_DB) {
        $data['_mock_mode'] = true;
    }
    
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Fonction pour envoyer une erreur JSON
 */
function sendError(string $message, int $statusCode = 400): void {
    sendResponse([
        'success' => false,
        'error' => $message
    ], $statusCode);
}

/**
 * Filtre category GET optionnel (car | moto).
 */
function parseBrandCategoryParam(): ?string {
    $category = $_GET['category'] ?? null;
    if ($category === null || $category === '') {
        return null;
    }
    if (!in_array($category, ['car', 'moto'], true)) {
        sendError('category invalide (valeurs: car, moto)');
    }
    return $category;
}

/**
 * engine_label + fuel_type → colonne vehicles.engine_type (label + carburant si absent).
 */
function resolveStoredEngineType(array $input): ?string {
    $engineLabel = trim((string) ($input['engine_label'] ?? ''));
    if ($engineLabel === '') {
        $engineLabel = trim((string) ($input['engine_type'] ?? ''));
    }
    if ($engineLabel === '') {
        return null;
    }
    $fuelType = trim((string) ($input['fuel_type'] ?? ''));
    if ($fuelType === '') {
        return $engineLabel;
    }
    if (stripos($engineLabel, $fuelType) === false) {
        return $engineLabel . ' ' . $fuelType;
    }
    return $engineLabel;
}

/**
 * Corps JSON ou POST pour les actions POST.
 *
 * @return array<string, mixed>
 */
function parseJsonBody(): array {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    return is_array($input) ? $input : [];
}

/**
 * @param array<string, mixed> $vehicle
 */
function formatGarageVehicle(array $vehicle): array {
    return [
        'id' => (int) $vehicle['id'],
        'brand' => $vehicle['brand'],
        'model' => $vehicle['model'],
        'year' => (int) $vehicle['year'],
        'engine_type' => $vehicle['engine_type'],
        'engine_size' => $vehicle['engine_size'],
        'transmission' => $vehicle['transmission'],
        'is_active' => (int) ($vehicle['is_active'] ?? 0),
        'slot' => isset($vehicle['slot']) && $vehicle['slot'] !== null && $vehicle['slot'] !== ''
            ? (int) $vehicle['slot']
            : null,
        'created_at' => $vehicle['created_at'] ?? null,
        'category' => $vehicle['category'] ?? 'car',
        'display_name' => sprintf(
            '%s %s (%d)',
            $vehicle['brand'],
            $vehicle['model'],
            (int) $vehicle['year']
        ),
    ];
}

/**
 * @return array{vehicles: list<array>, active_count: int, total: int, slots: array<int, array|null>}
 */
function buildGaragePayload(PDO $db, ?string $sessionId = null): array
{
    unset($sessionId);

    return vehicle_scope_build_garage_payload($db);
}

// ============================================
// ROUTAGE DES ACTIONS
// ============================================

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    // Mode MOCK - utilise MockDatabase
    if (USE_MOCK_DB) {
        switch ($action) {
            case 'current':
                handleGetCurrentMock();
                break;
            case 'brands':
            case 'getBrands':
                handleGetBrandsMock();
                break;
            case 'models':
            case 'getModels':
                handleGetModelsMock();
                break;
            case 'getEngines':
            case 'engines':
                handleGetEnginesMock();
                break;
            case 'save':
            case 'saveVehicle':
                handleSaveVehicleMock();
                break;
            case 'lookup':
                handleLookupPlateMock();
                break;
            case 'garage':
                mock_db_diag_log_garage_blocked();
                sendError('Action garage non disponible en mode mock', 501);
                break;
            case 'set_active':
                sendError('Action set_active non disponible en mode mock', 501);
                break;
            case 'delete_vehicle':
                sendError('Action delete_vehicle non disponible en mode mock', 501);
                break;
            case 'set_current':
                handleSetCurrentMock();
                break;
            case 'clear':
                handleClearVehicle();
                break;
            default:
                sendError('Action non reconnue. Actions disponibles: current, garage, brands, models, save, set_active, set_current, delete_vehicle, lookup, clear', 400);
        }
    } 
    // Mode NORMAL - SQLite (ou MySQL si configuré)
    else {
        $db = getDB();
        if (function_exists('isDemoAuthEnabled') && isDemoAuthEnabled()) {
            assertVehicleDemoSchemaReady($db);
        }

        switch ($action) {
            case 'current':
                handleGetCurrent($db);
                break;
            case 'brands':
            case 'getBrands':
                handleGetBrands($db);
                break;
            case 'models':
            case 'getModels':
                handleGetModels($db);
                break;
            case 'getEngines':
            case 'engines':
                handleGetEngines($db);
                break;
            case 'save':
            case 'saveVehicle':
                handleSaveVehicle($db);
                break;
            case 'lookup':
                handleLookupPlate($db);
                break;
            case 'garage':
                handleGarage($db);
                break;
            case 'set_active':
                handleSetActive($db);
                break;
            case 'delete_vehicle':
                handleDeleteVehicle($db);
                break;
            case 'set_current':
                handleSetCurrent($db);
                break;
            case 'clear':
                handleClearVehicle();
                break;
            default:
                sendError('Action non reconnue. Actions disponibles: current, garage, brands, models, save, set_active, set_current, delete_vehicle, lookup, clear', 400);
        }
    }
    
} catch (PDOException $e) {
    if (APP_DEBUG) {
        sendError('Erreur base de données: ' . $e->getMessage(), 500);
    } else {
        sendError('Erreur interne du serveur', 500);
    }
} catch (RuntimeException $e) {
    sendError($e->getMessage(), 500);
} catch (Exception $e) {
    sendError($e->getMessage(), 400);
}

// ============================================
// HANDLERS MODE MOCK
// ============================================

/**
 * [MOCK] Récupère le véhicule courant
 */
function handleGetCurrentMock(): void {
    if (!isset($_SESSION['vehicle_id'])) {
        sendResponse([
            'success' => true,
            'has_vehicle' => false,
            'vehicle' => null
        ]);
    }
    
    $vehicle = MockDatabase::getVehicle($_SESSION['vehicle_id']);
    
    if (!$vehicle) {
        unset($_SESSION['vehicle_id']);
        sendResponse([
            'success' => true,
            'has_vehicle' => false,
            'vehicle' => null
        ]);
    }
    
    sendResponse([
        'success' => true,
        'has_vehicle' => true,
        'vehicle' => [
            'id' => (int) $vehicle['id'],
            'license_plate' => $vehicle['license_plate'],
            'brand' => $vehicle['brand'],
            'model' => $vehicle['model'],
            'year' => (int) $vehicle['year'],
            'engine_type' => $vehicle['engine_type'],
            'engine_size' => $vehicle['engine_size'],
            'transmission' => $vehicle['transmission'],
            'display_name' => sprintf('%s %s (%d)', $vehicle['brand'], $vehicle['model'], $vehicle['year'])
        ]
    ]);
}

/**
 * [MOCK] Liste des marques
 */
function handleGetBrandsMock(): void {
    $category = parseBrandCategoryParam();
    $brands = MockDatabase::getBrands($category);
    
    sendResponse([
        'success' => true,
        'brands' => array_map(static fn($b) => [
            'id' => $b['id'],
            'name' => $b['name'],
            'country' => $b['country'],
            'category' => $b['category'] ?? 'car',
        ], array_values($brands))
    ]);
}

/**
 * [MOCK] Motorisations — lit SQLite si disponible, sinon tableau vide.
 */
function handleGetEnginesMock(): void {
    $modelId = (int) ($_GET['model_id'] ?? 0);
    if ($modelId <= 0) {
        sendError('model_id est requis');
    }

    try {
        $pdo = getSQLite();
        handleGetEngines($pdo);
    } catch (Throwable $e) {
        sendResponse(['success' => true, 'engines' => []]);
    }
}

/**
 * [MOCK] Liste des modèles
 */
function handleGetModelsMock(): void {
    $brandId = (int) ($_GET['brand_id'] ?? 0);
    
    if (!$brandId) {
        sendError('brand_id est requis');
    }
    
    $models = MockDatabase::getModels($brandId);
    
    sendResponse([
        'success' => true,
        'models' => array_map(fn($m) => [
            'id' => $m['id'],
            'name' => $m['name'],
            'year_start' => $m['year_start'],
            'year_end' => $m['year_end']
        ], $models)
    ]);
}

/**
 * [MOCK] Enregistrer un véhicule
 */
function handleSaveVehicleMock(): void {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $brand = trim($input['brand'] ?? '');
    $model = trim($input['model'] ?? '');
    $year = (int) ($input['year'] ?? 0);
    
    if (empty($brand)) {
        sendError('La marque est requise');
    }
    if (empty($model)) {
        sendError('Le modèle est requis');
    }
    if ($year < 1900 || $year > (int) date('Y') + 1) {
        sendError('L\'année doit être comprise entre 1900 et ' . (date('Y') + 1));
    }
    
    $vehicleData = [
        'license_plate' => trim($input['license_plate'] ?? '') ?: null,
        'brand' => $brand,
        'model' => $model,
        'year' => $year,
        'engine_type' => resolveStoredEngineType($input),
        'engine_size' => trim($input['engine_size'] ?? '') ?: null,
        'transmission' => trim($input['transmission'] ?? '') ?: null
    ];
    
    $vehicleId = MockDatabase::saveVehicle($vehicleData);
    $_SESSION['vehicle_id'] = $vehicleId;
    
    sendResponse([
        'success' => true,
        'message' => 'Véhicule enregistré avec succès (mode mock)',
        'vehicle' => [
            'id' => $vehicleId,
            'license_plate' => $vehicleData['license_plate'],
            'brand' => $brand,
            'model' => $model,
            'year' => $year,
            'engine_type' => $vehicleData['engine_type'],
            'engine_size' => $vehicleData['engine_size'],
            'transmission' => $vehicleData['transmission'],
            'display_name' => sprintf('%s %s (%d)', $brand, $model, $year)
        ]
    ], 201);
}

/**
 * [MOCK] Recherche par plaque (simulée)
 */
function handleLookupPlateMock(): void {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $plate = strtoupper(trim($input['license_plate'] ?? ''));
    
    if (empty($plate)) {
        sendError('La plaque d\'immatriculation est requise');
    }
    
    $plateClean = preg_replace('/[^A-Z0-9]/', '', $plate);
    
    // Données mockées basées sur la plaque
    $mockVehicles = [
        'AB123CD' => ['brand' => 'Renault', 'model' => 'Clio', 'year' => 2019, 'engine_type' => 'Essence', 'engine_size' => '1.0L'],
        'EF456GH' => ['brand' => 'Peugeot', 'model' => '308', 'year' => 2020, 'engine_type' => 'Diesel', 'engine_size' => '1.5L'],
        'IJ789KL' => ['brand' => 'Volkswagen', 'model' => 'Golf', 'year' => 2021, 'engine_type' => 'Essence', 'engine_size' => '1.4L'],
        'MN012OP' => ['brand' => 'Citroën', 'model' => 'C3', 'year' => 2018, 'engine_type' => 'Essence', 'engine_size' => '1.2L'],
        'QR345ST' => ['brand' => 'Toyota', 'model' => 'Yaris', 'year' => 2022, 'engine_type' => 'Hybride', 'engine_size' => '1.5L'],
    ];
    
    if (isset($mockVehicles[$plateClean])) {
        $vehicleData = $mockVehicles[$plateClean];
    } else {
        // Génère des données aléatoires basées sur la plaque
        $brands = ['Renault', 'Peugeot', 'Citroën', 'Volkswagen', 'Toyota', 'Ford', 'Dacia'];
        $models = [
            'Renault' => ['Clio', 'Mégane', 'Captur'],
            'Peugeot' => ['208', '308', '3008'],
            'Citroën' => ['C3', 'C4', 'C5 Aircross'],
            'Volkswagen' => ['Golf', 'Polo', 'Tiguan'],
            'Toyota' => ['Yaris', 'Corolla', 'RAV4'],
            'Ford' => ['Fiesta', 'Focus', 'Puma'],
            'Dacia' => ['Sandero', 'Duster', 'Logan']
        ];
        
        $brand = $brands[crc32($plateClean) % count($brands)];
        $model = $models[$brand][crc32($plateClean . 'model') % count($models[$brand])];
        $year = 2015 + (crc32($plateClean . 'year') % 10);
        $engineTypes = ['Essence', 'Diesel', 'Hybride'];
        $engineType = $engineTypes[crc32($plateClean . 'engine') % count($engineTypes)];
        $engineSizes = ['1.0L', '1.2L', '1.4L', '1.5L', '1.6L', '2.0L'];
        $engineSize = $engineSizes[crc32($plateClean . 'size') % count($engineSizes)];
        
        $vehicleData = [
            'brand' => $brand,
            'model' => $model,
            'year' => $year,
            'engine_type' => $engineType,
            'engine_size' => $engineSize
        ];
    }
    
    sendResponse([
        'success' => true,
        'found' => true,
        'license_plate' => $plate,
        'vehicle' => $vehicleData,
        'message' => 'Véhicule trouvé (données simulées - mode mock)'
    ]);
}

// ============================================
// HANDLERS MODE NORMAL (MySQL)
// ============================================

/**
 * Récupère le véhicule actif du slot demandé (défaut : slot 1).
 */
function handleGetCurrent(PDO $db): void {
    $slot = (int) ($_GET['slot'] ?? 1);
    if ($slot < 1 || $slot > 3) {
        sendError('slot invalide (valeurs: 1, 2, 3)');
    }

    $scope = vehicle_scope_owner_sql('v');
    $stmt = $db->prepare(
        "SELECT v.*, COALESCE(vb.category, 'car') AS category
         FROM vehicles v
         LEFT JOIN vehicle_brands vb ON LOWER(vb.name) = LOWER(v.brand)
         WHERE {$scope['sql']} AND v.slot = ? AND v.is_active = 1
         LIMIT 1"
    );
    $stmt->execute(array_merge($scope['params'], [$slot]));
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        sendResponse([
            'success' => true,
            'has_vehicle' => false,
            'vehicle' => null,
            'slot' => $slot,
        ]);
    }

    $formatted = formatGarageVehicle($vehicle);
    $formatted['license_plate'] = $vehicle['license_plate'] ?? null;
    $_SESSION['vehicle_id'] = (int) $vehicle['id'];

    sendResponse([
        'success' => true,
        'has_vehicle' => true,
        'slot' => $slot,
        'vehicle' => $formatted,
    ]);
}

/**
 * Liste le garage de la session (jusqu'à 12 véhicules, 3 slots actifs).
 */
function handleGarage(PDO $db): void {
    $garage = buildGaragePayload($db);

    sendResponse([
        'success' => true,
        'vehicles' => $garage['vehicles'],
        'active_count' => $garage['active_count'],
        'total' => $garage['total'],
        'slots' => $garage['slots'],
    ]);
}

/**
 * Active ou désactive un véhicule sur un slot (1–3).
 */
function handleSetActive(PDO $db): void {
    $input = parseJsonBody();
    $vehicleId = (int) ($input['vehicle_id'] ?? 0);
    $slot = (int) ($input['slot'] ?? 0);
    $active = filter_var($input['active'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($vehicleId <= 0) {
        sendError('vehicle_id est requis');
    }

    $scope = vehicle_scope_owner_sql();
    $vehicle = vehicle_scope_assert_owns($db, $vehicleId);

    if (!$active) {
        $db->prepare("UPDATE vehicles SET is_active = 0, slot = NULL WHERE id = ? AND {$scope['sql']}")
            ->execute(array_merge([$vehicleId], $scope['params']));

        if (isset($_SESSION['vehicle_id']) && (int) $_SESSION['vehicle_id'] === $vehicleId) {
            unset($_SESSION['vehicle_id']);
        }

        $garage = buildGaragePayload($db);
        sendResponse([
            'success' => true,
            'message' => 'Véhicule retiré des slots actifs',
            'garage' => $garage,
        ]);
    }

    if ($slot < 1 || $slot > 3) {
        sendError('slot invalide (valeurs: 1, 2, 3)');
    }

    $wasActive = (int) ($vehicle['is_active'] ?? 0) === 1;
    if (!$wasActive) {
        $activeCount = $db->prepare("SELECT COUNT(*) FROM vehicles WHERE {$scope['sql']} AND is_active = 1");
        $activeCount->execute($scope['params']);
        if ((int) $activeCount->fetchColumn() >= 3) {
            sendError('3 véhicules actifs maximum', 400);
        }
    }

    $currentSlot = isset($vehicle['slot']) && $vehicle['slot'] !== null && $vehicle['slot'] !== ''
        ? (int) $vehicle['slot']
        : null;
    if ($wasActive && $currentSlot !== null && $currentSlot !== $slot) {
        $db->prepare("UPDATE vehicles SET is_active = 0, slot = NULL WHERE id = ? AND {$scope['sql']}")
            ->execute(array_merge([$vehicleId], $scope['params']));
    }

    $occupant = $db->prepare(
        "SELECT id FROM vehicles WHERE {$scope['sql']} AND slot = ? AND is_active = 1 AND id != ? LIMIT 1"
    );
    $occupant->execute(array_merge($scope['params'], [$slot, $vehicleId]));
    $otherId = $occupant->fetchColumn();
    if ($otherId !== false) {
        $db->prepare("UPDATE vehicles SET is_active = 0, slot = NULL WHERE id = ? AND {$scope['sql']}")
            ->execute(array_merge([(int) $otherId], $scope['params']));
        if (isset($_SESSION['vehicle_id']) && (int) $_SESSION['vehicle_id'] === (int) $otherId) {
            unset($_SESSION['vehicle_id']);
        }
    }

    $db->prepare("UPDATE vehicles SET is_active = 1, slot = ? WHERE id = ? AND {$scope['sql']}")
        ->execute(array_merge([$slot, $vehicleId], $scope['params']));

    if ($slot === 1) {
        $_SESSION['vehicle_id'] = $vehicleId;
    }

    $garage = buildGaragePayload($db);
    sendResponse([
        'success' => true,
        'message' => 'Véhicule activé sur le slot ' . $slot,
        'garage' => $garage,
    ]);
}

/**
 * Définit le véhicule de travail courant (session uniquement, sans modifier les slots).
 */
function handleSetCurrent(PDO $db): void {
    $input = parseJsonBody();
    $vehicleId = (int) ($input['vehicle_id'] ?? 0);

    if ($vehicleId <= 0) {
        sendError('vehicle_id est requis');
    }

    $row = vehicle_scope_assert_owns($db, $vehicleId);

    $_SESSION['vehicle_id'] = $vehicleId;

    sendResponse([
        'success' => true,
        'vehicle_id' => $vehicleId,
        'vehicle' => formatGarageVehicle($row),
    ]);
}

/**
 * [MOCK] Véhicule de travail courant en session.
 */
function handleSetCurrentMock(): void {
    $input = parseJsonBody();
    $vehicleId = (int) ($input['vehicle_id'] ?? 0);

    if ($vehicleId <= 0) {
        sendError('vehicle_id est requis');
    }

    $vehicle = MockDatabase::getVehicle($vehicleId);
    if (!$vehicle) {
        sendError('Véhicule introuvable', 404);
    }

    $_SESSION['vehicle_id'] = $vehicleId;

    sendResponse([
        'success' => true,
        'vehicle_id' => $vehicleId,
        'vehicle' => [
            'id' => $vehicleId,
            'brand' => $vehicle['brand'],
            'model' => $vehicle['model'],
            'year' => (int) $vehicle['year'],
            'engine_type' => $vehicle['engine_type'] ?? null,
            'engine_size' => $vehicle['engine_size'] ?? null,
            'transmission' => $vehicle['transmission'] ?? null,
            'is_active' => 0,
            'slot' => null,
            'category' => 'car',
            'display_name' => sprintf('%s %s (%d)', $vehicle['brand'], $vehicle['model'], (int) $vehicle['year']),
        ],
    ]);
}

/**
 * Supprime un véhicule du garage de la session.
 */
function handleDeleteVehicle(PDO $db): void {
    $input = parseJsonBody();
    $vehicleId = (int) ($input['vehicle_id'] ?? 0);

    if ($vehicleId <= 0) {
        sendError('vehicle_id est requis');
    }

    vehicle_scope_assert_owns($db, $vehicleId);
    $scope = vehicle_scope_owner_sql();
    $stmt = $db->prepare("DELETE FROM vehicles WHERE id = ? AND {$scope['sql']}");
    $stmt->execute(array_merge([$vehicleId], $scope['params']));

    if ($stmt->rowCount() === 0) {
        sendError('Véhicule introuvable', 404);
    }

    if (isset($_SESSION['vehicle_id']) && (int) $_SESSION['vehicle_id'] === $vehicleId) {
        unset($_SESSION['vehicle_id']);
    }

    $garage = buildGaragePayload($db);
    sendResponse([
        'success' => true,
        'message' => 'Véhicule supprimé',
        'garage' => $garage,
    ]);
}

/**
 * Récupère la liste des marques disponibles
 */
function handleGetBrands(PDO $db): void {
    $category = parseBrandCategoryParam();
    $sql = "
        SELECT id, name, country, category
        FROM vehicle_brands
        WHERE is_active = 1
    ";
    $params = [];
    if ($category !== null) {
        $sql .= ' AND category = ?';
        $params[] = $category;
    }
    $sql .= ' ORDER BY name ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $brands = $stmt->fetchAll();
    
    sendResponse([
        'success' => true,
        'brands' => $brands
    ]);
}

/**
 * Motorisations de référence pour un modèle.
 */
function handleGetEngines(PDO $db): void {
    $modelId = (int) ($_GET['model_id'] ?? 0);
    if ($modelId <= 0) {
        sendError('model_id est requis');
    }

    $stmt = $db->prepare("
        SELECT id, label, fuel_type, displacement, power_hp
        FROM engine_types
        WHERE model_id = ?
        ORDER BY fuel_type, power_hp
    ");
    $stmt->execute([$modelId]);
    $engines = $stmt->fetchAll();

    sendResponse([
        'success' => true,
        'engines' => $engines ?: [],
    ]);
}

/**
 * Récupère la liste des modèles pour une marque donnée
 */
function handleGetModels(PDO $db): void {
    $brandId = $_GET['brand_id'] ?? null;
    
    if (!$brandId) {
        sendError('brand_id est requis');
    }
    
    $stmt = $db->prepare("
        SELECT id, name, year_start, year_end 
        FROM vehicle_models 
        WHERE brand_id = ? AND is_active = TRUE 
        ORDER BY name ASC
    ");
    $stmt->execute([$brandId]);
    $models = $stmt->fetchAll();
    
    sendResponse([
        'success' => true,
        'models' => $models
    ]);
}

/**
 * Enregistre un nouveau véhicule en BDD et en session
 */
function handleSaveVehicle(PDO $db): void {
    $input = parseJsonBody();
    $sessionId = session_id();
    $scope = vehicle_scope_owner_sql();
    $demoUserId = vehicle_scope_demo_user_id();

    $count = $db->prepare("SELECT COUNT(*) FROM vehicles WHERE {$scope['sql']}");
    $count->execute($scope['params']);
    if ((int) $count->fetchColumn() >= 12) {
        sendError('Garage plein (12 véhicules maximum)', 400);
    }

    $brand = trim($input['brand'] ?? '');
    $model = trim($input['model'] ?? '');
    $year = (int) ($input['year'] ?? 0);
    
    if (empty($brand)) {
        sendError('La marque est requise');
    }
    if (empty($model)) {
        sendError('Le modèle est requis');
    }
    if ($year < 1900 || $year > (int) date('Y') + 1) {
        sendError('L\'année doit être comprise entre 1900 et ' . (date('Y') + 1));
    }
    
    $licensePlate = trim($input['license_plate'] ?? '') ?: null;
    $engineType = resolveStoredEngineType($input);
    $engineSize = trim($input['engine_size'] ?? '') ?: null;
    $transmission = trim($input['transmission'] ?? '') ?: null;
    
    if ($demoUserId !== null && $demoUserId > 0) {
        $stmt = $db->prepare("
            INSERT INTO vehicles (
                license_plate, brand, model, year, engine_type, engine_size, transmission,
                session_id, is_active, slot, demo_user_id, is_demo_seed
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, ?, 0)
        ");
        $stmt->execute([
            $licensePlate,
            $brand,
            $model,
            $year,
            $engineType,
            $engineSize,
            $transmission,
            $sessionId,
            $demoUserId,
        ]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO vehicles (
                license_plate, brand, model, year, engine_type, engine_size, transmission,
                session_id, is_active, slot, demo_user_id, is_demo_seed
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NULL, 0)
        ");
        $stmt->execute([
            $licensePlate,
            $brand,
            $model,
            $year,
            $engineType,
            $engineSize,
            $transmission,
            $sessionId,
        ]);
    }
    
    $vehicleId = $db->lastInsertId();
    $_SESSION['vehicle_id'] = $vehicleId;
    
    sendResponse([
        'success' => true,
        'message' => 'Véhicule enregistré avec succès',
        'vehicle' => [
            'id' => (int) $vehicleId,
            'license_plate' => $licensePlate,
            'brand' => $brand,
            'model' => $model,
            'year' => $year,
            'engine_type' => $engineType,
            'engine_size' => $engineSize,
            'transmission' => $transmission,
            'display_name' => sprintf('%s %s (%d)', $brand, $model, $year)
        ]
    ], 201);
}

/**
 * Simule une recherche de véhicule par plaque d'immatriculation
 */
function handleLookupPlate(PDO $db): void {
    if (!isPlateApiEnabled()) {
        handleLookupPlateMock();
        return;
    }

    if (!defined('PLATE_LOOKUP_LOADED')) {
        define('PLATE_LOOKUP_LOADED', true);
        require_once __DIR__ . '/../includes/plate_lookup.php';
    }
    if (!defined('LLM_BRIDGE_LOADED')) {
        define('LLM_BRIDGE_LOADED', true);
        require_once __DIR__ . '/../includes/llm_bridge.php';
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $plate = strtoupper(trim($input['license_plate'] ?? ''));
    if (empty($plate)) {
        sendError('La plaque d\'immatriculation est requise');
    }

    $plateLookupConfig = getEffectiveSettings()['plate_lookup'] ?? [];
    if (!is_array($plateLookupConfig)) {
        $plateLookupConfig = [];
    }

    $result = lookupPlate($plate, $plateLookupConfig);

    $src = null;
    $dataSource = null;
    $message = '';

    if ($result['found'] === true) {
        $src = $result;
        $dataSource = 'plate_api';
        $message = 'Véhicule trouvé';
    } else {
        $settings = getEffectiveSettings();
        if (
            $result['found'] === false
            && ($settings['llm_fallback_enabled'] ?? false) === true
            && getEffectiveLlmProvider() !== null
        ) {
            if (!function_exists('getEffectiveLlmProvider')) {
                require_once __DIR__ . '/../includes/byok.php';
            }
            $provider = getEffectiveLlmProvider();
            $snippets = searchWebForVehicle($plate);
            $llmResult = queryLlmForVehicle($plate, $snippets, $provider);
            if (($llmResult['found'] ?? false) === true) {
                $src = $llmResult;
                $dataSource = 'llm_inference';
                $message = 'Véhicule identifié (inférence LLM)';
            }
        }

        if ($src === null) {
            $_POST['license_plate'] = $input['license_plate'] ?? $plate;
            handleLookupPlateMock();
            return;
        }
    }

    $year = (int) ($src['year'] ?? 0);
    if ($year < 1900 || $year > 2100) {
        $year = (int) date('Y');
    }

    $vehicleData = [
        'brand' => (string) ($src['brand'] ?? ''),
        'model' => (string) ($src['model'] ?? ''),
        'year' => $year,
        'engine_type' => (isset($src['fuel']) && $src['fuel'] !== null && $src['fuel'] !== '')
            ? (string) $src['fuel']
            : 'Non spécifié',
        'engine_size' => (isset($src['engine']) && $src['engine'] !== null && $src['engine'] !== '')
            ? (string) $src['engine']
            : 'Non spécifié',
    ];

    sendResponse([
        'success' => true,
        'found' => true,
        'license_plate' => $plate,
        'vehicle' => $vehicleData,
        'message' => $message,
        'data_source' => $dataSource,
    ]);
}

/**
 * Supprime le véhicule de la session
 */
function handleClearVehicle(): void {
    unset($_SESSION['vehicle_id']);
    
    sendResponse([
        'success' => true,
        'message' => 'Véhicule supprimé de la session'
    ]);
}
