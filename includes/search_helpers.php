<?php
if (defined('SEARCH_HELPERS_LOADED')) {
    return;
}
define('SEARCH_HELPERS_LOADED', true);

/** URL DuckDuckGo HTML (endpoint documenté pour le scraping léger). */
const MECABUDDY_DDG_HTML_URL = 'https://html.duckduckgo.com/html/';

const MECABUDDY_DDG_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

/**
 * Debug recherche web : APP_DEBUG ou panneau debug activé dans settings.
 */
function mecabuddy_web_search_debug_enabled(): bool
{
    if (defined('APP_DEBUG') && APP_DEBUG) {
        return true;
    }
    if (function_exists('isDebugPanelEnabled')) {
        return isDebugPanelEnabled();
    }

    return false;
}

/**
 * @return array{curl_available: bool, dom_available: bool, serper_configured: bool}
 */
function mecabuddy_get_search_environment(): array
{
    $serperConfigured = false;
    if (function_exists('getEffectiveSettings')) {
        $serperConfigured = trim((string) (getEffectiveSettings()['serper_api_key'] ?? '')) !== '';
    } elseif (function_exists('getSettings')) {
        $serperConfigured = trim((string) (getSettings()['serper_api_key'] ?? '')) !== '';
    }

    return [
        'curl_available' => function_exists('curl_init'),
        'dom_available' => class_exists('DOMDocument') && class_exists('DOMXPath'),
        'serper_configured' => $serperConfigured,
    ];
}

function mecabuddy_sanitize_body_hint(string $html, int $max = 300): string
{
    $t = preg_replace('/\s+/u', ' ', strip_tags($html));
    $t = trim((string) $t);
    if ($t === '') {
        return '(body vide ou HTML non textuel)';
    }

    return mb_substr($t, 0, $max);
}

/**
 * Détecte une page anti-bot / captcha / consentement DuckDuckGo.
 */
function mecabuddy_detect_ddg_block_page(string $html): ?string
{
    $h = strtolower($html);
    $needles = [
        'captcha' => 'captcha or challenge page detected',
        'anomaly-modal' => 'ddg anomaly modal detected',
        'bots use duckduckgo' => 'bot usage warning detected',
        'unusual traffic' => 'unusual traffic detected',
        'confirm you are human' => 'human verification detected',
        'javascript is disabled' => 'javascript required page',
        'enable javascript' => 'javascript required page',
        'cloudflare' => 'cloudflare interstitial suspected',
        'consent' => 'consent page suspected',
    ];
    foreach ($needles as $needle => $reason) {
        if (str_contains($h, $needle)) {
            return $reason;
        }
    }

    return null;
}

/**
 * @return list<string>
 */
function mecabuddy_blacklist_patterns(): array
{
    return [
        'fiche-technique', 'fiche technique',
        'données techniques', 'donnees techniques',
        'modesdemploi', 'mode-d-emploi',
        '1000ps.com', 'motoplanete.com', 'caradisiac.com',
        'largus.fr', 'leboncoin.fr', 'lacentrale.fr',
        'pieces-ktm', 'pieces-moto', 'pieces-auto',
        'ktmonline.fr', 'ktm.com/fr',
        '/shop/', '/store/', '/boutique/', '/catalogue/',
        'contact.html',
    ];
}

/**
 * @param array{title?: string, url?: string, snippet?: string} $row
 * @return string|null raison de rejet ou null si OK
 */
function mecabuddy_blacklist_reject_reason(array $row): ?string
{
    $title = trim((string) ($row['title'] ?? ''));
    $url = trim((string) ($row['url'] ?? ''));
    if ($title === '') {
        return 'empty_title';
    }
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return 'empty_url';
    }

    $h = strtolower($title . ' ' . $url);
    foreach (mecabuddy_blacklist_patterns() as $p) {
        if (str_contains($h, $p)) {
            if (str_contains($p, '/') || str_contains($p, '.')) {
                return 'url_blacklisted';
            }

            return 'title_blacklisted';
        }
    }

    return null;
}

/**
 * @param array<int, array{title?: string, url?: string, snippet?: string}> $results
 * @return array<int, array{title?: string, url?: string, snippet?: string}>
 */
function applyBlacklist(array $results): array
{
    return applyBlacklistWithMeta($results)['kept'];
}

/**
 * @param array<int, array{title?: string, url?: string, snippet?: string}> $results
 * @return array{kept: array<int, array{title: string, snippet: string, url: string}>, rejected: array<int, array{title: string, url: string, reason: string}>}
 */
function applyBlacklistWithMeta(array $results): array
{
    $kept = [];
    $rejected = [];

    foreach ($results as $r) {
        if (!is_array($r)) {
            continue;
        }
        $reason = mecabuddy_blacklist_reject_reason($r);
        if ($reason !== null) {
            if (count($rejected) < 12) {
                $rejected[] = [
                    'title' => mb_substr((string) ($r['title'] ?? ''), 0, 80),
                    'url' => mb_substr((string) ($r['url'] ?? ''), 0, 120),
                    'reason' => $reason,
                ];
            }
            continue;
        }
        $kept[] = [
            'title' => (string) ($r['title'] ?? ''),
            'snippet' => (string) ($r['snippet'] ?? ''),
            'url' => (string) ($r['url'] ?? ''),
        ];
    }

    return ['kept' => $kept, 'rejected' => $rejected];
}
