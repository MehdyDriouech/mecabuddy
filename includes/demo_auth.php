<?php
/**
 * MecaBuddy — Authentification démo & quotas journaliers (tutoriel / Buddy)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/settings.php';

const DEMO_AUTH_SESSION_USER_ID = 'demo_user_id';
const DEMO_AUTH_SESSION_USERNAME = 'demo_username';

/**
 * PDO SQLite pour les tables demo_* (indépendant du mode mock applicatif).
 */
function demo_auth_pdo(): ?PDO
{
    static $pdo = null;
    static $failed = false;

    if ($failed) {
        return null;
    }
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        if (!function_exists('getSQLite')) {
            require_once __DIR__ . '/../config/db_sqlite.php';
        }
        $pdo = getSQLite();
        if (function_exists('migrateSQLiteDemoAuth')) {
            migrateSQLiteDemoAuth($pdo);
        }
        if (!function_exists('migrateSQLiteDemoVehicles')) {
            require_once __DIR__ . '/demo_vehicles.php';
        }
        migrateSQLiteDemoVehicles($pdo);

        return $pdo;
    } catch (Throwable $e) {
        $failed = true;
        error_log('demo_auth PDO: ' . $e->getMessage());

        return null;
    }
}

function demo_auth_today_date(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d');
}

function demo_auth_reset_at(): string
{
    $tomorrow = new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Paris'));

    return $tomorrow->format('Y-m-d 00:00:00');
}

/**
 * @return array<string, mixed>|null
 */
