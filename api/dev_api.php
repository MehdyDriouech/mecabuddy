<?php
/**
 * MecaBuddy — API outils développeur / admin POC
 *
 * Endpoints JSON pour public/dev.php (administration POC).
 *
 * - POST ?action=save_settings — corps JSON des paramètres
 * - GET  ?action=get_settings — état courant (getSettings)
 * - GET  ?action=test_plate — test plaque AB-123-CD (includes/plate_lookup.php)
 * - GET  ?action=test_llm&provider_id= — ping LLM « Réponds juste OK » (includes/llm_bridge.php)
 * - GET  ?action=test_search — test recherche web Serper / DuckDuckGo (includes/llm_chat.php)
 * - POST ?action=rebuild_db — migration catalogue SQLite + INSERT OR IGNORE
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
require_once __DIR__ . '/../config/db_sqlite.php';
require_once __DIR__ . '/../includes/demo_auth.php';
require_once __DIR__ . '/../includes/demo_vehicles.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * @param array<string, mixed> $data
 */
function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function sendError(string $message, int $statusCode = 400): void
{
    sendResponse([
        'success' => false,
        'error' => $message,
    ], $statusCode);
}

/**
 * Masque les secrets avant envoi au navigateur (admin POC).
 *
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function dev_api_mask_settings_for_client(array $settings): array
{
    if (!empty($settings['serper_api_key'])) {
        $settings['serper_api_key'] = '';
        $settings['serper_api_key_configured'] = true;
    }

    if (isset($settings['plate_lookup']) && is_array($settings['plate_lookup'])) {
        if (!empty($settings['plate_lookup']['api_key'])) {
            $settings['plate_lookup']['api_key'] = '';
            $settings['plate_lookup']['api_key_configured'] = true;
        }
    }

    if (isset($settings['llm_providers']) && is_array($settings['llm_providers'])) {
        foreach ($settings['llm_providers'] as $idx => $provider) {
            if (!is_array($provider) || empty($provider['api_key'])) {
                continue;
            }
            $settings['llm_providers'][$idx]['api_key'] = '';
            $settings['llm_providers'][$idx]['api_key_configured'] = true;
        }
    }

    return $settings;
}

/**
 * @param array<int, mixed> $list
 * @return array<int, array<string, mixed>>
 */
function dev_api_sanitize_providers(array $list): array
{
    $prevList = getSettings()['llm_providers'] ?? [];
    $prevById = [];
    if (is_array($prevList)) {
        foreach ($prevList as $op) {
            if (is_array($op) && isset($op['id'])) {
                $prevById[(string) $op['id']] = $op;
            }
        }
    }

    $clean = [];
    foreach ($list as $p) {
        if (!is_array($p)) {
            continue;
        }
        $type = $p['type'] ?? 'ollama';
        if (!in_array($type, ['ollama', 'openai_compatible', 'mistral'], true)) {
            $type = 'ollama';
        }
        $id = substr((string) ($p['id'] ?? ''), 0, 96);
        $old = $prevById[$id] ?? [];
        if (array_key_exists('api_key', $p)) {
            $apiKey = substr(trim((string) $p['api_key']), 0, 512);
            if ($apiKey === '' && !empty($old['api_key'])) {
                $apiKey = substr((string) $old['api_key'], 0, 512);
            }
        } else {
            $apiKey = substr((string) ($old['api_key'] ?? ''), 0, 512);
        }

        $chatPath = '';
        if (array_key_exists('chat_path', $p)) {
            $chatPath = substr(trim((string) $p['chat_path']), 0, 128);
        } elseif (!empty($old['chat_path'])) {
            $chatPath = substr(trim((string) $old['chat_path']), 0, 128);
        }

        $row = [
            'id' => $id,
            'name' => substr((string) ($p['name'] ?? ''), 0, 160),
            'type' => $type,
            'base_url' => substr((string) ($p['base_url'] ?? ''), 0, 512),
            'model' => substr((string) ($p['model'] ?? ''), 0, 160),
            'active' => ($p['active'] ?? false) === true,
            'api_key' => $apiKey,
        ];
        if ($chatPath !== '') {
            $row['chat_path'] = $chatPath[0] === '/' ? $chatPath : '/' . $chatPath;
        } elseif ($type === 'openai_compatible') {
            $base = strtolower((string) ($row['base_url'] ?? ''));
            $row['chat_path'] = str_contains($base, 'generativelanguage.googleapis.com')
                ? '/chat/completions'
                : '/v1/chat/completions';
        }

        $clean[] = $row;
    }

    return $clean;
}

