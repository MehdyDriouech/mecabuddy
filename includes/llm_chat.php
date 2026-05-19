<?php
/**
 * MecaBuddy — Chat LLM multi-tour (diagnostic Buddy), distinct de llm_bridge.php
 */

if (defined('LLM_CHAT_LOADED')) {
    return;
}
define('LLM_CHAT_LOADED', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/vehicle_context.php';
require_once __DIR__ . '/search_helpers.php';

function buildSystemPrompt(?array $vehicle = null): string
{
    $base = <<<'PROMPT'
Tu es Buddy, assistant expert en mécanique automobile et moto (mode Diagnostic).
Tu réponds UNIQUEMENT aux sujets : mécanique, entretien, réparation, diagnostic de pannes,
pièces, pneumatiques, carrosserie, électronique embarquée — voitures et motos uniquement.
Hors sujet : une phrase polie d'excuse, puis invite à poser une question mécanique.

=== RÈGLES DE DIAGNOSTIC (prioritaires) ===

Quand l'utilisateur décrit un symptôme ou un problème :

1) Reformule brièvement ce que tu as compris.
2) Propose au maximum 3 pistes plausibles (jamais plus).
3) Pour CHAQUE piste, indique :
   - pourquoi elle est compatible avec les symptômes décrits ;
   - les autres symptômes souvent associés à cette panne (à vérifier) ;
   - les signes qui renforcent cette piste ;
   - les signes qui affaiblissent cette piste ;
   - les questions utiles à poser à l'utilisateur ;
   - un test simple, non dangereux et non invasif ;
   - un niveau de confiance : faible, moyen ou élevé (jamais « certain » sans preuve directe).

4) Ne JAMAIS affirmer qu'une pièce est défectueuse sans preuve directe (mesure, code défaut
   confirmé, test concluant, constat visuel évident).
5) Ne JAMAIS recommander un remplacement direct sans diagnostic suffisant.
6) Priorise les contrôles simples : observation visuelle, bruit, odeur, contexte d'apparition,
   voyants tableau de bord, lecture OBD si disponible, niveaux et fuites visibles.
7) Si les informations sont insuffisantes : réponse plus courte, privilégie questions utiles
   et contrôles simples plutôt que des listes longues.

=== NIVEAU DE RISQUE (obligatoire, une seule ligne après « Ce que j'ai compris ») ===

Indique clairement : Faible | Moyen | Élevé | Critique
- Faible : le véhicule peut généralement être surveillé.
- Moyen : diagnostic conseillé rapidement.
- Élevé : éviter de rouler si le symptôme s'aggrave.
- Critique : immobiliser le véhicule et consulter un professionnel.

Sois particulièrement conservateur (pas de formulations rassurantes abusives ; oriente vers
un professionnel) si le problème touche : freinage, direction, pneus, surchauffe moteur,
fuite carburant, odeur de brûlé, fumée anormale, perte de puissance importante, voyant rouge,
bruit métallique important, problème électrique sévère, airbag ou ceinture, transmission qui
patine ou bloque.

=== FORMAT DE RÉPONSE (titres exacts, dans cet ordre) ===

## Ce que j'ai compris
## Niveau de risque
## Pistes possibles
### Piste 1 — [nom court]
- **Pourquoi c'est compatible** : …
- **Symptômes associés à vérifier** : …
- **Signes qui renforcent cette piste** : …
- **Signes qui l'affaiblissent** : …
- **Test simple** : …
- **Confiance** : faible | moyen | élevé
(Répète pour Piste 2 et Piste 3 seulement si utile.)
## Symptômes associés à vérifier
(synthèse transversale des vérifications complémentaires)
## Signes qui changeraient le diagnostic
(éléments qui feraient pencher vers une autre cause)
## Questions utiles
## À éviter
(ex. : remplacer une pièce sans confirmation ; effacer les codes avant de les noter ;
continuer à rouler en surchauffe ; intervenir sur freinage/airbag/direction sans compétence)
## Prochaine action recommandée

Ton : pédagogique, prudent, accessible. Utilise des listes à puces. Pas de jargon inutile.

=== EXEMPLE (à imiter, ne pas recopier mot pour mot) ===

Utilisateur : « Ma voiture cale parfois et a du mal à redémarrer à chaud. »
→ Ne pas conclure. Piste possible capteur PMH avec symptômes associés (démarrage chaud difficile,
coupures aléatoires, ralenti instable, compte-tours à zéro, code régime moteur si OBD).
→ Autres pistes : alimentation carburant, allumage, prise d'air, autre capteur moteur.
→ Questions : voyant moteur, codes, chaud/froid, régime. Tests : OBD, observation compte-tours.
→ Confiance souvent moyenne car symptômes peuvent être communs à plusieurs causes.

=== RECHERCHE WEB ===

Quand tu utilises des informations issues d'une recherche web, cite tes sources à la fin avec
EXACTEMENT ce format (sinon rien) :
[SOURCES]
[{"title":"titre de la page","url":"https://..."}]
[/SOURCES]

Réponds toujours en français.
PROMPT;

    $fragment = buildVehiclePromptFragment($vehicle);
    if ($fragment === '') {
        return $base;
    }

    return $base . "\n" . $fragment;
}

/**
 * Dernier provider de recherche web utilisé ('serper' | 'duckduckgo' | 'none').
 */
function getLastSearchProvider(): string
{
    return _mecabuddySearchProviderState();
}

/**
 * @param 'serper'|'duckduckgo'|'none'|null $provider
 */
function _mecabuddySearchProviderState(?string $provider = null): string
{
    static $lastProvider = 'none';
    if ($provider !== null) {
        $lastProvider = $provider;
    }

    return $lastProvider;
}

/**
 * @param array<int, mixed> $options
 * @return array{body: string, http_code: int, error: string, ok: bool}
 */
function _mecabuddyCurl(string $url, array $options = []): array
{
    if (!function_exists('curl_init')) {
        return [
            'body' => '',
            'http_code' => 0,
            'error' => 'curl_unavailable',
            'ok' => false,
        ];
    }

    $ch = curl_init();
    if ($ch === false) {
        return [
            'body' => '',
            'http_code' => 0,
            'error' => 'curl_init_failed',
            'ok' => false,
        ];
    }

    curl_setopt_array($ch, $options);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // ⚠️ SSL désactivé uniquement si APP_DEBUG=true (environnement local)
    // Ne JAMAIS déployer en production avec APP_DEBUG=true
    // En production : installer un vrai certificat SSL ou configurer
    // CURLOPT_CAINFO avec le bundle cacert.pem approprié
    $isLocal = defined('APP_DEBUG') && APP_DEBUG;
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$isLocal);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $isLocal ? 0 : 2);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'body' => $response !== false ? (string) $response : '',
        'http_code' => $httpCode,
        'error' => $error,
        'ok' => $response !== false && $httpCode >= 200 && $httpCode < 300,
    ];
}

/**
 * @param array<int, array{title: string, snippet: string, url: string}> $results
 * @return array<int, array{title: string, snippet: string, url: string}>
 */
