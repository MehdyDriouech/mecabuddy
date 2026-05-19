<?php
/**
 * MecaBuddy — Recherche web de visuels pour étapes tutoriel (POC).
 * Le LLM ne fournit que des requêtes ; les images viennent de sources externes (Serper / liens DDG).
 */

if (defined('TUTORIAL_VISUAL_SEARCH_LOADED')) {
    return;
}
define('TUTORIAL_VISUAL_SEARCH_LOADED', true);

require_once __DIR__ . '/search_helpers.php';

const TUTORIAL_VISUAL_DISCLAIMER_DEFAULT =
    'Les images et schémas proviennent de résultats web externes. MecaBuddy ne peut pas garantir '
    . 'qu\'ils correspondent exactement à votre véhicule.';

const TUTORIAL_VISUAL_RESULTS_DISCLAIMER =
    'Résultats web indicatifs. L\'emplacement exact peut varier selon véhicule, moteur, année et finition.';

const TUTORIAL_VISUAL_CRITICAL_WARNING =
    'Intervention sur organe critique : les visuels ne remplacent pas l\'expertise ni l\'outillage adapté. '
    . 'Consultez un professionnel si vous n\'êtes pas compétent ou équipé.';

/** @var list<string> */
const TUTORIAL_VISUAL_TYPES_ALLOWED = [
    'part_location',
    'engine_view',
    'connector',
    'clip_fastener',
    'fastener',
    'removal_direction',
    'tool_in_use',
    'before_after',
    'safety_diagram',
    'fluid_level_leak',
    'generic',
];

/** @var list<string> */
const TUTORIAL_VISUAL_SPECIFICITY_ALLOWED = [
    'generic',
    'vehicle_family',
    'vehicle_specific',
    'unknown',
];

/** Mots-clés organes critiques (aligné safety_layer.php). */
const TUTORIAL_VISUAL_CRITICAL_KEYWORDS = [
    'frein', 'plaquette', 'disque', 'direction', 'airbag', 'ceinture', 'carburant',
    'essence', 'diesel', 'injecteur', 'hybride', 'électrique', 'electrique', 'haute tension',
    'distribution', 'courroie', 'chaîne', 'chaine', 'embrayage', 'transmission', 'boîte',
    'boite', 'suspension', 'amortisseur', 'pneu', 'surchauffe', 'refroidissement',
];

/**
 * URL recherche images DuckDuckGo (ouverture manuelle).
 */
function tutorial_visual_ddg_search_url(string $query): string
{
    return 'https://duckduckgo.com/?q=' . rawurlencode($query) . '&iax=images&ia=images';
}

/**
 * @param array<string, mixed>|null $vehicle
 */
function tutorial_visual_is_critical_context(string $actionType, ?array $vehicle, string $stepTitle = ''): bool
{
    $blob = mb_strtolower($actionType . ' ' . $stepTitle, 'UTF-8');
    foreach (TUTORIAL_VISUAL_CRITICAL_KEYWORDS as $kw) {
        if (str_contains($blob, $kw)) {
            return true;
        }
    }

    return false;
}

/**
 * Retire les requêtes contenant un code moteur inventé (non présent dans le contexte véhicule).
 *
 * @param list<string> $queries
 * @param array<string, mixed>|null $vehicle
 * @return list<string>
 */
function tutorial_visual_filter_invented_engine_codes(array $queries, ?array $vehicle): array
{
    $known = '';
    if ($vehicle !== null) {
        $known = mb_strtolower(implode(' ', array_filter([
            (string) ($vehicle['brand'] ?? ''),
            (string) ($vehicle['model'] ?? ''),
            (string) ($vehicle['engine_type'] ?? ''),
            (string) ($vehicle['engine_size'] ?? ''),
        ])), 'UTF-8');
    }

    $out = [];
    foreach ($queries as $q) {
        $q = trim((string) $q);
        if ($q === '') {
            continue;
        }
        if (preg_match_all('/\b([A-Z]{3,6})\b/u', $q, $m)) {
            $suspect = false;
            foreach ($m[1] as $code) {
                if (strlen($code) < 3) {
                    continue;
                }
                if ($known !== '' && str_contains($known, mb_strtolower($code, 'UTF-8'))) {
                    continue;
                }
                if (preg_match('/^(TSI|TDI|HDi|CDI|GDI|PMH|OBD|ABS|ESP)$/u', $code)) {
                    continue;
                }
                $suspect = true;
                break;
            }
            if ($suspect) {
                continue;
            }
        }
        $out[] = $q;
    }

    return $out;
}

