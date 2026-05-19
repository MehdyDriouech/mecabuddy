<?php
/**
 * MecaBuddy — API administration (comptes, véhicules, tableau de bord)
 *
 * Toutes les routes : auth démo + rôle admin.
 * POST/PUT/DELETE : jeton CSRF (header X-CSRF-Token).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/demo_auth.php';
require_once __DIR__ . '/../includes/demo_csrf.php';
require_once __DIR__ . '/../includes/admin_insights.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

requireDemoApiAdmin();
demo_csrf_ensure_token();

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
    demo_csrf_require_for_write_api();
}

/**
 * @param array<string, mixed> $data
 */
function admin_api_send(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function admin_api_error(string $message, string $error = 'error', int $code = 400): void
{
    admin_api_send(['success' => false, 'error' => $error, 'message' => $message], $code);
}

function admin_api_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function admin_api_pdo(): ?PDO
{
    try {
        return demo_auth_pdo();
    } catch (Throwable $e) {
        return null;
    }
}

function admin_api_stats_pdo(): ?PDO
{
    if (db_is_demo_mode()) {
        return null;
    }
    try {
        if (isDatabaseAvailable()) {
            return getSQLite();
        }
    } catch (Throwable $e) {
        return null;
    }

    return admin_api_pdo();
}

function handle_get_csrf(): void
{
    admin_api_send([
        'success' => true,
        'csrf_token' => demo_csrf_ensure_token(),
    ]);
}

function handle_dashboard_stats(): void
{
    $counts = [
        'accounts' => 0,
        'vehicles' => 0,
        'tutorials' => 0,
        'conversations' => 0,
        'catalog_cars' => 0,
        'catalog_motos' => 0,
    ];

    $authPdo = admin_api_pdo();
    if ($authPdo !== null) {
        $counts['accounts'] = (int) $authPdo->query('SELECT COUNT(*) FROM demo_users')->fetchColumn();
    }

    $pdo = admin_api_stats_pdo();
    if ($pdo !== null) {
        $tables = [];
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
        if (in_array('vehicles', $tables, true)) {
            $counts['vehicles'] = (int) $pdo->query('SELECT COUNT(*) FROM vehicles')->fetchColumn();
        }
        if (in_array('tutorials', $tables, true)) {
            $counts['tutorials'] = (int) $pdo->query('SELECT COUNT(*) FROM tutorials')->fetchColumn();
        }
        if (in_array('diagnostic_conversations', $tables, true)) {
            $counts['conversations'] = (int) $pdo->query('SELECT COUNT(*) FROM diagnostic_conversations')->fetchColumn();
        }
        if (in_array('vehicle_brands', $tables, true) && in_array('vehicle_models', $tables, true)) {
            $counts['catalog_cars'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM vehicle_models vm
                 INNER JOIN vehicle_brands vb ON vb.id = vm.brand_id
                 WHERE COALESCE(vb.category, 'car') = 'car'"
            )->fetchColumn();
            $counts['catalog_motos'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM vehicle_models vm
                 INNER JOIN vehicle_brands vb ON vb.id = vm.brand_id
                 WHERE vb.category = 'moto'"
            )->fetchColumn();
        }
    }

    $dbMode = 'mock';
    if (db_is_demo_mode()) {
        $dbMode = 'mock';
    } elseif (defined('USE_MYSQL') && USE_MYSQL && isDatabaseAvailable()) {
        try {
            Database::getInstance();
            $dbMode = 'mysql';
        } catch (Throwable $e) {
            $dbMode = 'sqlite';
        }
    } elseif (isDatabaseAvailable()) {
        $dbMode = 'sqlite';
    }

    $provider = getActiveLlmProvider();
    $providerSummary = null;
    if (is_array($provider)) {
        $providerSummary = [
            'name' => (string) ($provider['name'] ?? ''),
            'type' => (string) ($provider['type'] ?? ''),
            'model' => (string) ($provider['model'] ?? ''),
        ];
    }

    admin_api_send([
        'success' => true,
        'stats' => $counts,
        'environment' => [
            'db_mode' => $dbMode,
            'app_debug' => defined('APP_DEBUG') && APP_DEBUG,
            'demo_auth_enabled' => isDemoAuthEnabled(),
            'demo_mode' => isDemoMode(),
            'debug_panel_setting' => isDebugPanelEnabled(),
        ],
        'provider' => $providerSummary,
    ]);
}

function handle_list_users(): void
{
    admin_api_send([
        'success' => true,
        'users' => demo_auth_list_users_with_usage(),
    ]);
}

function handle_create_user(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_api_error('Méthode non autorisée', 'method_not_allowed', 405);
    }

    $body = admin_api_json_body();
    $username = trim((string) ($body['username'] ?? ''));
    $password = (string) ($body['password'] ?? '');
    $role = demo_auth_normalize_role((string) ($body['role'] ?? DEMO_ROLE_USER));
    $tQuota = max(1, (int) ($body['tutorial_daily_quota'] ?? 15));
    $bQuota = max(1, (int) ($body['buddy_daily_quota'] ?? 15));
    $isActive = ($body['is_active'] ?? true) !== false;

    if ($username === '' || strlen($username) > 64) {
        admin_api_error('Identifiant invalide.');
    }
    if (strlen($password) < 4) {
        admin_api_error('Mot de passe trop court (min. 4 caractères).');
    }

    $pdo = admin_api_pdo();
    if ($pdo === null) {
        admin_api_error('Base comptes indisponible.', 'storage_error', 500);
    }

    $check = $pdo->prepare('SELECT id FROM demo_users WHERE username = ?');
    $check->execute([$username]);
    if ($check->fetchColumn() !== false) {
        admin_api_error('Cet identifiant existe déjà.', 'duplicate_username');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO demo_users (username, password_hash, role, tutorial_daily_quota, buddy_daily_quota, is_active, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, datetime(\'now\'))'
    );
    $stmt->execute([$username, $hash, $role, $tQuota, $bQuota, $isActive ? 1 : 0]);

    demo_auth_admin_log('create_user', ['target_username' => $username, 'role' => $role]);

    admin_api_send(['success' => true, 'id' => (int) $pdo->lastInsertId()]);
}

function handle_update_user(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_api_error('Méthode non autorisée', 'method_not_allowed', 405);
    }

    $body = admin_api_json_body();
    $userId = (int) ($body['id'] ?? 0);
    if ($userId <= 0) {
        admin_api_error('Identifiant utilisateur invalide.');
    }

    $pdo = admin_api_pdo();
    if ($pdo === null) {
        admin_api_error('Base comptes indisponible.', 'storage_error', 500);
    }

    $cur = $pdo->prepare('SELECT id, username, role, is_active FROM demo_users WHERE id = ?');
    $cur->execute([$userId]);
    $existing = $cur->fetch(PDO::FETCH_ASSOC);
    if ($existing === false) {
        admin_api_error('Compte introuvable.', 'not_found', 404);
    }

    $currentAdmin = getCurrentDemoUser();
    $selfId = $currentAdmin !== null ? (int) $currentAdmin['id'] : 0;

    $newUsername = array_key_exists('username', $body)
        ? trim((string) $body['username'])
        : (string) $existing['username'];
    $newRole = array_key_exists('role', $body)
        ? demo_auth_normalize_role((string) $body['role'])
        : demo_auth_normalize_role((string) $existing['role']);
    $newActive = array_key_exists('is_active', $body)
        ? (($body['is_active'] ?? false) !== false)
        : ((int) $existing['is_active'] === 1);

    if ($newUsername === '' || strlen($newUsername) > 64) {
        admin_api_error('Identifiant invalide.');
    }

    if ($newUsername !== (string) $existing['username']) {
        $dup = $pdo->prepare('SELECT id FROM demo_users WHERE username = ? AND id != ?');
        $dup->execute([$newUsername, $userId]);
        if ($dup->fetchColumn() !== false) {
            admin_api_error('Cet identifiant existe déjà.', 'duplicate_username');
        }
    }

    $wasAdmin = demo_auth_normalize_role((string) $existing['role']) === DEMO_ROLE_ADMIN;
    if ($wasAdmin && $newRole !== DEMO_ROLE_ADMIN && demo_auth_count_active_admins($pdo) <= 1) {
        admin_api_error('Impossible de retirer le rôle du dernier administrateur actif.', 'last_admin');
    }
    if ($userId === $selfId && $wasAdmin && $newRole !== DEMO_ROLE_ADMIN && demo_auth_count_active_admins($pdo) <= 1) {
        admin_api_error('Vous ne pouvez pas vous retirer le rôle admin tant que vous êtes le seul administrateur.', 'last_admin');
    }
    if ($wasAdmin && !$newActive && demo_auth_count_active_admins($pdo) <= 1) {
        admin_api_error('Impossible de désactiver le dernier administrateur actif.', 'last_admin');
    }

    $tQuota = array_key_exists('tutorial_daily_quota', $body)
        ? max(1, (int) $body['tutorial_daily_quota'])
        : null;
    $bQuota = array_key_exists('buddy_daily_quota', $body)
        ? max(1, (int) $body['buddy_daily_quota'])
        : null;

    $sets = ['username = ?', 'role = ?', 'is_active = ?', "updated_at = datetime('now')"];
    $params = [$newUsername, $newRole, $newActive ? 1 : 0];
    if ($tQuota !== null) {
        $sets[] = 'tutorial_daily_quota = ?';
        $params[] = $tQuota;
    }
    if ($bQuota !== null) {
        $sets[] = 'buddy_daily_quota = ?';
        $params[] = $bQuota;
    }
    $params[] = $userId;

    $pdo->prepare('UPDATE demo_users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);

    demo_auth_admin_log('update_user', [
        'target_id' => $userId,
        'target_username' => $newUsername,
        'role' => $newRole,
        'is_active' => $newActive,
    ]);

    admin_api_send(['success' => true]);
}

function handle_reset_password(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_api_error('Méthode non autorisée', 'method_not_allowed', 405);
    }

    $body = admin_api_json_body();
    $userId = (int) ($body['id'] ?? 0);
    $password = (string) ($body['password'] ?? '');
    if ($userId <= 0 || strlen($password) < 4) {
        admin_api_error('Paramètres invalides.');
    }

    $pdo = admin_api_pdo();
    if ($pdo === null) {
        admin_api_error('Base comptes indisponible.', 'storage_error', 500);
    }

    $check = $pdo->prepare('SELECT username FROM demo_users WHERE id = ?');
    $check->execute([$userId]);
    $uname = $check->fetchColumn();
    if ($uname === false) {
        admin_api_error('Compte introuvable.', 'not_found', 404);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare(
        "UPDATE demo_users SET password_hash = ?, updated_at = datetime('now') WHERE id = ?"
    )->execute([$hash, $userId]);

    demo_auth_admin_log('reset_password', ['target_id' => $userId, 'target_username' => (string) $uname]);

    admin_api_send(['success' => true]);
}

function handle_delete_user(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_api_error('Méthode non autorisée', 'method_not_allowed', 405);
    }

    $body = admin_api_json_body();
    $userId = (int) ($body['id'] ?? 0);
    if ($userId <= 0) {
        admin_api_error('Identifiant utilisateur invalide.');
    }

    $current = getCurrentDemoUser();
    if ($current !== null && (int) $current['id'] === $userId) {
        admin_api_error('Vous ne pouvez pas supprimer votre propre compte.', 'self_delete');
    }

    $pdo = admin_api_pdo();
    if ($pdo === null) {
        admin_api_error('Base comptes indisponible.', 'storage_error', 500);
    }

    $row = $pdo->prepare('SELECT username, role, is_active FROM demo_users WHERE id = ?');
    $row->execute([$userId]);
    $user = $row->fetch(PDO::FETCH_ASSOC);
    if ($user === false) {
        admin_api_error('Compte introuvable.', 'not_found', 404);
    }

    if (demo_auth_normalize_role((string) $user['role']) === DEMO_ROLE_ADMIN
        && (int) $user['is_active'] === 1
        && demo_auth_count_active_admins($pdo) <= 1) {
        admin_api_error('Impossible de supprimer le dernier administrateur actif.', 'last_admin');
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare('DELETE FROM demo_usage_daily WHERE user_id = ?')->execute([$userId]);
        try {
            $pdo->prepare('DELETE FROM demo_user_llm_keys WHERE user_id = ?')->execute([$userId]);
        } catch (Throwable $e) {
            // table optionnelle
        }

        // Garage démo lié (FK SQLite si véhicules référencent demo_user_id)
        try {
            $vehiclesExist = $pdo->query(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='vehicles'"
            )->fetchColumn();
            if ($vehiclesExist !== false) {
                $pdo->prepare('DELETE FROM vehicles WHERE demo_user_id = ?')->execute([$userId]);
            }
        } catch (Throwable $e) {
            error_log('[MecaBuddy][admin] delete_user vehicles: ' . $e->getMessage());
        }

        $del = $pdo->prepare('DELETE FROM demo_users WHERE id = ?');
        $del->execute([$userId]);
        if ($del->rowCount() < 1) {
            $pdo->rollBack();
            admin_api_error('Suppression impossible (compte introuvable ou contrainte base).', 'delete_failed', 409);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[MecaBuddy][admin] delete_user: ' . $e->getMessage());
        admin_api_error(
            defined('APP_DEBUG') && APP_DEBUG
                ? 'Erreur suppression : ' . $e->getMessage()
                : 'Erreur lors de la suppression du compte.',
            'delete_failed',
            500
        );
    }

    demo_auth_admin_log('delete_user', [
        'target_id' => $userId,
        'target_username' => (string) $user['username'],
    ]);

    admin_api_send(['success' => true, 'deleted_id' => $userId]);
}

/**
 * @return array<string, mixed>|null
 */
function admin_api_vehicle_row_to_public(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'demo_user_id' => isset($row['demo_user_id']) ? (int) $row['demo_user_id'] : null,
        'license_plate' => $row['license_plate'] ?? null,
        'brand' => (string) ($row['brand'] ?? ''),
        'model' => (string) ($row['model'] ?? ''),
        'year' => (int) ($row['year'] ?? 0),
        'engine_type' => $row['engine_type'] ?? null,
        'engine_size' => $row['engine_size'] ?? null,
        'transmission' => $row['transmission'] ?? null,
        'slot' => isset($row['slot']) ? (int) $row['slot'] : null,
        'is_active' => (int) ($row['is_active'] ?? 0) === 1,
        'is_demo_seed' => (int) ($row['is_demo_seed'] ?? 0) === 1,
        'session_id' => $row['session_id'] ?? null,
    ];
}

function admin_api_content_pdo(): ?PDO
{
    if (db_is_demo_mode()) {
        return null;
    }
    try {
        return getDB();
    } catch (Throwable $e) {
        return null;
    }
}

function handle_list_tutorials(): void
{
    $limit = min(max((int) ($_GET['limit'] ?? 100), 1), 500);

    if (defined('USE_MOCK_DB') && USE_MOCK_DB) {
        $mock = admin_insights_list_tutorials_mock();
        admin_api_send([
            'success' => true,
            'tutorials' => array_slice($mock['items'] ?? [], 0, $limit),
            'warning' => $mock['warning'] ?? null,
        ]);
    }

    $pdo = admin_api_content_pdo();
    if ($pdo === null) {
        admin_api_send(['success' => true, 'tutorials' => [], 'warning' => 'Base indisponible (mode mock ou erreur SQLite).']);
    }

    $stmt = $pdo->prepare(
        'SELECT t.id, t.title, t.action_type, t.difficulty, t.estimated_time, t.danger_level,
                t.created_at, t.session_id, t.vehicle_id,
                v.brand, v.model, v.year
         FROM tutorials t
         LEFT JOIN vehicles v ON t.vehicle_id = v.id
         ORDER BY t.created_at DESC
         LIMIT ?'
    );
    $stmt->execute([$limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        $label = null;
        if (!empty($row['brand'])) {
            $label = trim($row['brand'] . ' ' . $row['model'] . ' (' . $row['year'] . ')');
        }
        $out[] = [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'action_type' => (string) $row['action_type'],
            'difficulty' => (string) ($row['difficulty'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'session_id' => (string) ($row['session_id'] ?? ''),
            'vehicle_id' => isset($row['vehicle_id']) ? (int) $row['vehicle_id'] : null,
            'vehicle_label' => $label,
        ];
    }

    admin_api_send(['success' => true, 'tutorials' => $out, 'count' => count($out)]);
}

function handle_get_tutorial(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        admin_api_error('ID tutoriel invalide.');
    }

    if (defined('USE_MOCK_DB') && USE_MOCK_DB) {
        $found = null;
        foreach ($_SESSION['mock_db']['tutorials'] ?? [] as $t) {
            if (is_array($t) && (int) ($t['id'] ?? 0) === $id) {
                $found = $t;
                break;
            }
        }
        if ($found === null) {
            admin_api_error('Tutoriel introuvable.', 'not_found', 404);
        }
        admin_api_send([
            'success' => true,
            'tutorial' => $found,
            'debug_logs' => admin_insights_error_log_snippet((string) ($found['created_at'] ?? ''), (string) ($found['session_id'] ?? '')),
            'public_url' => PUBLIC_URL . '/tutorial.php',
        ]);
    }

    $pdo = admin_api_content_pdo();
    if ($pdo === null) {
        admin_api_error('Base indisponible.', 'storage_error', 500);
    }

    $stmt = $pdo->prepare(
        'SELECT t.*, v.brand, v.model, v.year
         FROM tutorials t
         LEFT JOIN vehicles v ON t.vehicle_id = v.id
         WHERE t.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        admin_api_error('Tutoriel introuvable.', 'not_found', 404);
    }

    $tutorial = [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'description' => $row['description'],
        'action_type' => (string) $row['action_type'],
        'steps' => admin_insights_json_decode((string) ($row['steps'] ?? '')) ?? [],
        'tools_required' => admin_insights_json_decode((string) ($row['tools_required'] ?? '')) ?? [],
        'parts_required' => admin_insights_json_decode((string) ($row['parts_required'] ?? '')) ?? [],
        'danger_level' => (string) ($row['danger_level'] ?? 'none'),
        'global_warnings' => admin_insights_json_decode((string) ($row['global_warnings'] ?? '')) ?? [],
        'estimated_time' => $row['estimated_time'] !== null ? (int) $row['estimated_time'] : null,
        'difficulty' => (string) ($row['difficulty'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'session_id' => (string) ($row['session_id'] ?? ''),
        'vehicle_id' => $row['vehicle_id'] !== null ? (int) $row['vehicle_id'] : null,
    ];
    if (!empty($row['brand'])) {
        $tutorial['vehicle'] = [
            'brand' => (string) $row['brand'],
            'model' => (string) $row['model'],
            'year' => (int) $row['year'],
        ];
    }

    admin_api_send([
        'success' => true,
        'tutorial' => $tutorial,
        'debug_logs' => admin_insights_error_log_snippet(
            (string) ($row['created_at'] ?? ''),
            (string) ($row['session_id'] ?? '')
        ),
        'public_url' => PUBLIC_URL . '/tutorial.php',
    ]);
}

function handle_list_conversations(): void
{
    $limit = min(max((int) ($_GET['limit'] ?? 100), 1), 500);

    if (defined('USE_MOCK_DB') && USE_MOCK_DB) {
        $mock = admin_insights_list_conversations_mock();
        admin_api_send([
            'success' => true,
            'conversations' => array_slice($mock['items'] ?? [], 0, $limit),
            'warning' => $mock['warning'] ?? null,
        ]);
    }

    $pdo = admin_api_content_pdo();
    if ($pdo === null) {
        admin_api_send(['success' => true, 'conversations' => [], 'warning' => 'Base indisponible.']);
    }

    $stmt = $pdo->prepare(
        'SELECT id, vehicle_id, user_message, created_at, session_id
         FROM diagnostic_conversations
         ORDER BY created_at DESC
         LIMIT ?'
    );
    $stmt->execute([$limit]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $msg = (string) ($row['user_message'] ?? '');
        $out[] = [
            'id' => (int) $row['id'],
            'created_at' => (string) ($row['created_at'] ?? ''),
            'session_id' => (string) ($row['session_id'] ?? ''),
            'vehicle_id' => $row['vehicle_id'] !== null ? (int) $row['vehicle_id'] : null,
            'user_message_preview' => mb_strlen($msg) > 120 ? mb_substr($msg, 0, 120) . '…' : $msg,
        ];
    }

    admin_api_send(['success' => true, 'conversations' => $out, 'count' => count($out)]);
}

function handle_get_conversation(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        admin_api_error('ID conversation invalide.');
    }

    if (defined('USE_MOCK_DB') && USE_MOCK_DB) {
        $found = null;
        foreach ($_SESSION['mock_db']['diagnostic_conversations'] ?? [] as $c) {
            if (is_array($c) && (int) ($c['id'] ?? 0) === $id) {
                $found = $c;
                break;
            }
        }
        if ($found === null) {
            admin_api_error('Conversation introuvable.', 'not_found', 404);
        }
        $ctx = $found['context'] ?? null;
        if (is_string($ctx)) {
            $ctx = admin_insights_json_decode($ctx);
        }
        admin_api_send([
            'success' => true,
            'conversation' => array_merge($found, ['context' => $ctx]),
            'debug_logs' => admin_insights_error_log_snippet((string) ($found['created_at'] ?? ''), (string) ($found['session_id'] ?? '')),
            'public_url' => PUBLIC_URL . '/diagnostic.php',
        ]);
    }

    $pdo = admin_api_content_pdo();
    if ($pdo === null) {
        admin_api_error('Base indisponible.', 'storage_error', 500);
    }

    $stmt = $pdo->prepare(
        'SELECT c.*, v.brand, v.model, v.year
         FROM diagnostic_conversations c
         LEFT JOIN vehicles v ON c.vehicle_id = v.id
         WHERE c.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        admin_api_error('Conversation introuvable.', 'not_found', 404);
    }

    $conversation = [
        'id' => (int) $row['id'],
        'user_message' => (string) $row['user_message'],
        'buddy_response' => (string) $row['buddy_response'],
        'context' => admin_insights_json_decode((string) ($row['context'] ?? '')),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'session_id' => (string) ($row['session_id'] ?? ''),
        'vehicle_id' => $row['vehicle_id'] !== null ? (int) $row['vehicle_id'] : null,
    ];
    if (!empty($row['brand'])) {
        $conversation['vehicle'] = [
            'brand' => (string) $row['brand'],
            'model' => (string) $row['model'],
            'year' => (int) $row['year'],
        ];
    }

    admin_api_send([
        'success' => true,
        'conversation' => $conversation,
        'debug_logs' => admin_insights_error_log_snippet(
            (string) ($row['created_at'] ?? ''),
            (string) ($row['session_id'] ?? '')
        ),
        'public_url' => PUBLIC_URL . '/diagnostic.php',
    ]);
}

function handle_list_vehicle_catalog(): void
{
    if (db_is_demo_mode()) {
        admin_api_send([
            'success' => true,
            'items' => [],
            'warning' => 'Mode démo applicatif : catalogue indisponible (MockDatabase pour les garages).',
        ]);
    }

    $pdo = admin_api_content_pdo();
    if ($pdo === null) {
        admin_api_send(['success' => true, 'items' => [], 'warning' => 'Catalogue indisponible.']);
    }

    $category = $_GET['category'] ?? 'car';
    if (!in_array($category, ['car', 'moto'], true)) {
        admin_api_error('category invalide (car ou moto).');
    }

    $search = trim((string) ($_GET['q'] ?? ''));
    $limit = min(max((int) ($_GET['limit'] ?? 800), 1), 2000);
    $offset = max((int) ($_GET['offset'] ?? 0), 0);

    $sql = "SELECT vb.id AS brand_id, vb.name AS brand, vb.country, vb.category,
                   vm.id AS model_id, vm.name AS model, vm.year_start, vm.year_end,
                   (SELECT COUNT(*) FROM engine_types et WHERE et.model_id = vm.id) AS engine_count
            FROM vehicle_models vm
            INNER JOIN vehicle_brands vb ON vb.id = vm.brand_id
            WHERE COALESCE(vb.category, 'car') = ?";
    $params = [$category];

    if ($search !== '') {
        $sql .= ' AND (LOWER(vb.name) LIKE ? OR LOWER(vm.name) LIKE ?)';
        $like = '%' . strtolower($search) . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= ' ORDER BY vb.name ASC, vm.name ASC LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'brand_id' => (int) $row['brand_id'],
            'brand' => (string) $row['brand'],
            'country' => $row['country'],
            'category' => (string) ($row['category'] ?? 'car'),
            'model_id' => (int) $row['model_id'],
            'model' => (string) $row['model'],
            'year_start' => $row['year_start'] !== null ? (int) $row['year_start'] : null,
            'year_end' => $row['year_end'] !== null ? (int) $row['year_end'] : null,
            'engine_count' => (int) ($row['engine_count'] ?? 0),
        ];
    }

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM vehicle_models vm
         INNER JOIN vehicle_brands vb ON vb.id = vm.brand_id
         WHERE COALESCE(vb.category, 'car') = ?"
    );
    $countStmt->execute([$category]);
    $total = (int) $countStmt->fetchColumn();

    admin_api_send([
        'success' => true,
        'category' => $category,
        'items' => $items,
        'total_models' => $total,
        'limit' => $limit,
        'offset' => $offset,
    ]);
}

function handle_list_vehicles(): void
{
    if (db_is_demo_mode()) {
        admin_api_send([
            'success' => true,
            'vehicles' => [],
            'warning' => 'Mode démo applicatif actif : les véhicules sont servis par MockDatabase, pas par SQLite.',
        ]);
    }

    $pdo = admin_api_content_pdo();
    if ($pdo === null) {
        admin_api_send(['success' => true, 'vehicles' => [], 'warning' => 'Base véhicules indisponible.']);
    }

    $filterUser = isset($_GET['demo_user_id']) ? (int) $_GET['demo_user_id'] : 0;
    $sql = 'SELECT id, demo_user_id, license_plate, brand, model, year, engine_type, engine_size,
            transmission, slot, is_active, is_demo_seed, session_id
            FROM vehicles';
    $params = [];
    if ($filterUser > 0) {
        $sql .= ' WHERE demo_user_id = ?';
        $params[] = $filterUser;
    }
    $sql .= ' ORDER BY demo_user_id, slot, id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        $out[] = admin_api_vehicle_row_to_public($row);
    }

    admin_api_send(['success' => true, 'vehicles' => $out]);
}

function handle_create_vehicle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_api_error('Méthode non autorisée', 'method_not_allowed', 405);
    }
    if (db_is_demo_mode()) {
        admin_api_error('Création impossible en mode démo applicatif (MockDatabase).', 'demo_mode', 409);
    }

    $body = admin_api_json_body();
    $brand = trim((string) ($body['brand'] ?? ''));
    $model = trim((string) ($body['model'] ?? ''));
    $year = (int) ($body['year'] ?? 0);
    if ($brand === '' || $model === '' || $year < 1900 || $year > 2100) {
        admin_api_error('Marque, modèle et année valides requis.');
    }

    try {
        $pdo = getDB();
    } catch (Throwable $e) {
        admin_api_error('Base véhicules indisponible.', 'storage_error', 500);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO vehicles (license_plate, brand, model, year, engine_type, engine_size, transmission,
         demo_user_id, slot, is_active, is_demo_seed, session_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)'
    );
    $stmt->execute([
        ($body['license_plate'] ?? null) !== null && $body['license_plate'] !== ''
            ? substr((string) $body['license_plate'], 0, 20) : null,
        substr($brand, 0, 100),
        substr($model, 0, 100),
        $year,
        $body['engine_type'] ?? null,
        $body['engine_size'] ?? null,
        $body['transmission'] ?? null,
        isset($body['demo_user_id']) && (int) $body['demo_user_id'] > 0 ? (int) $body['demo_user_id'] : null,
        isset($body['slot']) ? (int) $body['slot'] : null,
        ($body['is_active'] ?? false) === true ? 1 : 0,
        $body['session_id'] ?? null,
    ]);

    $id = (int) $pdo->lastInsertId();
    demo_auth_admin_log('create_vehicle', ['vehicle_id' => $id, 'brand' => $brand, 'model' => $model]);

    admin_api_send(['success' => true, 'id' => $id]);
}

