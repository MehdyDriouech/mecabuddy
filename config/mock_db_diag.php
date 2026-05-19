<?php
/**
 * Instrumentation temporaire — diagnostic USE_MOCK_DB.
 * Supprimer ce fichier et les appels associés dans db.php / vehicle_api.php après investigation.
 *
 * Actif uniquement si APP_DEBUG === true (pas d'exposition utilisateur).
 */

if (!function_exists('mock_db_diag_enabled')) {
    function mock_db_diag_enabled(): bool
    {
        return defined('APP_DEBUG') && APP_DEBUG;
    }

    function mock_db_diag_log_line(string $message): void
    {
        if (!mock_db_diag_enabled()) {
            return;
        }

        $line = '[MOCK_DB_DIAG] ' . $message;
        error_log($line);

        $logFile = dirname(__DIR__) . '/data/mock_db_diag.log';
        $dataDir = dirname($logFile);
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }
        @file_put_contents(
            $logFile,
            date('c') . ' ' . $line . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * @return array<string, string>
     */
    function mock_db_diag_request_context(): array
    {
        $sessionId = 'none';
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            $sid = session_id();
            if ($sid !== '') {
                $sessionId = strlen($sid) > 16 ? substr($sid, 0, 8) . '…' : $sid;
            }
        }

        return [
            'ts' => gmdate('Y-m-d\TH:i:s\Z'),
            'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
            'action' => (string) ($_GET['action'] ?? $_POST['action'] ?? ''),
            'session_id' => $sessionId,
        ];
    }

    /**
     * @param array<string, bool|string|null> $fields
     */
    function mock_db_diag_format_fields(array $fields): string
    {
        $parts = [];
        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $value = (string) $value;
            if (preg_match('/[\s="]/', $value)) {
                $value = '"' . str_replace(['"', "\n", "\r"], ["'", ' ', ' '], $value) . '"';
            }
            $parts[] = $key . '=' . $value;
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<string, bool|string|null> $probe
     */
    function mock_db_diag_log_unavailable(array $probe): void
    {
        if (!mock_db_diag_enabled()) {
            return;
        }

        $fields = array_merge(mock_db_diag_request_context(), $probe);
        mock_db_diag_log_line(mock_db_diag_format_fields($fields));
    }

    function mock_db_diag_infer_caller_page(): string
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref === '') {
            return 'unknown';
        }

        $path = (string) (parse_url($ref, PHP_URL_PATH) ?: '');
        if ($path === '') {
            return 'unknown';
        }

        $basename = basename($path);
        $known = ['garage.php', 'diagnostic.php', 'tutorial.php', 'vehicle.php', 'index.php', 'dev.php'];
        if (in_array($basename, $known, true)) {
            return $basename;
        }

        return $basename !== '' ? $basename : 'unknown';
    }

    function mock_db_diag_log_garage_blocked(): void
    {
        if (!mock_db_diag_enabled()) {
            return;
        }

        $fields = array_merge(mock_db_diag_request_context(), [
            'event' => 'garage_blocked',
            'action' => 'garage',
            'use_mock_db' => 'true',
            'referer' => (string) ($_SERVER['HTTP_REFERER'] ?? ''),
            'caller_page' => mock_db_diag_infer_caller_page(),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
        ]);

        mock_db_diag_log_line(mock_db_diag_format_fields($fields));
    }

    /**
     * Sonde SQLite fraîche (hors cache isDatabaseAvailable).
     *
     * @return array{ok: bool, error: string|null}
     */
    function mock_db_diag_probe_sqlite(): array
    {
        try {
            if (!function_exists('getSQLite')) {
                return ['ok' => false, 'error' => 'getSQLite_unavailable'];
            }
            $pdo = getSQLite();
            $pdo->query('SELECT 1');

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Dernières lignes du fichier de log diagnostic (lecture locale uniquement).
     *
     * @return list<string>
     */
    function mock_db_diag_tail_log(int $maxLines = 15): array
    {
        $logFile = dirname(__DIR__) . '/data/mock_db_diag.log';
        if (!is_readable($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || $lines === []) {
            return [];
        }

        return array_slice($lines, -$maxLines);
    }
}