/**
 * @param list<string> $queries
 * @return list<string>
 */
function tutorial_visual_cap_queries(array $queries, int $max = 4): array
{
    $clean = [];
    foreach ($queries as $q) {
        $q = trim((string) $q);
        if ($q !== '') {
            $clean[] = $q;
        }
        if (count($clean) >= $max) {
            break;
        }
    }

    return $clean;
}

/**
 * @return array{title: string, page_url: string, image_url: string, snippet: string, source_domain: string, query_used: string}|null
 */
function tutorial_visual_normalize_image_hit(array $row, string $queryUsed): ?array
{
    $pageUrl = trim((string) ($row['link'] ?? $row['url'] ?? ''));
    $imageUrl = trim((string) ($row['imageUrl'] ?? $row['image'] ?? ''));
    $title = trim((string) ($row['title'] ?? ''));
    $snippet = trim((string) ($row['snippet'] ?? ''));

    if ($pageUrl === '' && $imageUrl === '') {
        return null;
    }
    if ($pageUrl !== '' && !preg_match('#^https?://#i', $pageUrl)) {
        return null;
    }
    if ($imageUrl !== '' && !preg_match('#^https?://#i', $imageUrl)) {
        $imageUrl = '';
    }

    $domain = '';
    $hostUrl = $pageUrl !== '' ? $pageUrl : $imageUrl;
    $host = parse_url($hostUrl, PHP_URL_HOST);
    if (is_string($host)) {
        $domain = $host;
    }

    return [
        'title' => $title !== '' ? $title : ($domain !== '' ? $domain : 'Résultat image'),
        'page_url' => $pageUrl !== '' ? $pageUrl : $imageUrl,
        'image_url' => $imageUrl,
        'snippet' => $snippet,
        'source_domain' => $domain,
        'query_used' => $queryUsed,
    ];
}

/**
 * @return list<array{title: string, page_url: string, image_url: string, snippet: string, source_domain: string, query_used: string}>
 */
