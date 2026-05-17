<?php
/**
 * MecaBuddy - Page Diagnostic (Buddy Mode)
 * 
 * Interface de chat simple avec le buddy mécano
 * Historique en mémoire JS + sauvegarde en session
 */

$pageTitle = 'Buddy Mode - MecaBuddy';
$currentPage = 'diagnostic';
?>
<style>
.buddy-sources {
  margin-top: 6px;
  font-size: 0.78rem;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}
.sources-label {
  color: var(--text-secondary, #666);
  font-weight: 500;
}
.buddy-sources a {
  color: var(--primary, #2563eb);
  text-decoration: underline;
  word-break: break-word;
}
.buddy-sources a:hover {
  opacity: 0.8;
}
.llm-badge {
  display: inline-block;
  font-size: 0.72rem;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--surface-alt, #f0f4ff);
  color: var(--primary, #2563eb);
  margin-left: 8px;
  vertical-align: middle;
}
.failsafe-badge {
  background: rgba(217, 119, 6, 0.1);
  border: 1px solid rgba(217, 119, 6, 0.4);
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 0.8rem;
  color: #d97706;
  margin-top: 10px;
  line-height: 1.5;
}
</style>
<?php
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

<div class="chat-container">
    <!-- Header du chat -->
    <div class="chat-header">
        <div class="chat-buddy-info">
            <div class="buddy-avatar-small">
                <span>🔧</span>
            </div>
            <div class="buddy-details">
                <h1 class="buddy-name">MecaBuddy<span id="buddyLlmBadge" class="llm-badge" hidden></span></h1>
                <span class="buddy-status">
                    <span class="status-dot"></span>
                    En ligne - Prêt à t'aider !
                </span>
            </div>
        </div>
        
        <div id="chat-vehicle-banner-wrap" class="chat-vehicle-banner-wrap<?= $currentVehicle ? '' : ' hidden' ?>">
            <div class="chat-vehicle-info" id="chat-vehicle-info">
                <span class="vehicle-icon-small" id="chat-vehicle-icon">🚗</span>
                <span class="vehicle-text" id="chat-vehicle-text">
                    <?php if ($currentVehicle): ?>
                        <?= htmlspecialchars($currentVehicle['brand'] . ' ' . $currentVehicle['model']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <button type="button" id="btn-change-vehicle" class="btn-ghost btn-sm"
                    onclick="VehicleSelector.open(onVehicleSelectorConfirmed)">
                🔄 Changer de véhicule
            </button>
        </div>

        <button class="btn-clear-chat" onclick="clearChat()" title="Effacer l'historique">
            <span>🗑️</span>
        </button>
    </div>
    
    <!-- Zone de messages -->
    <div class="chat-messages" id="chatMessages">
        <!-- Message de bienvenue -->
        <div class="message message-buddy welcome-message">
            <div class="message-avatar">🔧</div>
            <div class="message-content">
                <div class="message-bubble">
                    <p>Salut ! 👋 Je suis <strong>MecaBuddy</strong>, ton pote mécano !</p>
                    <p>Décris-moi ton problème ou pose-moi une question sur l'entretien de ta voiture. Je suis là pour t'aider ! 🚗</p>
                    <?php if (!$currentVehicle): ?>
                    <p class="tip">💡 <em>Tip : <a href="<?= PUBLIC_URL ?>/vehicle.php">Ajoute ton véhicule</a> pour des conseils personnalisés !</em></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Les messages seront ajoutés ici dynamiquement -->
    </div>
    
    <!-- Suggestions rapides -->
    <div class="quick-suggestions" id="quickSuggestions">
        <button type="button" class="quick-btn" onclick="sendQuickMessage('Ma voiture fait un bruit bizarre')">
            🔊 Bruit bizarre
        </button>
        <button type="button" class="quick-btn" onclick="sendQuickMessage('Mon voyant moteur est allumé')">
            ⚠️ Voyant moteur
        </button>
        <button type="button" class="quick-btn" onclick="sendQuickMessage('Quand dois-je faire ma vidange ?')">
            🛢️ Vidange
        </button>
        <button type="button" class="quick-btn" onclick="sendQuickMessage('Ma batterie semble faible')">
            🔋 Batterie
        </button>
    </div>
    
    <!-- Zone de saisie -->
    <form class="chat-input-area" id="chatForm">
        <div class="input-container">
            <textarea
                id="message-input"
                placeholder="Décris ton problème ou pose ta question..."
                rows="1"
                maxlength="1000"
            ></textarea>
            <button type="submit" class="btn-send" id="sendBtn">
                <span class="send-icon">➤</span>
            </button>
        </div>
        <div class="input-hint">
            <span id="charCount">0</span>/1000 caractères
        </div>
    </form>
</div>

<script>
const API_BASE = '<?= API_URL ?>';
let isTyping = false;

// ============================================
// Initialisation
// ============================================
function onVehicleSelectorConfirmed(vehicleId, vehicle) {
    reloadVehicleBanner(vehicle);
    document.getElementById('message-input')?.removeAttribute('disabled');
}

function reloadVehicleBanner(vehicle) {
    const wrap = document.getElementById('chat-vehicle-banner-wrap');
    const textEl = document.getElementById('chat-vehicle-text');
    const iconEl = document.getElementById('chat-vehicle-icon');
    if (!wrap || !textEl || !vehicle) {
        return;
    }
    wrap.classList.remove('hidden');
    textEl.textContent = `${vehicle.brand} ${vehicle.model}`.trim();
    if (iconEl) {
        iconEl.textContent = vehicle.category === 'moto' ? '🏍️' : '🚗';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setupFormListener();
    setupTextareaAutoResize();
    loadHistory();
    scrollToBottom();

    document.getElementById('message-input')?.setAttribute('disabled', 'disabled');

    VehicleSelector.open(onVehicleSelectorConfirmed);
});

// ============================================
// Configuration du formulaire
// ============================================
function setupFormListener() {
    const form = document.getElementById('chatForm');
    const input = document.getElementById('message-input');
    const charCount = document.getElementById('charCount');
    
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const message = input.value.trim();
        if (message && !isTyping) {
            sendMessage(message);
            input.value = '';
            charCount.textContent = '0';
            resizeTextarea(input);
        }
    });
    
    // Compteur de caractères
    input.addEventListener('input', () => {
        charCount.textContent = input.value.length;
    });
    
    // Envoi avec Enter (Shift+Enter pour nouvelle ligne)
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });
}