function getCurrentDemoUser(): ?array
{
    if (!isset($_SESSION[DEMO_AUTH_SESSION_USER_ID])) {
        return null;
    }

    $userId = (int) $_SESSION[DEMO_AUTH_SESSION_USER_ID];
    if ($userId <= 0) {
        return null;
    }

    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        $username = (string) ($_SESSION[DEMO_AUTH_SESSION_USERNAME] ?? '');

        return $username !== '' ? [
            'id' => $userId,
            'username' => $username,
            'tutorial_daily_quota' => 15,
            'buddy_daily_quota' => 15,
            'is_active' => 1,
        ] : null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, username, tutorial_daily_quota, buddy_daily_quota, is_active
         FROM demo_users WHERE id = ? AND is_active = 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

/**
 * Redirection vers login pour les pages HTML.
 */
function requireDemoLogin(): void
{
    if (!isDemoAuthEnabled()) {
        return;
    }

    if (getCurrentDemoUser() !== null) {
        return;
    }

    $redirect = $_SERVER['REQUEST_URI'] ?? (PUBLIC_URL . '/index.php');
    $loginUrl = PUBLIC_URL . '/login.php?redirect=' . rawurlencode($redirect);
    header('Location: ' . $loginUrl);
    exit;
}

/**
 * Refus JSON API si non connecté (auth démo activée).
 */
function requireDemoApiLogin(): void
{
    if (!isDemoAuthEnabled()) {
        return;
    }

    if (getCurrentDemoUser() !== null) {
        return;
    }

    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'auth_required',
        'message' => 'Connexion démo requise.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function loginDemoUser(string $username, string $password): bool
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return false;
    }

    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id, username, password_hash, tutorial_daily_quota, buddy_daily_quota, is_active
         FROM demo_users WHERE username = ? AND is_active = 1'
    );
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row === false || !password_verify($password, (string) $row['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION[DEMO_AUTH_SESSION_USER_ID] = (int) $row['id'];
    $_SESSION[DEMO_AUTH_SESSION_USERNAME] = (string) $row['username'];

    if (isDemoAuthEnabled()) {
        require_once __DIR__ . '/demo_vehicles.php';
        ensureDemoVehiclesForUser((int) $row['id']);
    }

    return true;
}

function logoutDemoUser(): void
{
    unset(
        $_SESSION[DEMO_AUTH_SESSION_USER_ID],
        $_SESSION[DEMO_AUTH_SESSION_USERNAME]
    );
}

/**
 * @return array{tutorial: array{limit: int, used: int, remaining: int}, buddy: array{limit: int, used: int, remaining: int}, reset_at: string}
 */
function getDemoUsageStatus(int $userId): array
{
    $user = demo_auth_fetch_user_by_id($userId);
    if ($user === null) {
        $sessionUser = getCurrentDemoUser();
        if ($sessionUser !== null && (int) ($sessionUser['id'] ?? 0) === $userId) {
            $user = $sessionUser;
        }
    }
    $tutorialLimit = $user !== null ? (int) ($user['tutorial_daily_quota'] ?? 15) : 15;
    $buddyLimit = $user !== null ? (int) ($user['buddy_daily_quota'] ?? 15) : 15;
    $today = demo_auth_today_date();

    $tutorialUsed = demo_auth_get_used_count($userId, $today, 'tutorial');
    $buddyUsed = demo_auth_get_used_count($userId, $today, 'buddy');

    return [
        'tutorial' => [
            'limit' => $tutorialLimit,
            'used' => $tutorialUsed,
            'remaining' => max(0, $tutorialLimit - $tutorialUsed),
        ],
        'buddy' => [
            'limit' => $buddyLimit,
            'used' => $buddyUsed,
            'remaining' => max(0, $buddyLimit - $buddyUsed),
        ],
        'reset_at' => demo_auth_reset_at(),
    ];
}

/*
 * Quotas journaliers (demo_usage_daily)
 * -------------------------------------
 * - Pas de cron pour le reset : une ligne par (user_id, usage_date, usage_type).
 * - usage_date = jour civil Europe/Paris (demo_auth_today_date).
 * - Un cron optionnel peut purger les vieilles lignes ; il n'est pas requis.
 * - Une tentative lancée consomme 1 quota même si le LLM échoue ensuite.
 */

/**
 * Vérifie le quota sans incrémenter.
 *
 * @return array{allowed: bool, usage_type: string, quota: array<string, mixed>}
 */
function assertDemoQuotaAvailable(string $usageType): array
{
    if (!in_array($usageType, ['tutorial', 'buddy'], true)) {
        throw new InvalidArgumentException('usageType invalide');
    }

    if (!isDemoAuthEnabled()) {
        return ['allowed' => true, 'usage_type' => $usageType, 'quota' => []];
    }

    $user = getCurrentDemoUser();
    if ($user === null) {
        return [
            'allowed' => false,
            'usage_type' => $usageType,
            'quota' => [
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
                'reset_at' => demo_auth_reset_at(),
            ],
        ];
    }

    $status = getDemoUsageStatus((int) $user['id']);
    $bucket = $usageType === 'tutorial' ? $status['tutorial'] : $status['buddy'];
    $remaining = (int) ($bucket['remaining'] ?? 0);

    return [
        'allowed' => $remaining > 0,
        'usage_type' => $usageType,
        'quota' => [
            'limit' => (int) $bucket['limit'],
            'used' => (int) $bucket['used'],
            'remaining' => $remaining,
            'reset_at' => $status['reset_at'],
        ],
    ];
}

/**
 * Incrémente le compteur du jour (une unité par appel).
 * Préférer demo_auth_consume_quota_atomic() pour les garde-fous API/SSE (atomique sous SQLite).
 *
 * @return array{success: bool, usage: array<string, mixed>}
 */
function incrementDemoUsage(string $usageType): array
{
    $result = demo_auth_consume_quota_atomic($usageType);
    if (($result['skipped'] ?? false) === true) {
        return ['success' => true, 'usage' => []];
    }
    if (($result['consumed'] ?? false) === true) {
        return ['success' => true, 'usage' => $result['usage'] ?? []];
    }

    return ['success' => false, 'usage' => $result['usage'] ?? []];
}

/**
 * Consomme atomiquement une unité de quota (check + increment dans une transaction SQLite).
 *
 * @return array{
 *   consumed: bool,
 *   exceeded?: bool,
 *   skipped?: bool,
 *   auth_required?: bool,
 *   error?: bool,
 *   usage_type: string,
 *   quota?: array{limit: int, used: int, remaining: int, reset_at: string},
 *   usage?: array<string, mixed>,
 *   message?: string
 * }
 */
function demo_auth_consume_quota_atomic(string $usageType): array
{
    if (!in_array($usageType, ['tutorial', 'buddy'], true)) {
        throw new InvalidArgumentException('usageType invalide');
    }

    if (!isDemoAuthEnabled()) {
        return [
            'consumed' => false,
            'skipped' => true,
            'usage_type' => $usageType,
        ];
    }

    $user = getCurrentDemoUser();
    if ($user === null) {
        return [
            'consumed' => false,
            'auth_required' => true,
            'usage_type' => $usageType,
            'quota' => [
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
                'reset_at' => demo_auth_reset_at(),
            ],
        ];
    }

    if (!function_exists('demo_auth_quota_bypass_active')) {
        require_once __DIR__ . '/byok.php';
    }
    if (demo_auth_quota_bypass_active()) {
        return [
            'consumed' => false,
            'skipped' => true,
            'usage_type' => $usageType,
        ];
    }

    $userId = (int) $user['id'];
    $today = demo_auth_today_date();
    $pdo = demo_auth_pdo();

    if ($pdo === null) {
        return demo_auth_consume_quota_atomic_session($userId, $user, $usageType, $today);
    }

    $inTransaction = false;

    try {
        $pdo->exec('BEGIN IMMEDIATE');
        $inTransaction = true;

        $insert = $pdo->prepare(
            "INSERT INTO demo_usage_daily (user_id, usage_date, usage_type, used_count, updated_at)
             VALUES (?, ?, ?, 0, datetime('now'))
             ON CONFLICT(user_id, usage_date, usage_type) DO NOTHING"
        );
        $insert->execute([$userId, $today, $usageType]);

        $sel = $pdo->prepare(
            'SELECT used_count FROM demo_usage_daily
             WHERE user_id = ? AND usage_date = ? AND usage_type = ?'
        );
        $sel->execute([$userId, $today, $usageType]);
        $usedCount = (int) $sel->fetchColumn();

        if ($usageType === 'tutorial') {
            $limitStmt = $pdo->prepare(
                'SELECT tutorial_daily_quota FROM demo_users WHERE id = ? AND is_active = 1'
            );
        } else {
            $limitStmt = $pdo->prepare(
                'SELECT buddy_daily_quota FROM demo_users WHERE id = ? AND is_active = 1'
            );
        }
        $limitStmt->execute([$userId]);
        $limitVal = $limitStmt->fetchColumn();
        $limit = $limitVal !== false ? (int) $limitVal : 15;
        if ($limit < 1) {
            $limit = 15;
        }

        if ($usedCount >= $limit) {
            $pdo->exec('ROLLBACK');
            $inTransaction = false;
            $resetAt = demo_auth_reset_at();

            return [
                'consumed' => false,
                'exceeded' => true,
                'usage_type' => $usageType,
                'quota' => [
                    'limit' => $limit,
                    'used' => $usedCount,
                    'remaining' => 0,
                    'reset_at' => $resetAt,
                ],
                'usage' => getDemoUsageStatus($userId),
            ];
        }

        $upd = $pdo->prepare(
            "UPDATE demo_usage_daily
             SET used_count = used_count + 1, updated_at = datetime('now')
             WHERE user_id = ? AND usage_date = ? AND usage_type = ?"
        );
        $upd->execute([$userId, $today, $usageType]);

        $pdo->exec('COMMIT');
        $inTransaction = false;

        $status = getDemoUsageStatus($userId);
        $bucket = $usageType === 'tutorial' ? $status['tutorial'] : $status['buddy'];

        return [
            'consumed' => true,
            'usage_type' => $usageType,
            'quota' => [
                'limit' => (int) $bucket['limit'],
                'used' => (int) $bucket['used'],
                'remaining' => (int) $bucket['remaining'],
                'reset_at' => $status['reset_at'],
            ],
            'usage' => $status,
        ];
    } catch (Throwable $e) {
        if ($inTransaction) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (Throwable $rollbackErr) {
                error_log('demo_auth quota rollback: ' . $rollbackErr->getMessage());
            }
        }
        error_log('demo_auth_consume_quota_atomic: ' . $e->getMessage());

        return [
            'consumed' => false,
            'error' => true,
            'usage_type' => $usageType,
            'message' => defined('APP_DEBUG') && APP_DEBUG
                ? $e->getMessage()
                : 'Impossible de mettre à jour le quota.',
        ];
    }
}

