<?php
/**
 * MecaBuddy — Connexion démo
 */

$skipDemoAuthGuard = true;
$hideMainNav = true;
$pageTitle = 'Connexion démo — MecaBuddy';
$currentPage = 'login';

require_once __DIR__ . '/../includes/header.php';

$redirect = $_GET['redirect'] ?? (PUBLIC_URL . '/index.php');
if (!is_string($redirect) || $redirect === '') {
    $redirect = PUBLIC_URL . '/index.php';
}
// Autoriser chemin relatif ou URL sous PUBLIC_URL
if (!str_starts_with($redirect, '/') && !str_starts_with($redirect, 'http')) {
    $redirect = PUBLIC_URL . '/index.php';
}

if (isDemoAuthEnabled() && getCurrentDemoUser() !== null) {
    header('Location: ' . $redirect);
    exit;
}
?>

<div class="page-header">
    <h1 class="page-title">
        <span class="title-icon">🔐</span>
        Connexion démo
    </h1>
    <p class="page-subtitle">Accès limité pour la démonstration MecaBuddy</p>
</div>

<?php if (!isDemoAuthEnabled()): ?>
    <div class="card login-card">
        <p>L'authentification démo est actuellement <strong>désactivée</strong>.</p>
        <a href="<?= htmlspecialchars(PUBLIC_URL . '/index.php') ?>" class="btn btn-primary">Retour à l'accueil</a>
    </div>
<?php else: ?>
    <div class="card login-card">
        <p class="login-intro">
            Quotas journaliers par compte : <strong>15 tutoriels</strong> et <strong>15 questions Buddy</strong> (compteurs séparés).
        </p>

        <form id="demoLoginForm" class="login-form" autocomplete="on">
            <input type="hidden" name="redirect" id="loginRedirect" value="<?= htmlspecialchars($redirect) ?>">

            <div class="form-group">
                <label for="loginUsername">Identifiant</label>
                <input type="text" id="loginUsername" name="username" required autocomplete="username">
            </div>

            <div class="form-group">
                <label for="loginPassword">Mot de passe</label>
                <input type="password" id="loginPassword" name="password" required autocomplete="current-password">
            </div>

            <p id="loginError" class="login-error" hidden></p>

            <button type="submit" class="btn btn-primary btn-block">Se connecter</button>
        </form>

    </div>
<?php endif; ?>

<style>
.login-card { max-width: 420px; margin: 0 auto 2rem; }
.login-form .form-group { margin-bottom: 1rem; }
.login-form label { display: block; margin-bottom: 0.35rem; font-size: 0.9rem; opacity: 0.9; }
.login-form input {
    width: 100%;
    padding: 0.65rem 0.75rem;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.15);
    background: rgba(0,0,0,0.25);
    color: inherit;
}
.login-error { color: #f87171; font-size: 0.9rem; margin: 0.5rem 0; }
.btn-block { width: 100%; }
</style>

<?php if (isDemoAuthEnabled()): ?>
<script>
document.getElementById('demoLoginForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const errEl = document.getElementById('loginError');
    errEl.hidden = true;
    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value;
    const redirect = document.getElementById('loginRedirect').value;
    try {
        const res = await fetch((window.API_URL || '') + '/auth_api.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password }),
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = redirect;
            return;
        }
        errEl.textContent = data.error === 'invalid_credentials'
            ? 'Identifiant ou mot de passe incorrect.'
            : (data.message || 'Connexion impossible.');
        errEl.hidden = false;
    } catch (err) {
        errEl.textContent = 'Erreur réseau lors de la connexion.';
        errEl.hidden = false;
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
