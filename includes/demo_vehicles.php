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

function migrateSQLiteDemoVehicles(PDO $pdo): void
{
    $columns = [];
    foreach ($pdo->query('PRAGMA table_info(vehicles)') as $col) {
        $columns[$col['name'] ?? ''] = true;
    }

    if (!isset($columns['demo_user_id'])) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN demo_user_id INTEGER NULL');
    }
    if (!isset($columns['is_demo_seed'])) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN is_demo_seed INTEGER NOT NULL DEFAULT 0');
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vehicles_demo_user_id ON vehicles(demo_user_id)');
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