function _mecabuddyFilterWebResultsByVehicle(array $results, ?array $vehicle): array
{
    if ($vehicle === null || empty($vehicle['brand'])) {
        return $results;
    }

    $brand = mb_strtolower(trim((string) $vehicle['brand']));
    if ($brand === '') {
        return $results;
    }

    $otherBrands = [
        'peugeot', 'renault', 'citroën', 'citroen', 'volkswagen',
        'toyota', 'ford', 'bmw', 'mercedes', 'audi', 'opel', 'fiat',
        'honda', 'nissan', 'hyundai', 'kia', 'dacia', 'seat', 'skoda',
    ];

    $filtered = array_filter($results, static function (array $r) use ($brand, $otherBrands): bool {
        $titleLower = mb_strtolower(($r['title'] ?? '') . ' ' . ($r['url'] ?? ''));
        foreach ($otherBrands as $other) {
            if ($other === $brand) {
                continue;
            }
            if (str_contains($titleLower, $other) && !str_contains($titleLower, $brand)) {
                return false;
            }
        }

        return true;
    });

    return array_values($filtered);
}

/**
 * @return list<string>
 */
function mecabuddy_get_search_queries_run(): array
{
    return $GLOBALS['_mecabuddy_queries_run'] ?? [];
}

/**
 * @return list<array{query: string, added: int, total: int}>
 */
function mecabuddy_get_search_query_details(): array
{
    return $GLOBALS['_mecabuddy_query_details'] ?? [];
}

/**
 * @return array<string, mixed>
 */
function _mecabuddy_empty_search_diag(string $query, string $attempted = 'duckduckgo'): array
{
    $env = mecabuddy_get_search_environment();

    return [
        'query' => $query,
        'search_provider_attempted' => $attempted,
        'url' => $attempted === 'serper' ? 'https://google.serper.dev/search' : MECABUDDY_DDG_HTML_URL,
        'http_status' => 0,
        'curl_errno' => 0,
        'curl_error' => '',
        'body_length' => 0,
        'body_hint' => '',
        'dom_available' => $env['dom_available'],
        'dom_parse_success' => false,
        'raw_result_count' => 0,
        'after_blacklist_count' => 0,
        'after_vehicle_filter_count' => 0,
        'final_count' => 0,
        'rejected_samples' => [],
        'provider_final' => 'none',
        'error_code' => !($env['curl_available'] ?? false) ? 'curl_unavailable' : null,
    ];
}

/**
 * @param array<int, array{title: string, snippet: string, url: string}> $results
 * @return array{kept: array<int, array{title: string, snippet: string, url: string}>, rejected: array<int, array{title: string, url: string, reason: string}>}
 */
function _mecabuddyFilterWebResultsByVehicleMeta(array $results, ?array $vehicle): array
{
    if ($vehicle === null || empty($vehicle['brand'])) {
        return ['kept' => $results, 'rejected' => []];
    }

    $brand = mb_strtolower(trim((string) $vehicle['brand']));
    if ($brand === '') {
        return ['kept' => $results, 'rejected' => []];
    }

    $otherBrands = [
        'peugeot', 'renault', 'citroën', 'citroen', 'volkswagen',
        'toyota', 'ford', 'bmw', 'mercedes', 'audi', 'opel', 'fiat',
        'honda', 'nissan', 'hyundai', 'kia', 'dacia', 'seat', 'skoda',
    ];

    $kept = [];
    $rejected = [];

    foreach ($results as $r) {
        if (!is_array($r)) {
            continue;
        }
        $titleLower = mb_strtolower(($r['title'] ?? '') . ' ' . ($r['url'] ?? ''));
        $reject = false;
        foreach ($otherBrands as $other) {
            if ($other === $brand) {
                continue;
            }
            if (str_contains($titleLower, $other) && !str_contains($titleLower, $brand)) {
                $reject = true;
                break;
            }
        }
        if ($reject) {
            if (count($rejected) < 12) {
                $rejected[] = [
                    'title' => mb_substr((string) ($r['title'] ?? ''), 0, 80),
                    'url' => mb_substr((string) ($r['url'] ?? ''), 0, 120),
                    'reason' => 'vehicle_brand_mismatch',
                ];
            }
            continue;
        }
        $kept[] = $r;
    }

    return ['kept' => array_values($kept), 'rejected' => $rejected];
}

/**
 * @return array{items: array<int, array{title: string, snippet: string, url: string}>, diagnostic: array<string, mixed>}
 */
function _mecabuddySerperFetch(string $query, string $apiKey): array
{
    $diag = [
        'search_provider_attempted' => 'serper',
        'url' => 'https://google.serper.dev/search',
        'http_status' => 0,
        'curl_errno' => 0,
        'curl_error' => '',
        'body_length' => 0,
        'body_hint' => '',
        'dom_available' => true,
        'dom_parse_success' => true,
        'raw_result_count' => 0,
        'error_code' => null,
    ];

    if (!function_exists('curl_init')) {
        $diag['error_code'] = 'curl_unavailable';

        return ['items' => [], 'diagnostic' => $diag];
    }

    $items = _searchViaSerper($query, $apiKey);
    $last = $GLOBALS['_mecabuddy_last_serper_curl'] ?? [];
    $diag['http_status'] = (int) ($last['http_code'] ?? 0);
    $diag['curl_error'] = (string) ($last['error'] ?? '');
    $diag['body_length'] = (int) ($last['body_length'] ?? 0);
    if ($diag['body_length'] > 0 && mecabuddy_web_search_debug_enabled()) {
        $diag['body_hint'] = mecabuddy_sanitize_body_hint((string) ($last['body_preview'] ?? ''), 300);
    }
    $diag['raw_result_count'] = count($items);
    if ($items === [] && $diag['http_status'] !== 200) {
        $diag['error_code'] = 'http_error';
    } elseif ($items === [] && $diag['http_status'] === 200) {
        $diag['error_code'] = 'serper_empty_response';
    }

    return ['items' => $items, 'diagnostic' => $diag];
}

/**
 * @return array<int, array{title: string, snippet: string, url: string}>
 */
function _mecabuddyDuckDuckGoParseRegex(string $html): array
{
    $items = [];
    if (!preg_match_all(
        '/<a[^>]+class="[^"]*result__a[^"]*"[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/is',
        $html,
        $matches,
        PREG_SET_ORDER
    )) {
        return [];
    }

    $seen = [];
    foreach ($matches as $m) {
        if (count($items) >= 5) {
            break;
        }
        $rawUrl = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = trim(strip_tags($m[2]));
        $finalUrl = _mecabuddyResolveDuckDuckGoUrl($rawUrl);
        if ($finalUrl === '' || !str_starts_with($finalUrl, 'http') || $title === '') {
            continue;
        }
        $host = strtolower((string) (parse_url($finalUrl, PHP_URL_HOST) ?? ''));
        if ($host === '' || isset($seen[$host])) {
            continue;
        }
        $seen[$host] = true;
        $items[] = ['title' => $title, 'snippet' => '', 'url' => $finalUrl];
    }

    return $items;
}