function mecabuddy_search_images_via_serper(string $query, string $apiKey, int $limit = 3): array
{
    if (!function_exists('_mecabuddyCurl')) {
        require_once __DIR__ . '/llm_chat.php';
    }

    $payload = json_encode([
        'q' => $query,
        'gl' => 'fr',
        'hl' => 'fr',
        'num' => min(10, max(1, $limit)),
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return [];
    }

    $result = _mecabuddyCurl('https://google.serper.dev/images', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-KEY: ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    if (!$result['ok']) {
        error_log('[MecaBuddy][visual] Serper images HTTP ' . ($result['http_code'] ?? 0));

        return [];
    }

    $data = json_decode($result['body'], true);
    if (!is_array($data)) {
        return [];
    }

    $images = $data['images'] ?? [];
    if (!is_array($images)) {
        return [];
    }

    $out = [];
    foreach ($images as $row) {
        if (!is_array($row) || count($out) >= $limit) {
            break;
        }
        $hit = tutorial_visual_normalize_image_hit($row, $query);
        if ($hit !== null) {
            $out[] = $hit;
        }
    }

    return $out;
}

/**
 * Recherche images pour une requête (Serper si clé, sinon aucun résultat auto).
 *
 * @return list<array{title: string, page_url: string, image_url: string, snippet: string, source_domain: string, query_used: string}>
 */
function mecabuddy_search_images_for_query(string $query, int $limitPerQuery = 2): array
{
    $query = trim($query);
    if ($query === '' || !function_exists('curl_init')) {
        return [];
    }

    $settings = function_exists('getEffectiveSettings')
        ? getEffectiveSettings()
        : (function_exists('getSettings') ? getSettings() : []);
    $key = trim((string) ($settings['serper_api_key'] ?? ''));

    if ($key !== '') {
        return mecabuddy_search_images_via_serper($query, $key, $limitPerQuery);
    }

    return [];
}

/**
 * @param list<string> $queries
 * @return list<array{label: string, url: string, provider: string}>
 */
function tutorial_visual_build_manual_links(array $queries): array
{
    $links = [];
    foreach (tutorial_visual_cap_queries($queries, 4) as $q) {
        $links[] = [
            'label' => $q,
            'url' => tutorial_visual_ddg_search_url($q),
            'provider' => 'duckduckgo',
        ];
    }

    return $links;
}

/**
 * @param array<string, mixed> $step
 * @param array<string, mixed>|null $vehicle
 * @return array<string, mixed>
 */
function tutorial_visual_normalize_step_fields(array $step, ?array $vehicle): array
{
    $needs = ($step['needs_visual'] ?? false) === true;
    $vType = trim((string) ($step['visual_type'] ?? ''));
    if ($vType === '' || strcasecmp($vType, 'null') === 0) {
        $vType = $needs ? 'generic' : '';
    }
    if ($vType !== '' && !in_array($vType, TUTORIAL_VISUAL_TYPES_ALLOWED, true)) {
        $vType = 'generic';
    }

    $spec = trim((string) ($step['visual_specificity'] ?? 'unknown'));
    if (!in_array($spec, TUTORIAL_VISUAL_SPECIFICITY_ALLOWED, true)) {
        $spec = 'unknown';
    }

    $queries = [];
    if (isset($step['visual_search_queries']) && is_array($step['visual_search_queries'])) {
        $queries = tutorial_visual_cap_queries(
            tutorial_visual_filter_invented_engine_codes(
                array_map('strval', $step['visual_search_queries']),
                $vehicle
            ),
            4
        );
    }

    $purpose = trim((string) ($step['visual_purpose'] ?? ''));
    if (strcasecmp($purpose, 'null') === 0) {
        $purpose = '';
    }

    $disclaimer = trim((string) ($step['visual_disclaimer'] ?? ''));
    if ($disclaimer === '' || strcasecmp($disclaimer, 'null') === 0) {
        $disclaimer = $needs ? TUTORIAL_VISUAL_DISCLAIMER_DEFAULT : '';
    }

    return [
        'needs_visual' => $needs,
        'visual_type' => $needs ? ($vType !== '' ? $vType : 'generic') : null,
        'visual_purpose' => $needs ? $purpose : null,
        'visual_search_queries' => $needs ? $queries : [],
        'visual_specificity' => $needs ? $spec : null,
        'visual_disclaimer' => $needs ? $disclaimer : null,
    ];
}

/**
 * Enrichit les étapes normalisées avec résultats image (non bloquant).
 *
 * @param list<array<string, mixed>> $steps
 * @param array<string, mixed>|null $vehicle
 * @return list<array<string, mixed>>
 */
function tutorial_enrich_steps_with_visuals(
    array $steps,
    ?array $vehicle,
    string $actionType = ''
): array {
    $maxStepsWithFetch = 6;
    $fetchedSteps = 0;

    foreach ($steps as $i => $step) {
        if (!is_array($step)) {
            continue;
        }

        $visual = tutorial_visual_normalize_step_fields($step, $vehicle);
        $step = array_merge($step, $visual);

        if (!$visual['needs_visual']) {
            $step['visual_results'] = [];
            $step['visual_search_status'] = 'skipped';
            $step['visual_manual_links'] = [];
            $step['visual_critical_warning'] = false;
            $steps[$i] = $step;
            continue;
        }

        $critical = tutorial_visual_is_critical_context(
            $actionType,
            $vehicle,
            (string) ($step['title'] ?? '')
        );
        $step['visual_critical_warning'] = $critical;

        $queries = $visual['visual_search_queries'];
        $step['visual_manual_links'] = tutorial_visual_build_manual_links($queries);

        $results = [];
        $status = 'no_results';

        if ($queries !== [] && $fetchedSteps < $maxStepsWithFetch) {
            $seen = [];
            foreach (array_slice($queries, 0, 2) as $q) {
                foreach (mecabuddy_search_images_for_query($q, 2) as $hit) {
                    $key = ($hit['image_url'] ?? '') . '|' . ($hit['page_url'] ?? '');
                    if ($key === '|' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $results[] = $hit;
                    if (count($results) >= 4) {
                        break 2;
                    }
                }
            }
            $fetchedSteps++;
            $status = $results !== [] ? 'ok' : 'no_results';
        } elseif ($queries === []) {
            $status = 'no_queries';
        }

        $step['visual_results'] = $results;
        $step['visual_search_status'] = $status;
        $steps[$i] = $step;
    }

    return $steps;
}
