<?php
/**
 * Diagnostic BDD (supprimer après debug) — nécessite APP_DEBUG.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_sqlite.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/demo_vehicles.php';

header('Content-Type: application/json; charset=utf-8');

if (!defined('APP_DEBUG') || !APP_DEBUG) {
    http_response_code(403);
    echo json_encode(['error' => 'APP_DEBUG requis']);
    exit;
}

$sqliteProbe = function_exists('mock_db_diag_probe_sqlite')
    ? mock_db_diag_probe_sqlite()
    : ['ok' => null, 'error' => 'mock_db_diag_unavailable'];

$out = [
    'sqlite_path' => SQLITE_PATH,
    'sqlite_exists' => is_file(SQLITE_PATH),
    'sqlite_writable' => is_file(SQLITE_PATH) && is_writable(SQLITE_PATH),
    'data_dir_writable' => is_writable(dirname(SQLITE_PATH)),
    'sqlite_probe_ok' => $sqliteProbe['ok'],
    'sqlite_probe_error' => $sqliteProbe['error'],
    'use_mock_db' => defined('USE_MOCK_DB') && USE_MOCK_DB,
    'use_mysql' => defined('USE_MYSQL') && USE_MYSQL,
    'demo_mode' => function_exists('isDemoMode') ? isDemoMode() : null,
    'settings_json_path' => defined('PATH_SETTINGS') ? PATH_SETTINGS : null,
    'settings_json_exists' => defined('PATH_SETTINGS') && is_file(PATH_SETTINGS),
    'demo_auth_enabled' => function_exists('isDemoAuthEnabled') ? isDemoAuthEnabled() : null,
    'mock_db_diag_log' => dirname(__DIR__) . '/data/mock_db_diag.log',
    'mock_db_diag_tail' => function_exists('mock_db_diag_tail_log') ? mock_db_diag_tail_log(20) : [],
];

try {
    $db = getDB();
    $out['getdb_driver'] = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $out['vehicles_columns'] = vehicleDemoSchemaListColumns($db);
    $out['demo_schema_ready'] = vehicleDemoSchemaIsReady($db);
    $db->query('SELECT v.demo_user_id FROM vehicles v LIMIT 1');
    $out['select_v_demo_user_id'] = 'ok';
} catch (Throwable $e) {
    $out['getdb_error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
