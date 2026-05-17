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
Tu es Buddy, un assistant expert en mécanique automobile et moto.
Tu réponds UNIQUEMENT aux questions relatives à : mécanique, entretien, réparation,
diagnostic de pannes, pièces détachées, pneumatiques, carrosserie, électronique
embarquée — pour voitures et motos uniquement.
Si la question ne concerne pas ces sujets, réponds poliment en une phrase que tu
n'es pas en mesure d'aider sur ce sujet, et invite l'utilisateur à poser
une question mécanique.
Quand tu utilises des informations issues d'une recherche web, tu DOIS citer tes sources
à la fin de ta réponse en utilisant EXACTEMENT ce format, sans exception :
[SOURCES]
[{"title":"titre de la page","url":"https://..."}]
[/SOURCES]
Si tu n'as pas utilisé de sources web, n'inclus pas ce bloc.
Réponds toujours en français. Sois précis, concis, pédagogique.
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
 * Une requête web unique (Serper ou DDG).
 *
 * @return array<int, array{title: string, snippet: string, url: string}>
 */
function _mecabuddyWebSearchOnce(string $query, ?array $vehicle = null): array
{
    if (!function_exists('curl_init')) {
        return [];
    }

    $settings = getEffectiveSettings();
    $key = trim((string) ($settings['serper_api_key'] ?? ''));

    if ($key !== '') {
        $raw = _searchViaSerper($query, $key);
        if ($raw !== []) {
            return _mecabuddyFilterWebResultsByVehicle($raw, $vehicle);
        }
        error_log('[MecaBuddy] Serper a échoué, fallback DuckDuckGo');
    }

    $raw = _searchViaDuckDuckGo($query);
    if ($raw !== []) {
        return _mecabuddyFilterWebResultsByVehicle($raw, $vehicle);
    }

    return [];
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

    foreach ($queries as $q) {
        if (count($searchResults) >= 3) {
            break;
        }

        $before = count($searchResults);
        $queriesRun[] = $q;

        $raw = _mecabuddyWebSearchOnce($q, $vehicle);
        $cleaned = applyBlacklist($raw);

        foreach ($cleaned as $r) {
            $host = parse_url((string) ($r['url'] ?? ''), PHP_URL_HOST) ?? '';
            $path = parse_url((string) ($r['url'] ?? ''), PHP_URL_PATH) ?? '';
            $key = $host . $path;
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $searchResults[] = $r;
        }

        $queryDetails[] = [
            'query' => $q,
            'added' => count($searchResults) - $before,
            'total' => count($searchResults),
        ];
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
    try {
        if (!function_exists('curl_init') || !class_exists('DOMDocument')) {
            return [];
        }

        $result = _mecabuddyCurl('https://html.duckduckgo.com/html/', [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'q' => $query,
                'kl' => 'fr-fr',
                'ia' => 'web',
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: Mozilla/5.0 (compatible; MecaBuddy/1.0)',
                'Accept: text/html,application/xhtml+xml',
                'Accept-Language: fr-FR,fr;q=0.9',
            ],
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        if (!$result['ok']) {
            error_log('[MecaBuddy][DDG] curl error: ' . $result['error']
                . ' HTTP: ' . $result['http_code']);
            return [];
        }

        $html = $result['body'];
        if ($html === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $items = [];

        $nodes = $xpath->query(
            '//div[contains(@class,"result") '
            . 'and not(contains(@class,"result--ad")) '
            . 'and not(contains(@class,"result--news--item")) '
            . 'and contains(@class,"web-result")]'
        );

        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query(
                '//div[@id="links"]//div[contains(@class,"result") '
                . 'and not(contains(@class,"result--ad"))]'
            );
        }

        if ($nodes === false || $nodes->length === 0) {
            return [];
        }

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

            $finalUrl = $rawUrl;
            if (str_contains($rawUrl, 'uddg=')) {
                $queryString = parse_url($rawUrl, PHP_URL_QUERY);
                parse_str(is_string($queryString) ? $queryString : '', $params);
                $finalUrl = urldecode((string) ($params['uddg'] ?? $rawUrl));
            } elseif (str_contains($rawUrl, 'y.js')) {
                $queryString = parse_url($rawUrl, PHP_URL_QUERY);
                parse_str(is_string($queryString) ? $queryString : '', $params);
                if (!empty($params['u3'])) {
                    continue;
                }
                continue;
            }

            if (!str_starts_with($finalUrl, 'http')) {
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

            if ($title === '' || $finalUrl === '') {
                continue;
            }

            $items[] = [
                'title' => $title,
                'snippet' => $snippet,
                'url' => $finalUrl,
            ];
        }

        return $items;
    } catch (Throwable $e) {
        return [];
    }
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
 * Message utilisateur pour erreurs HTTP LLM (sans exposer de secrets).
 */
function _mecabuddyInterpretLlmHttpError(int $httpCode, string $body, string $curlError = ''): string
{
    if ($curlError !== '') {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            return 'Erreur réseau : ' . $curlError;
        }

        return 'Délai dépassé ou erreur réseau. Réessayez.';
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

    switch ($httpCode) {
        case 401:
            return $apiMessage !== ''
                ? 'Clé API invalide ou manquante : ' . $apiMessage
                : 'Clé API invalide ou manquante.';
        case 403:
            return $apiMessage !== ''
                ? 'Clé API non autorisée : ' . $apiMessage
                : 'Clé API non autorisée.';
        case 429:
            return 'Quota ou limite de débit atteint (Google AI Studio / API). Réessayez plus tard.';
        case 400:
            return $apiMessage !== ''
                ? $apiMessage
                : 'Requête invalide (modèle ou paramètre non supporté).';
        case 404:
            return $apiMessage !== ''
                ? 'Endpoint introuvable : ' . $apiMessage
                : 'Endpoint introuvable (vérifiez base_url et chat_path).';
        default:
            if ($apiMessage !== '') {
                return $apiMessage;
            }

            return 'http_' . $httpCode;
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
            $err = _mecabuddyInterpretLlmHttpError(
                $curlResult['http_code'],
                $curlResult['body'],
                $curlResult['error']
            );
            return $fail($err);
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
            return $fail('empty_assistant');
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