// ============================================
// Auto-resize du textarea
// ============================================
function setupTextareaAutoResize() {
    const textarea = document.getElementById('message-input');
    
    textarea.addEventListener('input', () => resizeTextarea(textarea));
}

function resizeTextarea(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 150) + 'px';
}

// ============================================
// Message de chargement diagnostic (un tirage, pas de rotation)
// ============================================
function showDiagnosticLoading() {
    const messages = [
        ['🔍 MecaBuddy consulte ses sources...', ''],
        ['🧰 MecaBuddy sort ses outils mentaux...', ''],
        ['📡 MecaBuddy interroge internet...', ''],
        ['⚙️ MecaBuddy analyse le problème...', ''],
        ['📖 MecaBuddy vérifie dans ses notes...', ''],
        ['🤔 MecaBuddy réfléchit...', ''],
        ['🔦 MecaBuddy inspecte tout ça...', ''],
        ['🛠️ MecaBuddy s\'y colle...', ''],
        ['🔩 MecaBuddy cherche la clé de 13...', ''],
        ['🧪 MecaBuddy fait le diagnostic...', ''],
    ].sort(() => Math.random() - 0.5);

    const [msg, sub] = messages[0];

    const typingBubble = document.getElementById('typingIndicator')
        ?? document.querySelector('.typing-indicator')
        ?? document.querySelector('.buddy-typing');

    if (!typingBubble) {
        const container = document.getElementById('chatMessages')
            ?? document.querySelector('.messages-container, #chat-messages, .chat-body');
        if (!container) {
            return () => {};
        }
        const el = document.createElement('div');
        el.className = 'message message-buddy typing-indicator typing-temp';
        el.id = 'diagnostic-loading-msg';
        el.innerHTML = `
            <div class="message-avatar">🔧</div>
            <div class="message-content">
                <span class="typing-msg"></span>
                ${sub ? '<span class="typing-sub"></span>' : ''}
            </div>
        `;
        container.appendChild(el);
        const msgEl = el.querySelector('.typing-msg');
        const subEl = el.querySelector('.typing-sub');
        if (msgEl) {
            msgEl.textContent = msg;
        }
        if (subEl) {
            subEl.textContent = sub;
        }
        el.style.display = 'flex';
        el.scrollIntoView({ behavior: 'smooth', block: 'end' });
        return () => {
            el.remove();
        };
    }

    const msgEl = typingBubble.querySelector('#typingStatusMsg')
        ?? typingBubble.querySelector('.typing-msg');
    const subEl = typingBubble.querySelector('#typingStatusSub')
        ?? typingBubble.querySelector('.typing-sub');

    if (msgEl) {
        msgEl.textContent = msg;
    }
    if (subEl) {
        subEl.textContent = sub;
    }

    typingBubble.style.display = '';
    typingBubble.scrollIntoView({ behavior: 'smooth', block: 'end' });

    return () => {
        if (typingBubble.id === 'diagnostic-loading-msg') {
            typingBubble.remove();
            return;
        }
        if (msgEl) {
            msgEl.textContent = '';
        }
        if (subEl) {
            subEl.textContent = '';
        }
    };
}