/**
 * @return array{items: array<int, array{title: string, snippet: string, url: string}>, diagnostic: array<string, mixed>}
 */
function _mecabuddyDuckDuckGoFetch(string $query): array
{
    $env = mecabuddy_get_search_environment();
    $diag = [
        'search_provider_attempted' => 'duckduckgo',
        'url' => MECABUDDY_DDG_HTML_URL,
        'http_status' => 0,
        'curl_errno' => 0,
        'curl_error' => '',
        'body_length' => 0,
        'body_hint' => '',
        'dom_available' => $env['dom_available'],
        'dom_parse_success' => false,
        'raw_result_count' => 0,
        'error_code' => null,
    ];

    if (!function_exists('curl_init')) {
        $diag['error_code'] = 'curl_unavailable';
        error_log('[MecaBuddy][DDG] curl extension missing');

        return ['items' => [], 'diagnostic' => $diag];
    }

    if (!$env['dom_available']) {
        $diag['error_code'] = 'php_dom_extension_missing';
        error_log('[MecaBuddy][DDG] DOMDocument/DOMXPath unavailable');

        return ['items' => [], 'diagnostic' => $diag];
    }

    $ch = curl_init();
    if ($ch === false) {
        $diag['error_code'] = 'curl_init_failed';

        return ['items' => [], 'diagnostic' => $diag];
    }

    $isLocal = defined('APP_DEBUG') && APP_DEBUG;
    curl_setopt_array($ch, [
        CURLOPT_URL => MECABUDDY_DDG_HTML_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'q' => $query,
            'kl' => 'fr-fr',
            'ia' => 'web',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: ' . MECABUDDY_DDG_USER_AGENT,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: fr-FR,fr;q=0.9,en;q=0.8',
        ],
        CURLOPT_TIMEOUT => 12,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => !$isLocal,
        CURLOPT_SSL_VERIFYHOST => $isLocal ? 0 : 2,
    ]);

    $body = curl_exec($ch);
    $diag['http_status'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $diag['curl_errno'] = curl_errno($ch);
    $diag['curl_error'] = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        $diag['error_code'] = 'curl_error';
        error_log('[MecaBuddy][DDG] curl error: ' . $diag['curl_error']);

        return ['items' => [], 'diagnostic' => $diag];
    }

    $html = (string) $body;
    $diag['body_length'] = strlen($html);
    if (mecabuddy_web_search_debug_enabled()) {
        $diag['body_hint'] = mecabuddy_sanitize_body_hint($html, 300);
    }

    if ($diag['http_status'] < 200 || $diag['http_status'] >= 300) {
        $diag['error_code'] = 'http_error';
        error_log('[MecaBuddy][DDG] HTTP ' . $diag['http_status']);

        return ['items' => [], 'diagnostic' => $diag];
    }

    $block = mecabuddy_detect_ddg_block_page($html);
    if ($block !== null) {
        $diag['error_code'] = 'ddg_blocked_or_captcha';
        $diag['body_hint'] = mb_substr($block . ' — ' . ($diag['body_hint'] ?? ''), 0, 300);
        error_log('[MecaBuddy][DDG] ' . $block);

        return ['items' => [], 'diagnostic' => $diag];
    }

    $items = [];
    try {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $nodes = $xpath->query(
            '//div[contains(@class,"result") '
            . 'and not(contains(@class,"result--ad")) '
            . 'and not(contains(@class,"result--news--item")) '
            . 'and contains(@class,"web-result")]'
        );
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query(
                '//div[@id="links"]//div[contains(@class,"result") and not(contains(@class,"result--ad"))]'
            );
        }

        if ($nodes !== false && $nodes->length > 0) {
            $diag['dom_parse_success'] = true;
            $seen = [];
            foreach ($nodes as $node) {
                if (count($items) >= 5) {
                    break;
                }
                $linkNodes = $xpath->query(
                    './/a[@class="result__a" or contains(@class,"result__a")]',
                    $node
                );
                if ($linkNodes === false || $linkNodes->length === 0) {
                    continue;
                }
                $link = $linkNodes->item(0);
                if (!$link instanceof DOMElement) {
                    continue;
                }
                $title = trim($link->textContent);
                $rawUrl = trim($link->getAttribute('href'));
                $finalUrl = _mecabuddyResolveDuckDuckGoUrl($rawUrl);
                if ($finalUrl === '' || !str_starts_with($finalUrl, 'http') || $title === '') {
                    continue;
                }
                $urlKey = strtolower((string) (parse_url($finalUrl, PHP_URL_HOST) ?? $finalUrl));
                if (isset($seen[$urlKey])) {
                    continue;
                }
                $seen[$urlKey] = true;
                $snippetNodes = $xpath->query(
                    './/a[contains(@class,"result__snippet")]'
                    . ' | .//div[contains(@class,"result__snippet")]'
                    . ' | .//span[contains(@class,"result__snippet")]',
                    $node
                );
                $snippet = ($snippetNodes !== false && $snippetNodes->length > 0)
                    ? trim($snippetNodes->item(0)->textContent)
                    : '';
                $items[] = ['title' => $title, 'snippet' => $snippet, 'url' => $finalUrl];
            }
        }
    } catch (Throwable $e) {
        $diag['dom_parse_success'] = false;
        error_log('[MecaBuddy][DDG] DOM parse exception: ' . $e->getMessage());
    }

    if ($items === []) {
        $regexItems = _mecabuddyDuckDuckGoParseRegex($html);
        if ($regexItems !== []) {
            $items = $regexItems;
            $diag['dom_parse_success'] = true;
        } else {
            $diag['error_code'] = $diag['error_code'] ?? 'ddg_unparseable';
        }
    }

    $diag['raw_result_count'] = count($items);

    return ['items' => $items, 'diagnostic' => $diag];
}

/**
 * Une requête web : Serper puis DDG, avec diagnostic optionnel.
 *
 * @return array{results: array<int, array{title: string, snippet: string, url: string}>, diagnostic: array<string, mixed>}
 */
