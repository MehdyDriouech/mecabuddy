<?php
/**
 * MecaBuddy — Périmètre garage (session anonyme vs compte démo)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/settings.php';

/**
 * ID compte démo connecté, ou null si mode session classique.
 */
function vehicle_scope_demo_user_id(): ?int
{
    if (!function_exists('isDemoAuthEnabled') || !isDemoAuthEnabled()) {
        return null;
    }
    if (!function_exists('getCurrentDemoUser')) {
        require_once __DIR__ . '/demo_auth.php';
    }
    $user = getCurrentDemoUser();

    return $user !== null ? (int) $user['id'] : null;
}

/**
 * @return array{sql: string, params: list<mixed>, mode: string, demo_user_id: int|null}
 */
function vehicle_scope_owner_sql(string $alias = ''): array
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    $demoUserId = vehicle_scope_demo_user_id();

    if ($demoUserId !== null && $demoUserId > 0) {
        return [
            'sql' => "{$prefix}demo_user_id = ?",
            'params' => [$demoUserId],
            'mode' => 'demo',
            'demo_user_id' => $demoUserId,
        ];
    }

    return [
        'sql' => "{$prefix}session_id = ? AND {$prefix}demo_user_id IS NULL",
        'params' => [session_id()],
        'mode' => 'session',
        'demo_user_id' => null,
    ];
}

/**
 * @return array<string, mixed>|null
 */
function vehicle_scope_fetch_by_id(PDO $db, int $vehicleId): ?array
{
    $scope = vehicle_scope_owner_sql('v');
    $stmt = $db->prepare(
        "SELECT v.*, COALESCE(vb.category, 'car') AS category
         FROM vehicles v
         LEFT JOIN vehicle_brands vb ON LOWER(vb.name) = LOWER(v.brand)
         WHERE v.id = ? AND {$scope['sql']}"
    );
    $stmt->execute(array_merge([$vehicleId], $scope['params']));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

/**
 * @return array<string, mixed>
 */
function vehicle_scope_assert_owns(PDO $db, int $vehicleId): array
{
    $row = vehicle_scope_fetch_by_id($db, $vehicleId);
    if ($row === null) {
        if (!function_exists('sendError')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'vehicle_forbidden',
                'message' => 'Véhicule introuvable ou accès refusé.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        sendError('Véhicule introuvable ou accès refusé', 403);
    }

    return $row;
}

/**
 * @param array<string, mixed> $vehicle
 */
function vehicle_scope_format_garage_vehicle(array $vehicle): array
{
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
function vehicle_scope_build_garage_payload(PDO $db): array
{
    $scope = vehicle_scope_owner_sql('v');
    $stmt = $db->prepare(
        "SELECT v.id, v.brand, v.model, v.year, v.engine_type, v.engine_size, v.transmission,
                v.is_active, v.slot, v.created_at,
                COALESCE(vb.category, 'car') AS category
         FROM vehicles v
         LEFT JOIN vehicle_brands vb ON LOWER(vb.name) = LOWER(v.brand)
         WHERE {$scope['sql']}
         ORDER BY v.slot IS NULL, v.slot ASC, v.created_at DESC"
    );
    $stmt->execute($scope['params']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $vehicles = [];
    $slots = [1 => null, 2 => null, 3 => null];
    $activeCount = 0;

    foreach ($rows as $row) {
        $formatted = vehicle_scope_format_garage_vehicle($row);
        $vehicles[] = $formatted;
        if ((int) ($row['is_active'] ?? 0) === 1) {
            $activeCount++;
            $slotNum = isset($row['slot']) ? (int) $row['slot'] : 0;
            if ($slotNum >= 1 && $slotNum <= 3) {
                $slots[$slotNum] = $formatted;
            }
        }
    }

    return [
        'vehicles' => $vehicles,
        'active_count' => $activeCount,
        'total' => count($vehicles),
        'slots' => $slots,
    ];
}

/**
 * @return array{active_vehicle_id: int|null, vehicles_count: int, active_slots_count: int}
 */
function vehicle_scope_garage_summary(PDO $db): array
{
    $garage = vehicle_scope_build_garage_payload($db);
    $activeVehicleId = isset($_SESSION['vehicle_id']) ? (int) $_SESSION['vehicle_id'] : null;

    return [
        'active_vehicle_id' => $activeVehicleId > 0 ? $activeVehicleId : null,
        'vehicles_count' => $garage['total'],
        'active_slots_count' => $garage['active_count'],
    ];
}