// ============================================
// Envoi d'un message
// ============================================
async function sendMessage(message) {
    // Affiche le message utilisateur
    addMessage(message, 'user');
    
    // Affiche l'indicateur de frappe
    showTypingIndicator();
    isTyping = true;
    
    // Cache les suggestions après le premier message
    document.getElementById('quickSuggestions').classList.add('hidden');

    let shown = false;
    let stopDiagnosticLoading = null;
    const showTimer = setTimeout(() => {
        shown = true;
        stopDiagnosticLoading = showDiagnosticLoading();
    }, 500);

    if (window.DebugPanel) {
        DebugPanel.start();
        DebugPanel.info('Message envoyé', { length: message.length });
    }

    try {
        const response = await fetch(`${API_BASE}/diagnostic_api.php?action=ask`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message })
        });
        
        const data = await response.json();

        clearTimeout(showTimer);
        if (shown && stopDiagnosticLoading) {
            stopDiagnosticLoading();
        }
        
        // Retire l'indicateur de frappe
        hideTypingIndicator();
        isTyping = false;
        
        if (data.success) {
            if (window.DebugPanel) {
                if (data.debug) {
                    DebugPanel.injectApiDebug(data.debug);
                }
                if (data.sources) {
                    DebugPanel.source(`${data.sources.length} source(s) retournée(s) au LLM`);
                }
                if (data.provider) {
                    DebugPanel.llm('Provider', data.provider);
                }
                DebugPanel.result('Réponse reçue', {
                    response_length: data.response?.length,
                    has_sources: (data.sources?.length ?? 0) > 0,
                });
            }
            if (data.debug) {
                console.debug('[Buddy diagnostic]', data.debug);
            }
            if (data.provider) {
                setLlmProviderBadge(data.provider);
            }
            const buddyMsg = addMessage(data.response, 'buddy', data.safety_warning, data.sources);
            if (data.failsafe && buddyMsg) {
                appendFailsafeBadge(buddyMsg.querySelector('.message-content'));
            }
        } else if (response.status === 429 || data.error === 'quota_exceeded') {
            const qMsg = data.message || 'Quota journalier Buddy atteint. Réessayez demain.';
            addMessage('⚠️ ' + qMsg, 'buddy');
            showToast(qMsg, 'warning', 6000);
        } else if (data.error === 'auth_required') {
            addMessage('🔐 Connexion démo requise pour utiliser Buddy.', 'buddy');
            showToast('Veuillez vous connecter.', 'warning', 5000);
        } else {
            if (window.DebugPanel) {
                DebugPanel.error('Réponse API en échec', data.error || null);
            }
            addMessage("Oups, j'ai eu un petit bug ! 🤖 Réessaie dans quelques secondes.", 'buddy');
        }
    } catch (error) {
        clearTimeout(showTimer);
        if (shown && stopDiagnosticLoading) {
            stopDiagnosticLoading();
        }
        if (window.DebugPanel) {
            DebugPanel.error('Fetch échoué', error?.message || String(error));
        }
        console.error('Erreur envoi message:', error);
        hideTypingIndicator();
        isTyping = false;
        addMessage("Hmm, problème de connexion... Vérifie ton réseau et réessaie ! 📡", 'buddy');
    }
}

