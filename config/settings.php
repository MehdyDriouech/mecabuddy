<?php
/**
 * MecaBuddy — Paramètres persistants (config/settings.json)
 *
 * La table SQLite `settings` n'est plus utilisée par ce module (données métier SQLite inchangées ailleurs).
 *
 * ⚠️ settings.json ne doit PAS être dans public/ ni versionné (voir config/.gitignore).
 * Les api_key des providers y sont stockées en clair — réservé au POC local.
 */

define('PATH_SETTINGS', __DIR__ . '/settings.json');

/**
 * @return array<string, mixed>
 */
function getDefaultSettings(): array
{
    return [
        'plate_lookup' => [
            'enabled' => false,
            'api_key' => '',
            'provider' => 'apiplaqueimmatriculation',
        ],
        'llm_fallback_enabled' => false,
        'demo_mode' => false,
        'demo_auth_enabled' => false,
        'byok_enabled' => true,
        'dev_mode' => false,
        'debug_panel' => false,
        'serper_api_key' => '',
        'llm_providers' => [
            [
                'id' => 'local_gemma',
                'name' => 'Gemma4 local',
                'type' => 'ollama',
                'base_url' => 'http://localhost:11434',
                'model' => 'gemma4:26b',
                'api_key' => '',
                'active' => true,
            ],
        ],
    ];
}

/**
 * @param mixed $default
 * @return mixed
 */
function getSetting(string $key, $default = null)
{
    $settings = getSettings();

    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

/**
 * @return array<string, mixed>
 */
function getSettings(): array
{
    $defaults = getDefaultSettings();

    if (!file_exists(PATH_SETTINGS)) {
        if (!saveSettings($defaults)) {
            return $defaults;
        }

        return $defaults;
    }

    $raw = @file_get_contents(PATH_SETTINGS);
    if ($raw === false || trim($raw) === '') {
        return $defaults;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        error_log('MecaBuddy: settings.json invalide ou corrompu (' . PATH_SETTINGS . ').');

        return $defaults;
    }

    $merged = array_replace_recursive($defaults, $decoded);

    // Alias JSON « dev-mode » (lisible dans le fichier de config)
    if (array_key_exists('dev-mode', $decoded) && !array_key_exists('dev_mode', $decoded)) {
        $merged['dev_mode'] = ($decoded['dev-mode'] ?? false) === true;
    }

    return $merged;
}

/**
 * @param array<string, mixed> $settings
 */
function saveSettings(array $settings): bool
{
    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $written = @file_put_contents(PATH_SETTINGS, $json, LOCK_EX);

    return $written !== false;
}

/**
 * Paramètres effectifs (fichier + surcharge compte démo connecté — Serper / plaque).
 *
 * @return array<string, mixed>
 */
function getEffectiveSettings(): array
{
    $settings = getSettings();

    if (isDemoAuthEnabled()) {
        if (!function_exists('demo_user_apply_overrides')) {
            require_once __DIR__ . '/../includes/demo_user_settings.php';
        }
        if (!function_exists('getCurrentDemoUser')) {
            require_once __DIR__ . '/../includes/demo_auth.php';
        }
        $user = getCurrentDemoUser();
        if ($user !== null && isset($user['id'])) {
            $settings = demo_user_apply_overrides($settings, (int) $user['id']);
        }
    }

    return $settings;
}

function isDevMode(): bool
{
    $settings = getSettings();

    if (($settings['dev_mode'] ?? false) === true) {
        return true;
    }

    return ($settings['dev-mode'] ?? false) === true;
}

function getActiveLlmProvider(): ?array
{
    if (!function_exists('getActiveLlmProviderGlobal')) {
        require_once __DIR__ . '/../includes/byok.php';
    }

    return getActiveLlmProviderGlobal();
}

function isPlateApiEnabled(): bool
{
    $settings = getEffectiveSettings();

    return ($settings['plate_lookup']['enabled'] ?? false) === true;
}

function isDemoMode(): bool
{
    return getSettings()['demo_mode'] === true;
}

function isDemoAuthEnabled(): bool
{
    return getSetting('demo_auth_enabled', false) === true;
}

function isDebugPanelEnabled(): bool
{
    $settings = getSettings();

    return ($settings['debug_panel'] ?? false) === true;
}
