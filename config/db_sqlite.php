<?php
/**
 * MecaBuddy - Connexion SQLite (PDO) pour le POC
 */

define('SQLITE_PATH', dirname(__DIR__) . '/data/mecabuddy.sqlite');

/**
 * @return PDO
 * @throws PDOException
 */
function initSQLite(): PDO
{
    $dataDir = dirname(SQLITE_PATH);
    if (!is_dir($dataDir)) {
        if (!@mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
            throw new PDOException('Impossible de créer le répertoire data/ pour SQLite.');
        }
    }

    $pdo = new PDO('sqlite:' . SQLITE_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
    $hasSettings = $stmt && $stmt->fetchColumn() !== false;

    if (!$hasSettings) {
        $schemaFile = dirname(__DIR__) . '/sql/schema_sqlite.sql';
        if (!is_readable($schemaFile)) {
            throw new PDOException('Fichier de schéma SQLite introuvable ou illisible : ' . $schemaFile);
        }
        $sql = file_get_contents($schemaFile);
        if ($sql === false || $sql === '') {
            throw new PDOException('Impossible de lire sql/schema_sqlite.sql.');
        }
        $pdo->exec($sql);
    }

    migrateSQLiteVehicleCatalog($pdo);
    migrateSQLiteDemoAuth($pdo);

    if (!function_exists('migrateVehicleDemoSchema')) {
        require_once dirname(__DIR__) . '/includes/demo_vehicles.php';
    }
    migrateVehicleDemoSchema($pdo);

    return $pdo;
}

/**
 * Tables auth démo + seed des comptes (idempotent).
 */
function migrateSQLiteDemoAuth(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='demo_users'"
    );
    if (!$stmt || $stmt->fetchColumn() === false) {
        $migrationFile = dirname(__DIR__) . '/sql/migrate_demo_auth.sql';
        if (is_readable($migrationFile)) {
            $sql = file_get_contents($migrationFile);
            if ($sql !== false && trim($sql) !== '') {
                $pdo->exec($sql);
            }
        }
    }

    if (function_exists('demo_auth_seed_users')) {
        demo_auth_seed_users($pdo);
    } else {
        require_once dirname(__DIR__) . '/includes/demo_auth.php';
        demo_auth_seed_users($pdo);
    }

    if (!function_exists('migrateSQLiteDemoUserSettings')) {
        require_once dirname(__DIR__) . '/includes/demo_user_settings.php';
    }
    migrateSQLiteDemoUserSettings($pdo);

    if (!function_exists('migrateSQLiteDemoByok')) {
        require_once dirname(__DIR__) . '/includes/byok.php';
    }
    migrateSQLiteDemoByok($pdo);
}

/** Version du seed catalogue — incrémenter si sql/seed_vehicle_catalog.sql change. */
const VEHICLE_CATALOG_SEED_VERSION = '3';

/**
 * Migrations catalogue véhicules (BDD existantes) + seed idempotent.
 */
function migrateSQLiteVehicleCatalog(PDO $pdo): void
{
    $stmt = $pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='vehicle_brands'"
    );
    if (!$stmt || $stmt->fetchColumn() === false) {
        return;
    }

    $hasCategory = false;
    foreach ($pdo->query('PRAGMA table_info(vehicle_brands)') as $col) {
        if (($col['name'] ?? '') === 'category') {
            $hasCategory = true;
            break;
        }
    }
    if (!$hasCategory) {
        $pdo->exec("ALTER TABLE vehicle_brands ADD COLUMN category TEXT DEFAULT 'car'");
    }
    $pdo->exec("UPDATE vehicle_brands SET category = 'car' WHERE category IS NULL OR category = ''");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vb_name ON vehicle_brands(name)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vb_category ON vehicle_brands(category)');

    $pdo->exec("CREATE TABLE IF NOT EXISTS engine_types (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        model_id INTEGER NOT NULL,
        label TEXT NOT NULL,
        fuel_type TEXT NOT NULL CHECK (fuel_type IN ('essence', 'diesel', 'hybride', 'electrique', 'gpl')),
        displacement TEXT DEFAULT NULL,
        power_hp INTEGER DEFAULT NULL,
        year_start INTEGER DEFAULT NULL,
        year_end INTEGER DEFAULT NULL,
        FOREIGN KEY (model_id) REFERENCES vehicle_models(id) ON DELETE CASCADE,
        UNIQUE (model_id, label)
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_et_model_id ON engine_types(model_id)');

    applyVehicleCatalogSeed($pdo);
}

/**
 * Applique sql/seed_vehicle_catalog.sql une fois par version (INSERT OR IGNORE).
 */
function applyVehicleCatalogSeed(PDO $pdo): void
{
    $versionKey = 'vehicle_catalog_seed_version';
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$versionKey]);
    $current = $stmt->fetchColumn();
    if ($current === VEHICLE_CATALOG_SEED_VERSION) {
        return;
    }

    $seedFile = dirname(__DIR__) . '/sql/seed_vehicle_catalog.sql';
    if (!is_readable($seedFile)) {
        return;
    }
    $sql = file_get_contents($seedFile);
    if ($sql === false || trim($sql) === '') {
        return;
    }
    $pdo->exec($sql);

    $upsert = $pdo->prepare(
        "INSERT INTO settings (key, value, updated_at) VALUES (?, ?, datetime('now'))
         ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at"
    );
    $upsert->execute([$versionKey, VEHICLE_CATALOG_SEED_VERSION]);
}

/**
 * Singleton PDO SQLite
 *
 * @return PDO
 * @throws PDOException
 */
function getSQLite(): PDO
{
    static $instance = null;
    if ($instance === null) {
        $instance = initSQLite();
    }
    return $instance;
}
