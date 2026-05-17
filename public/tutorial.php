<?php
/**
 * MecaBuddy - Page de génération de tutoriels
 * 
 * Permet de :
 * - Saisir une action à effectuer
 * - Voir des suggestions
 * - Générer et afficher un tutoriel étape par étape
 */

$pageTitle = 'Tutoriels - MecaBuddy';
$currentPage = 'tutorial';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/db.php';

// Récupère le véhicule courant si disponible
$currentVehicle = null;
if (isset($_SESSION['vehicle_id'])) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM vehicles WHERE id = ?");
        $stmt->execute([$_SESSION['vehicle_id']]);
        $currentVehicle = $stmt->fetch();
    } catch (Exception $e) {
        // Ignore
    }
}
?>

<div class="page-header">
    <h1 class="page-title">
        <span class="title-icon">📖</span>
        Génération de tutoriel
    </h1>
    <p class="page-subtitle">
        Dis-moi ce que tu veux faire et je te guide étape par étape
    </p>
</div>

<div id="tutorial-vehicle-banner" class="vehicle-banner<?= $currentVehicle ? '' : ' warning' ?>">
    <span class="banner-icon" id="tutorial-banner-icon"><?= $currentVehicle ? '🚗' : '⚠️' ?></span>
    <span class="banner-text" id="tutorial-banner-text">
        <?php if ($currentVehicle): ?>
            Tutoriel pour : <strong><?= htmlspecialchars($currentVehicle['brand'] . ' ' . $currentVehicle['model'] . ' (' . $currentVehicle['year'] . ')') ?></strong>
        <?php else: ?>
            Aucun véhicule sélectionné. <a href="<?= PUBLIC_URL ?>/vehicle.php">Ajoute ton véhicule</a> pour des tutoriels personnalisés !
        <?php endif; ?>
    </span>
</div>
<button type="button" id="btn-change-vehicle" class="btn-ghost btn-sm tutorial-change-vehicle"
        onclick="VehicleSelector.open(onVehicleSelectorConfirmed)">
    🔄 Changer de véhicule
</button>

<!-- Section de génération -->
<section class="tutorial-generator">
    <form id="tutorialForm" class="generator-form">
        <div class="input-group">
            <label for="actionInput" class="input-label">Que veux-tu faire ?</label>
            <div class="input-wrapper">
                <input type="text" 
                       id="actionInput" 
                       name="action_type"
                       placeholder="Ex: vidange, changer les plaquettes, remplacer la batterie..."
                       autocomplete="off"
                       required>
                <button type="submit" id="btn-generate" class="btn btn-primary btn-generate">
                    <span class="btn-icon">🔧</span>
                    <span class="btn-text">Générer</span>
                </button>
            </div>
        </div>
    </form>
    
    <!-- Suggestions -->
    <div class="suggestions-section">
        <h3 class="suggestions-title">Suggestions populaires</h3>
        <div id="suggestionsGrid" class="suggestions-grid">
            <!-- Chargé dynamiquement -->
        </div>
    </div>
</section>

<!-- Zone d'affichage du tutoriel -->
<section id="tutorialDisplay" class="tutorial-display hidden">
    <!-- Contenu généré dynamiquement -->
</section>

<!-- Historique des tutoriels -->
<section class="tutorial-history">
    <h2 class="section-title">
        <span class="title-icon">📚</span>
        Mes tutoriels récents
    </h2>
    <div id="tutorialHistory" class="history-list">
        <p class="empty-state">Aucun tutoriel généré pour le moment.</p>
    </div>
</section>

