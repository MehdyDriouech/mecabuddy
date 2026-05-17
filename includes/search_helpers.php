<?php
if (defined('SEARCH_HELPERS_LOADED')) {
    return;
}
define('SEARCH_HELPERS_LOADED', true);

/**
 * @param array<int, array{title?: string, url?: string, snippet?: string}> $results
 * @return array<int, array{title?: string, url?: string, snippet?: string}>
 */
function applyBlacklist(array $results): array
{
    $blacklist = [
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

    return array_values(array_filter($results, static function ($r) use ($blacklist) {
        if (!is_array($r)) {
            return false;
        }
        $h = strtolower(($r['title'] ?? '') . ' ' . ($r['url'] ?? ''));
        foreach ($blacklist as $p) {
            if (str_contains($h, $p)) {
                return false;
            }
        }

        return true;
    }));
}