function _mecabuddyWebSearchOnceDetailed(string $query, ?array $vehicle = null): array
{
    $settings = getEffectiveSettings();
    $key = trim((string) ($settings['serper_api_key'] ?? ''));
    $diag = _mecabuddy_empty_search_diag($query, $key !== '' ? 'serper' : 'duckduckgo');
    $raw = [];

    if ($key !== '') {
        $serper = _mecabuddySerperFetch($query, $key);
        $diag = array_merge($diag, $serper['diagnostic']);
        $diag['query'] = $query;
        if ($serper['items'] !== []) {
            $raw = $serper['items'];
            $diag['search_provider_attempted'] = 'serper';
        } else {
            error_log('[MecaBuddy] Serper a échoué, fallback DuckDuckGo');
            $ddg = _mecabuddyDuckDuckGoFetch($query);
            $diag = array_merge($diag, $ddg['diagnostic']);
            $diag['query'] = $query;
            $diag['serper_fallback'] = true;
            $raw = $ddg['items'];
        }
    } else {
        $ddg = _mecabuddyDuckDuckGoFetch($query);
        $diag = array_merge($diag, $ddg['diagnostic']);
        $diag['query'] = $query;
        $raw = $ddg['items'];
    }

    $vehicleMeta = _mecabuddyFilterWebResultsByVehicleMeta($raw, $vehicle);
    $diag['after_vehicle_filter_count'] = count($vehicleMeta['kept']);
    $blackMeta = applyBlacklistWithMeta($vehicleMeta['kept']);
    $diag['after_blacklist_count'] = count($blackMeta['kept']);
    $diag['raw_result_count'] = count($raw);

    $rejected = array_merge($vehicleMeta['rejected'], $blackMeta['rejected']);
    $diag['rejected_samples'] = array_slice($rejected, 0, 3);
    $diag['final_count'] = count($blackMeta['kept']);
    $diag['provider_final'] = $blackMeta['kept'] !== [] ? ($diag['search_provider_attempted'] ?? 'duckduckgo') : 'none';

    return ['results' => $blackMeta['kept'], 'diagnostic' => $diag];
}

/**
 * @return array<int, array{title: string, snippet: string, url: string}>
 */
function _mecabuddyWebSearchOnce(string $query, ?array $vehicle = null): array
{
    return _mecabuddyWebSearchOnceDetailed($query, $vehicle)['results'];
}

/**
 * @return array<int, array{title: string, snippet: string, url: string}>
 */
function searchWebForContext(string $query, ?array $vehicle = null): array
{
    _mecabuddySearchProviderState('none');
    $GLOBALS['_mecabuddy_queries_run'] = [];
    $GLOBALS['_mecabuddy_query_details'] = [];

    if (!function_exists('curl_init')) {
        return [];
    }

    $cleanQuery = preg_replace(
        '/^(je\s+veux?\s+|je\s+voudrais?\s+|comment\s+|aide.moi\s+(à|a)\s+|dis.moi\s+comment\s+)/ui',
        '',
        trim($query)
    );
    $cleanQuery = trim((string) $cleanQuery);

    $vehiclePrefix = '';
    if ($vehicle !== null) {
        $vehiclePrefix = trim(
            ($vehicle['brand'] ?? '') . ' ' .
            ($vehicle['model'] ?? '')
        );
        if ($vehiclePrefix !== '') {
            $vehiclePrefix .= ' ';
        }
    }

    $queries = [
        $vehiclePrefix . $cleanQuery,
        $vehiclePrefix . $cleanQuery . ' forum',
        $vehiclePrefix . $cleanQuery . ' tutoriel',
    ];

    $searchResults = [];
    $seen = [];
    $queriesRun = [];
    $queryDetails = [];
    $settings = getEffectiveSettings();
    $provider = !empty($settings['serper_api_key']) ? 'serper' : 'duckduckgo';

    $debugSearch = mecabuddy_web_search_debug_enabled();

    foreach ($queries as $q) {
        if (count($searchResults) >= 3) {
            break;
        }

        $before = count($searchResults);
        $queriesRun[] = $q;

        $once = _mecabuddyWebSearchOnceDetailed($q, $vehicle);
        $cleaned = $once['results'];
        $diag = $once['diagnostic'];

        foreach ($cleaned as $r) {
            $host = parse_url((string) ($r['url'] ?? ''), PHP_URL_HOST) ?? '';
            $path = parse_url((string) ($r['url'] ?? ''), PHP_URL_PATH) ?? '';
            $key = $host . $path;
            if ($key === '' || isset($seen[$key])) {
                if ($debugSearch && isset($seen[$key])) {
                    $samples = $diag['rejected_samples'] ?? [];
                    if (count($samples) < 3) {
                        $samples[] = [
                            'title' => mb_substr((string) ($r['title'] ?? ''), 0, 80),
                            'url' => mb_substr((string) ($r['url'] ?? ''), 0, 120),
                            'reason' => 'duplicate',
                        ];
                        $diag['rejected_samples'] = $samples;
                    }
                }
                continue;
            }
            $seen[$key] = true;
            $searchResults[] = $r;
        }

        $added = count($searchResults) - $before;
        $entry = [
            'query' => $q,
            'added' => $added,
            'total' => count($searchResults),
        ];
        if ($debugSearch) {
            $entry = array_merge($entry, $diag);
            $entry['final_count'] = $added;
        }
        $queryDetails[] = $entry;
    }

    $GLOBALS['_mecabuddy_queries_run'] = $queriesRun;
    $GLOBALS['_mecabuddy_query_details'] = $queryDetails;

    if ($searchResults !== []) {
        _mecabuddySearchProviderState($provider);
    } else {
        _mecabuddySearchProviderState('none');
        error_log('[MecaBuddy] Aucune source fiable après ' . count($queriesRun) . ' requêtes : ' . $cleanQuery);
    }

    return $searchResults;
}

/**
 * Sonde recherche web (API dev / diagnostic) — ne journalise jamais de clé API.
 *
 * @return array<string, mixed>
 */
