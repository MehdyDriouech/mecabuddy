<?php
/**
 * MecaBuddy — Paramètres par compte démo (clés API personnelles)
 */

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function demo_user_settings_defaults(): array
{
    return [
        'serper_api_key' => '',
        'plate_lookup' => [
            'enabled' => false,
            'api_key' => '',
        ],
        'llm_provider' => [
            'api_key' => '',
            'base_url' => '',
            'model' => '',
        ],
    ];
}

function migrateSQLiteDemoUserSettings(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='demo_users'"
    );
    if (!$stmt || $stmt->fetchColumn() === false) {
        return;
    }

    $hasColumn = false;
    foreach ($pdo->query('PRAGMA table_info(demo_users)') as $col) {
        if (($col['name'] ?? '') === 'settings_json') {
            $hasColumn = true;
            break;
        }
    }

    if (!$hasColumn) {
        $pdo->exec('ALTER TABLE demo_users ADD COLUMN settings_json TEXT DEFAULT NULL');
    }
}

/**
 * @return array<string, mixed>
 */
function demo_user_get_stored_settings(int $userId): array
{
    if ($userId <= 0 || !function_exists('demo_auth_pdo')) {
        return demo_user_settings_defaults();
    }

    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return demo_user_settings_defaults();
    }

    migrateSQLiteDemoUserSettings($pdo);

    $stmt = $pdo->prepare('SELECT settings_json FROM demo_users WHERE id = ? AND is_active = 1');
    $stmt->execute([$userId]);
    $raw = $stmt->fetchColumn();
    if ($raw === false || $raw === null || trim((string) $raw) === '') {
        return demo_user_settings_defaults();
    }

    $decoded = json_decode((string) $raw, true);

    return is_array($decoded)
        ? array_replace_recursive(demo_user_settings_defaults(), $decoded)
        : demo_user_settings_defaults();
}

/**
 * @param array<string, mixed> $baseSettings
 * @return array<string, mixed>
 */
function demo_user_apply_overrides(array $baseSettings, int $userId): array
{
    $user = demo_user_get_stored_settings($userId);
    $out = $baseSettings;

    $serper = trim((string) ($user['serper_api_key'] ?? ''));
    if ($serper !== '') {
        $out['serper_api_key'] = $serper;
    }

    $pl = $user['plate_lookup'] ?? [];
    if (is_array($pl)) {
        if (!isset($out['plate_lookup']) || !is_array($out['plate_lookup'])) {
            $out['plate_lookup'] = [];
        }
        if (array_key_exists('enabled', $pl)) {
            $out['plate_lookup']['enabled'] = ($pl['enabled'] ?? false) === true;
        }
        $plateKey = trim((string) ($pl['api_key'] ?? ''));
        if ($plateKey !== '') {
            $out['plate_lookup']['api_key'] = $plateKey;
        }
    }

    return $out;
}

/**
 * État public pour l’UI (sans exposer les secrets).
 *
 * @return array<string, mixed>
 */
function demo_user_settings_public_view(int $userId): array
{
    $stored = demo_user_get_stored_settings($userId);
    $active = function_exists('getActiveLlmProvider') ? getActiveLlmProvider() : null;
    if ($active === null) {
        $global = getSettings();
        foreach ($global['llm_providers'] ?? [] as $p) {
            if (is_array($p) && ($p['active'] ?? false) === true) {
                $active = $p;
                break;
            }
        }
    }

    $llmStored = is_array($stored['llm_provider'] ?? null) ? $stored['llm_provider'] : [];

    return [
        'serper_configured' => trim((string) ($stored['serper_api_key'] ?? '')) !== '',
        'plate_lookup' => [
            'enabled' => ($stored['plate_lookup']['enabled'] ?? false) === true,
            'api_key_configured' => trim((string) ($stored['plate_lookup']['api_key'] ?? '')) !== '',
        ],
        'llm' => [
            'name' => (string) ($active['name'] ?? '—'),
            'type' => (string) ($active['type'] ?? ''),
            'model' => (string) ($llmStored['model'] ?? $active['model'] ?? ''),
            'base_url' => (string) ($llmStored['base_url'] ?? $active['base_url'] ?? ''),
            'api_key_configured' => trim((string) ($llmStored['api_key'] ?? '')) !== '',
        ],
    ];
}

/**
 * @param array<string, mixed> $input
 */
function demo_user_settings_save(int $userId, array $input): bool
{
    if ($userId <= 0) {
        return false;
    }

    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return false;
    }

    migrateSQLiteDemoUserSettings($pdo);

    $current = demo_user_get_stored_settings($userId);

    if (array_key_exists('serper_api_key', $input)) {
        $val = trim((string) $input['serper_api_key']);
        if ($val !== '') {
            $current['serper_api_key'] = substr($val, 0, 256);
        }
    }

    if (isset($input['plate_lookup']) && is_array($input['plate_lookup'])) {
        $plIn = $input['plate_lookup'];
        if (array_key_exists('enabled', $plIn)) {
            $current['plate_lookup']['enabled'] = ($plIn['enabled'] ?? false) === true;
        }
        if (array_key_exists('api_key', $plIn)) {
            $pk = trim((string) $plIn['api_key']);
            if ($pk !== '') {
                $current['plate_lookup']['api_key'] = substr($pk, 0, 512);
            }
        }
    }

    if (isset($input['llm_provider']) && is_array($input['llm_provider'])) {
        $llmIn = $input['llm_provider'];
        foreach (['api_key', 'base_url', 'model'] as $field) {
            if (!array_key_exists($field, $llmIn)) {
                continue;
            }
            $val = trim((string) $llmIn[$field]);
            if ($val !== '') {
                $max = $field === 'api_key' ? 512 : 256;
                $current['llm_provider'][$field] = substr($val, 0, $max);
            }
        }
    }

    $json = json_encode($current, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $stmt = $pdo->prepare(
        "UPDATE demo_users SET settings_json = ?, updated_at = datetime('now') WHERE id = ? AND is_active = 1"
    );

    return $stmt->execute([$json, $userId]);
}
