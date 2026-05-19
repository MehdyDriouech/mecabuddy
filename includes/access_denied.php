<?php
/**
 * MecaBuddy — Page 403 « atelier verrouillé » (HTML) et refus JSON API.
 */

declare(strict_types=1);

/**
 * Réponse JSON 403 générique (API admin/dev).
 */
function demo_access_denied_json(): void
{
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'access_denied',
        'message' => 'Accès refusé',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Page HTML 403 thème atelier (inclut header + footer, puis exit).
 *
 * @param array{
 *   is_logged_in?: bool,
 *   home_url?: string,
 *   login_url?: string,
 *   variant?: string
 * } $options
 */
function renderAccessDeniedPage(array $options = []): void
{
    if (!function_exists('getCurrentDemoUser')) {
        require_once __DIR__ . '/demo_auth.php';
    }

    if (session_status() === PHP_SESSION_NONE && defined('SESSION_NAME')) {
        session_name(SESSION_NAME);
        session_start();
    }

    $isLoggedIn = $options['is_logged_in'] ?? (getCurrentDemoUser() !== null);
    $homeUrl = $options['home_url'] ?? (defined('PUBLIC_URL') ? PUBLIC_URL . '/index.php' : 'index.php');
    $loginUrl = $options['login_url'] ?? (defined('PUBLIC_URL') ? PUBLIC_URL . '/login.php' : 'login.php');
    $variant = (string) ($options['variant'] ?? 'default');

    http_response_code(403);

    $pageTitle = 'Capot verrouillé — MecaBuddy';
    $skipDemoAuthGuard = true;
    $hideMainNav = true;
    $currentPage = 'access-denied';

    require_once __DIR__ . '/header.php';

    $title = 'Capot verrouillé';
    $subtitle = 'Cette zone est réservée au chef d\'atelier.';
    $bodyText = 'Tu as essayé d\'entrer dans l\'atelier admin, mais il te manque la clé de 12, le badge mécano et probablement deux ou trois droits d\'accès.';
    $secondaryText = 'Pas d\'inquiétude, rien n\'est cassé. Retourne à l\'accueil ou connecte-toi avec un compte autorisé.';

    if ($variant === 'auth_disabled') {
        $bodyText = 'L\'accès à cette zone n\'est pas disponible pour le moment.';
        $secondaryText = 'Retourne à l\'accueil pour continuer sur MecaBuddy.';
    } elseif ($isLoggedIn) {
        $secondaryText = 'Pas d\'inquiétude, rien n\'est cassé. Retourne à l\'accueil pour continuer sur MecaBuddy.';
    }
    ?>
<style>
.access-denied-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: calc(100vh - 140px);
    padding: 1.5rem 1rem 3rem;
}
.access-denied-card {
    max-width: 520px;
    width: 100%;
    margin: 0 auto;
    padding: 2rem 1.75rem 1.75rem;
    text-align: center;
    background: rgba(0, 0, 0, 0.28);
    border: 1px solid rgba(255, 107, 53, 0.35);
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.45);
}
.access-denied-illustration {
    width: 88px;
    height: 72px;
    margin: 0 auto 1.25rem;
    position: relative;
}
.access-denied-hood {
    position: absolute;
    left: 8px;
    right: 8px;
    top: 18px;
    height: 36px;
    background: linear-gradient(180deg, #3a3f55 0%, #25283a 100%);
    border: 2px solid rgba(255, 255, 255, 0.15);
    border-radius: 8px 8px 4px 4px;
    box-shadow: inset 0 -6px 0 rgba(0, 0, 0, 0.25);
}
.access-denied-hood::before {
    content: '';
    position: absolute;
    left: 50%;
    top: 8px;
    transform: translateX(-50%);
    width: 28px;
    height: 4px;
    background: rgba(255, 255, 255, 0.12);
    border-radius: 2px;
}
.access-denied-lock {
    position: absolute;
    left: 50%;
    top: 2px;
    transform: translateX(-50%);
    width: 22px;
    height: 18px;
    background: var(--color-primary, #ff6b35);
    border-radius: 4px 4px 2px 2px;
    box-shadow: 0 2px 8px var(--color-primary-glow, rgba(255, 107, 53, 0.4));
}
.access-denied-lock::before {
    content: '';
    position: absolute;
    left: 50%;
    top: -10px;
    transform: translateX(-50%);
    width: 14px;
    height: 12px;
    border: 3px solid var(--color-primary, #ff6b35);
    border-bottom: none;
    border-radius: 8px 8px 0 0;
    background: transparent;
    box-sizing: border-box;
}
.access-denied-wrench {
    position: absolute;
    right: 4px;
    bottom: 0;
    width: 28px;
    height: 8px;
    background: #8b92a8;
    border-radius: 2px;
    transform: rotate(-35deg);
    transform-origin: left center;
}
.access-denied-wrench::after {
    content: '';
    position: absolute;
    right: -6px;
    top: -4px;
    width: 10px;
    height: 10px;
    border: 3px solid #8b92a8;
    border-radius: 50%;
    background: transparent;
    box-sizing: border-box;
}
.access-denied-badge {
    display: inline-block;
    margin-bottom: 0.75rem;
    padding: 0.25rem 0.65rem;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--color-primary-light, #ff8f66);
    border: 1px solid rgba(255, 107, 53, 0.45);
    border-radius: 999px;
    background: rgba(255, 107, 53, 0.1);
}
.access-denied-card h1 {
    margin: 0 0 0.35rem;
    font-size: 1.65rem;
    font-weight: 700;
    color: var(--text-primary, #fff);
}
.access-denied-subtitle {
    margin: 0 0 1rem;
    font-size: 1.05rem;
    color: var(--color-primary-light, #ff8f66);
    font-weight: 500;
}
.access-denied-text {
    margin: 0 0 0.75rem;
    font-size: 0.95rem;
    line-height: 1.55;
    color: var(--text-secondary, rgba(255, 255, 255, 0.75));
}
.access-denied-secondary {
    margin: 0 0 1.5rem;
    font-size: 0.875rem;
    line-height: 1.5;
    color: var(--text-muted, rgba(255, 255, 255, 0.5));
}
.access-denied-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: center;
}
.access-denied-actions .btn {
    min-width: 10rem;
}
@media (max-width: 480px) {
    .access-denied-card { padding: 1.5rem 1.25rem; }
    .access-denied-actions { flex-direction: column; }
    .access-denied-actions .btn { width: 100%; }
}
</style>

<div class="access-denied-wrap">
    <div class="access-denied-card" role="alert">
        <div class="access-denied-illustration" aria-hidden="true">
            <span class="access-denied-lock"></span>
            <span class="access-denied-hood"></span>
            <span class="access-denied-wrench"></span>
        </div>
        <span class="access-denied-badge">Atelier réservé</span>
        <h1><?= htmlspecialchars($title) ?></h1>
        <p class="access-denied-subtitle"><?= htmlspecialchars($subtitle) ?></p>
        <p class="access-denied-text"><?= htmlspecialchars($bodyText) ?></p>
        <p class="access-denied-secondary"><?= htmlspecialchars($secondaryText) ?></p>
        <div class="access-denied-actions">
            <a href="<?= htmlspecialchars($homeUrl) ?>" class="btn btn-primary">Retour à l'accueil</a>
            <?php if (!$isLoggedIn && $variant !== 'auth_disabled'): ?>
            <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn-secondary">Se connecter</a>
            <?php endif; ?>
        </div>
    </div>
</div>
    <?php
    require_once __DIR__ . '/footer.php';
    exit;
}
