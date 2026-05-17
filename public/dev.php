<?php
/**
 * MecaBuddy — Page d'administration POC (paramètres persistants)
 */

require_once __DIR__ . '/../config/config.php';

if (!defined('APP_DEBUG') || !APP_DEBUG) {
    http_response_code(403);
    $pageTitle = 'Accès refusé — MecaBuddy';
    $currentPage = 'dev';
    $skipDemoAuthGuard = true;
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-header">
        <h1 class="page-title">
            <span class="title-icon">🔒</span>
            Administration indisponible
        </h1>
        <p class="page-subtitle">
            Activez <code>APP_DEBUG</code> dans <code>config/config.php</code>
            pour accéder à cette page d’administration.
        </p>
    </div>
    <div class="card login-card" style="max-width:520px;margin:0 auto 2rem">
        <p>
            Cette zone est réservée au mode développeur.
            Vos quotas et clés personnelles sont sur
            <a href="<?= htmlspecialchars(PUBLIC_URL . '/account-settings.php') ?>">Mon compte</a>.
        </p>
        <a href="<?= htmlspecialchars(PUBLIC_URL . '/index.php') ?>" class="btn btn-primary">Retour à l'accueil</a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = 'Admin POC — MecaBuddy';
$currentPage = 'dev';
$skipDemoAuthGuard = true;

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.dev-section--global {
    border: 1px dashed rgba(255, 255, 255, 0.2);
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.15);
}
.dev-section--global h2 {
    margin: 0 0 0.5rem;
    font-size: 1.15rem;
}
.toggle-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    margin-top: 0.75rem;
    position: relative;
}
.toggle-label input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-track {
    width: 44px;
    height: 24px;
    background: #555;
    border-radius: 12px;
    position: relative;
    flex-shrink: 0;
    transition: background 0.2s;
}
.toggle-track::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    top: 2px;
    left: 2px;
    transition: transform 0.2s;
}
.toggle-label input:checked + .toggle-track {
    background: var(--primary, #2563eb);
}
.toggle-label input:checked + .toggle-track::after {
    transform: translateX(20px);
}
.toggle-text {
    font-weight: 500;
}
.dev-status {
    margin-top: 10px;
    font-size: 0.85rem;
    opacity: 0.9;
}
</style>

<div class="page-header">
    <h1 class="page-title">
        <span class="title-icon">⚙️</span>
        Administration POC
    </h1>
    <p class="page-subtitle">
        Paramètres persistants (<code class="dev-inline-code">config/settings.json</code>) — administration POC.
    </p>
</div>

<div class="dev-admin">

    <section class="dev-section dev-section--global" id="dev-mode-section">
        <h2>🛠️ Mode développeur</h2>
        <p class="dev-hint">
            Contrôle l’accès à cette page et à l’API <code class="dev-inline-code">dev_api.php</code>.
            Désactivez-le en production ; pour le réactiver, éditez aussi
            <code class="dev-inline-code">config/settings.json</code> si besoin.
        </p>
        <label class="toggle-label">
            <input type="checkbox" id="toggle-dev-mode">
            <span class="toggle-track"></span>
            <span class="toggle-text">Mode développeur activé</span>
        </label>
        <div id="dev-mode-status" class="dev-status" style="display:none"></div>
    </section>

    <section class="dev-section dev-section--global">
        <h2>🎭 Mode démo</h2>
        <p class="dev-hint">
            Active les données mockées (MockDatabase) à la place de SQLite.
            Utile pour tester l'UI sans données réelles.
            Les autres paramètres (LLM, plaque) restent actifs selon leur propre toggle.
        </p>
        <label class="toggle-label">
            <input type="checkbox" id="toggle-demo-mode">
            <span class="toggle-track"></span>
            <span class="toggle-text">Mode démo activé</span>
        </label>
        <div id="demo-mode-status" class="dev-status" style="display:none"></div>
    </section>

    <section class="dev-section dev-section--global" id="demo-instance-section" style="border-top:1px solid var(--border,#333);padding-top:16px;margin-top:0">
        <h2>🔐 Instance démo (auth & quotas)</h2>
        <p class="dev-hint">
            Connexion obligatoire + quotas journaliers (15 tutoriels / 15 Buddy, compteurs séparés).
            Comptes : <code>demo-demo</code> / <code>demo-demo</code>, <code>demo-fairuse</code> / <code>demo-fairuse</code>.
        </p>
        <label class="toggle-label">
            <input type="checkbox" id="toggle-demo-auth">
            <span class="toggle-track"></span>
            <span class="toggle-text">Authentification démo activée</span>
        </label>
        <div class="dev-actions-row" style="margin-top:12px;flex-wrap:wrap;gap:8px">
            <button type="button" class="btn btn-secondary btn-sm" id="btn-reset-demo-usage">Réinitialiser usages du jour</button>
            <button type="button" class="btn btn-secondary btn-sm" id="btn-rebuild-demo-users">Recréer comptes démo</button>
            <button type="button" class="btn btn-secondary btn-sm" id="btn-refresh-demo-users">Rafraîchir</button>
            <button type="button" class="btn btn-secondary btn-sm" id="btn-reset-demo-vehicles">Réinitialiser garages démo</button>
            <button type="button" class="btn btn-secondary btn-sm" id="btn-rebuild-demo-vehicles">Recréer véhicules démo</button>
        </div>
        <div id="demo-users-table" class="dev-status" style="margin-top:12px;display:none"></div>
        <div id="demo-garages-table" class="dev-status" style="margin-top:12px;display:none"></div>
    </section>

    <section class="dev-section dev-section--global" id="byok-admin-section" style="border-top:1px solid var(--border,#333);padding-top:16px;margin-top:0">
        <h2>🔑 BYOK utilisateur (résumé)</h2>
        <p class="dev-hint">Gestion des clés sur <code class="dev-inline-code">account-settings.php</code> — aucun secret ici.</p>
        <label class="toggle-label">
            <input type="checkbox" id="toggle-byok-enabled">
            <span class="toggle-track"></span>
            <span class="toggle-text">BYOK activé sur l'instance</span>
        </label>
        <div id="byok-stats" class="dev-status" style="margin-top:12px"></div>
    </section>

    <section class="dev-section dev-section--global" style="border-top:1px solid var(--border,#333);padding-top:16px;margin-top:0">
        <h2>🗄️ Base de données</h2>
        <p class="dev-hint">
            Rejoue les INSERT du schéma SQLite (nouvelles marques, modèles, motorisations).
            N'efface aucune donnée existante — utilise INSERT OR IGNORE.
        </p>
        <button type="button" id="btn-rebuild-db" class="btn btn-secondary">
            <span class="btn-icon">🔄</span>
            Mettre à jour le référentiel (marques / modèles / moteurs)
        </button>
        <div id="rebuild-result" class="dev-status" style="display:none;margin-top:10px"></div>
    </section>

    <section class="dev-section dev-section--global" style="border-top:1px solid var(--border,#333);padding-top:16px;margin-top:0">
        <h2>🐛 Debug Panel</h2>
        <p class="dev-hint">
            Affiche un panneau de trace en temps réel sur les pages Diagnostic et Tutoriels.
            Visible sur Diagnostic et Tutoriels quand cette option est activée et que <code class="dev-inline-code">APP_DEBUG</code> est actif dans <code class="dev-inline-code">config/config.php</code>.
        </p>
        <label class="toggle-label">
            <input type="checkbox" id="toggle-debug-panel">
            <span class="toggle-track"></span>
            <span class="toggle-text">Debug Panel activé</span>
        </label>
    </section>

    <!-- Section plaque -->
    <section class="selection-section dev-section">
        <div class="section-header">
            <h2>
                <span class="section-number">1</span>
                Recherche par plaque
            </h2>
        </div>
        <p class="dev-hint">
            Fournisseur&nbsp;: <strong>apiplaqueimmatriculation.com</strong>
        </p>
        <div class="dev-switch-row">
            <label class="dev-switch">
                <input type="checkbox" id="plateEnabled" class="dev-switch-input" aria-describedby="plateEnabledHelp">
                <span class="dev-switch-slider" aria-hidden="true"></span>
            </label>
            <div>
                <span class="dev-switch-label">Activer la recherche par plaque (API externe)</span>
                <p id="plateEnabledHelp" class="dev-field-help">Les appels réels consomment votre quota API.</p>
            </div>
        </div>
        <div class="form-group">
            <label for="plateApiKey" class="form-label">
                <span class="label-icon">🔑</span>
                Clé API
            </label>
            <input type="password" id="plateApiKey" class="dev-text-input" autocomplete="off" placeholder="Token API">
        </div>
        <div class="dev-actions-row">
            <button type="button" class="btn btn-secondary" id="btnTestPlate">
                <span class="btn-icon">🧪</span>
                Tester
            </button>
        </div>
        <div class="form-group">
            <label class="form-label" for="plateTestOut">Réponse (JSON)</label>
            <pre id="plateTestOut" class="dev-json-pre" aria-live="polite">{}</pre>
        </div>
    </section>

    <!-- Section LLM -->
    <section class="selection-section dev-section">
        <div class="section-header">
            <h2>
                <span class="section-number">2</span>
                Fournisseurs LLM
            </h2>
        </div>
        <div id="providerList" class="dev-provider-list"></div>

        <div class="dev-actions-row" style="margin-bottom:12px">
            <button type="button" class="btn btn-secondary" id="btnPresetGemini">
                <span class="btn-icon">✨</span>
                Preset Gemini Flash-Lite (instance démo)
            </button>
        </div>
        <h3 class="dev-subheading">Ajouter un provider</h3>
        <form id="addProviderForm" class="dev-add-form">
            <div class="form-group">
                <label class="form-label" for="provider-name">Nom</label>
                <input type="text" id="provider-name" class="dev-text-input" required maxlength="120" placeholder="Mon serveur Ollama">
            </div>
            <div class="form-group">
                <label class="form-label" for="provider-type">Type</label>
                <select id="provider-type" class="form-select">
                    <option value="ollama">ollama</option>
                    <option value="openai_compatible">openai_compatible</option>
                    <option value="mistral">Mistral AI (cloud)</option>
                </select>
            </div>
            <div class="form-group" id="field-base-url">
                <label class="form-label" for="provider-base-url">URL de base</label>
                <input type="text" id="provider-base-url" class="dev-text-input" placeholder="http://localhost:11434" maxlength="512">
            </div>
            <div class="form-group" id="field-chat-path" style="display:none">
                <label class="form-label" for="provider-chat-path">Chemin chat (openai_compatible)</label>
                <input type="text" id="provider-chat-path" class="dev-text-input" placeholder="/chat/completions" maxlength="128">
                <p class="dev-field-help">Gemini : <code>/chat/completions</code> — OpenAI classique : <code>/v1/chat/completions</code></p>
            </div>
            <div class="form-group" id="field-api-key" style="display:none">
                <label class="form-label" for="provider-api-key">Clé API</label>
                <input type="password" id="provider-api-key" class="dev-text-input" autocomplete="off" placeholder="sk-...">
            </div>
            <div class="form-group">
                <label class="form-label" for="provider-model">Modèle</label>
                <datalist id="mistral-models">
                    <option value="mistral-small-latest">
                    <option value="mistral-medium-latest">
                    <option value="mistral-large-latest">
                    <option value="open-mistral-7b">
                    <option value="open-mixtral-8x7b">
                </datalist>
                <input type="text" id="provider-model" class="dev-text-input" placeholder="gemma4:26b" maxlength="160">
            </div>
            <button type="submit" class="btn btn-primary">
                <span class="btn-icon">➕</span>
                Ajouter
            </button>
        </form>
    </section>

    <!-- Section recherche web -->
    <section class="selection-section dev-section">
        <div class="section-header">
            <h2>
                <span class="section-number">3</span>
                Recherche web (enrichissement LLM)
            </h2>
        </div>
        <p class="dev-hint">
            Utilisée pour injecter des résultats web dans les réponses du chat.
            DuckDuckGo est actif par défaut sans configuration.
            Serper offre de meilleurs résultats si une clé est fournie.
        </p>
        <div class="form-group">
            <label for="serper-api-key" class="form-label">Clé Serper (optionnel — améliore la qualité)</label>
            <div class="dev-actions-row">
                <input type="password" id="serper-api-key" class="dev-text-input" autocomplete="off"
                    placeholder="Laisser vide pour utiliser DuckDuckGo" style="flex:1;min-width:200px">
                <button type="button" class="btn btn-secondary" id="btn-test-search">
                    <span class="btn-icon">🧪</span>
                    Tester
                </button>
            </div>
        </div>
        <div id="search-test-result" class="dev-status" style="display:none" aria-live="polite"></div>
    </section>

    <!-- Section fallback -->
    <section class="selection-section dev-section">
        <div class="section-header">
            <h2>
                <span class="section-number">4</span>
                Fallback LLM
            </h2>
        </div>
        <div class="dev-switch-row">
            <label class="dev-switch">
                <input type="checkbox" id="llmFallback" class="dev-switch-input" aria-describedby="fallbackHelp">
                <span class="dev-switch-slider" aria-hidden="true"></span>
            </label>
            <div>
                <span class="dev-switch-label">Activer le fallback LLM si la plaque ne retourne rien</span>
                <p id="fallbackHelp" class="dev-field-help">Utilise le fournisseur LLM actif lorsque l'API plaque est vide ou en erreur.</p>
            </div>
        </div>
    </section>
</div>

<script>
(function () {
    const API_BASE = '<?= htmlspecialchars(API_URL, ENT_QUOTES, 'UTF-8') ?>';

    /** État courant des paramètres (aligné sur getSettings / save_settings). */
    let currentSettings = {};

    async function loadInitialSettings() {
        const res = await fetch(API_BASE + '/dev_api.php?action=get_settings');
        if (!res.ok) {
            throw new Error('Impossible de charger les paramètres');
        }
        currentSettings = await res.json();
        if (typeof currentSettings.demo_mode !== 'boolean') {
            currentSettings.demo_mode = false;
        }
        if (typeof currentSettings.debug_panel !== 'boolean') {
            currentSettings.debug_panel = false;
        }
        if (typeof currentSettings.demo_auth_enabled !== 'boolean') {
            currentSettings.demo_auth_enabled = false;
        }
        if (typeof currentSettings.dev_mode !== 'boolean') {
            currentSettings.dev_mode = false;
        }
        if (typeof currentSettings.byok_enabled !== 'boolean') {
            currentSettings.byok_enabled = true;
        }
    }

    async function loadByokStats() {
        const el = document.getElementById('byok-stats');
        if (!el) return;
        try {
            const res = await fetch(API_BASE + '/dev_api.php?action=get_byok_stats');
            const data = await res.json();
            const s = data.stats || {};
            el.innerHTML = '<p>BYOK instance : <strong>' + (s.byok_enabled ? 'activé' : 'désactivé') + '</strong></p>'
                + '<p>Clés configurées : <strong>' + esc(String(s.configured ?? 0)) + '</strong> — validées : <strong>'
                + esc(String(s.validated ?? 0)) + '</strong> — actives : <strong>' + esc(String(s.active ?? 0)) + '</strong></p>';
        } catch (e) {
            el.textContent = 'Impossible de charger les stats BYOK.';
        }
    }

    async function loadDemoGaragesTable() {
        const el = document.getElementById('demo-garages-table');
        if (!el) return;
        try {
            const res = await fetch(API_BASE + '/dev_api.php?action=get_demo_garages');
            const data = await res.json();
            if (!data.success) {
                el.style.display = 'block';
                el.textContent = 'Impossible de charger les garages démo.';
                return;
            }
            let html = '';
            (data.accounts || []).forEach((acc) => {
                html += '<p><strong>' + esc(acc.username) + '</strong> — ' + esc(String(acc.vehicles_count)) + ' véhicule(s)</p>';
                html += '<ul style="margin:0 0 10px 1rem;font-size:0.8rem">';
                (acc.active_slots || []).forEach((v) => {
                    html += '<li>Slot ' + esc(String(v.slot)) + ' : ' + esc(v.brand + ' ' + v.model) + '</li>';
                });
                html += '</ul>';
                if (acc.primary_vehicle) {
                    html += '<p style="font-size:0.75rem;opacity:0.8">Principal (slot 1) : '
                        + esc(acc.primary_vehicle.brand + ' ' + acc.primary_vehicle.model) + '</p>';
                }
            });
            el.innerHTML = html || '<p>Aucun garage démo.</p>';
            el.style.display = 'block';
        } catch (e) {
            el.style.display = 'block';
            el.textContent = 'Erreur chargement garages démo.';
        }
    }

    async function loadDemoUsersTable() {
        const el = document.getElementById('demo-users-table');
        if (!el) return;
        try {
            const res = await fetch(API_BASE + '/dev_api.php?action=get_demo_users');
            const data = await res.json();
            if (!data.success) {
                el.style.display = 'block';
                el.textContent = 'Impossible de charger les comptes démo.';
                return;
            }
            let html = '<table style="width:100%;font-size:0.8rem;border-collapse:collapse">';
            html += '<tr><th>Compte</th><th>Tuto</th><th>Buddy</th></tr>';
            (data.users || []).forEach((u) => {
                const ut = u.usage_today?.tutorial || {};
                const ub = u.usage_today?.buddy || {};
                html += '<tr><td>' + esc(u.username) + '</td>';
                html += '<td>' + esc(String(ut.used)) + '/' + esc(String(ut.limit)) + ' (' + esc(String(ut.remaining)) + ' rest.)</td>';
                html += '<td>' + esc(String(ub.used)) + '/' + esc(String(ub.limit)) + ' (' + esc(String(ub.remaining)) + ' rest.)</td></tr>';
            });
            html += '</table><p style="margin-top:6px;opacity:0.7">Reset : ' + esc(data.reset_at || '') + '</p>';
            el.innerHTML = html;
            el.style.display = 'block';
        } catch (e) {
            el.style.display = 'block';
            el.textContent = 'Erreur chargement comptes démo.';
        }
    }

    function esc(s) {
        if (typeof window.escapeHtml === 'function') {
            return window.escapeHtml(String(s));
        }
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function escAttr(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function updateDemoStatus(enabled) {
        const status = document.getElementById('demo-mode-status');
        status.textContent = enabled
            ? '⚠️ Les données affichées sont fictives (MockDatabase).'
            : '✅ Données réelles (SQLite).';
        status.style.display = 'block';
    }

    function syncFormFromState() {
        const devModeToggle = document.getElementById('toggle-dev-mode');
        if (devModeToggle) {
            devModeToggle.checked = currentSettings.dev_mode === true;
        }
        document.getElementById('toggle-demo-mode').checked = currentSettings.demo_mode === true;
        const demoAuthToggle = document.getElementById('toggle-demo-auth');
        if (demoAuthToggle) {
            demoAuthToggle.checked = currentSettings.demo_auth_enabled === true;
        }
        const debugPanelToggle = document.getElementById('toggle-debug-panel');
        if (debugPanelToggle) {
            debugPanelToggle.checked = currentSettings.debug_panel === true;
        }
        const byokToggle = document.getElementById('toggle-byok-enabled');
        if (byokToggle) {
            byokToggle.checked = currentSettings.byok_enabled === true;
        }
        document.getElementById('plateEnabled').checked = !!(currentSettings.plate_lookup && currentSettings.plate_lookup.enabled);
        const plateKeyEl = document.getElementById('plateApiKey');
        if (plateKeyEl) {
            plateKeyEl.value = '';
            plateKeyEl.placeholder = (currentSettings.plate_lookup && currentSettings.plate_lookup.api_key_configured)
                ? 'Clé configurée — saisir une nouvelle valeur pour remplacer'
                : 'Token API';
        }
        document.getElementById('llmFallback').checked = !!currentSettings.llm_fallback_enabled;
        const serperKeyEl = document.getElementById('serper-api-key');
        if (serperKeyEl) {
            serperKeyEl.value = '';
            serperKeyEl.placeholder = currentSettings.serper_api_key_configured
                ? 'Clé configurée — saisir une nouvelle valeur pour remplacer'
                : 'Clé Serper (optionnel)';
        }
    }

    function setSearchTestResult(html, isError) {
        const el = document.getElementById('search-test-result');
        if (!el) {
            return;
        }
        el.style.display = 'block';
        el.innerHTML = html;
        el.style.color = isError ? 'var(--danger, #f87171)' : '';
    }

    /**
     * @param {string} [successToastMessage]
     * @returns {Promise<boolean>}
     */
    async function persist(successToastMessage) {
        showLoading(true);
        try {
            const res = await fetch(API_BASE + '/dev_api.php?action=save_settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({}, currentSettings, { llm_providers: providersForSave(currentSettings.llm_providers) }))
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.error || 'Échec de la sauvegarde');
            }
            currentSettings = data.settings;
            if (typeof currentSettings.demo_mode !== 'boolean') {
                currentSettings.demo_mode = false;
            }
            if (typeof currentSettings.debug_panel !== 'boolean') {
                currentSettings.debug_panel = false;
            }
            if (typeof currentSettings.demo_auth_enabled !== 'boolean') {
                currentSettings.demo_auth_enabled = false;
            }
            syncFormFromState();
            renderProviders();
            loadDemoUsersTable();
            showToast(successToastMessage || 'Paramètres enregistrés', 'success');
            return true;
        } catch (err) {
            console.error(err);
            showToast(err.message || 'Erreur', 'error');
            return false;
        } finally {
            showLoading(false);
        }
    }

    const GEMINI_PRESET = {
        id: 'demo_gemini',
        name: 'Gemini Flash-Lite (instance démo)',
        type: 'openai_compatible',
        base_url: 'https://generativelanguage.googleapis.com/v1beta/openai',
        chat_path: '/chat/completions',
        model: 'gemini-2.5-flash-lite'
    };

    function providersForSave(list) {
        return (list || []).map(function (p) {
            const copy = Object.assign({}, p);
            if (copy.api_key_configured && !copy.api_key) {
                delete copy.api_key;
            }
            delete copy.api_key_configured;
            return copy;
        });
    }

    function syncProviderFormFields() {
        const typeEl = document.getElementById('provider-type');
        const fieldApiKey = document.getElementById('field-api-key');
        const fieldBaseUrl = document.getElementById('field-base-url');
        const fieldChatPath = document.getElementById('field-chat-path');
        const apiKeyEl = document.getElementById('provider-api-key');
        const modelEl = document.getElementById('provider-model');
        if (!typeEl) {
            return;
        }
        const type = typeEl.value;
        const needsKey = ['mistral', 'openai_compatible'].includes(type);
        const needsUrl = ['ollama', 'openai_compatible'].includes(type);
        const isOpenAi = type === 'openai_compatible';
        if (fieldApiKey) {
            fieldApiKey.style.display = needsKey ? 'block' : 'none';
        }
        if (fieldBaseUrl) {
            fieldBaseUrl.style.display = needsUrl ? 'block' : 'none';
        }
        if (fieldChatPath) {
            fieldChatPath.style.display = isOpenAi ? 'block' : 'none';
        }
        if (apiKeyEl && needsKey) {
            apiKeyEl.placeholder = 'Clé API Google AI Studio';
        }
        if (modelEl) {
            if (type === 'mistral') {
                modelEl.setAttribute('list', 'mistral-models');
                modelEl.placeholder = 'mistral-small-latest';
            } else {
                modelEl.removeAttribute('list');
                modelEl.placeholder = type === 'ollama' ? 'gemma4:26b' : 'gemini-2.5-flash-lite';
            }
        }
        const chatPathEl = document.getElementById('provider-chat-path');
        if (chatPathEl && isOpenAi && !chatPathEl.value.trim()) {
            chatPathEl.value = '/chat/completions';
        }
        const baseUrlEl = document.getElementById('provider-base-url');
        if (baseUrlEl && isOpenAi && !baseUrlEl.value.trim()) {
            baseUrlEl.value = GEMINI_PRESET.base_url;
        }
    }

    function renderProviders() {
        const root = document.getElementById('providerList');
        const list = Array.isArray(currentSettings.llm_providers) ? currentSettings.llm_providers : [];
        root.innerHTML = '';

        if (list.length === 0) {
            root.innerHTML = '<p class="dev-empty">Aucun fournisseur. Ajoutez-en un ci-dessous.</p>';
            return;
        }

        list.forEach(function (p) {
            const id = p.id || '';
            const active = p.active === true;
            const pType = p.type || '';
            const isMistral = pType === 'mistral';
            const hasKey = p.api_key_configured === true;
            let metaHtml = '';
            if (isMistral) {
                metaHtml =
                    '<div class="dev-provider-meta">' +
                        '<span class="dev-meta-label">Service</span> <span>☁️ Mistral AI</span>' +
                    '</div>' +
                    '<div class="dev-provider-meta">' +
                        '<span class="dev-meta-label">Clé</span> ' +
                        (hasKey ? '<span>🔑 Clé : ••••••</span>' : '<span>⚠️ Clé manquante</span>') +
                    '</div>';
            } else {
                metaHtml =
                    '<div class="dev-provider-meta">' +
                        '<span class="dev-meta-label">URL</span> <code class="dev-meta-code">' + esc(p.base_url || '') + '</code>' +
                    '</div>';
                if (pType === 'openai_compatible' && p.chat_path) {
                    metaHtml +=
                        '<div class="dev-provider-meta">' +
                            '<span class="dev-meta-label">Chemin</span> <code class="dev-meta-code">' + esc(p.chat_path) + '</code>' +
                        '</div>';
                }
                if (pType === 'openai_compatible') {
                    metaHtml +=
                        '<div class="dev-provider-meta">' +
                            '<span class="dev-meta-label">Clé</span> ' +
                            (hasKey ? '<span>🔑 Configurée</span>' : '<span>⚠️ Clé manquante</span>') +
                        '</div>';
                }
            }
            const card = document.createElement('article');
            card.className = 'dev-provider-card';
            card.innerHTML =
                '<div class="dev-provider-head">' +
                    '<div class="dev-provider-title">' +
                        '<span class="dev-provider-name">' + esc(p.name || '') + '</span>' +
                        '<span class="dev-type-badge">' + esc(isMistral ? '☁️ Mistral AI' : pType) + '</span>' +
                        (active ? '<span class="dev-active-pill">Actif</span>' : '') +
                    '</div>' +
                    metaHtml +
                    '<div class="dev-provider-meta">' +
                        '<span class="dev-meta-label">Modèle</span> <code class="dev-meta-code">' + esc(p.model || '') + '</code>' +
                    '</div>' +
                '</div>' +
                '<div class="dev-provider-actions">' +
                    '<button type="button" class="btn btn-secondary dev-btn-compact" data-dev-action="activate" data-provider-id="' + escAttr(id) + '"' + (active ? ' disabled' : '') + '>Activer</button>' +
                    '<button type="button" class="btn btn-secondary dev-btn-compact" data-dev-action="test-llm" data-provider-id="' + escAttr(id) + '">Tester la connexion</button>' +
                    '<button type="button" class="btn btn-secondary dev-btn-compact dev-btn-danger" data-dev-action="delete" data-provider-id="' + escAttr(id) + '">Supprimer</button>' +
                '</div>' +
                '<pre class="dev-json-pre dev-llm-pre hidden" aria-live="polite"></pre>';
            root.appendChild(card);
        });
    }

    function setPlateTestOutput(obj) {
        document.getElementById('plateTestOut').textContent = JSON.stringify(obj, null, 2);
    }

    function ensurePlateLookup() {
        if (!currentSettings.plate_lookup || typeof currentSettings.plate_lookup !== 'object') {
            currentSettings.plate_lookup = { enabled: false, provider: 'apiplaqueimmatriculation', api_key: '' };
        }
    }

    document.addEventListener('DOMContentLoaded', async function () {
        try {
            showLoading(true);
            await loadInitialSettings();
        } catch (e) {
            console.error(e);
            showToast(e.message || 'Impossible de lire la configuration', 'error');
            currentSettings = {
                plate_lookup: { enabled: false, provider: 'apiplaqueimmatriculation', api_key: '' },
                llm_fallback_enabled: false,
                demo_mode: false,
                llm_providers: []
            };
        } finally {
            showLoading(false);
        }

        syncFormFromState();
        renderProviders();
        syncProviderFormFields();
        updateDemoStatus(currentSettings.demo_mode === true);
        loadDemoUsersTable();
        loadDemoGaragesTable();
        loadByokStats();

        const toggleByok = document.getElementById('toggle-byok-enabled');
        if (toggleByok) {
            toggleByok.addEventListener('change', async function () {
                const chk = this;
                const enabled = chk.checked;
                const prev = currentSettings.byok_enabled === true;
                currentSettings.byok_enabled = enabled;
                const ok = await persist(enabled ? '🔑 BYOK activé' : '🔑 BYOK désactivé');
                if (!ok) {
                    currentSettings.byok_enabled = prev;
                    chk.checked = prev;
                } else {
                    loadByokStats();
                }
            });
        }

        const providerTypeEl = document.getElementById('provider-type');
        if (providerTypeEl) {
            providerTypeEl.addEventListener('change', syncProviderFormFields);
        }

        const btnRebuildDb = document.getElementById('btn-rebuild-db');
        if (btnRebuildDb) {
            const rebuildLabel = btnRebuildDb.innerHTML;
            btnRebuildDb.addEventListener('click', function () {
                const btn = this;
                const result = document.getElementById('rebuild-result');
                btn.disabled = true;
                btn.innerHTML = '<span class="btn-icon">⏳</span> Mise à jour en cours...';
                result.style.display = 'none';

                fetch(API_BASE + '/dev_api.php?action=rebuild_db', { method: 'POST' })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            result.style.display = 'block';
                            const carBrands = (data.brands || 0) - (data.brands_moto || 0);
                            result.innerHTML =
                                '✅ Référentiel mis à jour<br>' +
                                '🚗 Marques voiture : <strong>' + carBrands + '</strong> · ' +
                                '🏍️ Marques moto : <strong>' + (data.brands_moto || 0) + '</strong><br>' +
                                '📋 Modèles : <strong>' + (data.models || 0) + '</strong> · ' +
                                '⚙️ Motorisations : <strong>' + (data.engine_types || 0) + '</strong>';
                            showToast('✅ Base de données mise à jour', 'success');
                        } else {
                            result.style.display = 'block';
                            result.textContent = '❌ Erreur : ' + (data.error || 'inconnue');
                        }
                    })
                    .catch(() => {
                        result.style.display = 'block';
                        result.textContent = '❌ Erreur réseau';
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = rebuildLabel;
                    });
            });
        }

        const toggleDevMode = document.getElementById('toggle-dev-mode');
        if (toggleDevMode) {
            toggleDevMode.addEventListener('change', async function () {
                const chk = this;
                const enabled = chk.checked;
                const prev = currentSettings.dev_mode === true;
                currentSettings.dev_mode = enabled;
                const ok = await persist(enabled ? '🛠️ Mode développeur activé' : '🛠️ Mode développeur désactivé');
                if (!ok) {
                    currentSettings.dev_mode = prev;
                    chk.checked = prev;
                } else if (!enabled) {
                    const status = document.getElementById('dev-mode-status');
                    if (status) {
                        status.textContent = 'Rechargez la page : l’administration sera inaccessible.';
                        status.style.display = 'block';
                    }
                }
            });
        }

        document.getElementById('toggle-demo-mode').addEventListener('change', async function () {
            const chk = this;
            const enabled = chk.checked;
            const prev = currentSettings.demo_mode === true;
            currentSettings.demo_mode = enabled;
            const ok = await persist(enabled ? '🎭 Mode démo activé' : '🎭 Mode démo désactivé');
            if (ok) {
                updateDemoStatus(enabled);
            } else {
                currentSettings.demo_mode = prev;
                chk.checked = prev;
            }
        });

        const toggleDemoAuth = document.getElementById('toggle-demo-auth');
        if (toggleDemoAuth) {
            toggleDemoAuth.addEventListener('change', async function () {
                const chk = this;
                const enabled = chk.checked;
                const prev = currentSettings.demo_auth_enabled === true;
                currentSettings.demo_auth_enabled = enabled;
                const ok = await persist(enabled ? '🔐 Auth démo activée' : '🔐 Auth démo désactivée');
                if (!ok) {
                    currentSettings.demo_auth_enabled = prev;
                    chk.checked = prev;
                }
            });
        }

        document.getElementById('btn-refresh-demo-users')?.addEventListener('click', () => {
            loadDemoUsersTable();
            loadDemoGaragesTable();
        });

        document.getElementById('btn-reset-demo-vehicles')?.addEventListener('click', async () => {
            if (!confirm('Supprimer les véhicules préconfigurés démo (seed) pour tous les comptes ?')) return;
            showLoading(true);
            try {
                const res = await fetch(API_BASE + '/dev_api.php?action=reset_demo_vehicles', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    showToast('Garages démo réinitialisés', 'success');
                    loadDemoGaragesTable();
                } else {
                    showToast(data.error || 'Erreur', 'error');
                }
            } catch (e) {
                showToast('Erreur réseau', 'error');
            } finally {
                showLoading(false);
            }
        });

        document.getElementById('btn-rebuild-demo-vehicles')?.addEventListener('click', async () => {
            showLoading(true);
            try {
                const res = await fetch(API_BASE + '/dev_api.php?action=rebuild_demo_vehicles', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    showToast('Véhicules démo recréés', 'success');
                    loadDemoGaragesTable();
                } else {
                    showToast(data.error || 'Erreur', 'error');
                }
            } catch (e) {
                showToast('Erreur réseau', 'error');
            } finally {
                showLoading(false);
            }
        });

        document.getElementById('btn-reset-demo-usage')?.addEventListener('click', async () => {
            showLoading(true);
            try {
                const res = await fetch(API_BASE + '/dev_api.php?action=reset_demo_usage_today', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    showToast('Usages du jour réinitialisés', 'success');
                    loadDemoUsersTable();
                } else {
                    showToast(data.error || 'Erreur', 'error');
                }
            } catch (e) {
                showToast('Erreur réseau', 'error');
            } finally {
                showLoading(false);
            }
        });

        document.getElementById('btn-rebuild-demo-users')?.addEventListener('click', async () => {
            showLoading(true);
            try {
                const res = await fetch(API_BASE + '/dev_api.php?action=rebuild_demo_users', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    showToast('Comptes démo recréés', 'success');
                    loadDemoUsersTable();
                } else {
                    showToast(data.error || 'Erreur', 'error');
                }
            } catch (e) {
                showToast('Erreur réseau', 'error');
            } finally {
                showLoading(false);
            }
        });

        const debugPanelChk = document.getElementById('toggle-debug-panel');
        if (debugPanelChk) {
            debugPanelChk.addEventListener('change', async function () {
                const chk = this;
                const enabled = chk.checked;
                const prev = currentSettings.debug_panel === true;
                currentSettings.debug_panel = enabled;
                const ok = await persist(
                    enabled ? '🐛 Debug Panel activé' : '🐛 Debug Panel désactivé'
                );
                if (!ok) {
                    currentSettings.debug_panel = prev;
                    chk.checked = prev;
                }
            });
        }

        document.getElementById('plateEnabled').addEventListener('change', function () {
            ensurePlateLookup();
            currentSettings.plate_lookup.enabled = this.checked;
            persist();
        });

        document.getElementById('plateApiKey').addEventListener('change', function () {
            ensurePlateLookup();
            currentSettings.plate_lookup.api_key = this.value;
            persist();
        });

        document.getElementById('llmFallback').addEventListener('change', function () {
            currentSettings.llm_fallback_enabled = this.checked;
            persist();
        });

        const serperKeyInput = document.getElementById('serper-api-key');
        if (serperKeyInput) {
            serperKeyInput.addEventListener('change', function () {
                currentSettings.serper_api_key = this.value;
                persist();
            });
        }

        const btnTestSearch = document.getElementById('btn-test-search');
        if (btnTestSearch) {
            btnTestSearch.addEventListener('click', async function () {
                showLoading(true);
                setSearchTestResult('Recherche en cours…', false);
                try {
                    const res = await fetch(API_BASE + '/dev_api.php?action=test_search');
                    const data = await res.json().catch(function () { return {}; });
                    if (!res.ok || !data.success) {
                        throw new Error(data.error || 'Échec du test de recherche');
                    }
                    const providerLabel = {
                        serper: 'Serper',
                        duckduckgo: 'DuckDuckGo',
                        none: 'aucun'
                    }[data.provider] || data.provider;
                    let html = '<strong>Provider :</strong> ' + esc(providerLabel)
                        + ' · <strong>' + esc(String(data.count)) + '</strong> résultat(s)<br>';
                    const rows = Array.isArray(data.results) ? data.results : [];
                    if (rows.length === 0) {
                        html += 'Aucun résultat (vérifiez la connectivité ou le parsing HTML).';
                    } else {
                        html += '<ul style="margin:0.5rem 0 0;padding-left:1.2rem">';
                        rows.forEach(function (r) {
                            const title = esc(r.title || '(sans titre)');
                            const url = esc(r.url || '');
                            html += '<li>' + title + (url ? ' — <a href="' + escAttr(url) + '" target="_blank" rel="noopener">' + url + '</a>' : '') + '</li>';
                        });
                        html += '</ul>';
                    }
                    setSearchTestResult(html, false);
                } catch (err) {
                    console.error(err);
                    setSearchTestResult(esc(err.message || 'Erreur'), true);
                    showToast(err.message || 'Erreur test recherche', 'error');
                } finally {
                    showLoading(false);
                }
            });
        }

        document.getElementById('btnTestPlate').addEventListener('click', async function () {
            showLoading(true);
            try {
                const res = await fetch(API_BASE + '/dev_api.php?action=test_plate');
                const data = await res.json().catch(function () { return { parse_error: true }; });
                setPlateTestOutput(data);
                if (!res.ok) {
                    showToast(data.error || 'Erreur test plaque', 'error');
                }
            } catch (err) {
                console.error(err);
                setPlateTestOutput({ success: false, error: String(err.message || err) });
                showToast('Erreur réseau (test plaque)', 'error');
            } finally {
                showLoading(false);
            }
        });

        document.getElementById('providerList').addEventListener('click', async function (e) {
            const btn = e.target.closest('[data-dev-action]');
            if (!btn) return;
            const action = btn.getAttribute('data-dev-action');
            const pid = btn.getAttribute('data-provider-id');
            if (!pid) return;

            if (action === 'activate') {
                currentSettings.llm_providers = (currentSettings.llm_providers || []).map(function (p) {
                    return Object.assign({}, p, { active: String(p.id) === String(pid) });
                });
                await persist();
                return;
            }

            if (action === 'delete') {
                currentSettings.llm_providers = (currentSettings.llm_providers || []).filter(function (p) {
                    return String(p.id) !== String(pid);
                });
                await persist();
                return;
            }

            if (action === 'test-llm') {
                const card = btn.closest('.dev-provider-card');
                const pre = card ? card.querySelector('.dev-llm-pre') : null;
                if (pre) {
                    pre.classList.remove('hidden');
                    pre.textContent = 'Requête en cours…';
                }
                showLoading(true);
                try {
                    const res = await fetch(API_BASE + '/dev_api.php?action=test_llm&provider_id=' + encodeURIComponent(pid));
                    const data = await res.json().catch(function () { return { success: false, error: 'JSON invalide' }; });
                    const block = {
                        ok: data.ok,
                        latency_ms: data.latency_ms,
                        response: data.response,
                        error: data.error,
                        debug: data.debug || null
                    };
                    if (pre) pre.textContent = JSON.stringify(block, null, 2);
                    showToast(data.ok ? 'Connexion LLM OK' : 'Échec du test LLM', data.ok ? 'success' : 'warning');
                } catch (err) {
                    if (pre) pre.textContent = String(err.message || err);
                    showToast('Erreur réseau (test LLM)', 'error');
                } finally {
                    showLoading(false);
                }
            }
        });

        const btnPresetGemini = document.getElementById('btnPresetGemini');
        if (btnPresetGemini) {
            btnPresetGemini.addEventListener('click', function () {
                document.getElementById('provider-name').value = GEMINI_PRESET.name;
                document.getElementById('provider-type').value = GEMINI_PRESET.type;
                document.getElementById('provider-base-url').value = GEMINI_PRESET.base_url;
                document.getElementById('provider-chat-path').value = GEMINI_PRESET.chat_path;
                document.getElementById('provider-model').value = GEMINI_PRESET.model;
                document.getElementById('provider-api-key').value = '';
                document.getElementById('provider-api-key').placeholder = 'Clé Google AI Studio';
                syncProviderFormFields();
            });
        }

        document.getElementById('addProviderForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const name = document.getElementById('provider-name').value.trim();
            const type = document.getElementById('provider-type').value;
            const baseUrl = document.getElementById('provider-base-url').value.trim();
            const model = document.getElementById('provider-model').value.trim();
            const apiKey = document.getElementById('provider-api-key').value;
            if (!name) {
                showToast('Nom requis', 'warning');
                return;
            }
            const id = 'p_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 7);
            const list = Array.isArray(currentSettings.llm_providers) ? currentSettings.llm_providers.slice() : [];
            list.forEach(function (p) { p.active = false; });
            const chatPath = document.getElementById('provider-chat-path').value.trim();
            const newProvider = {
                id: id,
                name: name,
                type: type,
                base_url: type === 'mistral' ? '' : (baseUrl || (type === 'openai_compatible' ? GEMINI_PRESET.base_url : 'http://localhost:11434')),
                model: model || (type === 'mistral' ? 'mistral-small-latest' : (type === 'openai_compatible' ? GEMINI_PRESET.model : 'gemma4:26b')),
                api_key: apiKey,
                active: true
            };
            if (type === 'openai_compatible') {
                newProvider.chat_path = chatPath || GEMINI_PRESET.chat_path;
            }
            list.push(newProvider);
            currentSettings.llm_providers = list;
            await persist();
            e.target.reset();
            syncProviderFormFields();
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
