<?php
/**
 * MecaBuddy — Mon compte (quotas + BYOK Mistral / Gemini)
 */

$pageTitle = 'Mon compte — MecaBuddy';
$currentPage = 'account';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">
        <span class="title-icon">👤</span>
        Mon compte
    </h1>
    <p class="page-subtitle">Quotas de démonstration et clé API personnelle (BYOK)</p>
</div>

<?php if (!isDemoAuthEnabled()): ?>
    <div class="card login-card" style="max-width:520px;margin:0 auto 2rem">
        <p>L'authentification démo n'est pas activée sur cette instance.</p>
        <a href="<?= htmlspecialchars(PUBLIC_URL . '/index.php') ?>" class="btn btn-primary">Retour à l'accueil</a>
    </div>
<?php else: ?>
    <div id="accountRoot" class="account-settings-wrap">
        <p class="account-loading">Chargement…</p>
    </div>
<?php endif; ?>

<style>
.account-settings-wrap { max-width: 680px; margin: 0 auto 2.5rem; }
.account-card {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
}
.account-card h2 { margin: 0 0 0.75rem; font-size: 1.1rem; }
.account-quotas { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 520px) { .account-quotas { grid-template-columns: 1fr; } }
.quota-box { padding: 0.75rem 1rem; border-radius: 8px; background: rgba(255,255,255,0.05); }
.quota-box strong { font-size: 1.35rem; display: block; margin-top: 0.25rem; }
.account-form .form-group { margin-bottom: 1rem; }
.account-form label { display: block; margin-bottom: 0.35rem; font-size: 0.9rem; }
.account-form input, .account-form select {
    width: 100%; padding: 0.65rem 0.75rem; border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.25); color: inherit;
}
.account-hint { font-size: 0.8rem; opacity: 0.75; margin: 0.35rem 0 0; }
.account-badge {
    display: inline-block; font-size: 0.75rem; padding: 0.15rem 0.5rem;
    border-radius: 4px; background: rgba(34,197,94,0.2); color: #86efac; margin-left: 0.35rem;
}
.account-msg { font-size: 0.9rem; margin: 0.5rem 0; }
.account-msg.is-error { color: #f87171; }
.account-msg.is-ok { color: #86efac; }
.account-mode-pill {
    display: inline-block; padding: 0.25rem 0.65rem; border-radius: 999px;
    font-size: 0.8rem; background: rgba(37,99,235,0.25); margin-top: 0.5rem;
}
.account-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
.account-loading { text-align: center; opacity: 0.8; }
</style>

<?php if (isDemoAuthEnabled()): ?>
<script>
(function () {
    const root = document.getElementById('accountRoot');
    if (!root) return;
    const API = (window.API_URL || '') + '/account_api.php';
    const GEMINI_BASE = 'https://generativelanguage.googleapis.com/v1beta/openai';

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
    }

    function render(data) {
        const q = data.quota || {};
        const tuto = q.tutorial || {};
        const buddy = q.buddy || {};
        const eff = data.effective_provider || {};
        const global = data.global_provider || {};
        const byok = data.byok || null;
        const byokOn = data.byok_enabled === true;
        const quotaApplied = q.quota_applied !== false;

        let modeLabel = 'Provider global de l\'instance';
        if (eff.source === 'user_byok' && eff.quota_bypass_allowed) {
            modeLabel = 'Clé personnelle active (' + esc(eff.provider_type || '') + ') — quotas ignorés';
        } else if (eff.byok_invalid) {
            modeLabel = 'Clé personnelle invalide ou non testée — quotas démo actifs';
        } else if (quotaApplied) {
            modeLabel = 'Provider global — quotas démo actifs';
        }

        let html = '<div class="account-card">';
        html += '<h2>' + esc(data.username || '') + '</h2>';
        html += '<span class="account-mode-pill">' + modeLabel + '</span>';
        if (global && global.name) {
            html += '<p class="account-hint">Instance : <strong>' + esc(global.name) + '</strong> (' + esc(global.type) + ', ' + esc(global.model) + ')</p>';
        }
        html += '</div>';

        html += '<div class="account-card"><h2>Quotas journaliers</h2><div class="account-quotas">';
        html += '<div class="quota-box"><span>Tutoriels</span><strong>' + esc(tuto.remaining) + '</strong> / ' + esc(tuto.limit);
        html += ' <span class="account-hint">(utilisés : ' + esc(tuto.used) + ')</span></div>';
        html += '<div class="quota-box"><span>Buddy</span><strong>' + esc(buddy.remaining) + '</strong> / ' + esc(buddy.limit);
        html += ' <span class="account-hint">(utilisés : ' + esc(buddy.used) + ')</span></div></div>';
        html += '<p class="account-hint">Réinitialisation : ' + esc(q.reset_at || '') + '. ';
        html += quotaApplied
            ? 'Les quotas s\'appliquent tant que vous utilisez le provider global.'
            : 'Avec votre clé validée, les quotas de démo ne s\'appliquent pas.</p></div>';

        if (!byokOn) {
            html += '<div class="account-card"><p>L\'utilisation d\'une clé personnelle est désactivée sur cette instance.</p></div>';
            root.innerHTML = html;
            return;
        }

        if (!data.encryption_available) {
            html += '<div class="account-card account-msg is-error"><p>Le chiffrement BYOK n\'est pas configuré (fichier <code>config/byok.key</code> requis en production).</p></div>';
        }

        const pt = byok && byok.provider_type ? byok.provider_type : 'mistral';
        const model = byok && byok.model ? byok.model : (pt === 'gemini' ? 'gemini-2.5-flash-lite' : 'mistral-small-latest');
        const baseUrl = byok && byok.base_url ? byok.base_url : GEMINI_BASE;
        const masked = byok && byok.api_key_masked ? byok.api_key_masked : '';
        const hasKey = byok && byok.has_api_key;
        const validated = byok && byok.is_validated;
        const active = byok && byok.is_active;
        const testMsg = byok && byok.last_test_message ? byok.last_test_message : '';

        html += '<form id="byokForm" class="account-card account-form">';
        html += '<h2>Clé API personnelle (BYOK)</h2>';
        html += '<p class="account-hint">Choisissez Mistral ou Gemini. Testez puis activez votre clé. ';
        html += 'Les quotas démo ne s\'appliquent pas avec une clé personnelle validée et active.</p>';

        html += '<div class="form-group"><label for="byokType">Fournisseur</label>';
        html += '<select id="byokType" name="provider_type">';
        html += '<option value="mistral"' + (pt === 'mistral' ? ' selected' : '') + '>Mistral AI</option>';
        html += '<option value="gemini"' + (pt === 'gemini' ? ' selected' : '') + '>Google AI Studio / Gemini</option>';
        html += '</select></div>';

        html += '<div class="form-group"><label for="byokModel">Modèle</label>';
        html += '<input type="text" id="byokModel" value="' + esc(model) + '"></div>';

        html += '<div class="form-group" id="byokBaseUrlGroup"><label for="byokBaseUrl">URL de base (Gemini)</label>';
        html += '<input type="url" id="byokBaseUrl" value="' + esc(baseUrl) + '" readonly></div>';

        html += '<div class="form-group"><label for="byokKey">Clé API';
        if (hasKey) html += '<span class="account-badge">Configurée ' + esc(masked) + '</span>';
        html += '</label><input type="password" id="byokKey" autocomplete="off" placeholder="Nouvelle clé (laisser vide pour conserver)"></div>';

        if (testMsg) {
            html += '<p class="account-hint">Dernier test : ' + esc(byok.last_test_status || '') + ' — ' + esc(testMsg) + '</p>';
        }
        if (active && validated) {
            html += '<p class="account-msg is-ok">Clé active et validée.</p>';
        } else if (hasKey && !validated) {
            html += '<p class="account-msg is-error">Clé enregistrée mais non validée — testez avant activation.</p>';
        }

        html += '<p id="byokMsg" class="account-msg" hidden></p>';
        html += '<div class="account-actions">';
        html += '<button type="button" class="btn btn-secondary" id="btnByokTest">Tester ma clé</button>';
        html += '<button type="submit" class="btn btn-primary">Enregistrer</button>';
        html += '<button type="button" class="btn btn-secondary" id="btnByokActivate">Activer cette clé</button>';
        html += '<button type="button" class="btn btn-secondary" id="btnByokDisable">Désactiver</button>';
        html += '<button type="button" class="btn btn-secondary" id="btnByokDelete">Supprimer</button>';
        html += '</div></form>';

        root.innerHTML = html;

        const typeEl = document.getElementById('byokType');
        const baseGroup = document.getElementById('byokBaseUrlGroup');
        function syncTypeUi() {
            const isGemini = typeEl.value === 'gemini';
            baseGroup.style.display = isGemini ? 'block' : 'none';
            if (typeEl.value === 'mistral' && !document.getElementById('byokModel').dataset.touched) {
                document.getElementById('byokModel').placeholder = 'mistral-small-latest';
            }
        }
        typeEl.addEventListener('change', syncTypeUi);
        syncTypeUi();

        function payloadFromForm() {
            const p = {
                provider_type: typeEl.value,
                model: document.getElementById('byokModel').value.trim(),
            };
            const key = document.getElementById('byokKey').value.trim();
            if (key) p.api_key = key;
            if (p.provider_type === 'gemini') {
                p.base_url = document.getElementById('byokBaseUrl').value.trim() || GEMINI_BASE;
            }
            return p;
        }

        function showMsg(text, ok) {
            const el = document.getElementById('byokMsg');
            el.hidden = false;
            el.textContent = text;
            el.className = 'account-msg ' + (ok ? 'is-ok' : 'is-error');
        }

        async function post(action, body) {
            const res = await fetch(API + '?action=' + action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body || {}),
            });
            return res.json();
        }

        document.getElementById('btnByokTest').addEventListener('click', async () => {
            const p = payloadFromForm();
            if (!p.api_key && !hasKey) {
                showMsg('Saisissez une clé API à tester.', false);
                return;
            }
            if (p.api_key) {
                const saved = await post('save_byok_provider', p);
                if (!saved.success) { showMsg(saved.message || 'Erreur enregistrement', false); return; }
            }
            const out = await post('test_byok_provider', { provider_type: p.provider_type });
            showMsg(out.test?.message || out.message || 'Test terminé', out.test?.ok === true);
            if (out.success) render(out);
        });

        document.getElementById('byokForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const p = payloadFromForm();
            if (!p.api_key && !hasKey) {
                showMsg('Clé API requise.', false);
                return;
            }
            const out = await post('save_byok_provider', p);
            showMsg(out.message || (out.success ? 'Enregistré' : 'Erreur'), out.success === true);
            if (out.success) render(out);
        });

        document.getElementById('btnByokActivate').addEventListener('click', async () => {
            const out = await post('set_byok_active', { provider_type: typeEl.value });
            showMsg(out.message || '', out.success === true);
            if (out.success) render(out);
        });

        document.getElementById('btnByokDisable').addEventListener('click', async () => {
            const out = await post('disable_byok_provider', {});
            showMsg(out.message || '', out.success === true);
            if (out.success) render(out);
        });

        document.getElementById('btnByokDelete').addEventListener('click', async () => {
            if (!confirm('Supprimer définitivement votre clé personnelle ?')) return;
            const out = await post('delete_byok_provider', { provider_type: typeEl.value });
            showMsg(out.message || '', out.success === true);
            if (out.success) render(out);
        });
    }

    fetch(API + '?action=get_account_settings')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                root.innerHTML = '<div class="account-card"><p class="account-msg is-error">' + esc(data.message || 'Erreur') + '</p></div>';
                return;
            }
            render(data);
        })
        .catch(() => {
            root.innerHTML = '<div class="account-card"><p class="account-msg is-error">Erreur réseau.</p></div>';
        });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