function mecabuddy_probe_web_search(string $query, ?array $vehicle = null): array
{
    $env = mecabuddy_get_search_environment();
    $settings = getEffectiveSettings();
    $serperConfigured = trim((string) ($settings['serper_api_key'] ?? '')) !== '';
    $attempted = $serperConfigured ? 'serper' : 'duckduckgo';

    $once = _mecabuddyWebSearchOnceDetailed($query, $vehicle);
    $results = $once['results'];
    $diag = $once['diagnostic'];
    $finalCount = count($results);

    $debug = [
        'curl_available' => $env['curl_available'],
        'dom_available' => $env['dom_available'],
        'serper_configured' => $env['serper_configured'],
        'search_provider_attempted' => $diag['search_provider_attempted'] ?? $attempted,
        'url' => $diag['url'] ?? ($attempted === 'serper'
            ? 'https://google.serper.dev/search'
            : MECABUDDY_DDG_HTML_URL),
        'http_status' => (int) ($diag['http_status'] ?? 0),
        'curl_errno' => (int) ($diag['curl_errno'] ?? 0),
        'curl_error' => (string) ($diag['curl_error'] ?? ''),
        'body_length' => (int) ($diag['body_length'] ?? 0),
        'dom_parse_success' => (bool) ($diag['dom_parse_success'] ?? false),
        'raw_result_count' => (int) ($diag['raw_result_count'] ?? 0),
        'after_blacklist_count' => (int) ($diag['after_blacklist_count'] ?? 0),
        'after_vehicle_filter_count' => (int) ($diag['after_vehicle_filter_count'] ?? 0),
        'final_count' => $finalCount,
        'provider_final' => (string) ($diag['provider_final'] ?? ($finalCount > 0 ? $attempted : 'none')),
        'rejected_samples' => $diag['rejected_samples'] ?? [],
        'error_code' => $diag['error_code'] ?? null,
        'ssl_mode' => (defined('APP_DEBUG') && APP_DEBUG) ? 'disabled (dev)' : 'enabled (prod)',
    ];

    if (!$success && !empty($diag['body_hint'])) {
        $debug['body_hint'] = mb_substr((string) $diag['body_hint'], 0, 300);
    }

    $warnings = [];
    if (!$env['curl_available']) {
        $warnings[] = 'Extension PHP curl requise pour la recherche web.';
    }
    if (!$env['dom_available'] && !$serperConfigured) {
        $warnings[] = 'L’extension PHP dom est requise pour DuckDuckGo HTML. Activez-la chez l’hébergeur ou configurez Serper.';
    }
    if (!$serperConfigured) {
        $warnings[] = 'Sans clé Serper, DuckDuckGo HTML est utilisé (fragile sur hébergement mutualisé). Serper est recommandé en production.';
    }
    if ($finalCount === 0 && ($diag['error_code'] ?? '') === 'ddg_blocked_or_captcha') {
        $warnings[] = 'DuckDuckGo HTML semble bloqué, non parsable ou indisponible sur cet hébergement. Configurez une clé Serper pour des sources fiables.';
    }

    $provider = (string) ($diag['provider_final'] ?? ($finalCount > 0 ? $attempted : 'none'));
    if ($finalCount > 0) {
        _mecabuddySearchProviderState($provider);
    }

    $success = $finalCount > 0;
    $error = null;
    if (!$success) {
        if (!$env['curl_available']) {
            $error = 'curl_unavailable';
        } elseif (!$env['dom_available'] && !$serperConfigured) {
            $error = 'php_dom_extension_missing';
        } elseif (($diag['error_code'] ?? '') === 'ddg_blocked_or_captcha') {
            $error = 'duckduckgo_unreachable_or_unparseable';
        } elseif ($attempted === 'serper' && $finalCount === 0) {
            $error = (string) ($diag['error_code'] ?? 'serper_empty_or_failed');
        } else {
            $error = (string) ($diag['error_code'] ?? 'duckduckgo_unreachable_or_unparseable');
        }
    }

    return [
        'success' => $success,
        'provider' => $success ? $provider : 'none',
        'query' => $query,
        'count' => $finalCount,
        'debug' => $debug,
        'warnings' => $warnings,
        'results' => array_slice($results, 0, 5),
        'error' => $error,
    ];
}

/**
 * @return array<int, array{title: string, snippet: string, url: string}>
 */
