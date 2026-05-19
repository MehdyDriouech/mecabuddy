    </main>
    
    <!-- Footer -->
    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-brand">
                <span class="footer-logo">🔧</span>
                <span class="footer-name"><?= APP_NAME ?></span>
                <span class="footer-version">v<?= APP_VERSION ?></span>
            </div>
            <p class="footer-tagline">Ton compagnon mécanique intelligent</p>
            <p class="footer-copyright">
                &copy; <?= date('Y') ?> MecaBuddy
                <span class="footer-sep" aria-hidden="true">·</span>
                <a href="<?= PUBLIC_URL ?>/about.php" class="footer-link">À propos</a>
            </p>
        </div>
    <?php
    $footerShowAdmin = function_exists('isDemoAdmin') && isDemoAdmin();
    $footerShowDev = $footerShowAdmin;
    if ($footerShowAdmin || $footerShowDev):
    ?>
    <div style="text-align:center;padding:8px 0;font-size:0.75rem;opacity:0.5;">
        <?php if ($footerShowAdmin): ?>
        <a href="<?= PUBLIC_URL ?>/admin.php" style="color:inherit;text-decoration:none;margin:0 6px;">🛡️ Admin</a>
        <?php endif; ?>
        <?php if ($footerShowDev): ?>
        <a href="<?= PUBLIC_URL ?>/dev.php" style="color:inherit;text-decoration:none;margin:0 6px;">⚙️ Dev</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    </footer>
    
    <!-- Toast notifications container -->
    <div id="toastContainer" class="toast-container"></div>
    
    <?php
    $aboutPromptScript = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
    $aboutPromptExcluded = ['about.php', 'login.php', 'dev.php', 'admin.php', 'test_api.php', 'account-settings.php', 'db_diag.php'];
    $showAboutPrompt = !in_array($aboutPromptScript, $aboutPromptExcluded, true);
    ?>

    <?php if ($showAboutPrompt): ?>
    <div
        id="aboutPromptModal"
        class="about-prompt"
        hidden
        role="dialog"
        aria-modal="true"
        aria-labelledby="aboutPromptTitle"
        data-about-url="<?= htmlspecialchars(PUBLIC_URL . '/about.php', ENT_QUOTES, 'UTF-8') ?>"
    >
        <div class="about-prompt__overlay" data-about-prompt-close tabindex="-1" aria-hidden="true"></div>
        <div class="about-prompt__dialog">
            <button type="button" class="about-prompt__close" data-about-prompt-close aria-label="Fermer">&times;</button>
            <p class="about-prompt__eyebrow">Prototype MecaBuddy</p>
            <h2 id="aboutPromptTitle">Envie d'en savoir plus sur MecaBuddy ?</h2>
            <p>
                MecaBuddy est un prototype né d'une conviction simple : l'IA peut aider dans l'automobile sans remplacer l'expertise humaine.
            </p>
            <p>
                La page À propos explique la vision du projet, ses limites et ce qu'il cherche à rendre possible.
            </p>
            <div class="about-prompt__actions">
                <a href="<?= htmlspecialchars(PUBLIC_URL . '/about.php', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary" data-about-prompt-cta>Découvrir la page À propos</a>
                <button type="button" class="btn btn-secondary" data-about-prompt-close>Plus tard</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Loading overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-inner">
            <div class="spinner loading-spinner-ring" aria-hidden="true"></div>
            <p id="loading-message" class="loading-msg">🔍 Je fouille internet...</p>
            <p id="loading-submessage" class="loading-sub">Parce que ma mémoire, c'est comme un carnet d'atelier : plein de taches d'huile.</p>
        </div>
    </div>
    
    <!-- JavaScript principal -->
    <script src="<?= asset_url('assets/js/app.js') ?>"></script>
    <?php if ($showAboutPrompt): ?>
    <script src="<?= asset_url('assets/js/about-prompt.js') ?>"></script>
    <?php endif; ?>
    
    <?php if (isset($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