/**
 * @param array<string, mixed> $in
 * @return array<string, mixed>
 */
function dev_api_normalize_settings(array $in): array
{
    $defaults = getDefaultSettings();
    $out = $defaults;

    if (isset($in['plate_lookup']) && is_array($in['plate_lookup'])) {
        $pl = $in['plate_lookup'];
        $out['plate_lookup'] = [
            'enabled' => ($pl['enabled'] ?? false) === true,
            'provider' => substr((string) ($pl['provider'] ?? $defaults['plate_lookup']['provider']), 0, 64),
            'api_key' => substr((string) ($pl['api_key'] ?? ''), 0, 512),
        ];
    }

    if (array_key_exists('llm_fallback_enabled', $in)) {
        $out['llm_fallback_enabled'] = ($in['llm_fallback_enabled'] ?? false) === true;
    }

    if (array_key_exists('demo_mode', $in)) {
        $out['demo_mode'] = ($in['demo_mode'] ?? false) === true;
    }

    if (array_key_exists('demo_auth_enabled', $in)) {
        $out['demo_auth_enabled'] = ($in['demo_auth_enabled'] ?? false) === true;
    }

    if (array_key_exists('dev_mode', $in)) {
        $out['dev_mode'] = ($in['dev_mode'] ?? false) === true;
    }

    if (array_key_exists('byok_enabled', $in)) {
        $out['byok_enabled'] = ($in['byok_enabled'] ?? false) === true;
    }

    if (array_key_exists('debug_panel', $in)) {
        $out['debug_panel'] = ($in['debug_panel'] ?? false) === true;
    }

    if (array_key_exists('serper_api_key', $in)) {
        $out['serper_api_key'] = substr((string) ($in['serper_api_key'] ?? ''), 0, 256);
    }

    if (isset($in['llm_providers']) && is_array($in['llm_providers'])) {
        $out['llm_providers'] = dev_api_sanitize_providers($in['llm_providers']);
    }

    if (isset($in['provider_limits']) && is_array($in['provider_limits'])
        && isset($in['provider_limits']['gemini']) && is_array($in['provider_limits']['gemini'])) {
        $g = $in['provider_limits']['gemini'];
        $out['provider_limits']['gemini'] = [
            'rpm' => max(1, min(999, (int) ($g['rpm'] ?? $defaults['provider_limits']['gemini']['rpm']))),
            'rpd' => max(1, min(99999, (int) ($g['rpd'] ?? $defaults['provider_limits']['gemini']['rpd']))),
            'input_tpm' => max(1, min(999999999, (int) ($g['input_tpm'] ?? $defaults['provider_limits']['gemini']['input_tpm']))),
            'display_enabled' => ($g['display_enabled'] ?? true) === true,
        ];
    }

    if ($out['llm_providers'] !== []) {
        $anyActive = false;
        foreach ($out['llm_providers'] as $row) {
            if (($row['active'] ?? false) === true) {
                $anyActive = true;
                break;
            }
        }
        if (!$anyActive) {
            $out['llm_providers'][0]['active'] = true;
        }
    }

    return $out;
}

function handle_save_settings(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Méthode non autorisée', 405);
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        sendError('Corps JSON vide');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        sendError('JSON invalide');
    }

    $normalized = dev_api_normalize_settings($decoded);
    if (!saveSettings($normalized)) {
        sendError('Impossible d\'enregistrer les paramètres (SQLite)', 500);
    }

    sendResponse([
        'success' => true,
        'settings' => dev_api_mask_settings_for_client(getSettings()),
    ]);
}

function handle_get_settings(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendError('Méthode non autorisée', 405);
    }

    sendResponse(dev_api_mask_settings_for_client(getSettings()));
}

