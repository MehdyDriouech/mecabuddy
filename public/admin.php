<?php
/**
 * MecaBuddy — Interface d’administration (comptes démo, véhicules, tableau de bord)
 */

$pageTitle = 'Administration — MecaBuddy';
$currentPage = 'admin';
$skipDemoAuthGuard = true;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/demo_auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
requireDemoAdmin();

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/demo_csrf.php';
$adminCsrfToken = demo_csrf_ensure_token();

$activeTab = $_GET['tab'] ?? 'dashboard';
$allowedTabs = ['dashboard', 'tutorials', 'conversations', 'devtools', 'accounts', 'vehicles', 'debug'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'dashboard';
}
$detailId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$appDebugOn = defined('APP_DEBUG') && APP_DEBUG;
$currentUser = getCurrentDemoUser();
?>

<style>
.admin-layout { display: flex; gap: 1.5rem; max-width: 1200px; margin: 0 auto 2.5rem; align-items: flex-start; }
.admin-sidebar {
    flex: 0 0 200px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px; padding: 0.75rem 0;
}
.admin-sidebar a {
    display: block; padding: 0.6rem 1rem; color: inherit; text-decoration: none; font-size: 0.95rem;
    border-left: 3px solid transparent;
}
.admin-sidebar a:hover { background: rgba(255,107,53,0.08); }
.admin-sidebar a.active {
    border-left-color: var(--color-primary, #ff6b35);
    background: rgba(255,107,53,0.12);
    font-weight: 600;
}
.admin-main { flex: 1; min-width: 0; }
.admin-card {
    background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1rem;
}
.admin-card h2 { margin: 0 0 0.75rem; font-size: 1.1rem; }
.admin-stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem; }
.admin-stat {
    padding: 0.75rem; border-radius: 8px; background: rgba(255,255,255,0.05);
    text-align: center;
}
.admin-stat strong { display: block; font-size: 1.5rem; color: var(--color-primary-light, #ff8f66); }
a.admin-stat--link {
    display: block; text-decoration: none; color: inherit; cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
    border: 1px solid transparent;
}
a.admin-stat--link:hover {
    background: rgba(255,107,53,0.12);
    border-color: rgba(255,107,53,0.35);
}
.admin-subtabs { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
.admin-subtabs button {
    padding: 0.45rem 0.85rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15);
    background: rgba(0,0,0,0.2); color: inherit; cursor: pointer; font-size: 0.85rem;
}
.admin-subtabs button.active {
    border-color: var(--color-primary, #ff6b35);
    background: rgba(255,107,53,0.15);
}
.admin-detail-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 900px) { .admin-detail-layout { grid-template-columns: 1fr; } }
.admin-log-pre {
    max-height: 280px; overflow: auto; font-size: 0.72rem; padding: 0.75rem;
    background: #0d0f1a; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);
    white-space: pre-wrap; word-break: break-word;
}
.admin-list-row { cursor: pointer; }
.admin-list-row:hover { background: rgba(255,107,53,0.08); }
.admin-list-row.is-selected { background: rgba(255,107,53,0.15); }
.admin-search-input { min-width: 200px; flex: 1; }
.admin-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.admin-table th, .admin-table td {
    padding: 0.5rem 0.65rem; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left;
}
.admin-table th { opacity: 0.75; font-weight: 600; }
.admin-badge {
    display: inline-block; padding: 0.15rem 0.45rem; border-radius: 4px; font-size: 0.72rem;
}
.admin-badge--admin { background: rgba(255,107,53,0.25); color: #ffb899; }
.admin-badge--user { background: rgba(255,255,255,0.1); }
.admin-badge--off { background: rgba(248,113,113,0.2); color: #fca5a5; }
.admin-form-row { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; align-items: flex-end; }
.admin-form-row input, .admin-form-row select {
    padding: 0.5rem 0.65rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.15);
    background: rgba(0,0,0,0.25); color: inherit; font-size: 0.9rem;
}
.admin-msg { font-size: 0.9rem; margin: 0.5rem 0; }
.admin-msg.is-error { color: #f87171; }
.admin-msg.is-ok { color: #86efac; }
.admin-env-list { margin: 0; padding-left: 1.2rem; font-size: 0.9rem; }
.toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; margin-top: 0.75rem; }
.toggle-label input { position: absolute; opacity: 0; width: 0; height: 0; }
.toggle-track {
    width: 44px; height: 24px; background: #555; border-radius: 12px; position: relative; flex-shrink: 0;
}
.toggle-track::after {
    content: ''; position: absolute; width: 20px; height: 20px; border-radius: 50%; background: #fff;
    top: 2px; left: 2px; transition: transform 0.2s;
}
.toggle-label input:checked + .toggle-track { background: var(--color-primary, #ff6b35); }
.toggle-label input:checked + .toggle-track::after { transform: translateX(20px); }
@media (max-width: 768px) {
    .admin-layout { flex-direction: column; }
    .admin-sidebar { flex: none; width: 100%; display: flex; flex-wrap: wrap; padding: 0.5rem; }
    .admin-sidebar a { flex: 1 1 auto; border-left: none; border-bottom: 2px solid transparent; text-align: center; }
    .admin-sidebar a.active { border-bottom-color: var(--color-primary, #ff6b35); }
}
</style>

<div class="page-header">
    <h1 class="page-title">
        <span class="title-icon">🛡️</span>
        Administration
    </h1>
    <p class="page-subtitle">
        Connecté en tant que <strong><?= htmlspecialchars((string) ($currentUser['username'] ?? '')) ?></strong>
        (administrateur)
    </p>
</div>

<div class="admin-layout">
    <nav class="admin-sidebar" aria-label="Sections administration">
        <?php
        $tabs = [
            'dashboard' => 'Dashboard',
            'tutorials' => 'Tutoriels',
            'conversations' => 'Conversations',
            'devtools' => 'DevTools',
            'accounts' => 'Comptes',
            'vehicles' => 'Véhicules',
            'debug' => 'Debug',
        ];
        foreach ($tabs as $key => $label):
            $href = PUBLIC_URL . '/admin.php?tab=' . rawurlencode($key);
            ?>
        <a href="<?= htmlspecialchars($href) ?>" class="<?= $activeTab === $key ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="admin-main">
        <?php if ($activeTab === 'dashboard'): ?>
        <div class="admin-card" id="adminDashboard">
            <p class="admin-msg">Chargement du tableau de bord…</p>
        </div>

        <?php elseif ($activeTab === 'tutorials'): ?>
        <div class="admin-card">
            <h2>Tutoriels générés</h2>
            <p class="dev-hint">Cliquez sur une ligne pour le détail, la date de génération et un extrait des logs serveur (debug panel).</p>
            <div id="tutorialsListWrap"><p class="admin-msg">Chargement…</p></div>
            <div id="tutorialDetailWrap" class="admin-detail-layout" style="margin-top:1rem;display:none"></div>
        </div>

        <?php elseif ($activeTab === 'conversations'): ?>
        <div class="admin-card">
            <h2>Conversations Buddy</h2>
            <p class="dev-hint">Historique des échanges enregistrés en base (tous les comptes / sessions).</p>
            <div id="conversationsListWrap"><p class="admin-msg">Chargement…</p></div>
            <div id="conversationDetailWrap" class="admin-detail-layout" style="margin-top:1rem;display:none"></div>
        </div>

        <?php elseif ($activeTab === 'devtools'): ?>
        <div class="admin-card">
            <h2>DevTools</h2>
            <p class="dev-hint">
                Paramètres applicatifs, tests LLM, plaque, recherche web et migration catalogue.
                Réservé au mode <code>APP_DEBUG</code>.
            </p>
            <?php if ($appDebugOn): ?>
            <p>
                <a href="<?= htmlspecialchars(PUBLIC_URL . '/dev.php') ?>" class="btn btn-primary">
                    Ouvrir l’administration POC complète
                </a>
            </p>
            <p class="dev-hint" style="margin-top:1rem">
                Les modifications passent par <code>dev_api.php</code> (jeton CSRF requis sur les écritures).
            </p>
            <?php else: ?>
            <p class="admin-msg is-error">
                Les DevTools complets sont indisponibles : activez <code>APP_DEBUG</code> dans
                <code>config/config.php</code>.
            </p>
            <?php endif; ?>
        </div>

        <?php elseif ($activeTab === 'accounts'): ?>
        <div class="admin-card">
            <h2>Comptes démo</h2>
            <div class="admin-form-row" id="adminUserCreateForm">
                <input type="text" id="newUsername" placeholder="Identifiant" maxlength="64" required>
                <input type="password" id="newPassword" placeholder="Mot de passe" autocomplete="new-password" required>
                <select id="newRole">
                    <option value="user">user</option>
                    <option value="admin">admin</option>
                </select>
                <input type="number" id="newTQuota" value="15" min="1" max="999" style="width:70px" title="Quota tutoriel">
                <input type="number" id="newBQuota" value="15" min="1" max="999" style="width:70px" title="Quota Buddy">
                <button type="button" class="btn btn-primary btn-sm" id="btnCreateUser">Créer</button>
            </div>
            <p id="accountsMsg" class="admin-msg" hidden></p>
            <div id="accountsTableWrap"><p class="admin-msg">Chargement…</p></div>
        </div>

        <?php elseif ($activeTab === 'vehicles'): ?>
        <div class="admin-card">
            <h2>Véhicules</h2>
            <p class="dev-hint">Catalogue référentiel (toutes les marques/modèles en base) et garages utilisateurs (table <code>vehicles</code>).</p>
            <div class="admin-subtabs" id="vehicleViewTabs">
                <button type="button" class="active" data-view="catalog-car">Catalogue voitures</button>
                <button type="button" data-view="catalog-moto">Catalogue motos</button>
                <button type="button" data-view="garage">Garages utilisateurs</button>
            </div>
            <div id="vehicleCatalogTools" class="admin-form-row">
                <input type="search" id="catalogSearch" class="admin-search-input" placeholder="Rechercher marque ou modèle…">
                <button type="button" class="btn btn-secondary btn-sm" id="btnReloadCatalog">Rafraîchir</button>
            </div>
            <div id="vehicleGarageTools" class="admin-form-row" style="display:none">
                <label>Filtrer par user_id (optionnel)
                    <input type="number" id="vehicleFilterUser" placeholder="ex. 1" min="1" style="width:100px">
                </label>
                <button type="button" class="btn btn-secondary btn-sm" id="btnReloadGarage">Rafraîchir</button>
            </div>
            <details id="garageCreateBlock" style="margin-bottom:1rem;display:none">
                <summary style="cursor:pointer">Créer un véhicule (garage)</summary>
                <div class="admin-form-row" style="margin-top:0.75rem">
                    <input type="text" id="vBrand" placeholder="Marque" required>
                    <input type="text" id="vModel" placeholder="Modèle" required>
                    <input type="number" id="vYear" placeholder="Année" min="1900" max="2100" style="width:90px" required>
                    <input type="number" id="vUserId" placeholder="user_id" min="0" style="width:80px">
                    <input type="number" id="vSlot" placeholder="slot 1-3" min="1" max="3" style="width:70px">
                    <button type="button" class="btn btn-primary btn-sm" id="btnCreateVehicle">Ajouter</button>
                </div>
            </details>
            <p id="vehiclesMsg" class="admin-msg" hidden></p>
            <div id="vehiclesTableWrap"><p class="admin-msg">Chargement…</p></div>
        </div>

        <?php elseif ($activeTab === 'debug'): ?>
        <div class="admin-card">
            <h2>Debug Panel</h2>
            <?php if ($appDebugOn): ?>
            <p class="dev-hint">
                Le panneau de trace s’affiche sur les pages Tutoriels et Diagnostic lorsque l’option ci-dessous est activée.
            </p>
            <label class="toggle-label">
                <input type="checkbox" id="adminDebugPanelToggle">
                <span class="toggle-track"></span>
                <span class="toggle-text">Debug Panel activé</span>
            </label>
            <p id="debugPanelMsg" class="admin-msg" hidden></p>
            <?php else: ?>
            <p class="admin-msg is-error">
                Le debug panel est désactivé car <code>APP_DEBUG</code> est false.
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const API = (window.API_URL || '') + '/admin_api.php';
    let csrfToken = <?= json_encode($adminCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
    }

    async function apiGet(action, query) {
        const q = query ? '&' + query : '';
        const res = await fetch(API + '?action=' + encodeURIComponent(action) + q, { credentials: 'same-origin' });
        return res.json();
    }

    async function apiPost(action, body) {
        const res = await fetch(API + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(body || {}),
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            return { success: false, message: 'Réponse serveur invalide.' };
        }
        if (!res.ok && data.success !== true) {
            data.success = false;
            if (!data.message && data.error) {
                data.message = data.error;
            }
        }
        if (data.error === 'csrf_invalid' && action !== 'get_csrf') {
            const fresh = await apiGet('get_csrf');
            if (fresh.csrf_token) {
                csrfToken = fresh.csrf_token;
                return apiPost(action, body);
            }
        }
        return data;
    }

    function showMsg(el, text, ok) {
        if (!el) return;
        el.hidden = false;
        el.textContent = text;
        el.className = 'admin-msg ' + (ok ? 'is-ok' : 'is-error');
    }

    <?php if ($activeTab === 'dashboard'): ?>
    (async function loadDashboard() {
        const root = document.getElementById('adminDashboard');
        try {
            const data = await apiGet('dashboard_stats');
            if (!data.success) {
                root.innerHTML = '<p class="admin-msg is-error">' + esc(data.message || 'Erreur') + '</p>';
                return;
            }
            const s = data.stats || {};
            const e = data.environment || {};
            const p = data.provider;
            let html = '<h2>Vue d’ensemble</h2><div class="admin-stats-grid">';
            html += '<div class="admin-stat"><span>Comptes</span><strong>' + esc(s.accounts) + '</strong></div>';
            const adminBase = <?= json_encode(PUBLIC_URL . '/admin.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            html += '<a href="' + esc(adminBase) + '?tab=vehicles" class="admin-stat admin-stat--link"><span>Garages</span><strong>' + esc(s.vehicles) + '</strong></a>';
            html += '<a href="' + esc(adminBase) + '?tab=tutorials" class="admin-stat admin-stat--link"><span>Tutoriels</span><strong>' + esc(s.tutorials) + '</strong></a>';
            html += '<a href="' + esc(adminBase) + '?tab=conversations" class="admin-stat admin-stat--link"><span>Conversations</span><strong>' + esc(s.conversations) + '</strong></a>';
            html += '</div>';
            html += '<h2 style="margin-top:1.25rem">Environnement</h2><ul class="admin-env-list">';
            html += '<li>Base : <strong>' + esc(e.db_mode) + '</strong></li>';
            html += '<li>APP_DEBUG : <strong>' + (e.app_debug ? 'actif' : 'inactif') + '</strong></li>';
            html += '<li>Auth démo : <strong>' + (e.demo_auth_enabled ? 'activée' : 'désactivée') + '</strong></li>';
            html += '<li>Mode démo applicatif : <strong>' + (e.demo_mode ? 'actif' : 'inactif') + '</strong></li>';
            html += '<li>Debug panel (réglage) : <strong>' + (e.debug_panel_setting ? 'activé' : 'désactivé') + '</strong></li>';
            html += '</ul>';
            if (p && p.name) {
                html += '<p class="dev-hint">Provider LLM actif : <strong>' + esc(p.name) + '</strong> (' + esc(p.type) + ', ' + esc(p.model) + ')</p>';
            }
            if (s.catalog_cars != null || s.catalog_motos != null) {
                html += '<p class="dev-hint">Catalogue référentiel : <strong>' + esc(s.catalog_cars ?? 0) + '</strong> modèles voiture · <strong>' + esc(s.catalog_motos ?? 0) + '</strong> modèles moto — voir onglet Véhicules.</p>';
            }
            root.innerHTML = html;
        } catch (err) {
            root.innerHTML = '<p class="admin-msg is-error">Erreur réseau.</p>';
        }
    })();
    <?php endif; ?>

    <?php if ($activeTab === 'accounts'): ?>
    const accountsMsg = document.getElementById('accountsMsg');
    const accountsWrap = document.getElementById('accountsTableWrap');

    async function loadUsers() {
        const data = await apiGet('list_users');
        if (!data.success) {
            accountsWrap.innerHTML = '<p class="admin-msg is-error">' + esc(data.message || 'Erreur') + '</p>';
            return;
        }
        let html = '<table class="admin-table"><thead><tr><th>ID</th><th>Login</th><th>Rôle</th><th>Quotas</th><th>Actif</th><th></th></tr></thead><tbody>';
        (data.users || []).forEach(function (u) {
            const roleBadge = u.role === 'admin'
                ? '<span class="admin-badge admin-badge--admin">admin</span>'
                : '<span class="admin-badge admin-badge--user">user</span>';
            const active = u.is_active
                ? '<span class="admin-badge">oui</span>'
                : '<span class="admin-badge admin-badge--off">non</span>';
            const tuto = u.usage_today?.tutorial || {};
            html += '<tr data-user-id="' + esc(u.id) + '">';
            html += '<td>' + esc(u.id) + '</td>';
            html += '<td><input type="text" class="edit-username" value="' + esc(u.username) + '" style="max-width:120px"></td>';
            html += '<td><select class="edit-role"><option value="user"' + (u.role === 'user' ? ' selected' : '') + '>user</option>';
            html += '<option value="admin"' + (u.role === 'admin' ? ' selected' : '') + '>admin</option></select></td>';
            html += '<td><input type="number" class="edit-tquota" value="' + esc(u.tutorial_daily_quota) + '" style="width:50px"> / ';
            html += '<input type="number" class="edit-bquota" value="' + esc(u.buddy_daily_quota) + '" style="width:50px"></td>';
            html += '<td>' + active + '</td>';
            html += '<td><button type="button" class="btn btn-secondary btn-sm btn-save-user">OK</button> ';
            html += '<button type="button" class="btn btn-secondary btn-sm btn-reset-pw">MDP</button> ';
            html += '<button type="button" class="btn btn-secondary btn-sm btn-del-user">Suppr.</button></td></tr>';
        });
        html += '</tbody></table>';
        accountsWrap.innerHTML = html;

        accountsWrap.querySelectorAll('.btn-save-user').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const tr = btn.closest('tr');
                const id = parseInt(tr.getAttribute('data-user-id'), 10);
                const out = await apiPost('update_user', {
                    id: id,
                    username: tr.querySelector('.edit-username').value.trim(),
                    role: tr.querySelector('.edit-role').value,
                    tutorial_daily_quota: parseInt(tr.querySelector('.edit-tquota').value, 10),
                    buddy_daily_quota: parseInt(tr.querySelector('.edit-bquota').value, 10),
                });
                showMsg(accountsMsg, out.message || (out.success ? 'Enregistré' : 'Erreur'), out.success);
                if (out.success) loadUsers();
            });
        });
        accountsWrap.querySelectorAll('.btn-reset-pw').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const tr = btn.closest('tr');
                const id = parseInt(tr.getAttribute('data-user-id'), 10);
                const pw = prompt('Nouveau mot de passe (min. 4 caractères) :');
                if (!pw) return;
                const out = await apiPost('reset_password', { id: id, password: pw });
                showMsg(accountsMsg, out.message || (out.success ? 'Mot de passe réinitialisé' : 'Erreur'), out.success);
            });
        });
        accountsWrap.querySelectorAll('.btn-del-user').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                if (!confirm('Supprimer ce compte ?')) return;
                const tr = btn.closest('tr');
                const out = await apiPost('delete_user', { id: parseInt(tr.getAttribute('data-user-id'), 10) });
                const deleted = out.success === true && out.deleted_id;
                showMsg(accountsMsg, out.message || (deleted ? 'Supprimé' : 'Erreur'), !!deleted);
                if (deleted) loadUsers();
            });
        });
    }

    document.getElementById('btnCreateUser')?.addEventListener('click', async function () {
        const out = await apiPost('create_user', {
            username: document.getElementById('newUsername').value.trim(),
            password: document.getElementById('newPassword').value,
            role: document.getElementById('newRole').value,
            tutorial_daily_quota: parseInt(document.getElementById('newTQuota').value, 10),
            buddy_daily_quota: parseInt(document.getElementById('newBQuota').value, 10),
        });
        showMsg(accountsMsg, out.message || (out.success ? 'Compte créé' : 'Erreur'), out.success);
        if (out.success) loadUsers();
    });

    loadUsers();
    <?php endif; ?>

    <?php if (in_array($activeTab, ['tutorials', 'conversations'], true)): ?>
    function renderDebugLogs(logs) {
        if (!logs || !logs.available) {
            return '<p class="dev-hint">' + esc(logs?.hint || 'Logs non disponibles.') + '</p>';
        }
        let h = '<p class="dev-hint">' + esc(logs.hint || '') + '</p>';
        if (logs.log_file) h += '<p class="dev-hint"><code>' + esc(logs.log_file) + '</code></p>';
        const lines = logs.lines || [];
        h += '<pre class="admin-log-pre">' + (lines.length ? esc(lines.join('\n')) : '(aucune ligne MecaBuddy trouvée pour cette fenêtre)') + '</pre>';
        return h;
    }
    <?php endif; ?>

    <?php if ($activeTab === 'tutorials'): ?>
    const tutorialsListWrap = document.getElementById('tutorialsListWrap');
    const tutorialDetailWrap = document.getElementById('tutorialDetailWrap');
    const initialTutorialId = <?= (int) $detailId ?>;

    async function loadTutorialDetail(id) {
        const data = await apiGet('get_tutorial', 'id=' + encodeURIComponent(id));
        if (!data.success) {
            tutorialDetailWrap.style.display = 'block';
            tutorialDetailWrap.innerHTML = '<p class="admin-msg is-error">' + esc(data.message || 'Erreur') + '</p>';
            return;
        }
        const t = data.tutorial || {};
        const steps = Array.isArray(t.steps) ? t.steps : [];
        let html = '<div class="admin-card"><h3>' + esc(t.title || '') + '</h3>';
        html += '<p><strong>Généré le :</strong> ' + esc(t.created_at || '—') + '</p>';
        html += '<p><strong>Action :</strong> ' + esc(t.action_type || '') + ' · <strong>Difficulté :</strong> ' + esc(t.difficulty || '') + '</p>';
        if (t.vehicle) html += '<p><strong>Véhicule :</strong> ' + esc(t.vehicle.brand + ' ' + t.vehicle.model + ' (' + t.vehicle.year + ')') + '</p>';
        html += '<p><strong>Session :</strong> <code>' + esc(t.session_id || '') + '</code></p>';
        html += '<p><a href="' + esc(data.public_url || '') + '" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">Ouvrir la page tutoriel</a></p>';
        html += '<p class="dev-hint">' + esc(t.description || '') + '</p>';
        html += '<h4>Étapes (' + steps.length + ')</h4><ol style="font-size:0.85rem">';
        steps.slice(0, 12).forEach(function (s) {
            html += '<li><strong>' + esc(s.title || '') + '</strong> — ' + esc((s.description || '').slice(0, 200)) + '</li>';
        });
        if (steps.length > 12) html += '<li>… ' + (steps.length - 12) + ' étapes supplémentaires</li>';
        html += '</ol></div>';
        html += '<div class="admin-card"><h3>Logs serveur (debug panel)</h3>' + renderDebugLogs(data.debug_logs) + '</div>';
        tutorialDetailWrap.innerHTML = html;
        tutorialDetailWrap.style.display = 'grid';
    }

    async function loadTutorialsList() {
        const data = await apiGet('list_tutorials', 'limit=200');
        if (!data.success) {
            tutorialsListWrap.innerHTML = '<p class="admin-msg is-error">' + esc(data.message || 'Erreur') + '</p>';
            return;
        }
        if (data.warning) tutorialsListWrap.innerHTML = '<p class="dev-hint">' + esc(data.warning) + '</p>';
        let html = '<table class="admin-table"><thead><tr><th>ID</th><th>Titre</th><th>Action</th><th>Véhicule</th><th>Généré le</th></tr></thead><tbody>';
        (data.tutorials || []).forEach(function (t) {
            html += '<tr class="admin-list-row" data-tid="' + esc(t.id) + '"><td>' + esc(t.id) + '</td>';
            html += '<td>' + esc(t.title) + '</td><td>' + esc(t.action_type) + '</td>';
            html += '<td>' + esc(t.vehicle_label || '—') + '</td><td>' + esc(t.created_at) + '</td></tr>';
        });
        html += '</tbody></table>';
        tutorialsListWrap.innerHTML = html;
        tutorialsListWrap.querySelectorAll('.admin-list-row').forEach(function (row) {
            row.addEventListener('click', function () {
                tutorialsListWrap.querySelectorAll('.admin-list-row').forEach(function (r) { r.classList.remove('is-selected'); });
                row.classList.add('is-selected');
                loadTutorialDetail(parseInt(row.getAttribute('data-tid'), 10));
            });
        });
        if (initialTutorialId > 0) {
            const row = tutorialsListWrap.querySelector('[data-tid="' + initialTutorialId + '"]');
            if (row) row.click();
            else loadTutorialDetail(initialTutorialId);
        }
    }
    loadTutorialsList();
    <?php endif; ?>

    <?php if ($activeTab === 'conversations'): ?>
    const conversationsListWrap = document.getElementById('conversationsListWrap');
    const conversationDetailWrap = document.getElementById('conversationDetailWrap');
    const initialConversationId = <?= (int) $detailId ?>;

    async function loadConversationDetail(id) {
        const data = await apiGet('get_conversation', 'id=' + encodeURIComponent(id));
        if (!data.success) {
            conversationDetailWrap.style.display = 'block';
            conversationDetailWrap.innerHTML = '<p class="admin-msg is-error">' + esc(data.message || 'Erreur') + '</p>';
            return;
        }
        const c = data.conversation || {};
        const ctx = c.context && typeof c.context === 'object' ? JSON.stringify(c.context, null, 2) : '';
        let html = '<div class="admin-card"><h3>Conversation #' + esc(c.id) + '</h3>';
        html += '<p><strong>Date :</strong> ' + esc(c.created_at || '—') + '</p>';
        html += '<p><strong>Session :</strong> <code>' + esc(c.session_id || '') + '</code></p>';
        if (c.vehicle) html += '<p><strong>Véhicule :</strong> ' + esc(c.vehicle.brand + ' ' + c.vehicle.model) + '</p>';
        html += '<p><a href="' + esc(data.public_url || '') + '" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">Ouvrir Buddy</a></p>';
        html += '<h4>Question</h4><p style="white-space:pre-wrap;font-size:0.9rem">' + esc(c.user_message || '') + '</p>';
        html += '<h4>Réponse</h4><p style="white-space:pre-wrap;font-size:0.9rem">' + esc(c.buddy_response || '') + '</p>';
        if (ctx) html += '<h4>Contexte (JSON)</h4><pre class="admin-log-pre">' + esc(ctx) + '</pre>';
        html += '</div>';
        html += '<div class="admin-card"><h3>Logs serveur (debug panel)</h3>' + renderDebugLogs(data.debug_logs) + '</div>';
        conversationDetailWrap.innerHTML = html;
        conversationDetailWrap.style.display = 'grid';
    }

    async function loadConversationsList() {
        const data = await apiGet('list_conversations', 'limit=200');
        if (!data.success) {
            conversationsListWrap.innerHTML = '<p class="admin-msg is-error">' + esc(data.message || 'Erreur') + '</p>';
            return;
        }
        let html = '<table class="admin-table"><thead><tr><th>ID</th><th>Aperçu</th><th>Date</th></tr></thead><tbody>';
        (data.conversations || []).forEach(function (c) {
            html += '<tr class="admin-list-row" data-cid="' + esc(c.id) + '"><td>' + esc(c.id) + '</td>';
            html += '<td>' + esc(c.user_message_preview || '') + '</td><td>' + esc(c.created_at) + '</td></tr>';
        });
        html += '</tbody></table>';
        conversationsListWrap.innerHTML = html;
        conversationsListWrap.querySelectorAll('.admin-list-row').forEach(function (row) {
            row.addEventListener('click', function () {
                conversationsListWrap.querySelectorAll('.admin-list-row').forEach(function (r) { r.classList.remove('is-selected'); });
                row.classList.add('is-selected');
                loadConversationDetail(parseInt(row.getAttribute('data-cid'), 10));
            });
        });
        if (initialConversationId > 0) {
            const row = conversationsListWrap.querySelector('[data-cid="' + initialConversationId + '"]');
            if (row) row.click();
            else loadConversationDetail(initialConversationId);
        }
    }
    loadConversationsList();
    <?php endif; ?>

    <?php if ($activeTab === 'vehicles'): ?>
    const vehiclesMsg = document.getElementById('vehiclesMsg');
    const vehiclesWrap = document.getElementById('vehiclesTableWrap');
    let vehicleView = 'catalog-car';

    document.getElementById('vehicleViewTabs')?.addEventListener('click', function (e) {
        const btn = e.target.closest('button[data-view]');
        if (!btn) return;
        vehicleView = btn.getAttribute('data-view');
        document.querySelectorAll('#vehicleViewTabs button').forEach(function (b) {
            b.classList.toggle('active', b === btn);
        });
        const isGarage = vehicleView === 'garage';
        document.getElementById('vehicleCatalogTools').style.display = isGarage ? 'none' : 'flex';
        document.getElementById('vehicleGarageTools').style.display = isGarage ? 'flex' : 'none';
        document.getElementById('garageCreateBlock').style.display = isGarage ? 'block' : 'none';
        if (isGarage) loadGarageVehicles();
        else loadCatalog(vehicleView === 'catalog-moto' ? 'moto' : 'car');
    });

    async function loadCatalog(category) {
        const q = (document.getElementById('catalogSearch')?.value || '').trim();
        let query = 'category=' + encodeURIComponent(category) + '&limit=1500';
        if (q) query += '&q=' + encodeURIComponent(q);
        const data = await apiGet('list_vehicle_catalog', query);
        if (data.warning) showMsg(vehiclesMsg, data.warning, false);
        if (!data.success) {
            vehiclesWrap.innerHTML = '<p class="admin-msg is-error">' + esc(data.message || 'Erreur') + '</p>';
            return;
        }
        let html = '<p class="dev-hint">' + esc(data.items?.length || 0) + ' modèle(s) affiché(s) — total catalogue : ' + esc(data.total_models) + '</p>';
        html += '<table class="admin-table"><thead><tr><th>Marque</th><th>Modèle</th><th>Années</th><th>Motorisations</th><th>Pays</th></tr></thead><tbody>';
        (data.items || []).forEach(function (row) {
            const years = (row.year_start || '?') + ' – ' + (row.year_end || '…');
            html += '<tr><td>' + esc(row.brand) + '</td><td>' + esc(row.model) + '</td><td>' + esc(years) + '</td>';
            html += '<td>' + esc(row.engine_count) + '</td><td>' + esc(row.country || '—') + '</td></tr>';
        });
        html += '</tbody></table>';
        vehiclesWrap.innerHTML = html;
    }

    async function loadGarageVehicles() {
        const uid = parseInt(document.getElementById('vehicleFilterUser')?.value || '0', 10);
        const q = uid > 0 ? 'demo_user_id=' + encodeURIComponent(uid) : '';
        const data = await apiGet('list_vehicles', q);
        if (data.warning) showMsg(vehiclesMsg, data.warning, false);
        if (!data.success) {
            vehiclesWrap.innerHTML = '<p class="admin-msg is-error">' + esc(data.message || 'Erreur') + '</p>';
            return;
        }
        let html = '<p class="dev-hint">' + esc(data.vehicles?.length || 0) + ' véhicule(s) enregistré(s) (garages / sessions).</p>';
        html += '<table class="admin-table"><thead><tr><th>ID</th><th>User</th><th>Marque</th><th>Modèle</th><th>Année</th><th>Moteur</th><th>Slot</th><th></th></tr></thead><tbody>';
        (data.vehicles || []).forEach(function (v) {
            html += '<tr data-vid="' + esc(v.id) + '"><td>' + esc(v.id) + '</td>';
            html += '<td>' + esc(v.demo_user_id ?? '—') + '</td>';
            html += '<td>' + esc(v.brand) + '</td><td>' + esc(v.model) + '</td><td>' + esc(v.year) + '</td>';
            html += '<td>' + esc(v.engine_type || '') + ' ' + esc(v.engine_size || '') + '</td>';
            html += '<td>' + esc(v.slot ?? '') + '</td>';
            html += '<td><button type="button" class="btn btn-secondary btn-sm btn-del-vehicle">Suppr.</button></td></tr>';
        });
        html += '</tbody></table>';
        vehiclesWrap.innerHTML = html;
        vehiclesWrap.querySelectorAll('.btn-del-vehicle').forEach(function (btn) {
            btn.addEventListener('click', async function (ev) {
                ev.stopPropagation();
                if (!confirm('Supprimer ce véhicule ?')) return;
                const id = parseInt(btn.closest('tr').getAttribute('data-vid'), 10);
                const out = await apiPost('delete_vehicle', { id: id });
                showMsg(vehiclesMsg, out.message || (out.success ? 'Supprimé' : 'Erreur'), out.success);
                if (out.success) loadGarageVehicles();
            });
        });
    }

    document.getElementById('btnReloadCatalog')?.addEventListener('click', function () {
        loadCatalog(vehicleView === 'catalog-moto' ? 'moto' : 'car');
    });
    document.getElementById('catalogSearch')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') loadCatalog(vehicleView === 'catalog-moto' ? 'moto' : 'car');
    });
    document.getElementById('btnReloadGarage')?.addEventListener('click', loadGarageVehicles);
    document.getElementById('btnCreateVehicle')?.addEventListener('click', async function () {
        const out = await apiPost('create_vehicle', {
            brand: document.getElementById('vBrand').value.trim(),
            model: document.getElementById('vModel').value.trim(),
            year: parseInt(document.getElementById('vYear').value, 10),
            demo_user_id: parseInt(document.getElementById('vUserId').value, 10) || null,
            slot: parseInt(document.getElementById('vSlot').value, 10) || null,
        });
        showMsg(vehiclesMsg, out.message || (out.success ? 'Véhicule créé' : 'Erreur'), out.success);
        if (out.success) loadGarageVehicles();
    });
    loadCatalog('car');
    <?php endif; ?>

    <?php if ($activeTab === 'debug' && $appDebugOn): ?>
    (async function () {
        const dash = await apiGet('dashboard_stats');
        const toggle = document.getElementById('adminDebugPanelToggle');
        const msg = document.getElementById('debugPanelMsg');
        if (toggle && dash.environment) {
            toggle.checked = dash.environment.debug_panel_setting === true;
        }
        toggle?.addEventListener('change', async function () {
            const out = await apiPost('save_debug_panel', { debug_panel: toggle.checked });
            showMsg(msg, out.message || (out.success ? 'Enregistré' : 'Erreur'), out.success);
        });
    })();
    <?php endif; ?>
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
