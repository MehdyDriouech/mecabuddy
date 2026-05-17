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
            <p class="footer-copyright">&copy; <?= date('Y') ?> MecaBuddy - Projet éducatif</p>
        </div>
    <?php if (defined('APP_DEBUG') && APP_DEBUG): ?>
    <div style="text-align:center;padding:8px 0;font-size:0.75rem;opacity:0.5;">
        <a href="<?= PUBLIC_URL ?>/dev.php"
           style="color:inherit;text-decoration:none;">
            ⚙️ Dev
        </a>
    </div>
    <?php endif; ?>
    </footer>
    
    <!-- Toast notifications container -->
    <div id="toastContainer" class="toast-container"></div>
    
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
    
    <?php if (isset($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="<?= htmlspecialchars($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