// ============================================
// Envoi d'un message rapide
// ============================================
function sendQuickMessage(message) {
    document.getElementById('message-input').value = message;
    sendMessage(message);
    document.getElementById('message-input').value = '';
}

// ============================================
// Bloc sources web (Buddy uniquement)
// ============================================
function appendFailsafeBadge(container) {
    if (!container || container.querySelector('.failsafe-badge')) {
        return;
    }
    const badge = document.createElement('div');
    badge.className = 'failsafe-badge';
    badge.innerHTML = '⚠️ <strong>Aucune source fiable trouvée</strong>'
        + ' — Réponse générée depuis la mémoire du LLM.'
        + ' Vérifiez les valeurs critiques dans votre manuel.';
    container.appendChild(badge);
}

function renderSources(sources, container) {
    if (!sources || !Array.isArray(sources) || sources.length === 0) {
        return;
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
        return;
    }
    const div = document.createElement('div');
    div.className = 'buddy-sources';
    div.innerHTML = '<span class="sources-label">🔗 Sources :</span> ' + links.join(' · ');
    const insertBefore = container.querySelector('.safety-warning')
        || container.querySelector('.message-time');
    if (insertBefore) {
        container.insertBefore(div, insertBefore);
    } else {
        container.appendChild(div);
    }
}

function setLlmProviderBadge(providerName) {
    const el = document.getElementById('buddyLlmBadge');
    if (!el || !providerName) {
        return;
    }
    el.textContent = '⚡ ' + providerName;
    el.hidden = false;
}

// ============================================
// Ajout d'un message au chat
// ============================================
function addMessage(content, type, safetyWarning = null, sources = null) {
    const messagesContainer = document.getElementById('chatMessages');
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `message message-${type}`;
    
    if (type === 'buddy') {
        messageDiv.innerHTML = `
            <div class="message-avatar">🔧</div>
            <div class="message-content">
                <div class="message-bubble">
                    ${formatMessage(content)}
                </div>
                ${safetyWarning ? `
                    <div class="safety-warning">
                        <span class="warning-icon">⚠️</span>
                        <span class="warning-text">${safetyWarning}</span>
                    </div>
                ` : ''}
                <div class="message-time">${getCurrentTime()}</div>
            </div>
        `;
        renderSources(sources, messageDiv.querySelector('.message-content'));
    } else {
        messageDiv.innerHTML = `
            <div class="message-content">
                <div class="message-bubble">${escapeHtml(content)}</div>
                <div class="message-time">${getCurrentTime()}</div>
            </div>
        `;
    }
    
    messagesContainer.appendChild(messageDiv);
    scrollToBottom();
    return messageDiv;
}