function _searchViaSerper(string $query, string $apiKey): array
{
    $out = [];
    $q = $query . ' site mécanique réparation tutoriel';
    $payload = json_encode([
        'q' => $q,
        'gl' => 'fr',
        'hl' => 'fr',
        'num' => 4,
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return $out;
    }

    $result = _mecabuddyCurl('https://google.serper.dev/search', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-KEY: ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $GLOBALS['_mecabuddy_last_serper_curl'] = [
        'http_code' => $result['http_code'],
        'error' => $result['error'],
        'body_length' => strlen($result['body']),
        'body_preview' => mb_substr($result['body'], 0, 500),
    ];

    if (!$result['ok']) {
        return $out;
    }

    $data = json_decode($result['body'], true);
    if (!is_array($data) || !isset($data['organic']) || !is_array($data['organic'])) {
        return $out;
    }

    $n = 0;
    foreach ($data['organic'] as $row) {
        if (!is_array($row) || $n >= 4) {
            break;
        }
        $title = isset($row['title']) && is_string($row['title']) ? $row['title'] : '';
        $snippet = isset($row['snippet']) && is_string($row['snippet']) ? $row['snippet'] : '';
        $url = '';
        if (isset($row['link']) && is_string($row['link'])) {
            $url = $row['link'];
        } elseif (isset($row['url']) && is_string($row['url'])) {
            $url = $row['url'];
        }
        if ($url === '' || !_mecabuddyIsValidHttpUrl($url)) {
            continue;
        }
        $out[] = [
            'title' => $title,
            'snippet' => $snippet,
            'url' => $url,
        ];
        $n++;
    }

    return $out;
}

/**
 * @return array<int, array{title: string, snippet: string, url: string}>
 */
function _searchViaDuckDuckGo(string $query): array
{
    return _mecabuddyDuckDuckGoFetch($query)['items'];
}

function _mecabuddyResolveDuckDuckGoUrl(string $href): string
{
    $href = trim($href);
    if ($href === '') {
        return '';
    }

    if (str_contains($href, 'uddg=')) {
        $query = parse_url($href, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            if (!empty($params['uddg']) && is_string($params['uddg'])) {
                return urldecode($params['uddg']);
            }
        }
    }

    return $href;
}

function _mecabuddyIsValidHttpUrl(string $url): bool
{
    return (bool) preg_match('#^https?://#i', $url);
}

/**
 * Extrait le contenu assistant d'une réponse Ollama /api/chat.
 * Fallback sur message.thinking si content vide (think:false ignoré par Ollama ancien).
 *
 * @param 'chat'|'tutorial' $context tutorial : JSON en fin de thinking ; chat : dernière ligne
 */
function _mecabuddyExtractOllamaContent(array $decoded, string $context = 'chat'): ?string
{
    $message = $decoded['message'] ?? null;
    if (!is_array($message)) {
        return null;
    }

    $content = isset($message['content']) && is_string($message['content'])
        ? $message['content']
        : '';
    if (trim($content) !== '') {
        return $content;
    }

    $thinking = $message['thinking'] ?? '';
    if (!is_string($thinking) || trim($thinking) === '') {
        return null;
    }

    if ($context === 'tutorial') {
        if (preg_match('/(\{[\s\S]*\})\s*$/u', $thinking, $m)) {
            error_log('[MecaBuddy] Contenu extrait depuis thinking (think:false ignoré)');

            return $m[1];
        }
        if (preg_match('/(\[[\s\S]*\])\s*$/u', $thinking, $m)) {
            error_log('[MecaBuddy] Contenu extrait depuis thinking (think:false ignoré)');

            return $m[1];
        }

        return null;
    }

    $lines = array_filter(explode("\n", trim($thinking)));
    $lastLine = end($lines);
    $fallback = is_string($lastLine) ? trim($lastLine) : '';
    if ($fallback === '') {
        return null;
    }

    error_log('[MecaBuddy][chat] Contenu extrait depuis thinking');

    return $fallback;
}

/**
 * Joint base_url et chat_path sans dupliquer /v1 ni /openai.
 */
function _mecabuddyBuildChatCompletionsUrl(string $baseUrl, string $chatPath): string
{
    $base = rtrim(trim($baseUrl), '/');
    $path = trim($chatPath);
    if ($path === '') {
        $path = '/v1/chat/completions';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    return $base . $path;
}

/**
 * Chemin chat/completions effectif pour openai_compatible (Gemini vs API OpenAI classique).
 */
function _mecabuddyResolveOpenAiCompatibleChatPath(array $provider): string
{
    $explicit = trim((string) ($provider['chat_path'] ?? ''));
    if ($explicit !== '') {
        return $explicit[0] === '/' ? $explicit : '/' . $explicit;
    }

    $baseUrl = strtolower(rtrim((string) ($provider['base_url'] ?? ''), '/'));
    if (str_contains($baseUrl, 'generativelanguage.googleapis.com')) {
        return '/chat/completions';
    }

    return '/v1/chat/completions';
}

/**
 * @param array<string, mixed>|null $provider
 */
function mecabuddy_is_gemini_provider(?array $provider): bool
{
    if ($provider === null || $provider === []) {
        return false;
    }

    $type = strtolower((string) ($provider['type'] ?? ''));
    if ($type !== 'openai_compatible') {
        return false;
    }

    $base = strtolower((string) ($provider['base_url'] ?? ''));
    if (str_contains($base, 'generativelanguage.googleapis.com')) {
        return true;
    }

    foreach (['model', 'id', 'name'] as $key) {
        $v = strtolower((string) ($provider[$key] ?? ''));
        if ($v !== '' && str_contains($v, 'gemini')) {
            return true;
        }
    }

    return false;
}

/**
 * Limites affichées pour le provider global Gemini (information Google AI Studio, pas compteur MecaBuddy).
 *
 * @return array{rpm: int, rpd: int, input_tpm: int, display_enabled: bool}
 */
function mecabuddy_get_gemini_provider_limits(): array
{
    $defaults = [
        'rpm' => 5,
        'rpd' => 20,
        'input_tpm' => 250000,
        'display_enabled' => true,
    ];

    if (!function_exists('getSettings')) {
        return $defaults;
    }

    $limits = getSettings()['provider_limits']['gemini'] ?? null;
    if (!is_array($limits)) {
        return $defaults;
    }

    return [
        'rpm' => max(1, (int) ($limits['rpm'] ?? $defaults['rpm'])),
        'rpd' => max(1, (int) ($limits['rpd'] ?? $defaults['rpd'])),
        'input_tpm' => max(1, (int) ($limits['input_tpm'] ?? $defaults['input_tpm'])),
        'display_enabled' => ($limits['display_enabled'] ?? true) !== false,
    ];
}

/**
 * Ligne bandeau « Gemini : … » si provider global Gemini actif (pas BYOK).
 */
function mecabuddy_gemini_limits_banner_line(?int $demoUserId = null): ?string
{
    if (!function_exists('getEffectiveLlmProvider')) {
        require_once __DIR__ . '/byok.php';
    }

    $meta = byok_effective_provider_meta($demoUserId);
    if (($meta['quota_bypass_allowed'] ?? false) === true) {
        return null;
    }

    $provider = getEffectiveLlmProvider($demoUserId);
    if (!mecabuddy_is_gemini_provider($provider)) {
        return null;
    }

    $limits = mecabuddy_get_gemini_provider_limits();
    if (!$limits['display_enabled']) {
        return null;
    }

    $tpm = number_format($limits['input_tpm'], 0, ',', ' ');

    return sprintf(
        'Gemini : %d req/min · %d req/jour · %s tokens entrée/min',
        $limits['rpm'],
        $limits['rpd'],
        $tpm
    );
}

/**
 * Message utilisateur pour échec LLM (sans clé API ni body brut).
 *
 * @param array<string, mixed>|string $error
 * @param array<string, mixed> $provider
 */
function mecabuddy_public_llm_error_message(array|string $error, array $provider): string
{
    $httpCode = 0;
    $curlError = '';
    $body = '';
    $code = '';

    if (is_array($error)) {
        $httpCode = (int) ($error['http'] ?? $error['http_code'] ?? $error['provider_status'] ?? 0);
        $curlError = trim((string) ($error['curl_error'] ?? ''));
        $body = (string) ($error['body'] ?? '');
        $code = trim((string) ($error['error'] ?? $error['code'] ?? ''));
    } else {
        $code = trim($error);
        if (preg_match('/^http_(\d{3})$/', $code, $m)) {
            $httpCode = (int) $m[1];
        }
    }

    $isGemini = mecabuddy_is_gemini_provider($provider);

    if ($code === 'empty_assistant' || $code === 'provider_empty_response') {
        return $isGemini
            ? 'Gemini a renvoyé une réponse vide. Réessayez ou changez de modèle.'
            : 'Le fournisseur IA a renvoyé une réponse vide. Réessayez.';
    }

    if ($curlError !== '') {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            return 'Le fournisseur IA ne répond pas assez vite. Réessayez dans quelques instants.'
                . ' (' . $curlError . ')';
        }

        return 'Le fournisseur IA ne répond pas assez vite. Réessayez dans quelques instants.';
    }

    if ($httpCode > 0) {
        if ($isGemini) {
            return match ($httpCode) {
                400 => 'Le fournisseur IA a refusé la requête. Le modèle ou un paramètre semble invalide.',
                401, 403 => 'La clé API Gemini semble invalide ou non autorisée. Vérifiez la configuration du provider.',
                429 => 'La limite d’utilisation Gemini est atteinte. Réessayez plus tard ou utilisez votre propre clé API dans Mon compte.',
                500, 502, 503 => 'Gemini semble temporairement indisponible. Réessayez dans quelques instants.',
                default => _mecabuddyInterpretLlmHttpError($httpCode, $body, ''),
            };
        }

        return _mecabuddyInterpretLlmHttpError($httpCode, $body, '');
    }

    if ($code !== '' && $code !== 'llm_provider_error') {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            return $code;
        }
    }

    return 'Le fournisseur IA a rencontré une erreur. Réessayez dans quelques instants.';
}

/**
 * @param array<string, mixed> $llmFailure
 * @param array<string, mixed> $provider
 * @return array{success: false, error: string, provider: string, provider_status: int, message: string}
 */
function mecabuddy_build_llm_provider_error_payload(array $llmFailure, array $provider): array
{
    $http = (int) ($llmFailure['http'] ?? $llmFailure['provider_status'] ?? 0);
    if ($http === 0 && isset($llmFailure['error']) && preg_match('/^http_(\d{3})$/', (string) $llmFailure['error'], $m)) {
        $http = (int) $m[1];
    }

    return [
        'success' => false,
        'error' => 'llm_provider_error',
        'provider' => mecabuddy_is_gemini_provider($provider) ? 'gemini' : (string) ($provider['type'] ?? 'llm'),
        'provider_status' => $http,
        'message' => mecabuddy_public_llm_error_message(
            array_merge($llmFailure, ['http' => $http, 'provider_status' => $http]),
            $provider
        ),
    ];
}

/**
 * @param array<string, mixed> $llmFailure
 */
function mecabuddy_llm_failure_http_status(array $llmFailure): int
{
    $http = (int) ($llmFailure['provider_status'] ?? $llmFailure['http'] ?? 0);
    if ($http >= 400 && $http < 600) {
        return $http;
    }

    return 502;
}

/**
 * @param array<string, mixed> $llmFailure
 */
function mecabuddy_is_llm_provider_transport_failure(array $llmFailure): bool
{
    $err = (string) ($llmFailure['error'] ?? '');
    if ($err === 'llm_provider_error') {
        return true;
    }
    if (isset($llmFailure['provider_status']) && (int) $llmFailure['provider_status'] >= 400) {
        return true;
    }
    if (preg_match('/^http_\d{3}$/', $err)) {
        return true;
    }

    return in_array($err, ['curl_unavailable', 'curl_error', 'timeout'], true);
}

/**
 * Message utilisateur pour erreurs HTTP LLM (sans exposer de secrets).
 */
function _mecabuddyInterpretLlmHttpError(int $httpCode, string $body, string $curlError = ''): string
{
    if ($curlError !== '') {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            return 'Erreur réseau : ' . $curlError;
        }

        return 'Le fournisseur IA ne répond pas assez vite. Réessayez dans quelques instants.';
    }

    $apiMessage = '';
    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        $err = $decoded['error'] ?? null;
        if (is_array($err)) {
            $apiMessage = trim((string) ($err['message'] ?? ''));
        } elseif (is_string($err)) {
            $apiMessage = trim($err);
        }
        if ($apiMessage === '' && isset($decoded['message']) && is_string($decoded['message'])) {
            $apiMessage = trim($decoded['message']);
        }
    }

    if (defined('APP_DEBUG') && APP_DEBUG && $apiMessage !== '') {
        return $apiMessage;
    }

    switch ($httpCode) {
        case 400:
            return 'Le fournisseur IA a refusé la requête. Le modèle ou un paramètre semble invalide.';
        case 401:
            return 'La clé API semble invalide ou manquante. Vérifiez la configuration du provider.';
        case 403:
            return 'La clé API semble non autorisée pour ce projet ou ce modèle.';
        case 429:
            return 'La limite d’utilisation du fournisseur IA est atteinte. Réessayez plus tard.';
        case 500:
        case 502:
        case 503:
            return 'Le fournisseur IA semble temporairement indisponible. Réessayez dans quelques instants.';
        case 404:
            return 'Endpoint introuvable (vérifiez base_url et chat_path).';
        default:
            if ($httpCode >= 400) {
                return 'Erreur fournisseur IA (HTTP ' . $httpCode . '). Réessayez.';
            }

            return 'Erreur de communication avec le fournisseur IA.';
    }
}