function handle_test_plate(): void
{
    require_once __DIR__ . '/../includes/plate_lookup.php';

    $settings = getSettings();
    $plate = 'AB-123-CD';
    $config = [
        'api_key' => (string) ($settings['plate_lookup']['api_key'] ?? ''),
    ];

    $lookup = lookupPlate($plate, $config);

    sendResponse([
        'success' => true,
        'plate' => $plate,
        'raw_body' => $lookup['raw_body'] ?? '',
        'http_code' => $lookup['http_code'] ?? null,
        'mapped' => [
            'found' => $lookup['found'],
            'source' => $lookup['source'],
            'brand' => $lookup['brand'],
            'model' => $lookup['model'],
            'year' => $lookup['year'],
            'engine' => $lookup['engine'],
            'fuel' => $lookup['fuel'],
            'transmission' => $lookup['transmission'],
            'error' => $lookup['error'] ?? null,
        ],
    ]);
}

function handle_test_search(): void
{
    require_once __DIR__ . '/../includes/llm_chat.php';

    $query = trim((string) ($_GET['q'] ?? 'changer plaquettes de frein Peugeot 308'));
    if ($query === '') {
        $query = 'changer plaquettes de frein Peugeot 308';
    }

    sendResponse(mecabuddy_probe_web_search($query));
}

/**
 * @return list<string>
 */
function rebuild_db_table_names(PDO $pdo): array
{
    return $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
}

function rebuild_db_table_exists(PDO $pdo, string $table): bool
{
    return in_array($table, rebuild_db_table_names($pdo), true);
}

/**
 * @param list<string> $columns
 */
function rebuild_db_has_unique_on_columns(PDO $pdo, string $table, array $columns): bool
{
    $expected = $columns;
    sort($expected);

    $indexes = $pdo->query('PRAGMA index_list(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($indexes as $idx) {
        if ((int) ($idx['unique'] ?? 0) !== 1) {
            continue;
        }
        $indexName = str_replace("'", "''", (string) ($idx['name'] ?? ''));
        $info = $pdo->query('PRAGMA index_info(' . $indexName . ')')->fetchAll(PDO::FETCH_ASSOC);
        $indexCols = array_column($info, 'name');
        sort($indexCols);
        if ($indexCols === $expected) {
            return true;
        }
    }

    return false;
}

/**
 * @return array{brands: int, models: int}
 */
function rebuild_db_count_duplicates(PDO $pdo): array
{
    $dupBrands = 0;
    $dupModels = 0;

    if (rebuild_db_table_exists($pdo, 'vehicle_brands')) {
        $dupBrands = (int) $pdo->query(
            'SELECT COUNT(*) - COUNT(DISTINCT name) FROM vehicle_brands'
        )->fetchColumn();
    }
    if (rebuild_db_table_exists($pdo, 'vehicle_models')) {
        $dupModels = (int) $pdo->query(
            "SELECT COUNT(*) - COUNT(DISTINCT brand_id || '|' || name) FROM vehicle_models"
        )->fetchColumn();
    }

    return [
        'brands' => max(0, $dupBrands),
        'models' => max(0, $dupModels),
    ];
}

function rebuild_db_recover_orphan_tables(PDO $pdo): void
{
    $tables = rebuild_db_table_names($pdo);

    if (in_array('vehicle_models_old', $tables, true)) {
        if (in_array('vehicle_models', $tables, true)) {
            $pdo->exec('DROP TABLE IF EXISTS vehicle_models_old');
        } else {
            $pdo->exec('ALTER TABLE vehicle_models_old RENAME TO vehicle_models');
        }
    }

    if (in_array('vehicle_brands_old', $tables, true)) {
        if (in_array('vehicle_brands', $tables, true)) {
            $pdo->exec('DROP TABLE IF EXISTS vehicle_brands_old');
        } else {
            $pdo->exec('ALTER TABLE vehicle_brands_old RENAME TO vehicle_brands');
        }
    }
}

/**
 * engine_types peut référencer vehicle_models_old après une migration interrompue.
 */
