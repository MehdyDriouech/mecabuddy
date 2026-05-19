<?php
/**
 * MecaBuddy — Jeton CSRF session (actions d’écriture admin / dev API).
 */

declare(strict_types=1);

const DEMO_CSRF_SESSION_KEY = 'demo_csrf_token';

function demo_csrf_ensure_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(defined('SESSION_NAME') ? SESSION_NAME : 'MECABUDDY_SESSION');
        session_start();
    }

    $existing = $_SESSION[DEMO_CSRF_SESSION_KEY] ?? '';
    if (is_string($existing) && strlen($existing) >= 32) {
        return $existing;
    }

    $_SESSION[DEMO_CSRF_SESSION_KEY] = bin2hex(random_bytes(32));

    return (string) $_SESSION[DEMO_CSRF_SESSION_KEY];
}

function demo_csrf_validate(?string $token): bool
{
    $expected = $_SESSION[DEMO_CSRF_SESSION_KEY] ?? '';
    if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
        return false;
    }

    return hash_equals($expected, $token);
}

/**
 * Refus JSON si méthode d’écriture sans jeton CSRF valide.
 */
function demo_csrf_require_for_write_api(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $token = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($token === '') {
        $token = trim((string) ($_POST['csrf_token'] ?? ''));
    }

    if (!demo_csrf_validate($token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'csrf_invalid',
            'message' => 'Jeton CSRF manquant ou invalide.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