/**
 * Prépare URL, headers et corps JSON pour un appel chat LLM (Ollama / Mistral / OpenAI-compatible).
 *
 * @param array<int, array{role: string, content: string}> $messages
 * @param array<string, mixed> $bodyExtras champs additionnels du body (ex. format, stream, temperature)
 * @return array{url: string, headers: array<int, string>, body: string, type: string}|null
 */
function _mecabuddyPrepareLlmCall(array $provider, array $messages, array $bodyExtras = []): ?array
{
    $type = (string) ($provider['type'] ?? 'ollama');
    $model = trim((string) ($provider['model'] ?? ''));
    if ($model === '') {
        return null;
    }

    $apiKey = trim((string) ($provider['api_key'] ?? ''));

    if ($type === 'ollama') {
        $baseUrl = rtrim((string) ($provider['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            return null;
        }
        $url = $baseUrl . '/api/chat';
        $bodyArr = array_merge([
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
        ], $bodyExtras);
        $headers = ['Content-Type: application/json'];
    } elseif ($type === 'mistral') {
        $url = 'https://api.mistral.ai/v1/chat/completions';
        $bodyArr = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $bodyExtras);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
    } else {
        $baseUrl = (string) ($provider['base_url'] ?? '');
        if (trim($baseUrl) === '') {
            return null;
        }
        $path = _mecabuddyResolveOpenAiCompatibleChatPath($provider);
        $url = _mecabuddyBuildChatCompletionsUrl($baseUrl, $path);
        $bodyArr = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $bodyExtras);
        $headers = ['Content-Type: application/json'];
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        if (defined('APP_DEBUG') && APP_DEBUG) {
            error_log(
                '[MecaBuddy][llm] openai_compatible url=' . $url
                . ' chat_path=' . $path
                . ' model=' . $model
            );
        }
    }

    $body = json_encode($bodyArr, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return null;
    }

    return [
        'url' => $url,
        'headers' => $headers,
        'body' => $body,
        'type' => $type,
        'chat_path' => $type === 'openai_compatible' ? _mecabuddyResolveOpenAiCompatibleChatPath($provider) : null,
    ];
}

function shouldSearchWeb(string $message): bool
{
    $wordCount = str_word_count($message);
    if ($wordCount <= 3) {
        return false;
    }

    $greetings = ['bonjour', 'salut', 'merci', 'ok', 'oui', 'non', 'bonsoir'];
    $lower = mb_strtolower(trim($message));
    foreach ($greetings as $g) {
        if ($lower === $g || str_starts_with($lower, $g . ' ')) {
            return false;
        }
    }

    return true;
}

/**
 * @return array{reply: string, sources: array<int, array{title: string, url: string}>}
 */
function extractSourcesFromReply(string $reply): array
{
    $sources = [];
    $cleanReply = $reply;

    if (preg_match('/\[SOURCES\](.*?)\[\/SOURCES\]/s', $reply, $m)) {
        $json = trim($m[1]);
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $t = isset($item['title']) && is_string($item['title']) ? $item['title'] : '';
                $u = isset($item['url']) && is_string($item['url']) ? $item['url'] : '';
                if ($t !== '' || $u !== '') {
                    $sources[] = ['title' => $t, 'url' => $u];
                }
            }
        }
        $cleanReply = preg_replace('/\[SOURCES\].*?\[\/SOURCES\]/s', '', $reply);
    }

    if ($sources === []) {
        preg_match_all('/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/', $reply, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $sources[] = ['title' => $match[1], 'url' => $match[2]];
        }
    }

    if ($sources === []) {
        preg_match_all('/^(https?:\/\/\S+)$/m', $reply, $matches);
        foreach ($matches[1] as $url) {
            $sources[] = [
                'title' => parse_url($url, PHP_URL_HOST) ?: $url,
                'url' => $url,
            ];
            $cleanReply = str_replace($url, '', $cleanReply);
        }
    }

    return [
        'reply' => trim((string) $cleanReply),
        'sources' => array_slice($sources, 0, 5),
    ];
}

/**
 * @param array<int, array{title: string, snippet: string, url: string}> $searchResults
 */
function formatContextForPrompt(array $searchResults): string
{
    if ($searchResults === []) {
        return '';
    }

    $lines = [
        'Voici des informations issues d\'une recherche web sur ce sujet :',
        '',
    ];

    $i = 1;
    foreach ($searchResults as $row) {
        if (!is_array($row)) {
            continue;
        }
        $title = (string) ($row['title'] ?? '');
        $snippet = (string) ($row['snippet'] ?? '');
        $url = (string) ($row['url'] ?? '');
        $lines[] = '[' . $i . '] ' . $title;
        $lines[] = $snippet;
        $lines[] = 'Source : ' . $url;
        $lines[] = '';
        $i++;
    }

    return rtrim(implode("\n", $lines));
}

