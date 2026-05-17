<?php
/**
 * MecaBuddy — Stream SSE du error_log PHP (tail en temps réel)
 * Accessible uniquement si APP_DEBUG === true
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/settings.php';

if (!defined('APP_DEBUG') || !APP_DEBUG || !isDebugPanelEnabled()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

while (ob_get_level()) {
    ob_end_clean();
}

ob_implicit_flush(true);
set_time_limit(0);

/**
 * @param array<string, mixed> $data
 */
function sseLog(array $data): void
{
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

function findErrorLog(): ?string
{
    $iniLog = ini_get('error_log');
    if (is_string($iniLog) && $iniLog !== '' && $iniLog !== 'syslog' && file_exists($iniLog) && is_readable($iniLog)) {
        return $iniLog;
    }

    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);

    $candidates = array_filter([
        is_string($iniLog) && $iniLog !== '' && $iniLog !== 'syslog' ? $iniLog : null,
        $basePath . '/logs/php_errors.log',
        sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php_errors.log',
        'C:/Program Files/Ampps/apache/logs/error.log',
        'C:/Program Files/Ampps/php/logs/php_error.log',
        'C:/AMPPS/apache/logs/error.log',
        'C:/AMPPS/php/logs/php_error.log',
        'C:/xampp/php/logs/php_error_log',
        'C:/xampp/apache/logs/error.log',
        '/var/log/apache2/error.log',
        '/var/log/httpd/error_log',
        '/tmp/php_errors.log',
    ]);

    foreach ($candidates as $path) {
        if ($path && file_exists($path) && is_readable($path) && !is_dir($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>
 */
function parseLogLine(string $line): array
{
    $line = trim($line);
    if ($line === '') {
        return [];
    }

    $level = 'info';
    $lower = strtolower($line);
    if (str_contains($lower, 'fatal error') || str_contains($lower, 'uncaught')) {
        $level = 'error';
    } elseif (str_contains($lower, 'warning') || str_contains($lower, ':warn')) {
        $level = 'warn';
    } elseif (str_contains($lower, 'notice') || str_contains($lower, 'deprecated')) {
        $level = 'notice';
    } elseif (str_contains($lower, 'mecabuddy')) {
        $level = 'app';
    }

    $timestamp = null;
    if (preg_match('/\[([^\]]+)\]/', $line, $m)) {
        $timestamp = $m[1];
    }

    $message = preg_replace('/^\[[^\]]+\]\s*/', '', $line);
    $message = preg_replace('/^PHP\s+/', '', (string) $message);

    return [
        'type' => 'log',
        'level' => $level,
        'timestamp' => $timestamp,
        'message' => $message,
        'raw' => $line,
    ];
}

$logFile = findErrorLog();
$checkedPaths = array_values(array_unique(array_filter([
    ini_get('error_log') ?: null,
    (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/logs/php_errors.log',
    sys_get_temp_dir() . '/php_errors.log',
    'C:/Program Files/Ampps/php/logs/php_error.log',
    'C:/AMPPS/php/logs/php_error.log',
])));

if ($logFile === null) {
    sseLog([
        'type' => 'meta',
        'level' => 'meta',
        'message' => 'Fichier error_log introuvable',
        'hint' => 'Vérifiez error_log dans php.ini ou créez logs/php_errors.log dans le projet',
        'checked' => $checkedPaths,
    ]);
}

sseLog([
    'type' => 'meta',
    'level' => 'meta',
    'message' => $logFile
        ? "Surveillance de : {$logFile}"
        : 'Aucun fichier error_log trouvé — en attente',
    'log_file' => $logFile,
]);

$lastSize = $logFile ? (int) filesize($logFile) : 0;
$lastInode = $logFile ? fileinode($logFile) : null;
$iterations = 0;
$maxIterations = 600;

while ($iterations < $maxIterations) {
    $iterations++;

    if ($logFile && file_exists($logFile)) {
        clearstatcache(true, $logFile);
        $currentSize = (int) filesize($logFile);
        $currentInode = fileinode($logFile);

        if ($lastInode !== null && $currentInode !== $lastInode) {
            sseLog([
                'type' => 'meta',
                'level' => 'meta',
                'message' => 'Rotation de fichier détectée',
            ]);
            $lastSize = 0;
            $lastInode = $currentInode;
        }

        if ($currentSize > $lastSize) {
            $fh = fopen($logFile, 'rb');
            if ($fh !== false) {
                fseek($fh, $lastSize);
                while (!feof($fh)) {
                    $line = fgets($fh);
                    if ($line === false) {
                        break;
                    }
                    $parsed = parseLogLine($line);
                    if ($parsed !== []) {
                        sseLog($parsed);
                    }
                }
                fclose($fh);
                $lastSize = $currentSize;
            }
        }
    }

    if ($iterations % 30 === 0) {
        echo ": heartbeat\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    sleep(1);

    if (connection_aborted()) {
        break;
    }
}