<script>
const API_BASE = '<?= API_URL ?>';
const PAGE_CURRENT_VEHICLE = <?= json_encode(
    $currentVehicle ? ['brand' => (string) $currentVehicle['brand'], 'model' => (string) $currentVehicle['model']] : null,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;
const HAS_CURRENT_VEHICLE = <?= json_encode(
    $currentVehicle !== null || (isset($_SESSION['vehicle_id']) && (int) $_SESSION['vehicle_id'] > 0)
) ?>;

// ============================================
// Initialisation
// ============================================
function onVehicleSelectorConfirmed(vehicleId, vehicle) {
    reloadTutorialVehicleBanner(vehicle);
    document.getElementById('btn-generate')?.removeAttribute('disabled');
}

function reloadTutorialVehicleBanner(vehicle) {
    if (!vehicle) {
        return;
    }
    window.PAGE_CURRENT_VEHICLE = {
        brand: vehicle.brand,
        model: vehicle.model,
    };

    const banner = document.getElementById('tutorial-vehicle-banner');
    const iconEl = document.getElementById('tutorial-banner-icon');
    const textEl = document.getElementById('tutorial-banner-text');
    if (!banner || !textEl) {
        return;
    }

    banner.classList.remove('warning');
    if (iconEl) {
        iconEl.textContent = vehicle.category === 'moto' ? '🏍️' : '🚗';
    }
    textEl.innerHTML = `Tutoriel pour : <strong>${escapeHtmlBanner(vehicle.brand)} ${escapeHtmlBanner(vehicle.model)} (${escapeHtmlBanner(String(vehicle.year))})</strong>`;
}

function escapeHtmlBanner(text) {
    const el = document.createElement('div');
    el.textContent = text ?? '';
    return el.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
    loadSuggestions();
    loadHistory();
    setupFormListener();

    const btnGenerate = document.getElementById('btn-generate');
    if (HAS_CURRENT_VEHICLE) {
        btnGenerate?.removeAttribute('disabled');
    } else {
        btnGenerate?.setAttribute('disabled', 'disabled');
        VehicleSelector.open(onVehicleSelectorConfirmed);
    }
});

// ============================================
// Chargement des suggestions
// ============================================
async function loadSuggestions() {
    try {
        const response = await fetch(`${API_BASE}/tutorial_api.php?action=suggestions`);
        const data = await response.json();
        
        if (data.success && data.suggestions) {
            displaySuggestions(data.suggestions);
        }
    } catch (error) {
        console.error('Erreur chargement suggestions:', error);
    }
}

// ============================================
// Affichage des suggestions
// ============================================
function displaySuggestions(suggestions) {
    const grid = document.getElementById('suggestionsGrid');
    
    grid.innerHTML = suggestions.map(s => `
        <button type="button" class="suggestion-btn" onclick="selectSuggestion('${s.id}')">
            <span class="suggestion-icon">${s.icon}</span>
            <span class="suggestion-label">${s.label}</span>
        </button>
    `).join('');
}

// ============================================
// Sélection d'une suggestion
// ============================================
function selectSuggestion(actionId) {
    document.getElementById('actionInput').value = actionId;
    generateTutorial(actionId);
}

// ============================================
// Setup du formulaire
// ============================================
function setupFormListener() {
    document.getElementById('tutorialForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const action = document.getElementById('actionInput').value.trim();
        if (action) {
            generateTutorial(action);
        }
    });
}

// ============================================
// Génération du tutoriel (SSE temps réel)
// ============================================
let tutorialStreamActive = false;
let tutorialStreamDone = false;
let tutorialLoadingPhase = 'idle';
let tutorialFunRotationActive = false;
let tutorialFunJokeIndex = 0;
let tutorialFunJokesShuffled = null;
let tutorialFunModelLabel = 'Le LLM';