function handle_update_vehicle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_api_error('Méthode non autorisée', 'method_not_allowed', 405);
    }
    if (db_is_demo_mode()) {
        admin_api_error('Modification impossible en mode démo applicatif.', 'demo_mode', 409);
    }

    $body = admin_api_json_body();
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        admin_api_error('Véhicule invalide.');
    }

    try {
        $pdo = getDB();
    } catch (Throwable $e) {
        admin_api_error('Base véhicules indisponible.', 'storage_error', 500);
    }

    $check = $pdo->prepare('SELECT id FROM vehicles WHERE id = ?');
    $check->execute([$id]);
    if ($check->fetchColumn() === false) {
        admin_api_error('Véhicule introuvable.', 'not_found', 404);
    }

    $fields = [
        'license_plate', 'brand', 'model', 'year', 'engine_type', 'engine_size',
        'transmission', 'demo_user_id', 'slot', 'is_active', 'session_id',
    ];
    $sets = [];
    $params = [];
    foreach ($fields as $field) {
        if (!array_key_exists($field, $body)) {
            continue;
        }
        $val = $body[$field];
        if ($field === 'year') {
            $val = (int) $val;
        } elseif ($field === 'is_active') {
            $val = $val === true || $val === 1 || $val === '1' ? 1 : 0;
        } elseif ($field === 'demo_user_id' || $field === 'slot') {
            $val = $val === null || $val === '' ? null : (int) $val;
        }
        $sets[] = $field . ' = ?';
        $params[] = $val;
    }

    if ($sets === []) {
        admin_api_error('Aucun champ à mettre à jour.');
    }

    $sets[] = "updated_at = datetime('now')";
    $params[] = $id;
    $pdo->prepare('UPDATE vehicles SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);

    demo_auth_admin_log('update_vehicle', ['vehicle_id' => $id]);

    admin_api_send(['success' => true]);
}

