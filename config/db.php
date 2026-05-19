<?php
/**
 * MecaBuddy - Gestionnaire de connexion à la base de données
 *
 * Priorité : SQLite (fichier data/mecabuddy.sqlite) → MySQL si USE_MYSQL → Mock en secours.
 */

require_once __DIR__ . '/db_sqlite.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mock_db_diag.php';

/**
 * Mode démo : données applicatives (véhicules, tutoriels, etc.) via MockDatabase,
 * indépendamment de la disponibilité de SQLite. Les paramètres restent lus dans SQLite (table settings).
 */
function db_is_demo_mode(): bool
{
    try {
        if (function_exists('isDemoMode')) {
            return isDemoMode();
        }
        if (function_exists('getSetting')) {
            return getSetting('demo_mode') === 'true';
        }
    } catch (Throwable $e) {
        return false;
    }

    return false;
}

// Variable globale pour indiquer si on utilise le mock
define('USE_MOCK_DB', !isDatabaseAvailable());

/**
 * Vérifie si une base de données persistante (SQLite ou MySQL) est utilisable
 *
 * @return bool True si SQLite ou MySQL (si activé) est joignable
 */
function isDatabaseAvailable(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    $mysqlEnabled = defined('USE_MYSQL') && USE_MYSQL;
    $demoMode = db_is_demo_mode();

    if ($demoMode) {
        $available = false;
        mock_db_diag_log_unavailable([
            'reason' => 'demo_mode',
            'demo_mode' => true,
            'sqlite_ok' => false,
            'sqlite_error' => 'skipped_demo_mode',
            'mysql_enabled' => $mysqlEnabled,
            'mysql_ok' => false,
            'use_mock_db' => true,
        ]);

        return $available;
    }

    $sqliteError = null;

    try {
        getSQLite();
        $available = true;

        return $available;
    } catch (\Exception $e) {
        $sqliteError = $e->getMessage();
    }

    $mysqlOk = false;
    $mysqlError = null;
    $reason = 'sqlite_failed';

    if ($mysqlEnabled) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2,
            ]);
            unset($pdo);
            $available = true;

            return $available;
        } catch (PDOException $e) {
            $mysqlError = $e->getMessage();
            $reason = 'sqlite_and_mysql_failed';
        }
    } else {
        $reason = 'sqlite_failed_no_mysql_fallback';
    }

    $available = false;
    mock_db_diag_log_unavailable([
        'reason' => $reason,
        'demo_mode' => false,
        'sqlite_ok' => false,
        'sqlite_error' => $sqliteError,
        'mysql_enabled' => $mysqlEnabled,
        'mysql_ok' => $mysqlOk,
        'mysql_error' => $mysqlError,
        'use_mock_db' => true,
    ]);

    return $available;
}

/**
 * Classe Database - Singleton PDO
 *
 * Fournit une instance unique de connexion PDO pour toute l'application.
 */
class Database
{
    /** @var PDO|null Instance PDO unique */
    private static ?PDO $instance = null;

    private function __construct() {}

    private function __clone() {}

    /**
     * Obtient l'instance PDO unique (SQLite en priorité, puis MySQL si USE_MYSQL)
     *
     * @return PDO Instance de connexion à la base de données
     * @throws PDOException En cas d'erreur de connexion
     */
    public static function getInstance(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (db_is_demo_mode()) {
            throw new PDOException('Mode démo actif : les données applicatives passent par MockDatabase.');
        }

        try {
            self::$instance = getSQLite();

            return self::$instance;
        } catch (\Throwable $e) {
            if (defined('USE_MYSQL') && USE_MYSQL) {
                try {
                    $dsn = sprintf(
                        'mysql:host=%s;dbname=%s;charset=%s',
                        DB_HOST,
                        DB_NAME,
                        DB_CHARSET
                    );
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
                    ];
                    self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);

                    return self::$instance;
                } catch (PDOException $e2) {
                    if (APP_DEBUG) {
                        error_log('Erreur de connexion MySQL: ' . $e2->getMessage());
                    }
                    throw new PDOException('Impossible de se connecter à la base de données.');
                }
            }

            if (APP_DEBUG) {
                error_log('Erreur SQLite: ' . $e->getMessage());
            }
            throw new PDOException('Impossible de se connecter à la base de données.');
        }
    }

    /**
     * Ferme la connexion à la base de données
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}

/**
 * Fonction helper pour obtenir rapidement une connexion PDO
 *
 * @return PDO Instance de connexion
 * @throws PDOException Si la connexion échoue et qu'on n'est pas en mode mock
 */
function getDB(): PDO
{
    $pdo = Database::getInstance();

    if (!function_exists('migrateVehicleDemoSchema')) {
        require_once dirname(__DIR__) . '/includes/demo_vehicles.php';
    }
    migrateVehicleDemoSchema($pdo);

    return $pdo;
}

/**
 * Vérifie si on utilise le mode mock
 */
function isUsingMock(): bool
{
    return USE_MOCK_DB;
}

// Charge automatiquement le mock si nécessaire
if (USE_MOCK_DB) {
    require_once __DIR__ . '/mock_db.php';
}