// ============================================
// Formatage du message (sauts de ligne, gras, etc.)
// ============================================
function formatMessage(text) {
    // Échappe le HTML
    text = escapeHtml(text);
    
    // Convertit les sauts de ligne
    text = text.replace(/\n/g, '<br>');
    
    // Met en gras le texte entre *
    text = text.replace(/\*([^*]+)\*/g, '<strong>$1</strong>');
    
    // Convertit les listes à puces
    text = text.replace(/• /g, '<span class="bullet">•</span> ');
    
    return text;
}

// ============================================
// Indicateur de frappe
// ============================================
function showTypingIndicator() {
    const messagesContainer = document.getElementById('chatMessages');
    
    const typingDiv = document.createElement('div');
    typingDiv.className = 'message message-buddy typing-indicator';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = `
        <div class="message-avatar">🔧</div>
        <div class="message-content">
            <div class="typing-status">
                <p class="typing-msg" id="typingStatusMsg">🔍 Buddy cherche des infos...</p>
                <p class="typing-sub" id="typingStatusSub"></p>
            </div>
        </div>
    `;

    messagesContainer.appendChild(typingDiv);
    scrollToBottom();
}

function hideTypingIndicator() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
        indicator.remove();
    }
}

// ============================================
// Chargement de l'historique
// ============================================
async function loadHistory() {
    try {
        const response = await fetch(`${API_BASE}/diagnostic_api.php?action=history&limit=20`);
        const data = await response.json();
        
        if (data.success && data.conversations?.length) {
            // Cache les suggestions si il y a un historique
            document.getElementById('quickSuggestions').classList.add('hidden');
            
            data.conversations.forEach(conv => {
                addHistoryMessage(conv.user_message, 'user');
                const buddyMsg = addHistoryMessage(conv.buddy_response, 'buddy', conv.sources);
                if (conv.failsafe && buddyMsg) {
                    appendFailsafeBadge(buddyMsg.querySelector('.message-content'));
                }
            });
        }
    } catch (error) {
        console.error('Erreur chargement historique:', error);
    }
}

function addHistoryMessage(content, type, sources = null) {
    const messagesContainer = document.getElementById('chatMessages');
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `message message-${type}`;
    
    if (type === 'buddy') {
        messageDiv.innerHTML = `
            <div class="message-avatar">🔧</div>
            <div class="message-content">
                <div class="message-bubble">${formatMessage(content)}</div>
            </div>
        `;
        renderSources(sources, messageDiv.querySelector('.message-content'));
    } else {
        messageDiv.innerHTML = `
            <div class="message-content">
                <div class="message-bubble">${escapeHtml(content)}</div>
            </div>
        `;
    }
    
    messagesContainer.appendChild(messageDiv);
    return messageDiv;
}

// ============================================
// Effacer l'historique
// ============================================
async function clearChat() {
    if (!confirm('Effacer tout l\'historique de conversation ?')) {
        return;
    }
    
    try {
        await fetch(`${API_BASE}/diagnostic_api.php?action=clear`, {
            method: 'POST'
        });
        
        // Vide l'interface
        const messagesContainer = document.getElementById('chatMessages');
        messagesContainer.innerHTML = `
            <div class="message message-buddy welcome-message">
                <div class="message-avatar">🔧</div>
                <div class="message-content">
                    <div class="message-bubble">
                        <p>Conversation effacée ! 🧹</p>
                        <p>On recommence à zéro. Qu'est-ce qui t'amène aujourd'hui ?</p>
                    </div>
                </div>
            </div>
        `;
        
        const badge = document.getElementById('buddyLlmBadge');
        if (badge) {
            badge.textContent = '';
            badge.hidden = true;
        }
        
        // Réaffiche les suggestions
        document.getElementById('quickSuggestions').classList.remove('hidden');
        
    } catch (error) {
        console.error('Erreur effacement:', error);
        showToast('Erreur lors de l\'effacement', 'error');
    }
}

// ============================================
// Fonctions utilitaires
// ============================================
function scrollToBottom() {
    const container = document.getElementById('chatMessages');
    container.scrollTop = container.scrollHeight;
}

function getCurrentTime() {
    return new Date().toLocaleTimeString('fr-FR', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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

