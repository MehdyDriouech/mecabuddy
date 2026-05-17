/**
 * MecaBuddy - JavaScript Principal
 * 
 * Fichier JavaScript global pour les fonctionnalités communes :
 * - Navigation mobile
 * - Notifications toast
 * - Loading overlay
 * - Utilitaires divers
 */

// ============================================
// CONFIGURATION
// ============================================
const MecaBuddy = {
    // URLs de l'API (définies dans les pages PHP)
    apiUrl: window.API_URL || window.API_BASE || '/api',
    
    // État de l'application
    state: {
        isLoading: false,
        currentVehicle: null
    }
};

// ============================================
// INITIALISATION AU CHARGEMENT
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initDemoAuthLogout();
    initServiceWorkerIfAvailable();
});

function initDemoAuthLogout() {
    const btn = document.getElementById('demoAuthLogout');
    if (!btn) {
        return;
    }
    btn.addEventListener('click', async () => {
        try {
            const base = (window.API_URL || '').replace(/\/$/, '');
            await fetch(`${base}/auth_api.php?action=logout`, { method: 'POST' });
        } catch (e) {
            console.warn('Logout démo:', e);
        }
        const loginBase = (window.PUBLIC_URL || window.BASE_URL || '').replace(/\/$/, '');
        window.location.href = `${loginBase}/login.php`;
    });
}

// ============================================
// NAVIGATION MOBILE
// ============================================
function initNavigation() {
    const navToggle = document.getElementById('navToggle');
    const navLinks = document.getElementById('navLinks');
    
    if (navToggle && navLinks) {
        navToggle.addEventListener('click', () => {
            navLinks.classList.toggle('open');
            navToggle.classList.toggle('active');
        });
        
        // Ferme le menu si on clique sur un lien
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('open');
                navToggle.classList.remove('active');
            });
        });
        
        // Ferme le menu si on clique en dehors
        document.addEventListener('click', (e) => {
            if (!navToggle.contains(e.target) && !navLinks.contains(e.target)) {
                navLinks.classList.remove('open');
                navToggle.classList.remove('active');
            }
        });
    }
}

// ============================================
// NOTIFICATIONS TOAST
// ============================================

/**
 * Affiche une notification toast
 * 
 * @param {string} message - Message à afficher
 * @param {string} type - Type de notification (success, error, warning, info)
 * @param {number} duration - Durée d'affichage en ms (défaut: 3000)
 */