const TUTORIAL_FUN_MESSAGES = [
    ['⚙️ Génération du tutoriel...', '{model} s\'y colle'],
    ['🔧 Il cherche la clé de 13...', "C'est toujours la dernière qu'on trouve."],
    ['🛢️ Il vérifie le niveau d\'huile...', 'Analogique. Respect.'],
    ['📐 Il mesure deux fois...', "Pour couper une fois. C'est dans le manuel."],
    ['🪛 Il déroule son tapis de sol...', 'Faut pas abîmer la moquette du garage.'],
    ['🧲 Il cherche la vis tombée...', 'Quelque part sous le moteur. Évidemment.'],
    ['☕ Il se fait un café...', 'Non. Il travaille. Mais il y pense.'],
    ['📦 Il commande la pièce sur internet...', 'Livraison estimée : avant la fin du tuto.'],
    ['🧴 Il met ses gants...', 'EPI obligatoires. Même pour les IA.'],
    ['💡 Il relit le manuel constructeur...', 'Page 247, note de bas de page, astérisque 3.'],
    ['🔩 Il serre au couple...', 'Couple de serrage : au feeling. Comme un pro.'],
    ['🎵 Il siffle en travaillant...', "C'est bon signe. Ça veut dire que ça avance."],
    ['🕵️ Il inspecte la pièce...', 'Avec la lampe frontale de travers sur la tête.'],
    ['📸 Il prend une photo avant démontage..', "Spoiler : il la retrouvera plus au remontage."],
    ['🗑️ Il trie les chiffons...', 'Celui du bas est propre. Normalement.'],
    ['⏱️ Il chronomètre...', "Le devis disait 2h. On est à 4h. C'est normal."],
    ['🧰 Il vide sa caisse à outils...', 'Pour trouver le truc qui était devant.'],
    ['🪜 Il cherche un escabeau...', "Pour voir en haut du moteur. Ou juste pour s'asseoir."],
    ['🤔 Il consulte un collègue...', 'Qui consulte un autre collègue. Forums quoi.'],
    ['📏 Il vérifie le jeu aux soupapes...', "Au micromètre. Parce que à l'œil c'est risqué."],
    ['🧪 Il sent l\'huile...', 'Diagnostic olfactif. Technique ancestrale.'],
    ['🔦 Il cherche la fuite...', 'Elle est là. Non. Là. En fait là.'],
    ['🛞 Il vérifie la pression...', "Sauf que le manomètre est dans l'autre garage."],
    ['💬 Il marmonne des trucs...', 'En langue technique. Intraduisible.'],
    ['📋 Il fait l\'inventaire des pièces...', 'Il en manque une. Évidemment.'],
    ['🪤 Il pose un joint torique...', 'Premier essai : il roule sous le frigo.'],
    ['🧑‍🔧 Il appelle le fournisseur...', 'En attente. Temps estimé : 17 minutes.'],
    ['🌡️ Il attend que ça refroidisse...', 'Pendant ce temps, il boit son café froid.'],
    ['📖 Il cherche le couple de serrage...', 'Manuel papier. Page arrachée. Classique.'],
    ['🔋 Il teste la batterie...', "12.4V. C'est bon. Enfin... ça dépend."],
    ['🚿 Il se lave les mains...', 'Sixième passage. Il reste quand même du cambouis.'],
    ['🎯 Il vise le bouchon...', "Il rate le bac. L'huile est partout. Bientôt."],
    ['🧩 Il remonte de mémoire...', "Il reste deux pièces. Il décide que c'est en trop."],
    ['🗺️ Il consulte un schéma électrique...', "C'est un schéma. Ou une œuvre d'art moderne."],
    ['🪝 Il suspend le moteur...', 'Avec une sangle qui date de 1987. Solide.'],
    ['🎲 Il tente sa chance...', 'La vis rentre. Elle ressort. Il recommence.'],
    ['🧸 Il retrouve un boulon perdu...', 'Celui de la dernière révision. Mystère résolu.'],
    ['📡 Il cherche le signal OBD...', 'Valise branchée. Code P0420. Encore.'],
    ['🌀 Il décoince une vis grippée...', 'Dégrippant, chalumeau, prière. Dans cet ordre.'],
    ['🏁 Dernière ligne droite...', 'Il rebranche la batterie. Espoir.'],
];

function tutorialLoadingDebug(msg, data) {
    if (!window.DebugPanel) {
        return;
    }
    DebugPanel.info(msg, data !== undefined ? data : null);
}

function clearAllTutorialLoadingTimers(reason) {
    if (window._llmJokeTimer) {
        clearInterval(window._llmJokeTimer);
        window._llmJokeTimer = null;
        tutorialLoadingDebug('Rotation messages fun arrêtée', { reason, interval_id: null });
    }
    tutorialFunRotationActive = false;
    if (window._llmJokeDelayTimer) {
        clearTimeout(window._llmJokeDelayTimer);
        window._llmJokeDelayTimer = null;
    }
    if (window._llmJokeFallbackTimer) {
        clearTimeout(window._llmJokeFallbackTimer);
        window._llmJokeFallbackTimer = null;
    }
}

