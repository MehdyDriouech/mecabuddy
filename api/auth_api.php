<?php
/**
 * MecaBuddy — API authentification démo
 *
 * POST ?action=login  — JSON { username, password }
 * POST ?action=logout
 * GET  ?action=me
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

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * @param array<string, mixed> $data
 */
function auth_send(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!isDemoAuthEnabled()) {
    if ($action === 'me') {
        auth_send(['success' => false, 'auth_enabled' => false]);
    }
    auth_send([
        'success' => false,
        'error' => 'demo_auth_disabled',
        'message' => 'Authentification démo désactivée.',
    ], 403);
}

switch ($action) {
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            auth_send(['success' => false, 'error' => 'method_not_allowed'], 405);
        }
        $input = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($input)) {
            $input = $_POST;
        }
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if (!loginDemoUser($username, $password)) {
            auth_send(['success' => false, 'error' => 'invalid_credentials'], 401);
        }

        $user = getCurrentDemoUser();
        $garage = [];
        if (function_exists('vehicle_scope_garage_summary') && demo_auth_pdo() !== null) {
            require_once __DIR__ . '/../includes/vehicle_scope.php';
            $garage = vehicle_scope_garage_summary(demo_auth_pdo());
        }
        auth_send([
            'success' => true,
            'user' => demo_auth_public_user($user),
            'usage' => getDemoUsageStatus((int) $user['id']),
            'garage' => $garage,
        ]);
        break;

    case 'logout':
        logoutDemoUser();
        auth_send(['success' => true]);
        break;

    case 'me':
        $user = getCurrentDemoUser();
        if ($user === null) {
            auth_send(['success' => false]);
        }
        $garage = [];
        if (demo_auth_pdo() !== null) {
            require_once __DIR__ . '/../includes/vehicle_scope.php';
            $garage = vehicle_scope_garage_summary(demo_auth_pdo());
        }
        auth_send([
            'success' => true,
            'user' => demo_auth_public_user($user),
            'usage' => getDemoUsageStatus((int) $user['id']),
            'garage' => $garage,
        ]);
        break;

    default:
        auth_send(['success' => false, 'error' => 'unknown_action'], 400);
}
