<?php
/**
 * MecaBuddy — Véhicules préconfigurés pour les comptes démo
 */

declare(strict_types=1);

require_once __DIR__ . '/demo_auth.php';
require_once __DIR__ . '/vehicle_scope.php';

/** @var array<int, array<string, mixed>> */
const DEMO_VEHICLE_SLOT_TEMPLATES = [
    1 => [
        'brand' => 'Skoda',
        'model' => 'Scala',
        'year' => 2023,
        'engine_type' => 'essence',
        'engine_size' => '1.5 TSI',
        'transmission' => 'automatique',
        'catalog_category' => 'car',
    ],
    2 => [
        'brand' => 'Renault',
        'model' => 'Twingo',
        'year' => 2010,
        'engine_type' => 'essence',
        'engine_size' => '1.2',
        'transmission' => 'manuelle',
        'catalog_category' => 'car',
    ],
    3 => [
        'brand' => 'KTM',
        'model' => 'Super Duke R 1290',
        'year' => 2023,
        'engine_type' => 'essence',
        'engine_size' => '1290',
        'transmission' => 'manuelle',
        'catalog_category' => 'moto',
    ],
];

/**
 * @return list<string>
 */
function vehicleDemoSchemaListColumns(PDO $pdo): array
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $cols = $pdo->query('PRAGMA table_info(vehicles)')->fetchAll(PDO::FETCH_COLUMN, 1);

        return is_array($cols) ? $cols : [];
    }
    if ($driver === 'mysql') {
        $stmt = $pdo->query('SHOW COLUMNS FROM vehicles');
        if ($stmt === false) {
            return [];
        }
        $names = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($row['Field'])) {
                $names[] = (string) $row['Field'];
            }
        }

        return $names;
    }

    return [];
}

function vehicleDemoSchemaIsReady(PDO $pdo): bool
{
    $cols = vehicleDemoSchemaListColumns($pdo);

    return in_array('demo_user_id', $cols, true) && in_array('is_demo_seed', $cols, true);
}

/**
 * Migration garage démo (SQLite + MySQL).
 */
function migrateVehicleDemoSchema(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        migrateSQLiteDemoVehicles($pdo);
    } elseif ($driver === 'mysql') {
        migrateMysqlDemoVehicles($pdo);
    }
}

function migrateMysqlDemoVehicles(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vehicles'"
    );
    if (!$stmt || $stmt->fetchColumn() === false) {
        return;
    }

    $cols = vehicleDemoSchemaListColumns($pdo);
    if (!in_array('demo_user_id', $cols, true)) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN demo_user_id INT NULL');
    }
    if (!in_array('is_demo_seed', $cols, true)) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN is_demo_seed TINYINT(1) NOT NULL DEFAULT 0');
    }
    try {
        $pdo->exec('CREATE INDEX idx_vehicles_demo_user_id ON vehicles (demo_user_id)');
    } catch (PDOException $e) {
        // index déjà présent
    }
}

/**
 * Recrée la table vehicles (SQLite) si ALTER TABLE échoue (fichier verrouillé, schéma corrompu, etc.).
 */