function clearLlmJokeTimer() {
    clearAllTutorialLoadingTimers('legacy_clear');
}

function pickFunIntervalMs() {
    return 4000 + Math.floor(Math.random() * 2001);
}

function displayFunMessage() {
    const msgEl = document.getElementById('loading-message');
    const subEl = document.getElementById('loading-submessage');
    if (!msgEl || !subEl || !tutorialFunJokesShuffled) {
        return;
    }
    const [msg, subTpl] = tutorialFunJokesShuffled[tutorialFunJokeIndex % tutorialFunJokesShuffled.length];
    const sub = String(subTpl).replace(/\{model\}/g, tutorialFunModelLabel);
    msgEl.textContent = msg;
    subEl.textContent = sub;
    tutorialLoadingDebug('Message fun affiché', { index: tutorialFunJokeIndex, message: msg });
    tutorialFunJokeIndex += 1;
}

function startFunMessageRotation(modelLabel, reason) {
    if (tutorialFunRotationActive) {
        tutorialLoadingDebug('Rotation messages fun déjà active', { reason });
        return;
    }
    const msgEl = document.getElementById('loading-message');
    const subEl = document.getElementById('loading-submessage');
    if (!msgEl || !subEl) {
        return;
    }

    tutorialFunModelLabel = modelLabel || 'Le LLM';
    tutorialFunJokesShuffled = TUTORIAL_FUN_MESSAGES.slice().sort(() => Math.random() - 0.5);
    tutorialFunJokeIndex = 0;
    tutorialFunRotationActive = true;

    displayFunMessage();
    const intervalMs = pickFunIntervalMs();
    window._llmJokeTimer = setInterval(displayFunMessage, intervalMs);
    tutorialLoadingDebug('Rotation messages fun démarrée', { reason, interval_ms: intervalMs, interval_id: 'active' });
}

function scheduleFunRotationAfterSearchDone(data) {
    if (window._llmJokeDelayTimer) {
        clearTimeout(window._llmJokeDelayTimer);
    }
    window._llmJokeDelayTimer = setTimeout(() => {
        window._llmJokeDelayTimer = null;
        if (tutorialStreamDone || !tutorialStreamActive) {
            return;
        }
        if (tutorialLoadingPhase === 'saving' || tutorialLoadingPhase === 'done') {
            return;
        }
        if (!tutorialFunRotationActive) {
            startFunMessageRotation(data?.model, 'after_search_done');
        }
    }, 2000);
}

function scheduleFunRotationFallback(delayMs) {
    if (window._llmJokeFallbackTimer) {
        clearTimeout(window._llmJokeFallbackTimer);
    }
    window._llmJokeFallbackTimer = setTimeout(() => {
        window._llmJokeFallbackTimer = null;
        if (tutorialStreamDone || !tutorialStreamActive || tutorialFunRotationActive) {
            return;
        }
        const waitPhases = ['vehicle', 'search', 'search_done', 'llm'];
        if (waitPhases.includes(tutorialLoadingPhase)) {
            tutorialLoadingDebug('Rotation fun (fallback temporisé)', { phase: tutorialLoadingPhase, delay_ms: delayMs });
            startFunMessageRotation(tutorialFunModelLabel, 'timeout_fallback');
        }
    }, delayMs);
}