function rebuild_db_reset_engine_types_if_stale(PDO $pdo): void
{
    if (!rebuild_db_table_exists($pdo, 'engine_types')) {
        return;
    }
    $fks = $pdo->query('PRAGMA foreign_key_list(engine_types)')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fks as $fk) {
        if (($fk['table'] ?? '') === 'vehicle_models_old') {
            $pdo->exec('DROP TABLE engine_types');
            return;
        }
    }
}

function rebuild_db_deduplicate_catalog(PDO $pdo): void
{
    $pdo->exec('BEGIN TRANSACTION');

    if (rebuild_db_table_exists($pdo, 'vehicle_models')) {
        $pdo->exec('
            DELETE FROM vehicle_models
            WHERE id NOT IN (
                SELECT MIN(id)
                FROM vehicle_models
                GROUP BY brand_id, name
            )
        ');
    }

    if (rebuild_db_table_exists($pdo, 'vehicle_brands')) {
        if (rebuild_db_table_exists($pdo, 'vehicle_models')) {
            $pdo->exec('
                UPDATE vehicle_models
                SET brand_id = (
                    SELECT MIN(vb2.id)
                    FROM vehicle_brands vb2
                    WHERE vb2.name = (
                        SELECT vb.name FROM vehicle_brands vb WHERE vb.id = vehicle_models.brand_id
                    )
                )
                WHERE brand_id IN (
                    SELECT id FROM vehicle_brands
                    WHERE id NOT IN (
                        SELECT MIN(id) FROM vehicle_brands GROUP BY name
                    )
                )
            ');
        }
        $pdo->exec('
            DELETE FROM vehicle_brands
            WHERE id NOT IN (
                SELECT MIN(id)
                FROM vehicle_brands
                GROUP BY name
            )
        ');
    }

    $pdo->exec('COMMIT');
}

function rebuild_db_migrate_vehicle_models_unique(PDO $pdo): string
{
    if (!rebuild_db_table_exists($pdo, 'vehicle_models')) {
        return 'skipped_no_table';
    }
    if (rebuild_db_has_unique_on_columns($pdo, 'vehicle_models', ['brand_id', 'name'])) {
        return 'already_present';
    }

    if (rebuild_db_table_exists($pdo, 'engine_types')) {
        $pdo->exec('DROP TABLE engine_types');
    }

    $pdo->exec('BEGIN TRANSACTION');
    $pdo->exec('ALTER TABLE vehicle_models RENAME TO vehicle_models_old');
    $pdo->exec('CREATE TABLE vehicle_models (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        brand_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        year_start INTEGER DEFAULT NULL,
        year_end INTEGER DEFAULT NULL,
        is_active INTEGER DEFAULT 1,
        FOREIGN KEY (brand_id) REFERENCES vehicle_brands(id) ON DELETE CASCADE,
        UNIQUE(brand_id, name)
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_brand_id ON vehicle_models(brand_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_name_models ON vehicle_models(name)');
    $pdo->exec('INSERT INTO vehicle_models
        SELECT id, brand_id, name, year_start, year_end, is_active
        FROM vehicle_models_old');
    $pdo->exec('DROP TABLE vehicle_models_old');
    $pdo->exec('COMMIT');

    return 'migrated';
}

function rebuild_db_migrate_vehicle_brands_unique(PDO $pdo): string
{
    if (!rebuild_db_table_exists($pdo, 'vehicle_brands')) {
        return 'skipped_no_table';
    }
    if (rebuild_db_has_unique_on_columns($pdo, 'vehicle_brands', ['name'])) {
        return 'already_present';
    }

    $cols = $pdo->query('PRAGMA table_info(vehicle_brands)')->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    $hasCategory = in_array('category', $colNames, true);

    $pdo->exec('BEGIN TRANSACTION');
    $pdo->exec('ALTER TABLE vehicle_brands RENAME TO vehicle_brands_old');

    if ($hasCategory) {
        $pdo->exec("CREATE TABLE vehicle_brands (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            country TEXT DEFAULT NULL,
            logo_url TEXT DEFAULT NULL,
            is_active INTEGER DEFAULT 1,
            category TEXT DEFAULT 'car' CHECK(category IN ('car','moto'))
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vb_name ON vehicle_brands(name)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vb_category ON vehicle_brands(category)');
        $pdo->exec("INSERT INTO vehicle_brands (id, name, country, logo_url, is_active, category)
            SELECT id, name, country, logo_url, is_active, COALESCE(category, 'car')
            FROM vehicle_brands_old");
    } else {
        $pdo->exec("CREATE TABLE vehicle_brands (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            country TEXT DEFAULT NULL,
            logo_url TEXT DEFAULT NULL,
            is_active INTEGER DEFAULT 1
        )");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vb_name ON vehicle_brands(name)');
        $pdo->exec("INSERT INTO vehicle_brands (id, name, country, logo_url, is_active)
            SELECT id, name, country, logo_url, is_active
            FROM vehicle_brands_old");
    }

    $pdo->exec('DROP TABLE vehicle_brands_old');
    $pdo->exec('COMMIT');

    return 'migrated';
}

/**
 * Migration category + contraintes UNIQUE + INSERT OR IGNORE du schéma / seed catalogue.
 */
function handle_rebuild_db(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Méthode non autorisée', 405);
    }

    $pdo = null;
    $categoryColStatus = 'already_present';
    $modelsUniqueStatus = 'already_present';
    $brandsUniqueStatus = 'already_present';

    try {
        $pdo = getSQLite();
        $pdo->exec('PRAGMA foreign_keys = OFF');

        rebuild_db_recover_orphan_tables($pdo);
        rebuild_db_reset_engine_types_if_stale($pdo);

        $duplicatesRemoved = rebuild_db_count_duplicates($pdo);
        rebuild_db_deduplicate_catalog($pdo);

        $cols = $pdo->query('PRAGMA table_info(vehicle_brands)')->fetchAll(PDO::FETCH_ASSOC);
        $hasCategory = in_array('category', array_column($cols, 'name'), true);

        if (!$hasCategory && rebuild_db_table_exists($pdo, 'vehicle_brands')) {
            $pdo->exec('BEGIN TRANSACTION');
            $pdo->exec('ALTER TABLE vehicle_brands RENAME TO vehicle_brands_old');
            $pdo->exec("CREATE TABLE vehicle_brands (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                country TEXT DEFAULT NULL,
                logo_url TEXT DEFAULT NULL,
                is_active INTEGER DEFAULT 1,
                category TEXT DEFAULT 'car' CHECK(category IN ('car','moto'))
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vb_name ON vehicle_brands(name)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vb_category ON vehicle_brands(category)');
            $pdo->exec("INSERT INTO vehicle_brands (id, name, country, logo_url, is_active, category)
                SELECT id, name, country, logo_url, is_active, 'car'
                FROM vehicle_brands_old");
            $pdo->exec('DROP TABLE vehicle_brands_old');
            $pdo->exec('COMMIT');
            $categoryColStatus = 'migrated';
            $hasCategory = true;
        }

        $brandsUniqueStatus = rebuild_db_migrate_vehicle_brands_unique($pdo);
        $modelsUniqueStatus = rebuild_db_migrate_vehicle_models_unique($pdo);
        rebuild_db_reset_engine_types_if_stale($pdo);

        $vcols = $pdo->query('PRAGMA table_info(vehicles)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (rebuild_db_table_exists($pdo, 'vehicles')) {
            if (!in_array('is_active', $vcols, true)) {
                $pdo->exec('ALTER TABLE vehicles ADD COLUMN is_active INTEGER DEFAULT 0');
            }
            if (!in_array('slot', $vcols, true)) {
                $pdo->exec('ALTER TABLE vehicles ADD COLUMN slot INTEGER DEFAULT NULL');
            }
        }

        if (!rebuild_db_table_exists($pdo, 'engine_types')) {
            $pdo->exec("CREATE TABLE engine_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                model_id INTEGER NOT NULL,
                label TEXT NOT NULL,
                fuel_type TEXT NOT NULL CHECK(fuel_type IN ('essence','diesel','hybride','electrique','gpl')),
                displacement TEXT DEFAULT NULL,
                power_hp INTEGER DEFAULT NULL,
                year_start INTEGER DEFAULT NULL,
                year_end INTEGER DEFAULT NULL,
                FOREIGN KEY (model_id) REFERENCES vehicle_models(id) ON DELETE CASCADE,
                UNIQUE (model_id, label)
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_et_model_id ON engine_types(model_id)');
        }

        $pdo->exec('PRAGMA foreign_keys = ON');

        $inserted = 0;
        $sqlSources = [
            __DIR__ . '/../sql/schema_sqlite.sql',
            __DIR__ . '/../sql/seed_vehicle_catalog.sql',
        ];

        $pdo->exec('BEGIN TRANSACTION');
        foreach ($sqlSources as $sqlPath) {
            if (!is_readable($sqlPath)) {
                continue;
            }
            $schema = file_get_contents($sqlPath);
            if ($schema === false || $schema === '') {
                continue;
            }
            if (!preg_match_all(
                '/INSERT\s+OR\s+IGNORE\s+INTO\s+(?:vehicle_brands|vehicle_models|engine_types)[^;]+;/si',
                $schema,
                $matches
            )) {
                continue;
            }
            foreach ($matches[0] as $stmt) {
                try {
                    $pdo->exec($stmt);
                    $inserted++;
                } catch (PDOException $e) {
                    error_log('[MecaBuddy][rebuild_db] INSERT ignoré : ' . $e->getMessage());
                }
            }
        }
        $pdo->exec('COMMIT');

        $pdo->exec("UPDATE vehicle_brands SET category = 'car' WHERE category IS NULL OR category = ''");

        $brandCount = (int) $pdo->query('SELECT COUNT(*) FROM vehicle_brands')->fetchColumn();
        $modelCount = (int) $pdo->query('SELECT COUNT(*) FROM vehicle_models')->fetchColumn();
        $engineCount = (int) $pdo->query('SELECT COUNT(*) FROM engine_types')->fetchColumn();
        $motoCount = (int) $pdo->query("SELECT COUNT(*) FROM vehicle_brands WHERE category='moto'")->fetchColumn();

        sendResponse([
            'success' => true,
            'rebuilt' => true,
            'category_col' => $categoryColStatus,
            'models_unique' => $modelsUniqueStatus,
            'brands_unique' => $brandsUniqueStatus,
            'duplicates_removed' => $duplicatesRemoved,
            'brands' => $brandCount,
            'brands_moto' => $motoCount,
            'models' => $modelCount,
            'engine_types' => $engineCount,
            'inserts_run' => $inserted,
        ]);
    } catch (PDOException $e) {
        if ($pdo instanceof PDO) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (PDOException $ignored) {
            }
            try {
                $pdo->exec('PRAGMA foreign_keys = ON');
            } catch (PDOException $ignored) {
            }
        }
        sendError('Erreur rebuild_db : ' . $e->getMessage(), 500);
    }
}

function handle_test_llm(): void
{
    require_once __DIR__ . '/../includes/llm_bridge.php';

    $id = (string) ($_GET['provider_id'] ?? '');
    if ($id === '') {
        sendError('Paramètre provider_id manquant');
    }

    $settings = getSettings();
    $providers = $settings['llm_providers'] ?? [];
    if (!is_array($providers)) {
        sendError('Aucun fournisseur configuré', 404);
    }

    $found = null;
    foreach ($providers as $p) {
        if (is_array($p) && (($p['id'] ?? '') === $id)) {
            $found = $p;
            break;
        }
    }

    if ($found === null) {
        sendError('Fournisseur introuvable', 404);
    }

    $r = testLlmProviderPrompt($found, 'Réponds juste OK');

    $payload = [
        'success' => true,
        'ok' => $r['ok'],
        'latency_ms' => $r['latency_ms'],
        'response' => $r['response'],
        'error' => $r['error'] ?? null,
        'provider_status' => $r['http_code'] ?? null,
        'user_message' => $r['user_message'] ?? null,
    ];
    if (defined('APP_DEBUG') && APP_DEBUG) {
        if (!defined('LLM_CHAT_LOADED')) {
            require_once __DIR__ . '/../includes/llm_chat.php';
        }
        $prepared = _mecabuddyPrepareLlmCall(
            $found,
            [['role' => 'user', 'content' => 'Réponds juste OK']],
            ['temperature' => 0]
        );
        if ($prepared !== null) {
            $payload['debug'] = [
                'request_url' => $prepared['url'],
                'chat_path' => $prepared['chat_path'] ?? null,
            ];
        }
        if (isset($r['request_url'])) {
            $payload['debug']['request_url'] = $r['request_url'];
        }
    }

    sendResponse($payload);
}

function handle_get_demo_users(): void
{
    sendResponse([
        'success' => true,
        'demo_auth_enabled' => isDemoAuthEnabled(),
        'users' => demo_auth_list_users_with_usage(),
        'reset_at' => demo_auth_reset_at(),
    ]);
}

function handle_reset_demo_usage_today(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Méthode non autorisée', 405);
    }
    $deleted = demo_auth_reset_usage_today();
    sendResponse([
        'success' => true,
        'deleted_rows' => $deleted,
        'users' => demo_auth_list_users_with_usage(),
    ]);
}

function handle_rebuild_demo_users(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Méthode non autorisée', 405);
    }
    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        sendError('SQLite indisponible pour les comptes démo', 500);
    }
    demo_auth_seed_users($pdo);
    sendResponse([
        'success' => true,
        'users' => demo_auth_list_users_with_usage(),
    ]);
}

function handle_reset_demo_vehicles(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Méthode non autorisée', 405);
    }
    $deleted = resetDemoSeedVehicles(null);
    sendResponse([
        'success' => true,
        'deleted' => $deleted,
        'accounts' => demo_vehicles_status_all_accounts(),
    ]);
}

function handle_rebuild_demo_vehicles(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Méthode non autorisée', 405);
    }
    $accounts = rebuildAllDemoSeedVehicles();
    sendResponse([
        'success' => true,
        'accounts' => demo_vehicles_status_all_accounts(),
        'rebuilt' => $accounts,
    ]);
}

function handle_get_demo_garages(): void
{
    sendResponse([
        'success' => true,
        'accounts' => demo_vehicles_status_all_accounts(),
    ]);
}

function handle_get_byok_stats(): void
{
    require_once __DIR__ . '/../includes/byok.php';

    sendResponse([
        'success' => true,
        'stats' => byok_admin_stats(),
    ]);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!defined('APP_DEBUG') || !APP_DEBUG) {
    sendError('Administration POC disponible uniquement si APP_DEBUG est actif dans config/config.php', 403);
}

try {
    switch ($action) {
        case 'save_settings':
            handle_save_settings();
            break;
        case 'get_settings':
            handle_get_settings();
            break;
        case 'test_plate':
            handle_test_plate();
            break;
        case 'test_llm':
            handle_test_llm();
            break;
        case 'test_search':
            handle_test_search();
            break;
        case 'rebuild_db':
            handle_rebuild_db();
            break;
        case 'get_demo_users':
            handle_get_demo_users();
            break;
        case 'reset_demo_usage_today':
            handle_reset_demo_usage_today();
            break;
        case 'rebuild_demo_users':
            handle_rebuild_demo_users();
            break;
        case 'reset_demo_vehicles':
            handle_reset_demo_vehicles();
            break;
        case 'rebuild_demo_vehicles':
            handle_rebuild_demo_vehicles();
            break;
        case 'get_demo_garages':
            handle_get_demo_garages();
            break;
        case 'get_byok_stats':
            handle_get_byok_stats();
            break;
        default:
            sendError('Action non reconnue. Actions disponibles: save_settings, get_settings, test_plate, test_llm, test_search, rebuild_db, get_demo_users, reset_demo_usage_today, rebuild_demo_users, reset_demo_vehicles, rebuild_demo_vehicles, get_demo_garages, get_byok_stats', 400);
    }
} catch (Throwable $e) {
    if (APP_DEBUG) {
        sendError('Erreur serveur: ' . $e->getMessage(), 500);
    } else {
        sendError('Erreur interne du serveur', 500);
    }
}
