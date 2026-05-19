<?php
/**
 * MecaBuddy - Header commun
 * 
 * Ce fichier contient le header HTML commun à toutes les pages.
 * Il gère également l'initialisation de la session.
 */

// Charge la configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/demo_auth.php';

// Initialise la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Récupère le titre de la page (défini avant l'inclusion du header)
$pageTitle = $pageTitle ?? APP_NAME;
$currentPage = $currentPage ?? 'home';
$hideMainNav = !empty($hideMainNav);

if (empty($skipDemoAuthGuard) && isDemoAuthEnabled()) {
    requireDemoLogin();
}

$demoAuthBar = null;
$demoUser = null;
if (isDemoAuthEnabled()) {
    try {
        $demoUser = getCurrentDemoUser();
        if ($demoUser !== null) {
            $demoAuthBar = getDemoUsageStatus((int) $demoUser['id']);
            $demoAuthBar['username'] = (string) $demoUser['username'];
            if (!function_exists('byok_effective_provider_meta')) {
                require_once __DIR__ . '/byok.php';
            }
            $demoAuthBar['llm_mode'] = byok_effective_provider_meta((int) $demoUser['id']);
        }
    } catch (Throwable $e) {
        error_log('MecaBuddy demo auth bar: ' . $e->getMessage());
        $demoAuthBar = null;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MecaBuddy - Votre assistant mécanique automobile intelligent">
    <meta name="theme-color" content="#1a1a2e">
    
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔧</text></svg>">
    
    <!-- Google Fonts - Outfit (moderne et lisible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- URLs globales (avant tout script inline / app.js) -->
    <script>
        window.BASE_URL = <?= json_encode(BASE_URL, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.PUBLIC_URL = <?= json_encode(PUBLIC_URL, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.API_URL = <?= json_encode(API_URL, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        window.API_BASE = window.API_URL;
    </script>

    <!-- CSS principal -->
    <link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
</head>
<body<?= $hideMainNav ? ' class="page-login"' : '' ?>>
    <?php if ($demoAuthBar !== null): ?>
    <div class="demo-auth-bar" aria-label="Compte démo et quotas">
        <span class="demo-auth-user">Connecté : <strong><?= htmlspecialchars($demoAuthBar['username']) ?></strong></span>
        <span class="demo-auth-quota">Tuto : <strong><?= (int) $demoAuthBar['tutorial']['remaining'] ?></strong>/<?= (int) $demoAuthBar['tutorial']['limit'] ?> restants</span>
        <span class="demo-auth-quota">Buddy : <strong><?= (int) $demoAuthBar['buddy']['remaining'] ?></strong>/<?= (int) $demoAuthBar['buddy']['limit'] ?> restants</span>
        <?php
        $llmMode = $demoAuthBar['llm_mode'] ?? [];
        $quotaBypass = ($llmMode['quota_bypass_allowed'] ?? false) === true;
        ?>
        <span class="demo-auth-llm-mode" title="Mode LLM actif">
            <?= $quotaBypass ? 'Clé personnelle active' : 'Quota démo actif' ?>
        </span>
        <?php
        $geminiLimitsLine = null;
        try {
            if (!function_exists('mecabuddy_gemini_limits_banner_line')) {
                require_once __DIR__ . '/llm_chat.php';
            }
            $geminiLimitsLine = mecabuddy_gemini_limits_banner_line((int) ($demoUser['id'] ?? 0));
        } catch (Throwable $e) {
            error_log('MecaBuddy gemini banner: ' . $e->getMessage());
        }
        if ($geminiLimitsLine !== null): ?>
        <span class="demo-auth-gemini-limits" title="Limites indicatives Google AI Studio (provider global)"><?= htmlspecialchars($geminiLimitsLine) ?></span>
        <?php endif; ?>
        <div class="account-menu" id="demoAccountMenu">
            <button
                type="button"
                class="account-menu-toggle btn btn-secondary btn-sm"
                id="demoAccountMenuToggle"
                aria-expanded="false"
                aria-haspopup="true"
                aria-controls="demoAccountMenuDropdown"
            >
                Mon compte
                <span class="account-menu-chevron" aria-hidden="true">▾</span>
            </button>
            <div class="account-menu-dropdown" id="demoAccountMenuDropdown" hidden role="menu">
                <a
                    href="<?= htmlspecialchars(PUBLIC_URL . '/account-settings.php') ?>"
                    class="account-menu-item"
                    role="menuitem"
                >Réglages du compte</a>
                <?php if (isDemoAdmin()): ?>
                <a
                    href="<?= htmlspecialchars(PUBLIC_URL . '/admin.php') ?>"
                    class="account-menu-item"
                    role="menuitem"
                >Administration</a>
                <?php endif; ?>
                <button type="button" class="account-menu-item" role="menuitem" data-action="logout">Déconnexion</button>
            </div>
        </div>
    </div>
  <?php endif; ?>
    <!-- Navigation principale -->
    <nav class="main-nav">
        <div class="nav-container">
            <a href="<?= PUBLIC_URL ?>/index.php" class="nav-logo">
                <span class="logo-icon">🔧</span>
                <span class="logo-text">MecaBuddy</span>
            </a>
            
            <?php if (!$hideMainNav): ?>
            <button class="nav-toggle" aria-label="Menu" id="navToggle">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <ul class="nav-links" id="navLinks">
                <li>
                    <a href="<?= PUBLIC_URL ?>/index.php" class="<?= $currentPage === 'home' ? 'active' : '' ?>">
                        <span class="nav-icon">🏠</span>
                        <span>Accueil</span>
                    </a>
                </li>
                <li>
                    <a href="<?= PUBLIC_URL ?>/vehicle.php" class="<?= $currentPage === 'vehicle' ? 'active' : '' ?>">
                        <span class="nav-icon">🚗</span>
                        <span>Véhicule</span>
                    </a>
                </li>
                <li>
                    <a href="<?= PUBLIC_URL ?>/garage.php" class="<?= $currentPage === 'garage' ? 'active' : '' ?>">
                        <span class="nav-icon">🏎️</span>
                        <span>Garage</span>
                    </a>
                </li>
                <li>
                    <a href="<?= PUBLIC_URL ?>/tutorial.php" class="<?= $currentPage === 'tutorial' ? 'active' : '' ?>">
                        <span class="nav-icon">📖</span>
                        <span>Tutoriels</span>
                    </a>
                </li>
                <li>
                    <a href="<?= PUBLIC_URL ?>/diagnostic.php" class="<?= $currentPage === 'diagnostic' ? 'active' : '' ?>">
                        <span class="nav-icon">💬</span>
                        <span>Buddy</span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- Contenu principal -->
    <main class="main-content">