function updateLoadingUI(phase, data) {
    const msgEl = document.getElementById('loading-message');
    const subEl = document.getElementById('loading-submessage');
    if (!msgEl || !subEl) {
        return;
    }

    const prevPhase = tutorialLoadingPhase;
    tutorialLoadingPhase = phase;
    tutorialLoadingDebug('Transition overlay UI', { from: prevPhase, to: phase });

    if (phase === 'llm') {
        if (window._llmJokeDelayTimer) {
            clearTimeout(window._llmJokeDelayTimer);
            window._llmJokeDelayTimer = null;
        }
        if (data?.model) {
            tutorialFunModelLabel = data.model;
        }
        startFunMessageRotation(data?.model, 'phase_llm');
        return;
    }

    if (phase === 'saving') {
        clearAllTutorialLoadingTimers('phase_saving');
        msgEl.textContent = '💾 Finalisation du tutoriel...';
        subEl.textContent = '';
        return;
    }

    if (phase === 'search_done') {
        if (window._llmJokeTimer) {
            clearInterval(window._llmJokeTimer);
            window._llmJokeTimer = null;
            tutorialFunRotationActive = false;
        }
        if (data?.failsafe) {
            msgEl.textContent = '📚 Aucune source fiable, mode sécurisé...';
            subEl.textContent = 'Vérifiez les valeurs critiques dans votre manuel constructeur.';
        } else {
            msgEl.textContent = '📚 Sources analysées...';
            const titles = (data?.sources || []).map((s) => s.title).filter(Boolean);
            subEl.textContent = titles.length
                ? titles.slice(0, 3).join(' · ')
                : `${data?.sources_count || 0} source(s) retenue(s)`;
        }
        scheduleFunRotationAfterSearchDone(data);
        return;
    }

    if (tutorialFunRotationActive && window._llmJokeTimer) {
        clearInterval(window._llmJokeTimer);
        window._llmJokeTimer = null;
        tutorialFunRotationActive = false;
        tutorialLoadingDebug('Rotation messages fun arrêtée', { reason: 'phase_' + phase });
    }

    const isMoto = data?.category === 'moto';
    const messages = {
        vehicle: [
            isMoto ? '🏍️ Identification du véhicule...' : '🚗 Identification du véhicule...',
            data?.vehicle ? String(data.vehicle) : '',
        ],
        search: [
            '🔎 Recherche de documentation fiable...',
            data?.vehicle ? `Documentation pour votre ${data.vehicle}` : '',
        ],
        load: ['📖 Chargement du tutoriel...', ''],
    };

    const [msg, sub] = messages[phase] || ['Chargement...', ''];
    msgEl.textContent = msg;
    subEl.textContent = sub || '';
}

function generateTutorial(actionType) {
    generateTutorialWithSSE(actionType);
}

