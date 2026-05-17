<?php
/**
 * MecaBuddy — Contexte véhicule courant (session + BDD)
 */

if (!function_exists('getCurrentVehicleContext')) {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/vehicle_scope.php';

    /**
     * @return array{
     *   brand: string|null,
     *   model: string|null,
     *   year: int|string|null,
     *   engine_type: string|null,
     *   engine_size: string|null,
     *   transmission: string|null,
     *   category: string|null
     * }|null
     */
    function getCurrentVehicleContext(int $_slot = 1): ?array
    {

        if (!isset($_SESSION['vehicle_id'])) {
            return null;
        }

        $id = (int) $_SESSION['vehicle_id'];
        if ($id <= 0) {
            return null;
        }

        try {
            $db = getDB();
            $row = vehicle_scope_fetch_by_id($db, $id);
            if ($row === null) {
                return null;
            }

            return [
                'brand' => $row['brand'],
                'model' => $row['model'],
                'year' => $row['year'],
                'engine_type' => $row['engine_type'],
                'engine_size' => $row['engine_size'],
                'transmission' => $row['transmission'],
                'category' => $row['category'] ?? 'car',
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Véhicules actifs (slots 1–3) pour l’aperçu accueil / garage.
     *
     * @return list<array{
     *   id: int,
     *   brand: string,
     *   model: string,
     *   year: int|string,
     *   slot: int|null,
     *   category: string,
     *   engine_type: string|null,
     *   engine_size: string|null,
     *   transmission: string|null
     * }>
     */
    function getActiveVehiclesForSession(): array
    {
        try {
            $db = getDB();
            $scope = vehicle_scope_owner_sql('v');
            $stmt = $db->prepare(
                "SELECT v.id, v.brand, v.model, v.year, v.slot,
                        v.engine_type, v.engine_size, v.transmission,
                        COALESCE(vb.category, 'car') AS category
                 FROM vehicles v
                 LEFT JOIN vehicle_brands vb ON LOWER(vb.name) = LOWER(v.brand)
                 WHERE {$scope['sql']} AND v.is_active = 1
                 ORDER BY v.slot ASC
                 LIMIT 3"
            );
            $stmt->execute($scope['params']);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    function vehicle_format_transmission_label(?string $transmission): ?string
    {
        if ($transmission === null || trim($transmission) === '') {
            return null;
        }
        $key = strtolower(trim($transmission));

        return match ($key) {
            'manuelle', 'manual', 'm' => 'Manuelle',
            'automatique', 'automatic', 'a' => 'Automatique',
            default => trim($transmission),
        };
    }

    function vehicle_extract_energy_label(?string $engineType): ?string
    {
        if ($engineType === null || trim($engineType) === '') {
            return null;
        }
        $t = mb_strtolower(trim($engineType));
        $fuels = [
            'électrique' => 'Électrique',
            'electrique' => 'Électrique',
            'hybride' => 'Hybride',
            'diesel' => 'Diesel',
            'essence' => 'Essence',
            'gpl' => 'GPL',
        ];
        foreach ($fuels as $needle => $label) {
            if (str_contains($t, $needle)) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Libellé moteur pour affichage (cylindrée + motorisation, sans redondance carburant).
     */
    function vehicle_format_engine_label(?string $engineType, ?string $engineSize): ?string
    {
        $type = trim((string) ($engineType ?? ''));
        $size = trim((string) ($engineSize ?? ''));
        $parts = [];

        if ($size !== '') {
            $parts[] = $size;
        }
        if ($type !== '') {
            $energy = vehicle_extract_energy_label($type);
            $motor = $type;
            if ($energy !== null) {
                $motor = trim(preg_replace('/\b' . preg_quote($energy, '/') . '\b/ui', '', $type));
                $motor = trim(preg_replace('/\s{2,}/', ' ', $motor));
            }
            if ($motor !== '' && !in_array($motor, $parts, true)) {
                $parts[] = $motor;
            } elseif ($motor === '' && $type !== '' && $energy === null) {
                $parts[] = $type;
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode(' · ', $parts);
    }

    function buildVehiclePromptFragment(?array $vehicle): string
    {
        if ($vehicle === null) {
            return '';
        }

        $brand = trim((string) ($vehicle['brand'] ?? ''));
        $model = trim((string) ($vehicle['model'] ?? ''));
        $year = trim((string) ($vehicle['year'] ?? ''));
        if ($brand === '' && $model === '' && $year === '') {
            return '';
        }

        $parts = [
            "Véhicule de l'utilisateur : {$brand} {$model} {$year}",
        ];

        $engineType = trim((string) ($vehicle['engine_type'] ?? ''));
        if ($engineType !== '') {
            $parts[] = "Motorisation : {$engineType}";
        }

        $engineSize = trim((string) ($vehicle['engine_size'] ?? ''));
        if ($engineSize !== '' && !str_contains($engineType, $engineSize)) {
            $parts[] = "Cylindrée : {$engineSize}";
        }

        $transmission = trim((string) ($vehicle['transmission'] ?? ''));
        if ($transmission !== '') {
            $transKey = strtolower($transmission);
            $parts[] = match ($transKey) {
                'manuelle', 'manual', 'm' => 'Boîte manuelle',
                'automatique', 'automatic', 'a' => 'Boîte automatique',
                default => "Transmission : {$transmission}",
            };
        }

        $parts[] = 'Tiens compte de toutes ces informations dans ta réponse.';

        return implode("\n", $parts);
    }
}
