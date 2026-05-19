/**
 * MecaBuddy - Invitation à découvrir la page À propos (session, après 2 min)
 */
(function () {
    'use strict';

    const STORAGE_KEY = 'mecabuddy_about_prompt_seen';
    const DELAY_MS = 120000;
    const RETRY_MS = 15000;
    const EXCLUDED_PAGES = [
        'about.php',
        'login.php',
        'dev.php',
        'test_api.php',
        'account-settings.php',
        'db_diag.php',
    ];

    function getCurrentPage() {
        const path = window.location.pathname || '';
        const parts = path.split('/').filter(Boolean);
        return parts.length ? parts[parts.length - 1] : 'index.php';
    }

    function isExcludedPage() {
        return EXCLUDED_PAGES.includes(getCurrentPage());
    }

    function isSeen() {
        try {
            return sessionStorage.getItem(STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function markSeen() {
        try {
            sessionStorage.setItem(STORAGE_KEY, '1');
        } catch (e) {
            /* ignore */
        }
    }

    function isLoadingOverlayVisible() {
        const overlay = document.getElementById('loadingOverlay');
        return overlay && overlay.classList.contains('visible');
    }

    function isVehicleSelectorOpen() {
        const modal = document.getElementById('vehicle-selector-modal');
        if (!modal) {
            return false;
        }
        const style = window.getComputedStyle(modal);
        return style.display !== 'none' && style.visibility !== 'hidden';
    }

    function isStandardModalOpen() {
        return Array.from(document.querySelectorAll('.modal')).some(
            (el) => !el.classList.contains('hidden')
        );
    }

    function isBuddyBusy() {
        return Boolean(document.getElementById('typingIndicator'));
    }

    function isAboutPromptOpen() {
        const modal = document.getElementById('aboutPromptModal');
        return modal && !modal.hasAttribute('hidden');
    }

    function isInteractionBlocked() {
        if (window.MecaBuddy && window.MecaBuddy.state && window.MecaBuddy.state.isLoading) {
            return true;
        }
        if (isLoadingOverlayVisible()) {
            return true;
        }
        if (isVehicleSelectorOpen()) {
            return true;
        }
        if (isStandardModalOpen()) {
            return true;
        }
        if (isBuddyBusy()) {
            return true;
        }
        if (isAboutPromptOpen()) {
            return true;
        }
        return false;
    }

    function getAboutUrl(modal) {
        const fromData = modal.getAttribute('data-about-url');
        if (fromData) {
            return fromData;
        }
        const base = (window.PUBLIC_URL || window.BASE_URL || '').replace(/\/$/, '');
        return `${base}/about.php`;
    }

    function showPrompt(modal) {
        modal.removeAttribute('hidden');
        document.body.classList.add('about-prompt-open');

        const focusTarget = modal.querySelector('[data-about-prompt-cta]')
            || modal.querySelector('.about-prompt__close');
        if (focusTarget && typeof focusTarget.focus === 'function') {
            focusTarget.focus();
        }
    }

    function hidePrompt(modal) {
        modal.setAttribute('hidden', '');
        document.body.classList.remove('about-prompt-open');
    }

    function init() {
        if (isExcludedPage() || isSeen()) {
            return;
        }

        const modal = document.getElementById('aboutPromptModal');
        if (!modal) {
            return;
        }

        const aboutUrl = getAboutUrl(modal);
        const cta = modal.querySelector('[data-about-prompt-cta]');
        if (cta) {
            cta.setAttribute('href', aboutUrl);
        }

        let retryScheduled = false;

        function dismiss() {
            markSeen();
            hidePrompt(modal);
        }

        modal.querySelectorAll('[data-about-prompt-close]').forEach((el) => {
            el.addEventListener('click', dismiss);
        });

        if (cta) {
            cta.addEventListener('click', () => {
                markSeen();
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hasAttribute('hidden')) {
                dismiss();
            }
        });

        function tryShow(isRetry) {
            if (isSeen()) {
                return;
            }
            if (isInteractionBlocked()) {
                if (!isRetry && !retryScheduled) {
                    retryScheduled = true;
                    window.setTimeout(() => tryShow(true), RETRY_MS);
                }
                return;
            }
            showPrompt(modal);
        }

        window.setTimeout(() => tryShow(false), DELAY_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