function generateTutorialWithSSE(actionType) {
    if (tutorialStreamActive) {
        return;
    }

    clearAllTutorialLoadingTimers('new_generation');
    tutorialStreamActive = true;
    tutorialStreamDone = false;
    tutorialLoadingPhase = 'vehicle';
    showLoading(true);
    tutorialLoadingDebug('Overlay génération affiché', { action: actionType });
    updateLoadingUI('vehicle', { category: null });
    scheduleFunRotationFallback(12000);

    const params = new URLSearchParams({ action_type: actionType });
    const es = new EventSource(`${API_BASE}/tutorial_stream.php?${params}`);

    if (window.DebugPanel) {
        DebugPanel.start();
        DebugPanel.info('Génération tutoriel', { action: actionType });
    }

    const finish = (reason) => {
        clearAllTutorialLoadingTimers(reason || 'finish');
        tutorialStreamDone = true;
        tutorialStreamActive = false;
        tutorialLoadingPhase = 'done';
        es.close();
        showLoading(false);
        tutorialLoadingDebug('Overlay génération masqué', { reason: reason || 'finish' });
    };

    es.addEventListener('status', (e) => {
        try {
            const data = JSON.parse(e.data);
            tutorialLoadingDebug('SSE status reçu', { phase: data.phase, message: data.message });
            if (window.DebugPanel) {
                DebugPanel.injectSSE('status', data);
            }
            if (data.phase) {
                updateLoadingUI(data.phase, data);
            }
        } catch (err) {
            console.warn('SSE status parse:', err);
        }
    });

    es.addEventListener('done', (e) => {
        try {
            const data = JSON.parse(e.data);
            if (window.DebugPanel) {
                DebugPanel.injectSSE('done', data);
            }
            finish('done');
            if (data.tutorial) {
                displayTutorial(
                    data.tutorial,
                    data.generated_by ?? 'llm',
                    data.sources ?? null,
                    data.failsafe === true
                );
                loadHistory();
            } else {
                showToast('Réponse tutoriel incomplète', 'error');
            }
        } catch (err) {
            finish('done_parse_error');
            console.error('SSE done parse:', err);
            showToast('❌ Erreur lors de la génération', 'error');
        }
    });

    es.addEventListener('error', (e) => {
        if (tutorialStreamDone) {
            return;
        }
        let message = 'Erreur lors de la génération';
        if (!e.data) {
            if (window.DebugPanel) {
                DebugPanel.error('Erreur SSE serveur');
            }
            finish('sse_error_empty');
            showToast('❌ ' + message, 'error');
            return;
        }
        try {
            const data = JSON.parse(e.data);
            if (data.raw_preview !== undefined) {
                DebugPanel?.llm('Raw LLM output', data.raw_preview || '(vide)');
                DebugPanel?.error(data.hint || data.message);
            }
            if (window.DebugPanel) {
                DebugPanel.injectSSE('error', data);
            }
            if (data.error === 'quota_exceeded') {
                message = data.message || 'Quota journalier tutoriel atteint.';
                tutorialStreamDone = true;
                showToast('⚠️ ' + message, 'warning', 6000);
                finish('quota_exceeded');
                return;
            }
            if (data.error === 'auth_required') {
                message = 'Connexion démo requise.';
                tutorialStreamDone = true;
                finish('auth_required');
                showToast(message, 'warning', 5000);
                return;
            }
            if (data.error === 'byok_provider_invalid') {
                message = data.message || 'Clé personnelle invalide ou non validée.';
                tutorialStreamDone = true;
                finish('byok_provider_invalid');
                showToast('⚠️ ' + message, 'warning', 8000);
                return;
            }
            if (data.error === 'llm_provider_error' || data.error === 'empty_assistant' || data.error === 'no_llm_provider') {
                message = data.message || 'Le fournisseur IA a rencontré une erreur.';
                tutorialStreamDone = true;
                finish('llm_provider_error');
                showToast('⚠️ ' + message, 'warning', 8000);
                return;
            }
            if (data.message) {
                message = data.message;
            }
        } catch (err) {
            console.warn('SSE error parse:', err);
        }
        finish('error_event');
        showToast('❌ ' + message, 'error');
    });

    es.onerror = () => {
        if (tutorialStreamDone) {
            return;
        }
        if (window.DebugPanel) {
            DebugPanel.warn('Connexion SSE momentanément perdue');
        }
        const subEl = document.getElementById('loading-submessage');
        if (subEl) {
            subEl.textContent = 'Connexion momentanément perdue, reconnexion...';
        }
    };
}

