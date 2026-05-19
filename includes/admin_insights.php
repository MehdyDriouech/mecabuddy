<?php
/**
 * MecaBuddy — Helpers admin (tutoriels, conversations, logs error_log)
 */

declare(strict_types=1);

/**
 * @return mixed
 */
function admin_insights_json_decode(?string $raw)
{
    if ($raw === null || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);

    return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
}

/**
 * Dernières lignes du fichier error_log (fenêtre autour de created_at si possible).
 *
 * @return array{available: bool, message?: string, log_file?: string, lines?: list<string>, hint?: string}
 */
function admin_insights_error_log_snippet(?string $createdAt, ?string $sessionId = null, int $windowMinutes = 20): array
{
    if (!defined('APP_DEBUG') || !APP_DEBUG) {
        return [
            'available' => false,
            'hint' => 'Les logs serveur du debug panel ne sont consultables que si APP_DEBUG est actif. '
                . 'Le panneau en direct reste sur les pages Tutoriel et Buddy pendant la génération.',
        ];
    }

    $logFile = admin_insights_find_error_log();
    if ($logFile === null) {
        return [
            'available' => false,
            'hint' => 'Fichier error_log introuvable sur ce serveur.',
        ];
    }

    $rawLines = admin_insights_tail_file($logFile, 768000);
    $center = $createdAt !== null && $createdAt !== '' ? strtotime($createdAt) : false;
    $fromTs = $center !== false ? $center - ($windowMinutes * 60) : false;
    $toTs = $center !== false ? $center + ($windowMinutes * 60) : false;

    $matched = [];
    foreach ($rawLines as $line) {
        if ($line === '') {
            continue;
        }
        $lower = strtolower($line);
        if (!str_contains($lower, 'mecabuddy') && !str_contains($lower, 'php ')) {
            continue;
        }
        if ($sessionId !== null && $sessionId !== '' && str_contains($line, $sessionId)) {
            $matched[] = $line;
            continue;
        }
        if ($fromTs !== false && $toTs !== false) {
            $lineTs = admin_insights_line_timestamp($line);
            if ($lineTs !== null && ($lineTs < $fromTs || $lineTs > $toTs)) {
                continue;
            }
        }
        $matched[] = $line;
    }

    if ($matched === [] && $sessionId !== null && $sessionId !== '') {
        foreach ($rawLines as $line) {
            if ($sessionId !== '' && str_contains($line, $sessionId)) {
                $matched[] = $line;
            }
        }
    }

    if ($matched === []) {
        foreach ($rawLines as $line) {
            if (str_contains(strtolower($line), 'mecabuddy')) {
                $matched[] = $line;
            }
        }
    }

    $matched = array_slice($matched, -120);

    return [
        'available' => true,
        'log_file' => $logFile,
        'lines' => $matched,
        'hint' => 'Extrait du error_log PHP (approximatif, non stocké par tutoriel/conversation en base). '
            . 'Pour le détail temps réel, utilisez le debug panel sur la page de génération.',
    ];
}

function admin_insights_find_error_log(): ?string
{
    $iniLog = ini_get('error_log');
    if (is_string($iniLog) && $iniLog !== '' && $iniLog !== 'syslog' && is_readable($iniLog)) {
        return $iniLog;
    }

    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
    $candidates = [
        $basePath . '/logs/php_errors.log',
        'C:/Program Files/Ampps/apache/logs/error.log',
        'C:/Program Files/Ampps/php/logs/php_error.log',
        'C:/AMPPS/apache/logs/error.log',
        'C:/AMPPS/php/logs/php_error.log',
    ];

    foreach ($candidates as $path) {
        if (is_readable($path) && !is_dir($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * @return list<string>
 */
function admin_insights_tail_file(string $path, int $maxBytes = 524288): array
{
    if (!is_readable($path)) {
        return [];
    }

    $size = (int) filesize($path);
    $start = max(0, $size - $maxBytes);
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        return [];
    }

    if ($start > 0) {
        fseek($fh, $start);
        fgets($fh);
    }

    $lines = [];
    while (($line = fgets($fh)) !== false) {
        $lines[] = rtrim($line, "\r\n");
    }
    fclose($fh);

    return $lines;
}

function admin_insights_line_timestamp(string $line): ?int
{
    if (preg_match('/\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}[^\]]*)\]/', $line, $m)) {
        $ts = strtotime($m[1]);

        return $ts !== false ? $ts : null;
    }
    if (preg_match('/\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}[^\]]*)\]/', $line, $m)) {
        $ts = strtotime($m[1]);

        return $ts !== false ? $ts : null;
    }

    return null;
}

/**
 * @return array{success: bool, items?: list<array<string, mixed>>, warning?: string}
 */
function admin_insights_list_tutorials_mock(): array
{
    if (!isset($_SESSION['mock_db']['tutorials']) || !is_array($_SESSION['mock_db']['tutorials'])) {
        return ['success' => true, 'items' => [], 'warning' => 'Mode mock : aucun tutoriel en session.'];
    }

    $items = [];
    foreach ($_SESSION['mock_db']['tutorials'] as $t) {
        if (!is_array($t)) {
            continue;
        }
        $vehicle = null;
        if (!empty($t['vehicle_id']) && class_exists('MockDatabase')) {
            $vehicle = MockDatabase::getVehicle((int) $t['vehicle_id']);
        }
        $items[] = [
            'id' => (int) ($t['id'] ?? 0),
            'title' => (string) ($t['title'] ?? ''),
            'action_type' => (string) ($t['action_type'] ?? ''),
            'difficulty' => (string) ($t['difficulty'] ?? ''),
            'created_at' => (string) ($t['created_at'] ?? ''),
            'session_id' => (string) ($t['session_id'] ?? ''),
            'vehicle_label' => $vehicle
                ? trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? '') . ' (' . ($vehicle['year'] ?? '') . ')')
                : null,
            'steps_count' => is_array($t['steps'] ?? null) ? count($t['steps']) : 0,
        ];
    }

    usort($items, static fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

    return ['success' => true, 'items' => $items];
}

/**
 * @return array{success: bool, items?: list<array<string, mixed>>, warning?: string}
 */
function admin_insights_list_conversations_mock(): array
{
    if (!isset($_SESSION['mock_db']['diagnostic_conversations']) || !is_array($_SESSION['mock_db']['diagnostic_conversations'])) {
        return ['success' => true, 'items' => [], 'warning' => 'Mode mock : aucune conversation en session.'];
    }

    $items = [];
    foreach ($_SESSION['mock_db']['diagnostic_conversations'] as $c) {
        if (!is_array($c)) {
            continue;
        }
        $msg = (string) ($c['user_message'] ?? '');
        $items[] = [
            'id' => (int) ($c['id'] ?? 0),
            'created_at' => (string) ($c['created_at'] ?? ''),
            'session_id' => (string) ($c['session_id'] ?? ''),
            'user_message_preview' => mb_strlen($msg) > 120 ? mb_substr($msg, 0, 120) . '…' : $msg,
            'vehicle_id' => isset($c['vehicle_id']) ? (int) $c['vehicle_id'] : null,
        ];
    }

    usort($items, static fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

    return ['success' => true, 'items' => $items];
}
