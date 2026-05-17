<?php
/**
 * MecaBuddy - Configuration principale
 * 
 * Ce fichier centralise toutes les constantes de configuration
 * de l'application MecaBuddy.
 */

// ============================================
// CONFIGURATION BASE DE DONNÉES
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'mecabuddy');
define('DB_USER', 'root');
define('DB_PASS', 'mysql');  // À modifier selon votre configuration
define('DB_CHARSET', 'utf8mb4');
/** Passer à true pour forcer MySQL à la place de SQLite (priorité SQLite sinon). */
define('USE_MYSQL', false);

// ============================================
// CONFIGURATION APPLICATION
// ============================================
define('APP_NAME', 'MecaBuddy');
define('APP_VERSION', 'A-1.0.0');
define('APP_DEBUG', true); // mettre à true uniquement pour débugger

// ============================================
// CHEMINS DE L'APPLICATION
// ============================================
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('API_PATH', BASE_PATH . '/api');
define('PUBLIC_PATH', BASE_PATH . '/public');

// ============================================
// URL DE BASE (détection automatique)
// ============================================
if (!function_exists('mecabuddy_normalize_fs_path')) {
    function mecabuddy_normalize_fs_path(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }
        $path = str_replace('\\', '/', rtrim($path, '/'));

        return $path === '' ? '' : $path;
    }
}

if (!function_exists('asset_url')) {
    /**
     * URL absolue vers un asset ou une page sous la racine publique visible.
     */
    function asset_url(string $path = ''): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $base = rtrim(PUBLIC_URL, '/');

        return $path === '' ? $base : $base . '/' . $path;
    }
}

$protocol = 'http';
if (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
) {
    $protocol = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$projectRootFs = mecabuddy_normalize_fs_path(realpath(dirname(__DIR__)) ?: dirname(__DIR__));
$publicDirFs = mecabuddy_normalize_fs_path(realpath(PUBLIC_PATH) ?: PUBLIC_PATH);
$docRootFs = mecabuddy_normalize_fs_path(
    realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ($_SERVER['DOCUMENT_ROOT'] ?? '')
);

// Mode A : DocumentRoot = public/
$isPublicDocumentRoot = (
    $docRootFs !== ''
    && $publicDirFs !== ''
    && strcasecmp($docRootFs, $publicDirFs) === 0
);

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$scriptFilename = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
$scriptHasPublicSegment = (bool) preg_match('#/public(/|$)#i', $scriptName);
$isServedFromPublicDir = (
    $publicDirFs !== ''
    && $scriptFilename !== ''
    && str_starts_with(strtolower($scriptFilename), strtolower($publicDirFs . '/'))
);

if ($isPublicDocumentRoot) {
    $publicWebPath = str_replace('\\', '/', dirname($scriptName));
    if ($publicWebPath === '/' || $publicWebPath === '.' || $publicWebPath === '\\') {
        $publicWebPath = '';
    } else {
        $publicWebPath = rtrim($publicWebPath, '/');
    }
    $projectWebPath = $publicWebPath;
} else {
    $projectWebPath = '';
    if ($docRootFs !== '' && $projectRootFs !== '' && str_starts_with(strtolower($projectRootFs), strtolower($docRootFs))) {
        $projectWebPath = substr($projectRootFs, strlen($docRootFs));
    }
    $projectWebPath = '/' . trim($projectWebPath, '/');
    if ($projectWebPath === '/') {
        $projectWebPath = '';
    }

    // Mode B avec réécriture racine → public/ : URL visible sans /public
    if ($isServedFromPublicDir && !$scriptHasPublicSegment) {
        $publicWebPath = $projectWebPath;
    } else {
        $publicWebPath = rtrim($projectWebPath, '/') . '/public';
    }
}

if ($isPublicDocumentRoot) {
    $apiWebPath = rtrim($publicWebPath, '/') . '/api';
} else {
    $apiWebPath = rtrim($projectWebPath, '/') . '/api';
}

$publicWebUrl = $protocol . '://' . $host . $publicWebPath;
$apiWebUrl = $protocol . '://' . $host . $apiWebPath;

// BASE_URL = racine publique visible (pages + assets)
define('BASE_URL', $publicWebUrl);
define('PUBLIC_URL', $publicWebUrl);
define('API_URL', $apiWebUrl);
define('IS_PUBLIC_DOCUMENT_ROOT', $isPublicDocumentRoot);

if (APP_DEBUG) {
    error_log('[MecaBuddy] IS_PUBLIC_DOCUMENT_ROOT : ' . ($isPublicDocumentRoot ? 'yes' : 'no'));
    error_log('[MecaBuddy] BASE_URL (public) : ' . BASE_URL);
    error_log('[MecaBuddy] API_URL : ' . API_URL);
    error_log('[MecaBuddy] DOCUMENT_ROOT : ' . $docRootFs);
    error_log('[MecaBuddy] projectRoot : ' . $projectRootFs);
    error_log('[MecaBuddy] publicWebPath : ' . $publicWebPath);
}

// ============================================
// CONFIGURATION SESSION
// ============================================
define('SESSION_NAME', 'mecabuddy_session');
define('SESSION_LIFETIME', 3600 * 24); // 24 heures

// ============================================
// GESTION DES ERREURS
// ============================================
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ============================================
// TIMEZONE
// ============================================
date_default_timezone_set('Europe/Paris');

// Clé de chiffrement BYOK (fichier config/byok.key non versionné ou constante env)
if (!defined('BYOK_ENCRYPTION_KEY')) {
    $byokKeyFile = CONFIG_PATH . '/byok.key';
    $byokFromFile = is_readable($byokKeyFile) ? trim((string) file_get_contents($byokKeyFile)) : '';
    define('BYOK_ENCRYPTION_KEY', $byokFromFile);
}

require_once __DIR__ . '/settings.php';