function showToast(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toastContainer');
    if (!container) {
        console.warn('Toast container not found');
        return;
    }
    
    // Crée l'élément toast
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    // Icône selon le type
    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    
    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || icons.info}</span>
        <span class="toast-message">${escapeHtml(message)}</span>
    `;
    
    container.appendChild(toast);
    
    // Animation d'entrée
    requestAnimationFrame(() => {
        toast.classList.add('visible');
    });
    
    // Animation de sortie et suppression
    setTimeout(() => {
        toast.classList.remove('visible');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, duration);
    
    return toast;
}

// Raccourcis pour les types de toast
function showSuccess(message, duration) {
    return showToast(message, 'success', duration);
}

function showError(message, duration) {
    return showToast(message, 'error', duration);
}

function showWarning(message, duration) {
    return showToast(message, 'warning', duration);
}

function showInfo(message, duration) {
    return showToast(message, 'info', duration);
}

// ============================================
// LOADING OVERLAY
// ============================================

/**
 * Affiche ou masque l'overlay de chargement
 * 
 * @param {boolean} show - True pour afficher, false pour masquer
 * @param {string} message - Message optionnel à afficher
 */
function showLoading(show, message = null) {
    const overlay = document.getElementById('loadingOverlay');
    if (!overlay) return;

    const messageEl = document.getElementById('loading-message');
    const subEl = document.getElementById('loading-submessage');
    if (show && message !== null && messageEl) {
        messageEl.textContent = message;
        if (subEl) {
            subEl.textContent = '';
        }
    }

    MecaBuddy.state.isLoading = show;
    overlay.classList.toggle('visible', show);
}

/**
 * Séquence de messages pendant un chargement long.
 * @param {Array<[number, string, string]>} steps [delayMs, message, subMessage]
 * @param {'overlay'|'typing'} target
 * @returns {function} cleanup — annule les timers restants
 */
function runLoadingMessageSequence(steps, target = 'overlay') {
    let msgEl;
    let subEl;
    if (target === 'typing') {
        msgEl = document.getElementById('typingStatusMsg');
        subEl = document.getElementById('typingStatusSub');
    } else {
        msgEl = document.getElementById('loading-message');
        subEl = document.getElementById('loading-submessage');
    }
    if (!msgEl || !subEl) {
        return () => {};
    }

    const timers = [];
    steps.forEach(([delay, msg, sub]) => {
        timers.push(setTimeout(() => {
            msgEl.style.opacity = '0';
            subEl.style.opacity = '0';
            setTimeout(() => {
                msgEl.textContent = msg;
                subEl.textContent = sub || '';
                msgEl.style.opacity = '1';
                subEl.style.opacity = sub ? '1' : '0';
            }, 200);
        }, delay));
    });

    return () => timers.forEach((t) => clearTimeout(t));
}

// ============================================
// UTILITAIRES FETCH API
// ============================================

/**
 * Effectue une requête GET vers l'API
 * 
 * @param {string} endpoint - Endpoint de l'API
 * @param {Object} params - Paramètres de requête
 * @returns {Promise<Object>} - Données de réponse
 */
function resolveApiUrl(endpoint) {
    const base = (window.API_URL || window.API_BASE || MecaBuddy.apiUrl || '/api').replace(/\/$/, '');
    if (/^https?:\/\//i.test(endpoint)) {
        return endpoint;
    }
    const path = endpoint.startsWith('/') ? endpoint : '/' + endpoint;
    return base + path;
}

async function apiGet(endpoint, params = {}) {
    const url = new URL(resolveApiUrl(endpoint));
    Object.keys(params).forEach(key => {
        if (params[key] !== undefined && params[key] !== null) {
            url.searchParams.append(key, params[key]);
        }
    });
    
    try {
        const response = await fetch(url.toString());
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Erreur de requête');
        }
        
        return data;
    } catch (error) {
        console.error('API GET Error:', error);
        throw error;
    }
}

/**
 * Effectue une requête POST vers l'API
 * 
 * @param {string} endpoint - Endpoint de l'API
 * @param {Object} data - Données à envoyer
 * @returns {Promise<Object>} - Données de réponse
 */
async function apiPost(endpoint, data = {}) {
    try {
        const response = await fetch(resolveApiUrl(endpoint), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const responseData = await response.json();
        
        if (!response.ok) {
            throw new Error(responseData.error || 'Erreur de requête');
        }
        
        return responseData;
    } catch (error) {
        console.error('API POST Error:', error);
        throw error;
    }
}

// ============================================
// UTILITAIRES DE FORMATAGE
// ============================================

/**
 * Échappe les caractères HTML pour éviter les injections XSS
 * 
 * @param {string} text - Texte à échapper
 * @returns {string} - Texte échappé
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Formate une date en français
 * 
 * @param {string|Date} date - Date à formater
 * @param {Object} options - Options de formatage
 * @returns {string} - Date formatée
 */
function formatDate(date, options = {}) {
    const d = new Date(date);
    const defaultOptions = {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    
    return d.toLocaleDateString('fr-FR', { ...defaultOptions, ...options });
}

/**
 * Formate un nombre avec séparateurs de milliers
 * 
 * @param {number} number - Nombre à formater
 * @returns {string} - Nombre formaté
 */
function formatNumber(number) {
    return new Intl.NumberFormat('fr-FR').format(number);
}

// ============================================
// UTILITAIRES DOM
// ============================================

/**
 * Attend que le DOM soit prêt
 * 
 * @param {Function} callback - Fonction à exécuter
 */
function domReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
}

/**
 * Sélectionne un élément du DOM
 * 
 * @param {string} selector - Sélecteur CSS
 * @param {Element} parent - Élément parent (défaut: document)
 * @returns {Element|null}
 */
function $(selector, parent = document) {
    return parent.querySelector(selector);
}

/**
 * Sélectionne plusieurs éléments du DOM
 * 
 * @param {string} selector - Sélecteur CSS
 * @param {Element} parent - Élément parent (défaut: document)
 * @returns {NodeList}
 */
function $$(selector, parent = document) {
    return parent.querySelectorAll(selector);
}

// ============================================
// GESTION DU VÉHICULE COURANT
// ============================================

/**
 * Charge les informations du véhicule courant depuis l'API
 * 
 * @returns {Promise<Object|null>} - Informations du véhicule ou null
 */
async function loadCurrentVehicle() {
    try {
        const data = await apiGet(`${MecaBuddy.apiUrl}/vehicle_api.php`, { action: 'current' });
        
        if (data.success && data.has_vehicle) {
            MecaBuddy.state.currentVehicle = data.vehicle;
            return data.vehicle;
        }
        
        MecaBuddy.state.currentVehicle = null;
        return null;
    } catch (error) {
        console.error('Error loading vehicle:', error);
        return null;
    }
}

/**
 * Efface le véhicule courant de la session
 * 
 * @returns {Promise<boolean>} - True si réussi
 */
async function clearCurrentVehicle() {
    try {
        const data = await apiPost(`${MecaBuddy.apiUrl}/vehicle_api.php?action=clear`);
        
        if (data.success) {
            MecaBuddy.state.currentVehicle = null;
            return true;
        }
        
        return false;
    } catch (error) {
        console.error('Error clearing vehicle:', error);
        return false;
    }
}

// ============================================
// SERVICE WORKER (PWA)
// ============================================

/**
 * Initialise le Service Worker si disponible
 */
function initServiceWorkerIfAvailable() {
    if ('serviceWorker' in navigator) {
        // Service Worker peut être ajouté pour faire une PWA
        // navigator.serviceWorker.register('/mecabuddy/sw.js');
    }
}

// ============================================
// GESTION DES ERREURS GLOBALES
// ============================================

window.addEventListener('error', (event) => {
    console.error('Global error:', event.error);
    // Peut envoyer à un service de monitoring
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
    // Peut envoyer à un service de monitoring
});

// ============================================
// DEBOUNCE & THROTTLE
// ============================================

/**
 * Crée une fonction debounced
 * 
 * @param {Function} func - Fonction à débouncer
 * @param {number} wait - Délai en ms
 * @returns {Function}
 */
function debounce(func, wait) {
    let timeout;
    
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Crée une fonction throttled
 * 
 * @param {Function} func - Fonction à throttler
 * @param {number} limit - Intervalle minimum en ms
 * @returns {Function}
 */
function throttle(func, limit) {
    let inThrottle;
    
    return function executedFunction(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// ============================================
// ANIMATIONS
// ============================================

/**
 * Anime un élément avec une classe CSS
 * 
 * @param {Element} element - Élément à animer
 * @param {string} animationClass - Classe d'animation
 * @returns {Promise}
 */
function animate(element, animationClass) {
    return new Promise(resolve => {
        element.classList.add(animationClass);
        
        element.addEventListener('animationend', function handler() {
            element.classList.remove(animationClass);
            element.removeEventListener('animationend', handler);
            resolve();
        });
    });
}

// ============================================
// EXPORT GLOBAL
// ============================================

// Expose les fonctions globalement pour utilisation dans les pages
window.MecaBuddy = MecaBuddy;
window.showToast = showToast;
window.showSuccess = showSuccess;
window.showError = showError;
window.showWarning = showWarning;
window.showInfo = showInfo;
window.showLoading = showLoading;
window.runLoadingMessageSequence = runLoadingMessageSequence;
window.apiGet = apiGet;
window.apiPost = apiPost;
window.escapeHtml = escapeHtml;
window.formatDate = formatDate;
window.formatNumber = formatNumber;
window.debounce = debounce;
window.throttle = throttle;
window.loadCurrentVehicle = loadCurrentVehicle;
window.clearCurrentVehicle = clearCurrentVehicle;