/**
 * Repli session si PDO demo indisponible (non partagé entre navigateurs).
 *
 * @param array<string, mixed> $user
 * @return array<string, mixed>
 */
function demo_auth_consume_quota_atomic_session(
    int $userId,
    array $user,
    string $usageType,
    string $today
): array {
    $limit = $usageType === 'tutorial'
        ? (int) ($user['tutorial_daily_quota'] ?? 15)
        : (int) ($user['buddy_daily_quota'] ?? 15);
    if ($limit < 1) {
        $limit = 15;
    }

    $usedCount = demo_auth_session_get_used($userId, $today, $usageType);
    if ($usedCount >= $limit) {
        return [
            'consumed' => false,
            'exceeded' => true,
            'usage_type' => $usageType,
            'quota' => [
                'limit' => $limit,
                'used' => $usedCount,
                'remaining' => 0,
                'reset_at' => demo_auth_reset_at(),
            ],
            'usage' => getDemoUsageStatus($userId),
        ];
    }

    demo_auth_session_increment($userId, $today, $usageType);
    $status = getDemoUsageStatus($userId);
    $bucket = $usageType === 'tutorial' ? $status['tutorial'] : $status['buddy'];

    return [
        'consumed' => true,
        'usage_type' => $usageType,
        'quota' => [
            'limit' => (int) $bucket['limit'],
            'used' => (int) $bucket['used'],
            'remaining' => (int) $bucket['remaining'],
            'reset_at' => $status['reset_at'],
        ],
        'usage' => $status,
    ];
}

