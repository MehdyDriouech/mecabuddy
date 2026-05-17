<?php
/**
 * MecaBuddy — Bring Your Own Key (Mistral / Gemini) par utilisateur démo
 */

declare(strict_types=1);

const BYOK_GEMINI_DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/openai';
const BYOK_GEMINI_DEFAULT_MODEL = 'gemini-2.5-flash-lite';
const BYOK_MISTRAL_DEFAULT_MODEL = 'mistral-small-latest';

function isByokEnabled(): bool
{
    return getSetting('byok_enabled', false) === true;
}

/** Alias explicite pour la revue / appels externes. */
function ensureDemoByokSchema(PDO $pdo): void
{
    migrateSQLiteDemoByok($pdo);
}

function migrateSQLiteDemoByok(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='demo_user_llm_keys'"
    );
    if ($stmt && $stmt->fetchColumn() !== false) {
        return;
    }

    $migrationFile = dirname(__DIR__) . '/sql/migrate_demo_byok.sql';
    if (is_readable($migrationFile)) {
        $sql = file_get_contents($migrationFile);
        if ($sql !== false && trim($sql) !== '') {
            $pdo->exec($sql);
        }
    }
}

function byok_encryption_key_bytes(): ?string
{
    $raw = '';
    if (defined('BYOK_ENCRYPTION_KEY') && BYOK_ENCRYPTION_KEY !== '') {
        $raw = (string) BYOK_ENCRYPTION_KEY;
    }

    if ($raw === '') {
        $file = dirname(__DIR__) . '/config/byok.key';
        if (is_readable($file)) {
            $raw = trim((string) file_get_contents($file));
        }
    }

    if ($raw === '') {
        return null;
    }

    return hash('sha256', $raw, true);
}

function byok_can_store_keys(): bool
{
    return byok_encryption_key_bytes() !== null;
}

function byok_encrypt(string $plain): ?string
{
    $key = byok_encryption_key_bytes();
    if ($key === null || $plain === '') {
        return null;
    }

    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        return null;
    }

    return base64_encode($iv . $cipher);
}

function byok_decrypt(string $encrypted): string
{
    $key = byok_encryption_key_bytes();
    if ($key === null || $encrypted === '') {
        return '';
    }

    $raw = base64_decode($encrypted, true);
    if ($raw === false || strlen($raw) < 17) {
        return '';
    }

    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    return $plain !== false ? $plain : '';
}

function byok_mask_key(string $apiKey): string
{
    $apiKey = trim($apiKey);
    if ($apiKey === '') {
        return '';
    }
    if (strlen($apiKey) <= 4) {
        return '****';
    }

    return '****' . substr($apiKey, -4);
}

/**
 * @return array<string, mixed>|null
 */