/** Fallback synchrone si SSE indisponible */
async function generateTutorialFallback(actionType) {
    clearAllTutorialLoadingTimers('fallback_start');
    showLoading(true);
    try {
        const response = await fetch(`${API_BASE}/tutorial_api.php?action=generate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action_type: actionType }),
        });
        const data = await response.json();
        if (data.success && data.tutorial) {
            displayTutorial(data.tutorial, data.generated_by ?? null, data.sources ?? null);
            loadHistory();
        } else if (response.status === 429 || data.error === 'quota_exceeded') {
            showToast(data.message || 'Quota journalier tutoriel atteint.', 'warning', 6000);
        } else {
            showToast(data.error || data.message || 'Erreur lors de la génération', 'error');
        }
    } catch (error) {
        console.error('Erreur génération:', error);
        showToast('❌ Erreur lors de la génération', 'error');
    } finally {
        clearAllTutorialLoadingTimers('fallback_end');
        showLoading(false);
    }
}

// ============================================
// Affichage du tutoriel généré
// ============================================
function renderTutorialSources(sources) {
    if (!sources || !Array.isArray(sources) || sources.length === 0) {
        return '';
    }
    const links = sources.map((s) => {
        if (!s || typeof s !== 'object') {
            return '';
        }
        const url = String(s.url ?? '').trim();
        if (!url) {
            return '';
        }
        const title = escapeHtml(String(s.title || url));
        const safeHref = escapeHtml(url);
        return `<a href="${safeHref}" target="_blank" rel="noopener noreferrer" title="${safeHref}">${title}</a>`;
    }).filter(Boolean);
    if (links.length === 0) {
        return '';
    }
    return `<div class="tutorial-sources">
        <span class="sources-label">🔗 Sources consultées :</span> ${links.join(' · ')}
    </div>`;
}

function displayTutorial(tutorial, generatedBy, sources, failsafe = false) {
    const display = document.getElementById('tutorialDisplay');
    
    const llmBadge = generatedBy === 'llm'
        ? '<span class="llm-badge">⚡ Généré par IA</span>'
        : '';

    const sourcesBlock = renderTutorialSources(sources);

    const vehicleCtxBadge = (typeof PAGE_CURRENT_VEHICLE !== 'undefined' && PAGE_CURRENT_VEHICLE)
        ? '<span class="vehicle-context-badge">🚗 Adapté à votre '
            + escapeHtml(PAGE_CURRENT_VEHICLE.brand) + ' ' + escapeHtml(PAGE_CURRENT_VEHICLE.model)
            + '</span>'
        : '';
    // Génère les badges de danger
    const dangerBadge = tutorial.danger_level !== 'none' ? `
        <span class="danger-badge danger-${tutorial.danger_level}">
            ${getDangerIcon(tutorial.danger_level)} ${getDangerText(tutorial.danger_level)}
        </span>
    ` : '';
    
    // Génère les avertissements globaux
    const globalWarnings = tutorial.global_warnings?.length ? `
        <div class="global-warnings">
            ${tutorial.global_warnings.map(w => `<div class="warning-item">${w}</div>`).join('')}
        </div>
    ` : '';
    
    // Génère les outils nécessaires
    const tools = tutorial.tools_required?.length ? `
        <div class="tutorial-tools">
            <h4><span class="icon">🔧</span> Outils nécessaires</h4>
            <ul>${tutorial.tools_required.map(t => `<li>${t}</li>`).join('')}</ul>
        </div>
    ` : '';
    
    // Génère les pièces nécessaires
    const parts = tutorial.parts_required?.length ? `
        <div class="tutorial-parts">
            <h4><span class="icon">📦</span> Pièces nécessaires</h4>
            <ul>${tutorial.parts_required.map(p => `<li>${p}</li>`).join('')}</ul>
        </div>
    ` : '';
    
    // Génère les étapes
    const steps = tutorial.steps.map((step, index) => `
        <div class="tutorial-step ${step.danger ? 'step-danger' : ''}" data-step="${index + 1}">
            <div class="step-header">
                <span class="step-number">${index + 1}</span>
                <h4 class="step-title">${step.title}</h4>
                ${step.danger ? `<span class="step-danger-badge">${getDangerIcon(step.danger_level)} Attention</span>` : ''}
            </div>
            <div class="step-content">
                <p class="step-description">${step.description}</p>
                ${step.warnings?.length ? `
                    <div class="step-warnings">
                        ${step.warnings.map(w => `<div class="step-warning">${w}</div>`).join('')}
                    </div>
                ` : ''}
            </div>
            ${index < tutorial.steps.length - 1 ? `
                <button type="button" class="btn-next-step" onclick="scrollToStep(${index + 2})">
                    Étape suivante →
                </button>
            ` : `
                <div class="step-final">
                    <span class="final-icon">🎉</span>
                    <span class="final-text">Tutoriel terminé !</span>
                </div>
            `}
        </div>
    `).join('');
    
    display.innerHTML = `
        <div class="tutorial-card">
            <div class="tutorial-header">
                <div class="tutorial-meta">
                    ${dangerBadge}
                    <span class="difficulty-badge difficulty-${tutorial.difficulty}">${tutorial.difficulty}</span>
                    ${tutorial.estimated_time ? `<span class="time-badge">⏱️ ~${tutorial.estimated_time} min</span>` : ''}
                </div>
                <h2 class="tutorial-title">${escapeHtml(tutorial.title)}${llmBadge}</h2>
                ${sourcesBlock}
                ${vehicleCtxBadge}
                <p class="tutorial-description">${tutorial.description}</p>
            </div>
            
            ${globalWarnings}
            
            <div class="tutorial-requirements">
                ${tools}
                ${parts}
            </div>
            
            <div class="tutorial-steps">
                <h3 class="steps-title">
                    <span class="icon">📝</span>
                    Étapes (${tutorial.steps.length})
                </h3>
                ${steps}
            </div>
        </div>
    `;
    
    display.classList.remove('hidden');

    if (failsafe) {
        const badge = document.createElement('d' + 'iv');
        badge.className = 'failsafe-badge';
        badge.innerHTML = '⚠️ <strong>Aucune source fiable trouvée</strong>'
            + ' — Ce tutoriel est généré depuis la mémoire du LLM (mode LLM failsafe).'
            + ' Vérifiez les valeurs critiques (couples de serrage, volumes) dans votre manuel.';
        const titleEl = display.querySelector('.tutorial-title');
        if (titleEl) {
            titleEl.insertAdjacentElement('afterend', badge);
        }
    }

    // Scroll vers le tutoriel
    display.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ============================================
// Scroll vers une étape
// ============================================
function scrollToStep(stepNumber) {
    const step = document.querySelector(`[data-step="${stepNumber}"]`);
    if (step) {
        step.scrollIntoView({ behavior: 'smooth', block: 'center' });
        step.classList.add('highlight');
        setTimeout(() => step.classList.remove('highlight'), 1500);
    }
}

// ============================================
// Chargement de l'historique
// ============================================
async function loadHistory() {
    try {
        const response = await fetch(`${API_BASE}/tutorial_api.php?action=list&limit=5`);
        const data = await response.json();
        
        if (data.success && data.tutorials?.length) {
            displayHistory(data.tutorials);
        }
    } catch (error) {
        console.error('Erreur chargement historique:', error);
    }
}

// ============================================
// Affichage de l'historique
// ============================================
function displayHistory(tutorials) {
    const historyDiv = document.getElementById('tutorialHistory');
    
    historyDiv.innerHTML = tutorials.map(t => `
        <div class="history-item" onclick="loadExistingTutorial(${t.id})">
            <div class="history-icon">${getActionIcon(t.action_type)}</div>
            <div class="history-info">
                <span class="history-title">${t.title}</span>
                <span class="history-meta">
                    ${t.brand ? `${t.brand} ${t.model} • ` : ''}
                    ${formatDate(t.created_at)}
                </span>
            </div>
            <span class="history-arrow">→</span>
        </div>
    `).join('');
}

// ============================================
// Chargement d'un tutoriel existant
// ============================================
async function loadExistingTutorial(tutorialId) {
    showLoading(true);
    updateLoadingUI('load', null);

    try {
        const response = await fetch(`${API_BASE}/tutorial_api.php?action=get&id=${tutorialId}`);
        const data = await response.json();
        
        if (data.success && data.tutorial) {
            displayTutorial(data.tutorial, data.generated_by ?? null);
        } else {
            showToast(data.error || 'Erreur lors du chargement', 'error');
        }
    } catch (error) {
        console.error('Erreur chargement tutoriel:', error);
        showToast('Erreur lors du chargement du tutoriel', 'error');
    } finally {
        showLoading(false);
    }
}

// ============================================
// Fonctions utilitaires
// ============================================
function getDangerIcon(level) {
    switch (level) {
        case 'high': return '🔴';
        case 'medium': return '🟠';
        case 'low': return '🟡';
        default: return '🟢';
    }
}

function getDangerText(level) {
    switch (level) {
        case 'high': return 'Opération sensible';
        case 'medium': return 'Attention requise';
        case 'low': return 'Précautions de base';
        default: return '';
    }
}

function getActionIcon(action) {
    const icons = {
        'vidange': '🛢️',
        'plaquettes': '🛑',
        'purge': '💧',
        'batterie': '🔋',
        'filtre': '💨',
        'bougies': '⚡',
        'refroidissement': '❄️',
        'essuie': '🌧️'
    };
    
    for (const [key, icon] of Object.entries(icons)) {
        if (action?.toLowerCase().includes(key)) {
            return icon;
        }
    }
    return '🔧';
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-FR', { 
        day: 'numeric', 
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.toggle('visible', show);
    }
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.add('visible'), 100);
    setTimeout(() => {
        toast.classList.remove('visible');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<?php require_once __DIR__ . '/../includes/vehicle_selector_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/debug_panel.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

