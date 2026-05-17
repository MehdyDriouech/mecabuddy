<?php
/**
 * MecaBuddy — API compte utilisateur (quotas + BYOK)
 *
 * GET/POST ?action=get_account_settings
 * POST ?action=save_byok_provider
 * POST ?action=test_byok_provider
 * POST ?action=disable_byok_provider
 * POST ?action=delete_byok_provider
 * POST ?action=set_byok_active
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/demo_auth.php';
require_once __DIR__ . '/../includes/byok.php';

$pdoByok = demo_auth_pdo();
if ($pdoByok instanceof PDO) {
    ensureDemoByokSchema($pdoByok);
}

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * @param array<string, mixed> $data
 */
function account_api_send(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!isDemoAuthEnabled()) {
    account_api_send([
        'success' => false,
        'error' => 'demo_auth_disabled',
        'message' => 'Authentification démo désactivée.',
    ], 403);
}

requireDemoApiLogin();

$user = getCurrentDemoUser();
if ($user === null) {
    account_api_send([
        'success' => false,
        'error' => 'not_authenticated',
        'message' => 'Connexion requise.',
    ], 401);
}

$userId = (int) $user['id'];

switch ($action) {
    case 'get_account_settings':
        account_api_send(byok_account_settings_payload($userId));
        break;

    case 'save_byok_provider':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            account_api_send(['success' => false, 'message' => 'Méthode non autorisée'], 405);
        }
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            account_api_send(['success' => false, 'message' => 'JSON invalide'], 400);
        }
        $activate = ($decoded['activate'] ?? false) === true;
        $result = byok_save_provider($userId, $decoded, $activate);
        if (!($result['success'] ?? false)) {
            account_api_send($result, 400);
        }
        $payload = byok_account_settings_payload($userId);
        $payload['message'] = $result['message'] ?? 'Enregistré.';
        account_api_send($payload);
        break;

    case 'test_byok_provider':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            account_api_send(['success' => false, 'message' => 'Méthode non autorisée'], 405);
        }
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $providerType = isset($decoded['provider_type']) ? (string) $decoded['provider_type'] : null;
        $test = byok_test_provider($userId, $providerType);
        $payload = byok_account_settings_payload($userId);
        $payload['test'] = $test;
        account_api_send($payload, ($test['ok'] ?? false) ? 200 : 400);
        break;

    case 'set_byok_active':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            account_api_send(['success' => false, 'message' => 'Méthode non autorisée'], 405);
        }
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            account_api_send(['success' => false, 'message' => 'JSON invalide'], 400);
        }
        $providerType = (string) ($decoded['provider_type'] ?? '');
        $result = byok_set_active($userId, $providerType, true);
        if (!($result['success'] ?? false)) {
            account_api_send($result, 400);
        }
        account_api_send(byok_account_settings_payload($userId));
        break;

    case 'disable_byok_provider':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            account_api_send(['success' => false, 'message' => 'Méthode non autorisée'], 405);
        }
        $result = byok_disable($userId);
        account_api_send(array_merge(byok_account_settings_payload($userId), $result));
        break;

    case 'delete_byok_provider':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            account_api_send(['success' => false, 'message' => 'Méthode non autorisée'], 405);
        }
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $providerType = isset($decoded['provider_type']) ? (string) $decoded['provider_type'] : null;
        $result = byok_delete($userId, $providerType);
        account_api_send(array_merge(byok_account_settings_payload($userId), $result));
        break;

    default:
        account_api_send([
            'success' => false,
            'message' => 'Action non reconnue.',
        ], 400);
}