function byok_row_for_user(int $userId, string $providerType): ?array
{
    $pdo = demo_auth_pdo();
    if ($pdo === null || $userId <= 0) {
        return null;
    }

    migrateSQLiteDemoByok($pdo);

    $stmt = $pdo->prepare(
        'SELECT * FROM demo_user_llm_keys WHERE user_id = ? AND provider_type = ? LIMIT 1'
    );
    $stmt->execute([$userId, $providerType]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

/**
 * Provider personnel actif (is_active=1).
 *
 * @return array<string, mixed>|null
 */
function byok_get_active_row(int $userId): ?array
{
    $pdo = demo_auth_pdo();
    if ($pdo === null || $userId <= 0) {
        return null;
    }

    migrateSQLiteDemoByok($pdo);

    $stmt = $pdo->prepare(
        'SELECT * FROM demo_user_llm_keys WHERE user_id = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

/**
 * @return array<string, mixed>|null
 */
function getActiveLlmProviderGlobal(): ?array
{
    $settings = getSettings();
    $providers = $settings['llm_providers'] ?? null;
    if (!is_array($providers)) {
        return null;
    }

    foreach ($providers as $provider) {
        if (!is_array($provider)) {
            continue;
        }
        if (($provider['active'] ?? false) === true) {
            return $provider;
        }
    }

    return null;
}

/**
 * Provider LLM effectif pour l'utilisateur (BYOK validé > global).
 *
 * @return array<string, mixed>|null
 */
function getEffectiveLlmProvider(?int $userId = null): ?array
{
    if ($userId === null && isDemoAuthEnabled() && function_exists('getCurrentDemoUser')) {
        $user = getCurrentDemoUser();
        $userId = $user !== null ? (int) $user['id'] : null;
    }

    if (isByokEnabled() && $userId !== null && $userId > 0) {
        $row = byok_get_active_row($userId);
        if ($row !== null) {
            $apiKey = byok_decrypt((string) ($row['api_key_encrypted'] ?? ''));
            $providerType = (string) ($row['provider_type'] ?? '');
            $isValidated = (int) ($row['is_validated'] ?? 0) === 1;
            $hasKey = $apiKey !== '';

            if (!$hasKey || !$isValidated) {
                return [
                    '_byok_invalid' => true,
                    'source' => 'user_byok',
                    'provider_type' => $providerType,
                    'user_owned_key' => true,
                    'quota_bypass_allowed' => false,
                    'is_active' => (int) ($row['is_active'] ?? 0) === 1,
                    'is_validated' => $isValidated,
                ];
            }

            $llmType = $providerType === 'gemini' ? 'openai_compatible' : 'mistral';
            $baseUrl = trim((string) ($row['base_url'] ?? ''));
            if ($providerType === 'gemini' && $baseUrl === '') {
                $baseUrl = BYOK_GEMINI_DEFAULT_BASE_URL;
            }

            $provider = [
                'id' => 'byok_' . $providerType . '_' . $userId,
                'name' => (string) ($row['provider_name'] ?? 'BYOK'),
                'type' => $llmType,
                'provider_type' => $providerType,
                'effective_type' => $llmType,
                'model' => (string) ($row['model'] ?? ''),
                'base_url' => $baseUrl,
                'api_key' => $apiKey,
                'active' => true,
                'source' => 'user_byok',
                'user_owned_key' => true,
                'quota_bypass_allowed' => true,
            ];

            if ($providerType === 'gemini') {
                $provider['chat_path'] = '/chat/completions';
            }

            return $provider;
        }
    }

    $global = getActiveLlmProviderGlobal();
    if ($global === null) {
        return null;
    }

    $type = (string) ($global['type'] ?? 'ollama');

    return array_merge($global, [
        'source' => 'global_settings',
        'provider_type' => $type,
        'effective_type' => $type,
        'user_owned_key' => false,
        'quota_bypass_allowed' => false,
    ]);
}

function demo_auth_quota_bypass_active(?int $userId = null): bool
{
    if (!isDemoAuthEnabled() || !isByokEnabled()) {
        return false;
    }

    $provider = getEffectiveLlmProvider($userId);

    return is_array($provider)
        && ($provider['quota_bypass_allowed'] ?? false) === true
        && empty($provider['_byok_invalid']);
}

/**
 * @return array<string, mixed>
 */
function byok_effective_provider_meta(?int $userId = null): array
{
    $provider = getEffectiveLlmProvider($userId);
    if ($provider === null) {
        return [
            'source' => 'none',
            'provider_type' => null,
            'user_owned_key' => false,
            'quota_bypass_allowed' => false,
        ];
    }

    if (!empty($provider['_byok_invalid'])) {
        return [
            'source' => 'user_byok',
            'provider_type' => $provider['provider_type'] ?? null,
            'user_owned_key' => true,
            'quota_bypass_allowed' => false,
            'byok_invalid' => true,
        ];
    }

    return [
        'source' => (string) ($provider['source'] ?? 'global_settings'),
        'provider_type' => (string) ($provider['provider_type'] ?? $provider['type'] ?? ''),
        'effective_type' => (string) ($provider['effective_type'] ?? $provider['type'] ?? ''),
        'user_owned_key' => ($provider['user_owned_key'] ?? false) === true,
        'quota_bypass_allowed' => ($provider['quota_bypass_allowed'] ?? false) === true,
        'provider_name' => (string) ($provider['name'] ?? ''),
        'model' => (string) ($provider['model'] ?? ''),
    ];
}

function byok_assert_provider_usable(?array $provider): void
{
    if ($provider === null || empty($provider['_byok_invalid'])) {
        return;
    }

    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'byok_provider_invalid',
        'message' => 'Votre clé personnelle est active mais non validée ou invalide. '
            . 'Testez-la ou désactivez-la depuis Mon compte.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function byok_assert_provider_usable_sse(?array $provider): void
{
    if ($provider === null || empty($provider['_byok_invalid'])) {
        return;
    }

    if (!function_exists('sse_event')) {
        header('Content-Type: text/event-stream; charset=utf-8');
    }

    sse_event('error', [
        'success' => false,
        'error' => 'byok_provider_invalid',
        'message' => 'Votre clé personnelle est active mais non validée ou invalide.',
    ]);
    exit;
}

/**
 * @param array<string, mixed> $input
 * @return array{success: bool, message?: string, row?: array<string, mixed>}
 */
function byok_save_provider(int $userId, array $input, bool $activate = false): array
{
    if (!isByokEnabled()) {
        return ['success' => false, 'message' => 'BYOK désactivé sur cette instance.'];
    }

    if (!byok_can_store_keys()) {
        return [
            'success' => false,
            'message' => 'Chiffrement BYOK indisponible. Configurez config/byok.key ou BYOK_ENCRYPTION_KEY.',
        ];
    }

    $providerType = strtolower(trim((string) ($input['provider_type'] ?? '')));
    if (!in_array($providerType, ['mistral', 'gemini'], true)) {
        return ['success' => false, 'message' => 'provider_type invalide (mistral ou gemini).'];
    }

    $apiKey = trim((string) ($input['api_key'] ?? ''));

    $model = trim((string) ($input['model'] ?? ''));
    if ($model === '') {
        $model = $providerType === 'gemini' ? BYOK_GEMINI_DEFAULT_MODEL : BYOK_MISTRAL_DEFAULT_MODEL;
    }

    $baseUrl = trim((string) ($input['base_url'] ?? ''));
    if ($providerType === 'gemini') {
        if ($baseUrl === '') {
            $baseUrl = BYOK_GEMINI_DEFAULT_BASE_URL;
        }
        $providerName = 'Gemini personnel';
    } else {
        $baseUrl = '';
        $providerName = 'Mistral personnel';
    }

    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return ['success' => false, 'message' => 'Base indisponible.'];
    }

    migrateSQLiteDemoByok($pdo);

    $existing = byok_row_for_user($userId, $providerType);
    $isActive = $activate ? 1 : (int) ($existing['is_active'] ?? 0);

    $keyUpdated = false;
    if ($apiKey !== '') {
        $encrypted = byok_encrypt($apiKey);
        if ($encrypted === null) {
            return ['success' => false, 'message' => 'Impossible de chiffrer la clé.'];
        }
        $keyUpdated = true;
    } elseif ($existing !== null && (string) ($existing['api_key_encrypted'] ?? '') !== '') {
        $encrypted = (string) $existing['api_key_encrypted'];
    } else {
        return ['success' => false, 'message' => 'Clé API requise.'];
    }

    if ($existing !== null) {
        if ($keyUpdated) {
            $stmt = $pdo->prepare(
                'UPDATE demo_user_llm_keys SET provider_name = ?, base_url = ?, model = ?,
                 api_key_encrypted = ?, is_validated = 0, is_active = ?,
                 last_test_status = NULL, last_test_message = NULL, last_test_at = NULL,
                 updated_at = datetime(\'now\')
                 WHERE user_id = ? AND provider_type = ?'
            );
            $stmt->execute([$providerName, $baseUrl, $model, $encrypted, $isActive, $userId, $providerType]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE demo_user_llm_keys SET provider_name = ?, base_url = ?, model = ?,
                 is_active = ?, updated_at = datetime(\'now\')
                 WHERE user_id = ? AND provider_type = ?'
            );
            $stmt->execute([$providerName, $baseUrl, $model, $isActive, $userId, $providerType]);
        }
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO demo_user_llm_keys
             (user_id, provider_type, provider_name, base_url, model, api_key_encrypted, is_active, is_validated, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, datetime(\'now\'))'
        );
        $stmt->execute([$userId, $providerType, $providerName, $baseUrl, $model, $encrypted, $isActive]);
    }

    if ($activate) {
        byok_set_active($userId, $providerType, true);
    }

    return ['success' => true, 'message' => 'Clé enregistrée. Testez-la avant utilisation.'];
}

/**
 * @return array{success: bool, ok: bool, message: string, status: string}
 */
function byok_test_provider(int $userId, ?string $providerType = null): array
{
    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return ['success' => false, 'ok' => false, 'message' => 'Base indisponible.', 'status' => 'error'];
    }

    migrateSQLiteDemoByok($pdo);

    if ($providerType === null || $providerType === '') {
        $active = byok_get_active_row($userId);
        if ($active === null) {
            $stmt = $pdo->prepare(
                'SELECT provider_type FROM demo_user_llm_keys WHERE user_id = ? ORDER BY updated_at DESC LIMIT 1'
            );
            $stmt->execute([$userId]);
            $providerType = $stmt->fetchColumn();
            if ($providerType === false) {
                return ['success' => false, 'ok' => false, 'message' => 'Aucune clé à tester.', 'status' => 'error'];
            }
            $providerType = (string) $providerType;
        } else {
            $providerType = (string) $active['provider_type'];
        }
    }

    $row = byok_row_for_user($userId, $providerType);
    if ($row === null) {
        return ['success' => false, 'ok' => false, 'message' => 'Aucune clé configurée.', 'status' => 'error'];
    }

    $apiKey = byok_decrypt((string) ($row['api_key_encrypted'] ?? ''));
    if ($apiKey === '') {
        return ['success' => false, 'ok' => false, 'message' => 'Clé illisible.', 'status' => 'error'];
    }

    $llmType = $providerType === 'gemini' ? 'openai_compatible' : 'mistral';
    $baseUrl = trim((string) ($row['base_url'] ?? ''));
    if ($providerType === 'gemini' && $baseUrl === '') {
        $baseUrl = BYOK_GEMINI_DEFAULT_BASE_URL;
    }

    $provider = [
        'type' => $llmType,
        'model' => (string) ($row['model'] ?? ''),
        'base_url' => $baseUrl,
        'api_key' => $apiKey,
    ];
    if ($providerType === 'gemini') {
        $provider['chat_path'] = '/chat/completions';
    }

    if (!defined('LLM_CHAT_LOADED')) {
        require_once __DIR__ . '/llm_chat.php';
    }
    require_once __DIR__ . '/llm_bridge.php';

    $result = testLlmProviderPrompt($provider, 'Réponds uniquement OK');
    $ok = ($result['ok'] ?? false) === true;
    $message = $ok ? 'OK' : (string) ($result['error'] ?? 'Échec du test');
    $status = $ok ? 'success' : 'error';

    $validated = $ok ? 1 : 0;
    $stmt = $pdo->prepare(
        'UPDATE demo_user_llm_keys SET is_validated = ?, last_test_status = ?, last_test_message = ?,
         last_test_at = datetime(\'now\'), updated_at = datetime(\'now\')
         WHERE user_id = ? AND provider_type = ?'
    );
    $stmt->execute([$validated, $status, substr($message, 0, 500), $userId, $providerType]);

    return [
        'success' => true,
        'ok' => $ok,
        'message' => $message,
        'status' => $status,
        'latency_ms' => (int) ($result['latency_ms'] ?? 0),
    ];
}

function byok_set_active(int $userId, string $providerType, bool $requireValidated = true): array
{
    $row = byok_row_for_user($userId, $providerType);
    if ($row === null) {
        return ['success' => false, 'message' => 'Aucune clé pour ce fournisseur.'];
    }

    if ($requireValidated && (int) ($row['is_validated'] ?? 0) !== 1) {
        return ['success' => false, 'message' => 'Clé non validée. Lancez un test réussi avant activation.'];
    }

    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return ['success' => false, 'message' => 'Base indisponible.'];
    }

    $pdo->prepare('UPDATE demo_user_llm_keys SET is_active = 0 WHERE user_id = ?')->execute([$userId]);
    $pdo->prepare(
        'UPDATE demo_user_llm_keys SET is_active = 1, updated_at = datetime(\'now\')
         WHERE user_id = ? AND provider_type = ?'
    )->execute([$userId, $providerType]);

    return ['success' => true, 'message' => 'Clé personnelle activée.'];
}

function byok_disable(int $userId): array
{
    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return ['success' => false, 'message' => 'Base indisponible.'];
    }

    $pdo->prepare(
        'UPDATE demo_user_llm_keys SET is_active = 0, updated_at = datetime(\'now\') WHERE user_id = ?'
    )->execute([$userId]);

    return ['success' => true, 'message' => 'Clé personnelle désactivée.'];
}

function byok_delete(int $userId, ?string $providerType = null): array
{
    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return ['success' => false, 'message' => 'Base indisponible.'];
    }

    if ($providerType !== null && $providerType !== '') {
        $pdo->prepare('DELETE FROM demo_user_llm_keys WHERE user_id = ? AND provider_type = ?')
            ->execute([$userId, $providerType]);
    } else {
        $pdo->prepare('DELETE FROM demo_user_llm_keys WHERE user_id = ?')->execute([$userId]);
    }

    return ['success' => true, 'message' => 'Clé supprimée.'];
}

/**
 * @return array<string, mixed>|null
 */
function byok_public_row(int $userId, ?array $row): ?array
{
    if ($row === null) {
        return null;
    }

    $apiKey = byok_decrypt((string) ($row['api_key_encrypted'] ?? ''));

    return [
        'provider_type' => (string) ($row['provider_type'] ?? ''),
        'provider_name' => (string) ($row['provider_name'] ?? ''),
        'model' => (string) ($row['model'] ?? ''),
        'base_url' => (string) ($row['base_url'] ?? ''),
        'is_active' => (int) ($row['is_active'] ?? 0) === 1,
        'is_validated' => (int) ($row['is_validated'] ?? 0) === 1,
        'has_api_key' => $apiKey !== '',
        'api_key_masked' => byok_mask_key($apiKey),
        'last_test_status' => $row['last_test_status'] ?? null,
        'last_test_message' => $row['last_test_message'] ?? null,
        'last_test_at' => $row['last_test_at'] ?? null,
    ];
}

/**
 * @return array<string, mixed>
 */
function byok_account_settings_payload(int $userId): array
{
    $userRow = demo_auth_fetch_user_by_id($userId);
    $usage = getDemoUsageStatus($userId);
    $meta = byok_effective_provider_meta($userId);
    $quotaApplied = isDemoAuthEnabled() && !($meta['quota_bypass_allowed'] ?? false);

    $activeRow = byok_get_active_row($userId);
    $mistralRow = byok_row_for_user($userId, 'mistral');
    $geminiRow = byok_row_for_user($userId, 'gemini');

    $global = getActiveLlmProviderGlobal();

    return [
        'success' => true,
        'username' => (string) ($userRow['username'] ?? ''),
        'byok_enabled' => isByokEnabled(),
        'encryption_available' => byok_can_store_keys(),
        'effective_provider' => $meta,
        'global_provider' => $global !== null ? [
            'name' => (string) ($global['name'] ?? ''),
            'type' => (string) ($global['type'] ?? ''),
            'model' => (string) ($global['model'] ?? ''),
        ] : null,
        'quota' => [
            'quota_applied' => $quotaApplied,
            'tutorial' => $usage['tutorial'],
            'buddy' => $usage['buddy'],
            'reset_at' => $usage['reset_at'],
        ],
        'byok' => byok_public_row($userId, $activeRow ?? $mistralRow ?? $geminiRow),
        'byok_mistral' => byok_public_row($userId, $mistralRow),
        'byok_gemini' => byok_public_row($userId, $geminiRow),
    ];
}

/**
 * Stats admin (sans secrets).
 *
 * @return array<string, int|bool>
 */
function byok_admin_stats(): array
{
    if (!isByokEnabled()) {
        return [
            'byok_enabled' => false,
            'configured' => 0,
            'validated' => 0,
            'active' => 0,
        ];
    }

    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return ['byok_enabled' => true, 'configured' => 0, 'validated' => 0, 'active' => 0];
    }

    migrateSQLiteDemoByok($pdo);

    $configured = (int) $pdo->query('SELECT COUNT(*) FROM demo_user_llm_keys')->fetchColumn();
    $validated = (int) $pdo->query('SELECT COUNT(*) FROM demo_user_llm_keys WHERE is_validated = 1')->fetchColumn();
    $active = (int) $pdo->query('SELECT COUNT(*) FROM demo_user_llm_keys WHERE is_active = 1')->fetchColumn();

    return [
        'byok_enabled' => true,
        'configured' => $configured,
        'validated' => $validated,
        'active' => $active,
    ];
}

/**
 * Champs debug LLM (APP_DEBUG).
 *
 * @return array<string, mixed>
 */
function byok_debug_fields(?int $userId = null): array
{
    $meta = byok_effective_provider_meta($userId);

    return [
        'provider_source' => $meta['source'] ?? 'none',
        'provider_type' => $meta['provider_type'] ?? null,
        'effective_type' => $meta['effective_type'] ?? null,
        'user_owned_key' => ($meta['user_owned_key'] ?? false) === true,
        'quota_applied' => isDemoAuthEnabled() && !($meta['quota_bypass_allowed'] ?? false),
        'quota_bypass_allowed' => ($meta['quota_bypass_allowed'] ?? false) === true,
        'byok_invalid' => ($meta['byok_invalid'] ?? false) === true,
    ];
}