/**
 * @param array<string, mixed> $result
 */
function demo_auth_handle_quota_atomic_failure(string $usageType, array $result, string $channel): void
{
    if (($result['exceeded'] ?? false) === true) {
        $quota = $result['quota'] ?? [];
        if ($channel === 'sse') {
            sendSseQuotaExceeded($usageType, $quota);
        } else {
            sendQuotaExceededResponse($usageType, $quota);
        }
    }

    if (($result['auth_required'] ?? false) === true) {
        if ($channel === 'sse') {
            sse_event('error', [
                'success' => false,
                'error' => 'auth_required',
                'message' => 'Connexion démo requise.',
            ]);
            exit;
        }
        requireDemoApiLogin();
    }

    if (($result['error'] ?? false) === true) {
        $message = (string) ($result['message'] ?? 'Erreur quota');
        if ($channel === 'sse') {
            if (!function_exists('sse_event')) {
                header('Content-Type: text/event-stream; charset=utf-8');
            }
            sse_event('error', [
                'success' => false,
                'error' => 'quota_storage_error',
                'message' => $message,
            ]);
            exit;
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'quota_storage_error',
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * Garde API : login + quota + consommation au démarrage du traitement coûteux.
 */
function demo_auth_consume_quota_start(string $usageType): void
{
    if (!isDemoAuthEnabled()) {
        return;
    }

    requireDemoApiLogin();

    $result = demo_auth_consume_quota_atomic($usageType);
    if (($result['skipped'] ?? false) === true || ($result['consumed'] ?? false) === true) {
        return;
    }

    demo_auth_handle_quota_atomic_failure($usageType, $result, 'json');
}

/**
 * Garde SSE tutoriel.
 */
function demo_auth_consume_quota_start_sse(string $usageType): void
{
    if (!isDemoAuthEnabled()) {
        return;
    }

    if (getCurrentDemoUser() === null) {
        sse_event('error', [
            'success' => false,
            'error' => 'auth_required',
            'message' => 'Connexion démo requise.',
        ]);
        exit;
    }

    $result = demo_auth_consume_quota_atomic($usageType);
    if (($result['skipped'] ?? false) === true || ($result['consumed'] ?? false) === true) {
        return;
    }

    demo_auth_handle_quota_atomic_failure($usageType, $result, 'sse');
}

/**
 * @param array<string, mixed> $quota
 */
function sendQuotaExceededResponse(string $usageType, array $quota): void
{
    $message = $usageType === 'buddy'
        ? 'Quota journalier dépassé pour le mode Buddy.'
        : 'Quota journalier dépassé pour le mode tutoriel.';

    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'quota_exceeded',
        'usage_type' => $usageType,
        'message' => $message,
        'quota' => $quota,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @param array<string, mixed> $quota
 */
function sendSseQuotaExceeded(string $usageType, array $quota): void
{
    if (!function_exists('sse_event')) {
        header('Content-Type: text/event-stream; charset=utf-8');
    }

    $message = $usageType === 'buddy'
        ? 'Quota journalier dépassé pour le mode Buddy.'
        : 'Quota journalier dépassé pour le mode tutoriel.';

    sse_event('error', [
        'success' => false,
        'error' => 'quota_exceeded',
        'usage_type' => $usageType,
        'message' => $message,
        'quota' => $quota,
    ]);
    exit;
}

/**
 * @return array<string, mixed>|null
 */
function demo_auth_public_user(?array $row): ?array
{
    if ($row === null) {
        return null;
    }

    return [
        'username' => (string) $row['username'],
        'tutorial_daily_quota' => (int) $row['tutorial_daily_quota'],
        'buddy_daily_quota' => (int) $row['buddy_daily_quota'],
    ];
}

// --- internes ---

/**
 * @return array<string, mixed>|null
 */
function demo_auth_fetch_user_by_id(int $userId): ?array
{
    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, username, tutorial_daily_quota, buddy_daily_quota, is_active
         FROM demo_users WHERE id = ?'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

function demo_auth_get_used_count(int $userId, string $date, string $usageType): int
{
    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return demo_auth_session_get_used($userId, $date, $usageType);
    }

    $stmt = $pdo->prepare(
        'SELECT used_count FROM demo_usage_daily
         WHERE user_id = ? AND usage_date = ? AND usage_type = ?'
    );
    $stmt->execute([$userId, $date, $usageType]);
    $val = $stmt->fetchColumn();

    return $val !== false ? (int) $val : 0;
}

function demo_auth_session_increment(int $userId, string $date, string $usageType): void
{
    if (!isset($_SESSION['demo_usage_fallback']) || !is_array($_SESSION['demo_usage_fallback'])) {
        $_SESSION['demo_usage_fallback'] = [];
    }
    $key = $userId . '|' . $date . '|' . $usageType;
    $_SESSION['demo_usage_fallback'][$key] = ($_SESSION['demo_usage_fallback'][$key] ?? 0) + 1;
}

function demo_auth_session_get_used(int $userId, string $date, string $usageType): int
{
    $key = $userId . '|' . $date . '|' . $usageType;

    return (int) ($_SESSION['demo_usage_fallback'][$key] ?? 0);
}

/**
 * Crée ou met à jour les comptes démo (mots de passe hashés).
 */
function demo_auth_seed_users(PDO $pdo): void
{
    $legacyRenames = [
        'demo' => 'demo-demo',
        'demo2' => 'demo-fairuse',
    ];
    $selectId = $pdo->prepare('SELECT id FROM demo_users WHERE username = ?');
    $rename = $pdo->prepare('UPDATE demo_users SET username = ?, is_active = 1, updated_at = datetime(\'now\') WHERE id = ?');
    $deactivate = $pdo->prepare('UPDATE demo_users SET is_active = 0 WHERE username = ?');

    foreach ($legacyRenames as $oldName => $newName) {
        $selectId->execute([$oldName]);
        $oldId = $selectId->fetchColumn();
        if ($oldId === false) {
            continue;
        }
        $selectId->execute([$newName]);
        if ($selectId->fetchColumn() !== false) {
            $deactivate->execute([$oldName]);
            continue;
        }
        $rename->execute([$newName, (int) $oldId]);
    }

    $accounts = [
        ['demo', 'demo', 10, 25],
        ['demo-demo', 'demo-demo', 10, 25],
        ['demo-fairuse', 'demo-fairuse', 50, 100],
    ];

    $select = $pdo->prepare('SELECT id FROM demo_users WHERE username = ?');
    $insert = $pdo->prepare(
        'INSERT INTO demo_users (username, password_hash, tutorial_daily_quota, buddy_daily_quota, is_active, updated_at)
         VALUES (?, ?, ?, ?, 1, datetime(\'now\'))'
    );
    $update = $pdo->prepare(
        'UPDATE demo_users SET password_hash = ?, tutorial_daily_quota = ?, buddy_daily_quota = ?,
         is_active = 1, updated_at = datetime(\'now\') WHERE username = ?'
    );

    foreach ($accounts as [$username, $password, $tQuota, $bQuota]) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $select->execute([$username]);
        $existing = $select->fetchColumn();
        if ($existing === false) {
            $insert->execute([$username, $hash, $tQuota, $bQuota]);
        } else {
            $update->execute([$hash, $tQuota, $bQuota, $username]);
        }
    }
}

/**
 * Liste publique des comptes + usages du jour (admin dev).
 *
 * @return array<int, array<string, mixed>>
 */
function demo_auth_list_users_with_usage(): array
{
    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        return [];
    }

    $today = demo_auth_today_date();
    $rows = $pdo->query(
        'SELECT id, username, tutorial_daily_quota, buddy_daily_quota, is_active FROM demo_users ORDER BY username'
    )->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $usage = getDemoUsageStatus($id);
        $out[] = [
            'id' => $id,
            'username' => (string) $row['username'],
            'tutorial_daily_quota' => (int) $row['tutorial_daily_quota'],
            'buddy_daily_quota' => (int) $row['buddy_daily_quota'],
            'is_active' => (int) $row['is_active'] === 1,
            'usage_today' => $usage,
        ];
    }

    return $out;
}

function demo_auth_reset_usage_today(): int
{
    $pdo = demo_auth_pdo();
    if ($pdo === null) {
        unset($_SESSION['demo_usage_fallback']);

        return 0;
    }

    $today = demo_auth_today_date();
    $stmt = $pdo->prepare('DELETE FROM demo_usage_daily WHERE usage_date = ?');
    $stmt->execute([$today]);

    return $stmt->rowCount();
}