function handle_delete_vehicle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_api_error('Méthode non autorisée', 'method_not_allowed', 405);
    }
    if (db_is_demo_mode()) {
        admin_api_error('Suppression impossible en mode démo applicatif.', 'demo_mode', 409);
    }

    $body = admin_api_json_body();
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        admin_api_error('Véhicule invalide.');
    }

    try {
        $pdo = getDB();
    } catch (Throwable $e) {
        admin_api_error('Base véhicules indisponible.', 'storage_error', 500);
    }

    $pdo->prepare('DELETE FROM vehicles WHERE id = ?')->execute([$id]);
    demo_auth_admin_log('delete_vehicle', ['vehicle_id' => $id]);

    admin_api_send(['success' => true]);
}

function handle_save_debug_panel(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_api_error('Méthode non autorisée', 'method_not_allowed', 405);
    }
    if (!defined('APP_DEBUG') || !APP_DEBUG) {
        admin_api_error('APP_DEBUG doit être actif pour modifier le debug panel.', 'app_debug_required', 403);
    }

    $body = admin_api_json_body();
    $enabled = ($body['debug_panel'] ?? false) === true;
    $settings = getSettings();
    $settings['debug_panel'] = $enabled;
    if (!saveSettings($settings)) {
        admin_api_error('Impossible d\'enregistrer les paramètres.', 'save_failed', 500);
    }

    demo_auth_admin_log('save_debug_panel', ['debug_panel' => $enabled]);

    admin_api_send([
        'success' => true,
        'debug_panel' => $enabled,
    ]);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_csrf':
            handle_get_csrf();
            break;
        case 'dashboard_stats':
            handle_dashboard_stats();
            break;
        case 'list_users':
            handle_list_users();
            break;
        case 'create_user':
            handle_create_user();
            break;
        case 'update_user':
            handle_update_user();
            break;
        case 'reset_password':
            handle_reset_password();
            break;
        case 'delete_user':
            handle_delete_user();
            break;
        case 'list_tutorials':
            handle_list_tutorials();
            break;
        case 'get_tutorial':
            handle_get_tutorial();
            break;
        case 'list_conversations':
            handle_list_conversations();
            break;
        case 'get_conversation':
            handle_get_conversation();
            break;
        case 'list_vehicle_catalog':
            handle_list_vehicle_catalog();
            break;
        case 'list_vehicles':
            handle_list_vehicles();
            break;
        case 'create_vehicle':
            handle_create_vehicle();
            break;
        case 'update_vehicle':
            handle_update_vehicle();
            break;
        case 'delete_vehicle':
            handle_delete_vehicle();
            break;
        case 'save_debug_panel':
            handle_save_debug_panel();
            break;
        default:
            admin_api_error(
                'Action non reconnue.',
                'unknown_action',
                400
            );
    }
} catch (Throwable $e) {
    $msg = defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Erreur interne.';
    admin_api_error($msg, 'server_error', 500);
}