/**
 * @param array<int, array{role: string, content: string}> $history
 * @param array<string, mixed> $provider
 * @return array{
 *   success: bool,
 *   reply: string,
 *   sources: array<int, array{title: string, url: string}>,
 *   sources_type?: 'llm_cited'|'serper_fallback',
 *   search_provider?: 'serper'|'duckduckgo'|'none',
 *   web_searched: bool,
 *   provider_used?: string,
 *   error?: string
 * }
 */
function callLlmChat(array $history, string $userMessage, array $provider, ?array $vehicle = null): array
{
    $fail = static function (string $error): array {
        return [
            'success' => false,
            'reply' => 'Je rencontre un problème technique. Réessayez dans un instant.',
            'sources' => [],
            'web_searched' => false,
            'error' => $error,
        ];
    };

    try {
        $model = (string) ($provider['model'] ?? '');
        $type = (string) ($provider['type'] ?? 'ollama');
        $baseUrl = rtrim((string) ($provider['base_url'] ?? ''), '/');

        if ($model === '') {
            return $fail('invalid_provider');
        }
        if ($type !== 'mistral' && $baseUrl === '') {
            return $fail('invalid_provider');
        }

        $results = [];
        $augmentedMessage = $userMessage;
        $isFailsafe = false;

        $searchProvider = 'none';
        if (shouldSearchWeb($userMessage)) {
            $results = searchWebForContext($userMessage, $vehicle);
            $searchProvider = getLastSearchProvider();
            $isFailsafe = empty($results);

            if ($isFailsafe) {
                $augmentedMessage = $userMessage
                    . "\n\n[AUCUNE SOURCE WEB DISPONIBLE — réponds depuis tes connaissances. "
                    . "Pour les valeurs critiques (couples, volumes, références), "
                    . "indique explicitement 'à vérifier dans le manuel'.] ";
            } elseif ($results !== []) {
                $context = formatContextForPrompt($results);
                $augmentedMessage = $userMessage . "\n\n" . $context;
            }
        }

        $systemPrompt = buildSystemPrompt($vehicle);
        $messages = [];

        $ollamaAsUser = ($type === 'ollama')
            && (($provider['ollama_system_as_user_message'] ?? false) === true);

        if ($ollamaAsUser) {
            $messages[] = [
                'role' => 'user',
                'content' => "[Instructions système]\n\n" . $systemPrompt,
            ];
        } else {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        $hist = array_values($history);
        $max = count($hist);
        if ($max > 10) {
            $hist = array_slice($hist, -10);
        }
        foreach ($hist as $turn) {
            if (!is_array($turn)) {
                continue;
            }
            $role = (string) ($turn['role'] ?? '');
            $content = (string) ($turn['content'] ?? '');
            if (($role !== 'user' && $role !== 'assistant') || $content === '') {
                continue;
            }
            $messages[] = ['role' => $role, 'content' => $content];
        }

        $messages[] = ['role' => 'user', 'content' => $augmentedMessage];

        if (!function_exists('curl_init')) {
            return $fail('curl_unavailable');
        }

        $bodyExtras = [];
        if ($type === 'ollama') {
            $bodyExtras = [
                'think' => false,
                'options' => [
                    'num_predict' => 4096,
                    'temperature' => 0.7,
                ],
            ];
        } elseif ($type === 'mistral' || $type === 'openai_compatible') {
            $bodyExtras = ['temperature' => 0.3];
        }

        $prepared = _mecabuddyPrepareLlmCall($provider, $messages, $bodyExtras);
        if ($prepared === null) {
            return $fail('encode_failed');
        }

        $curlResult = _mecabuddyCurl($prepared['url'], [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $prepared['body'],
            CURLOPT_HTTPHEADER => $prepared['headers'],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        if (!$curlResult['ok']) {
            $httpCode = (int) $curlResult['http_code'];

            return array_merge($fail('llm_provider_error'), [
                'error' => 'llm_provider_error',
                'http' => $httpCode,
                'provider_status' => $httpCode,
                'message' => mecabuddy_public_llm_error_message([
                    'http' => $httpCode,
                    'curl_error' => $curlResult['error'],
                    'body' => $curlResult['body'],
                    'error' => 'llm_provider_error',
                ], $provider),
            ]);
        }

        $decoded = json_decode($curlResult['body'], true);
        if (!is_array($decoded)) {
            return $fail('invalid_llm_json');
        }

        $content = null;
        if ($type === 'ollama') {
            $content = _mecabuddyExtractOllamaContent($decoded, 'chat');
        } else {
            $choices = $decoded['choices'] ?? null;
            if (is_array($choices) && isset($choices[0]) && is_array($choices[0])) {
                $msg = $choices[0]['message'] ?? null;
                if (is_array($msg) && isset($msg['content']) && is_string($msg['content'])) {
                    $content = $msg['content'];
                }
            }
        }

        if ($content === null || trim($content) === '') {
            return array_merge($fail('empty_assistant'), [
                'error' => 'empty_assistant',
                'message' => mecabuddy_public_llm_error_message('empty_assistant', $provider),
            ]);
        }

        $parsed = extractSourcesFromReply($content);
        $reply = $parsed['reply'];
        $sources = $parsed['sources'];
        $sourcesType = $sources !== [] ? 'llm_cited' : null;

        if ($sources === [] && $results !== []) {
            $fallbackSources = array_map(
                static fn (array $r): array => [
                    'title' => (string) ($r['title'] ?? ''),
                    'url' => (string) ($r['url'] ?? ''),
                ],
                array_slice($results, 0, 3)
            );
            $sources = array_values(array_filter(
                $fallbackSources,
                static fn (array $s): bool => ($s['url'] ?? '') !== ''
            ));
            if ($sources !== []) {
                $sourcesType = 'serper_fallback';
            }
        }

        $providerUsed = '';
        if (!empty($provider['name']) && is_string($provider['name'])) {
            $providerUsed = $provider['name'];
        } elseif ($model !== '') {
            $providerUsed = $model;
        }

        $out = [
            'success' => true,
            'reply' => $reply,
            'sources' => $sources,
            'web_searched' => $results !== [],
            'failsafe' => $isFailsafe,
            'search_provider' => $results !== [] ? $searchProvider : 'none',
            'provider_used' => $providerUsed,
            'queries_run' => mecabuddy_get_search_queries_run(),
            'query_details' => mecabuddy_get_search_query_details(),
        ];
        if ($sourcesType !== null) {
            $out['sources_type'] = $sourcesType;
        }

        return $out;
    } catch (Throwable $e) {
        return [
            'success' => false,
            'reply' => 'Je rencontre un problème technique. Réessayez dans un instant.',
            'sources' => [],
            'web_searched' => false,
            'error' => $e->getMessage(),
        ];
    }
}