function rebuildSqliteVehiclesTableForDemo(PDO $pdo): void
{
    $targetCols = [
        'id',
        'license_plate',
        'brand',
        'model',
        'year',
        'engine_type',
        'engine_size',
        'transmission',
        'session_id',
        'is_active',
        'slot',
        'demo_user_id',
        'is_demo_seed',
        'created_at',
        'updated_at',
    ];

    $oldNames = vehicleDemoSchemaListColumns($pdo);
    if ($oldNames === []) {
        return;
    }

    $selectExprs = [];
    foreach ($targetCols as $col) {
        if (in_array($col, $oldNames, true)) {
            $selectExprs[] = $col;
            continue;
        }
        $selectExprs[] = match ($col) {
            'demo_user_id' => 'NULL AS demo_user_id',
            'is_demo_seed' => '0 AS is_demo_seed',
            'is_active' => '0 AS is_active',
            'created_at', 'updated_at' => "datetime('now') AS {$col}",
            default => "NULL AS {$col}",
        };
    }

    $pdo->exec('PRAGMA foreign_keys = OFF');
    $pdo->beginTransaction();
    try {
        $pdo->exec("CREATE TABLE vehicles__demo_mig (
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
        )");

        $pdo->exec(
            'INSERT INTO vehicles__demo_mig (' . implode(', ', $targetCols) . ')
             SELECT ' . implode(', ', $selectExprs) . ' FROM vehicles'
        );
        $pdo->exec('DROP TABLE vehicles');
        $pdo->exec('ALTER TABLE vehicles__demo_mig RENAME TO vehicles');

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_license_plate ON vehicles(license_plate)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_brand_model ON vehicles(brand, model)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_session_id ON vehicles(session_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vehicles_demo_user_id ON vehicles(demo_user_id)');

        $pdo->exec('CREATE TRIGGER IF NOT EXISTS trg_vehicles_updated_at
            AFTER UPDATE ON vehicles
            FOR EACH ROW
            WHEN NEW.updated_at = OLD.updated_at
            BEGIN
                UPDATE vehicles SET updated_at = datetime(\'now\') WHERE id = OLD.id;
            END');

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    } finally {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
}

function migrateSQLiteDemoVehicles(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='vehicles'"
    );
    if (!$stmt || $stmt->fetchColumn() === false) {
        return;
    }

    $columns = array_fill_keys(vehicleDemoSchemaListColumns($pdo), true);

    try {
        if (!isset($columns['demo_user_id'])) {
            $pdo->exec('ALTER TABLE vehicles ADD COLUMN demo_user_id INTEGER NULL');
        }
        if (!isset($columns['is_demo_seed'])) {
            $pdo->exec('ALTER TABLE vehicles ADD COLUMN is_demo_seed INTEGER NOT NULL DEFAULT 0');
        }
    } catch (PDOException $e) {
        if (APP_DEBUG) {
            error_log('[MecaBuddy] ALTER vehicles (démo) : ' . $e->getMessage());
        }
    }

    if (!vehicleDemoSchemaIsReady($pdo)) {
        rebuildSqliteVehiclesTableForDemo($pdo);
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vehicles_demo_user_id ON vehicles(demo_user_id)');
}

/**
 * @throws RuntimeException si le schéma garage démo reste incomplet
 */
function assertVehicleDemoSchemaReady(PDO $pdo): void
{
    migrateVehicleDemoSchema($pdo);
    if (vehicleDemoSchemaIsReady($pdo)) {
        return;
    }

    $path = defined('SQLITE_PATH') ? SQLITE_PATH : 'PDO';
    throw new RuntimeException(
        'Schéma garage démo incomplet (colonnes demo_user_id / is_demo_seed). '
        . 'Fichier SQLite : ' . $path
        . ' — vérifiez les droits d’écriture sur data/.'
    );
}

function demo_vehicle_seed_license_plate(int $demoUserId, int $slot): string
{
    return 'DEMO-' . $demoUserId . '-S' . $slot;
}

function ensureDemoVehicleCatalog(PDO $pdo): void
{
    $pdo->exec("INSERT OR IGNORE INTO vehicle_brands (name, country, category) VALUES
        ('Skoda', 'République Tchèque', 'car'),
        ('KTM', 'Autriche', 'moto')");

    $catalog = [
        ['Skoda', 'Scala', 'car'],
        ['Renault', 'Twingo', 'car'],
        ['KTM', 'Super Duke R 1290', 'moto'],
        ['KTM', 'Duke 1290 Super Duke R', 'moto'],
    ];

    $stmt = $pdo->prepare(
        'INSERT OR IGNORE INTO vehicle_models (brand_id, name, year_start, year_end)
         SELECT id, ?, NULL, NULL FROM vehicle_brands WHERE name = ?'
    );

    foreach ($catalog as [$brand, $model]) {
        $stmt->execute([$model, $brand]);
    }
}

/**
 * Crée ou réactive les 3 véhicules démo du compte (idempotent).
 *
 * @return array{
 *   success: bool,
 *   active_vehicle_id: int|null,
 *   vehicles_count: int,
 *   active_slots_count: int,
 *   slot_vehicle_ids: array<int, int>
 * }
 */
function ensureDemoVehiclesForUser(int $demoUserId): array
{
    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return [
            'success' => false,
            'active_vehicle_id' => null,
            'vehicles_count' => 0,
            'active_slots_count' => 0,
            'slot_vehicle_ids' => [],
        ];
    }

    migrateSQLiteDemoVehicles($pdo);
    ensureDemoVehicleCatalog($pdo);

    $sessionId = session_id();
    $slotVehicleIds = [];
    $primaryVehicleId = null;

    $selectByPlate = $pdo->prepare(
        'SELECT id, slot, is_active FROM vehicles WHERE demo_user_id = ? AND license_plate = ? LIMIT 1'
    );
    $updateSeed = $pdo->prepare(
        "UPDATE vehicles SET brand = ?, model = ?, year = ?, engine_type = ?, engine_size = ?,
         transmission = ?, session_id = ?, is_active = 1, slot = ?, is_demo_seed = 1, updated_at = datetime('now')
         WHERE id = ? AND demo_user_id = ?"
    );
    $insert = $pdo->prepare(
        "INSERT INTO vehicles (
            license_plate, brand, model, year, engine_type, engine_size, transmission,
            session_id, is_active, slot, demo_user_id, is_demo_seed
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 1)"
    );

    foreach (DEMO_VEHICLE_SLOT_TEMPLATES as $slot => $tpl) {
        $plate = demo_vehicle_seed_license_plate($demoUserId, $slot);
        $selectByPlate->execute([$demoUserId, $plate]);
        $existing = $selectByPlate->fetch(PDO::FETCH_ASSOC);

        if ($existing !== false) {
            $vehicleId = (int) $existing['id'];
            $updateSeed->execute([
                $tpl['brand'],
                $tpl['model'],
                $tpl['year'],
                $tpl['engine_type'],
                $tpl['engine_size'],
                $tpl['transmission'],
                $sessionId,
                $slot,
                $vehicleId,
                $demoUserId,
            ]);
        } else {
            $insert->execute([
                $plate,
                $tpl['brand'],
                $tpl['model'],
                $tpl['year'],
                $tpl['engine_type'],
                $tpl['engine_size'],
                $tpl['transmission'],
                $sessionId,
                $slot,
                $demoUserId,
            ]);
            $vehicleId = (int) $pdo->lastInsertId();
        }

        $slotVehicleIds[$slot] = $vehicleId;
        if ($slot === 1) {
            $primaryVehicleId = $vehicleId;
        }
    }

    // Désactiver les autres véhicules seed du compte (hors slots 1–3)
    $pdo->prepare(
        "UPDATE vehicles SET is_active = 0, slot = NULL
         WHERE demo_user_id = ? AND is_demo_seed = 1
         AND license_plate NOT IN (?, ?, ?)"
    )->execute([
        $demoUserId,
        demo_vehicle_seed_license_plate($demoUserId, 1),
        demo_vehicle_seed_license_plate($demoUserId, 2),
        demo_vehicle_seed_license_plate($demoUserId, 3),
    ]);

    if ($primaryVehicleId !== null) {
        $_SESSION['vehicle_id'] = $primaryVehicleId;
    }

    $summary = vehicle_scope_garage_summary($pdo);
    $summary['success'] = true;
    $summary['slot_vehicle_ids'] = $slotVehicleIds;

    return $summary;
}

/**
 * Supprime uniquement les véhicules seed démo (tous comptes ou un compte).
 */
function resetDemoSeedVehicles(?int $demoUserId = null): int
{
    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return 0;
    }

    migrateSQLiteDemoVehicles($pdo);

    if ($demoUserId !== null && $demoUserId > 0) {
        $stmt = $pdo->prepare('DELETE FROM vehicles WHERE is_demo_seed = 1 AND demo_user_id = ?');
        $stmt->execute([$demoUserId]);

        return $stmt->rowCount();
    }

    $stmt = $pdo->query('DELETE FROM vehicles WHERE is_demo_seed = 1 AND demo_user_id IS NOT NULL');

    return $stmt ? $stmt->rowCount() : 0;
}

/**
 * Recrée les garages seed pour tous les comptes démo actifs.
 *
 * @return list<array<string, mixed>>
 */
function rebuildAllDemoSeedVehicles(): array
{
    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return [];
    }

    resetDemoSeedVehicles(null);

    $users = $pdo->query('SELECT id FROM demo_users WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($users as $row) {
        $id = (int) $row['id'];
        $out[] = [
            'demo_user_id' => $id,
            'garage' => ensureDemoVehiclesForUser($id),
        ];
    }

    return $out;
}

/**
 * Statut garage par compte démo (admin).
 *
 * @return list<array<string, mixed>>
 */
function demo_vehicles_status_all_accounts(): array
{
    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return [];
    }

    migrateSQLiteDemoVehicles($pdo);

    $users = $pdo->query(
        'SELECT id, username, tutorial_daily_quota, buddy_daily_quota FROM demo_users ORDER BY username'
    )->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($users as $user) {
        $uid = (int) $user['id'];
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM vehicles WHERE demo_user_id = ?');
        $countStmt->execute([$uid]);
        $total = (int) $countStmt->fetchColumn();

        $activeStmt = $pdo->prepare(
            'SELECT id, brand, model, slot FROM vehicles WHERE demo_user_id = ? AND is_active = 1 ORDER BY slot'
        );
        $activeStmt->execute([$uid]);
        $actives = $activeStmt->fetchAll(PDO::FETCH_ASSOC);

        $currentStmt = $pdo->prepare(
            "SELECT id, brand, model, slot FROM vehicles WHERE demo_user_id = ? AND slot = 1 AND is_active = 1 LIMIT 1"
        );
        $currentStmt->execute([$uid]);
        $primary = $currentStmt->fetch(PDO::FETCH_ASSOC);

        $out[] = [
            'demo_user_id' => $uid,
            'username' => $user['username'],
            'vehicles_count' => $total,
            'active_slots' => $actives,
            'primary_vehicle' => $primary !== false ? $primary : null,
        ];
    }

    return $out;
}
