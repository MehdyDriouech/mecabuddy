<?php
/**
 * MecaBuddy — Génération de tutoriels structurés via LLM
 */

require_once __DIR__ . '/search_helpers.php';

if (!function_exists('buildTutorialSystemPrompt')) {

    function buildTutorialSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un expert mécanicien automobile et moto. Tu génères des tutoriels de réparation
et d'entretien précis, structurés, et adaptés au véhicule si celui-ci est fourni.
Tu réponds UNIQUEMENT avec un objet JSON valide, sans texte avant ni après,
sans backticks, sans markdown. La structure JSON est OBLIGATOIREMENT :
{
  "title": "Titre court du tutoriel",
  "description": "Description générale en 1-2 phrases",
  "difficulty": "facile|moyen|difficile|expert",
  "estimated_time": <nombre entier de minutes>,
  "tools_required": ["outil1", "outil2"],
  "parts_required": ["pièce1", "pièce2"],
  "danger_level": "none|low|medium|high",
  "global_warnings": ["avertissement1"],
  "steps": [
    {
      "step": 1,
      "title": "Titre de l'étape",
      "description": "Instructions détaillées",
      "warning": "Avertissement spécifique ou null",
      "tip": "Conseil pratique ou null"
    }
  ]
}
Si une information est inconnue, utilise null (pas de chaîne vide).
Pour les tableaux vides, utilise [].

Règles de précision technique (NON NÉGOCIABLES) :
- Les valeurs numériques incertaines (poids, couples, dimensions) doivent indiquer
  "voir manuel constructeur" plutôt qu'une approximation.
- Si le véhicule nécessite de déposer des pièces d'accès (carénages, selles, réservoirs,
  protections) AVANT d'atteindre la pièce concernée, ces déposés sont des étapes
  numérotées à part entière, pas des parenthèses dans une autre étape.
- Les étapes doivent être dans l'ordre chronologique exact d'intervention.
- Distingue clairement les couples de serrage au retour de montage.
- Pour les motos : spécifie toujours si le travail nécessite une béquille centrale
  ou un paddock stand.
PROMPT;
    }

    /**
     * @param list<array<string, mixed>> $lists
     * @return list<array<string, mixed>>
     */
    function tutorial_merge_search_results(array ...$lists): array
    {
        $seen = [];
        $merged = [];
        foreach ($lists as $list) {
            foreach ($list as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $url = trim((string) ($row['url'] ?? ''));
                if ($url === '' || isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;
                $merged[] = $row;
            }
        }

        return $merged;
    }

    function tutorial_clean_action_type(string $actionType): string
    {
        $cleanAction = preg_replace(
            '/^(je\s+veux?\s+|je\s+voudrais?\s+|comment\s+|aide.moi\s+(à|a)\s+|dis.moi\s+comment\s+)/ui',
            '',
            trim($actionType)
        );
        $cleanAction = preg_replace(
            '/\s+(de\s+ma\s+moto|de\s+ma\s+voiture|de\s+mon\s+véhicule|de\s+mon\s+vehicule)$/ui',
            '',
            (string) $cleanAction
        );

        return trim((string) $cleanAction);
    }

    /**
     * @param list<array<string, mixed>> $searchResults
     * @return list<array<string, mixed>>
     */
    function tutorial_filter_search_results(array $searchResults): array
    {
        return applyBlacklist($searchResults);
    }

    function tutorial_get_last_web_search_query(): ?string
    {
        return $GLOBALS['_tutorial_last_search_query'] ?? null;
    }

    /**
     * @return list<string>
     */
    function tutorial_get_queries_run(): array
    {
        return $GLOBALS['_tutorial_queries_run'] ?? [];
    }

    /**
     * @return list<array{query: string, added: int, total: int}>
     */
    function tutorial_get_query_details(): array
    {
        return $GLOBALS['_tutorial_query_details'] ?? [];
    }

    function tutorial_get_is_failsafe(): bool
    {
        return ($GLOBALS['_tutorial_is_failsafe'] ?? false) === true;
    }

    /**
     * Recherche web en cascade (jusqu'à 3 requêtes, arrêt dès 3 sources fiables).
     *
     * @return array{
     *   results: list<array<string, mixed>>,
     *   queries_run: list<string>,
     *   query_details: list<array{query: string, added: int, total: int}>,
     *   is_failsafe: bool
     * }
     */
    function tutorial_run_web_search(string $actionType, ?array $vehicle): array
    {
        $empty = [
            'results' => [],
            'queries_run' => [],
            'query_details' => [],
            'is_failsafe' => true,
        ];

        if (!function_exists('searchWebForContext')) {
            if (!defined('LLM_CHAT_LOADED')) {
                require_once __DIR__ . '/llm_chat.php';
            }
        }
        if (!function_exists('searchWebForContext')) {
            return $empty;
        }

        $cleanAction = tutorial_clean_action_type($actionType);
        $searchResults = searchWebForContext($cleanAction, $vehicle);
        $queriesRun = mecabuddy_get_search_queries_run();
        $queryDetails = mecabuddy_get_search_query_details();
        $isFailsafe = count($searchResults) === 0;

        if ($isFailsafe) {
            error_log(
                '[MecaBuddy] Aucune source fiable trouvée après '
                . count($queriesRun) . ' requêtes — mode LLM failsafe'
            );
        }

        return [
            'results' => $searchResults,
            'queries_run' => $queriesRun,
            'query_details' => $queryDetails,
            'is_failsafe' => $isFailsafe,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    function tutorial_fetch_web_context(string $actionType, ?array $vehicle): array
    {
        $meta = tutorial_run_web_search($actionType, $vehicle);

        $GLOBALS['_tutorial_last_search_query'] = $meta['queries_run'][0] ?? null;
        $GLOBALS['_tutorial_queries_run'] = $meta['queries_run'];
        $GLOBALS['_tutorial_query_details'] = $meta['query_details'];
        $GLOBALS['_tutorial_is_failsafe'] = $meta['is_failsafe'];

        return $meta['results'];
    }

    /**
     * @param list<array<string, mixed>> $searchResults
     */
    function tutorial_build_web_context_block(array $searchResults): string
    {
        if ($searchResults === []) {
            return '';
        }

        $webContext = "\n\nINFORMATIONS ISSUES DE RECHERCHES WEB (utilise-les pour "
            . "être précis sur les étapes spécifiques à ce véhicule) :\n";
        foreach (array_slice($searchResults, 0, 4) as $i => $r) {
            $snippet = mb_substr((string) ($r['snippet'] ?? ''), 0, 300);
            $webContext .= "\n[" . ($i + 1) . '] ' . ($r['title'] ?? '') . "\n"
                . $snippet . "\n"
                . 'Source : ' . ($r['url'] ?? '') . "\n";
        }
        $webContext .= "\nSi ces sources donnent des étapes spécifiques (ex: dépose "
            . "de carénages, selle, réservoirs), inclus-les dans ton tutoriel.";
        if (mb_strlen($webContext) > 1500) {
            $webContext = mb_substr($webContext, 0, 1500) . "\n[...tronqué]";
        }

        return $webContext;
    }

    /**
     * @param array<string, mixed>|null $vehicle
     */
    function tutorial_build_vehicle_description(?array $vehicle): ?string
    {
        if ($vehicle === null) {
            return null;
        }
        $desc = trim(
            ($vehicle['brand'] ?? '') . ' '
            . ($vehicle['model'] ?? '') . ' '
            . ($vehicle['year'] ?? '')
            . (!empty($vehicle['engine_type']) ? ' ' . $vehicle['engine_type'] : '')
            . (!empty($vehicle['transmission']) ? ' boîte ' . $vehicle['transmission'] : '')
        );

        return $desc !== '' ? $desc : null;
    }

    /**
     * @param array<string, mixed>|null $vehicle
     * @param list<array<string, mixed>> $searchResults
     */
    function tutorial_build_user_message(
        string $actionType,
        ?array $vehicle,
        array $searchResults,
        bool $isFailsafe = false
    ): string {
        $vehicleDesc = tutorial_build_vehicle_description($vehicle);
        $webContext = $isFailsafe ? '' : tutorial_build_web_context_block($searchResults);
        $vehicleCtx = $vehicleDesc !== null
            ? "pour un {$vehicleDesc}"
            : '(véhicule non spécifié, instructions génériques)';

        $failsafeNotice = $isFailsafe
            ? "\n\nAUCUNE SOURCE WEB DISPONIBLE. Génère le tutoriel depuis tes connaissances "
            . "en étant explicite sur les points incertains (couples de serrage, volumes) : "
            . "indique 'voir manuel constructeur' plutôt qu'une valeur inventée."
            : '';

        return 'Génère un tutoriel complet et précis étape par étape '
            . "pour : {$actionType} {$vehicleCtx}.{$webContext}{$failsafeNotice}\n\n"
            . 'IMPORTANT : sois spécifique à ce modèle. Si des pièces de carrosserie, '
            . 'carénages, selles ou éléments doivent être retirés pour accéder à la '
            . 'pièce concernée, indique-les comme étapes distinctes. '
            . "Ne donne pas de valeurs génériques (poids, couples de serrage, "
            . "intervalles) si tu n'en es pas certain — indique 'voir manuel constructeur'.";
    }

    /**
     * @param list<array<string, mixed>> $searchResults
     * @return list<array{title: string, url: string}>
     */
    function tutorial_format_sources_for_response(array $searchResults): array
    {
        $out = [];
        foreach (array_slice($searchResults, 0, 4) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $url = trim((string) ($r['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $out[] = [
                'title' => trim((string) ($r['title'] ?? $url)),
                'url' => $url,
            ];
        }

        return $out;
    }

    function tutorial_normalize_json_layout(string $json): string
    {
        $json = preg_replace('/:\s*\n+\s*(\d)/', ': $1', $json);
        $json = preg_replace('/:\s*\n+\s*"/', ': "', $json);
        $json = preg_replace('/:\s*\n+\s*\[/', ': [', $json);
        $json = preg_replace('/:\s*\n+\s*\{/', ': {', $json);
        $json = preg_replace('/,\s*\n+\s*"/', ', "', $json);
        $json = preg_replace('/\t/', ' ', $json);

        return (string) $json;
    }

    function tutorial_strip_llm_json_pollution(string $raw): string
    {
        $cleaned = $raw;
        $cleaned = preg_replace('/eslint-style:[^"}\]]+/i', '', $cleaned);
        $cleaned = preg_replace('/\[MOTO\][^"}\]]*/', '', $cleaned);
        $cleaned = preg_replace('/\[VOITURE\][^"}\]]*/', '', $cleaned);
        $cleaned = preg_replace('/(true;\s*){3,}/', '', $cleaned);

        return (string) $cleaned;
    }

    function tutorial_repair_truncated_json(string $json): string
    {
        $repaired = preg_replace('/,\s*([\}\]])/', '$1', $json);
        $repaired = trim((string) $repaired);

        if ($repaired !== '' && !str_ends_with($repaired, '}') && !str_ends_with($repaired, ']')) {
            $depth = 0;
            $inStr = false;
            $len = strlen($repaired);
            for ($i = 0; $i < $len; $i++) {
                $c = $repaired[$i];
                if ($c === '"' && ($i === 0 || $repaired[$i - 1] !== '\\')) {
                    $inStr = !$inStr;
                }
                if (!$inStr) {
                    if ($c === '{' || $c === '[') {
                        $depth++;
                    } elseif ($c === '}' || $c === ']') {
                        $depth--;
                    }
                }
            }
            while ($depth > 0) {
                $repaired .= '}';
                $depth--;
            }
        }

        return $repaired;
    }

    /**
     * @return array<string, mixed>|null
     */
    function tutorial_prepare_llm_json_for_decode(string $rawContent): ?string
    {
        $cleaned = tutorial_strip_llm_json_pollution($rawContent);
        $cleaned = trim($cleaned);
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/i', '', $cleaned);
        $cleaned = trim((string) $cleaned);

        if ($cleaned !== '' && !str_starts_with($cleaned, '{') && !str_starts_with($cleaned, '[')) {
            if (preg_match('/\{[\s\S]*/u', $cleaned, $matches)) {
                $cleaned = $matches[0];
            }
        }

        $cleaned = tutorial_normalize_json_layout($cleaned);

        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    function tutorial_try_decode_llm_json(string $cleaned): ?array
    {
        $parsed = json_decode($cleaned, true);
        if (is_array($parsed)) {
            return $parsed;
        }

        $repaired = tutorial_normalize_json_layout(tutorial_repair_truncated_json($cleaned));
        $parsed = json_decode($repaired, true);

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * @param array<string, mixed> $parsed
     */
    function tutorial_is_missing_steps_only(array $parsed): bool
    {
        $title = $parsed['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            return false;
        }
        $steps = $parsed['steps'] ?? null;

        return !is_array($steps) || $steps === [];
    }

    /**
     * @param array{success?: bool, error?: string, partial?: array<string, mixed>} $parseResult
     */
    function tutorial_needs_steps_pass2(array $parseResult): bool
    {
        if (($parseResult['success'] ?? false) === true) {
            return false;
        }
        $error = (string) ($parseResult['error'] ?? '');
        if (!in_array($error, ['json_parse_failed', 'json_structure_invalid'], true)) {
            return false;
        }
        $partial = $parseResult['partial'] ?? null;

        return is_array($partial) && tutorial_is_missing_steps_only($partial);
    }

    /**
     * @return array{success: true, steps: list<array<string, mixed>>}|array{success: false, error: string, raw?: string}
     */
    function tutorial_fetch_llm_steps(
        array $provider,
        string $title,
        ?callable $onProgress = null
    ): array {
        $type = (string) ($provider['type'] ?? 'ollama');
        $model = (string) ($provider['model'] ?? '');
        if ($model === '' || !function_exists('curl_init')) {
            return ['success' => false, 'error' => 'invalid_provider'];
        }

        $stepsPrompt = "Tu génères la suite d'un tutoriel JSON déjà commencé.\n"
            . "Titre : {$title}\n"
            . "Génère UNIQUEMENT le tableau JSON 'steps' (sans aucun autre champ), "
            . "format strict :\n"
            . '[{"step":1,"title":"...","description":"...","warning":null,"tip":null}, ...]' . "\n"
            . 'Réponds UNIQUEMENT avec le tableau JSON, rien d\'autre.';

        $stepsMessages = [
            ['role' => 'system', 'content' => 'Tu réponds uniquement en JSON valide.'],
            ['role' => 'user', 'content' => $stepsPrompt],
        ];

        if ($type === 'ollama') {
            $bodyExtras = [
                'format' => 'json',
                'think' => false,
                'options' => [
                    'num_predict' => 7500,
                    'temperature' => 0.2,
                    'num_ctx' => 8192,
                ],
            ];
        } else {
            $bodyExtras = [
                'max_tokens' => 7500,
                'temperature' => 0.2,
            ];
        }

        $prepared = _mecabuddyPrepareLlmCall($provider, $stepsMessages, $bodyExtras);
        if ($prepared === null) {
            return ['success' => false, 'error' => 'encode_failed'];
        }

        $curlOptions = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $prepared['body'],
            CURLOPT_HTTPHEADER => $prepared['headers'],
            CURLOPT_TIMEOUT => 300,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        if ($onProgress !== null) {
            $curlOptions[CURLOPT_NOPROGRESS] = false;
            $curlOptions[CURLOPT_PROGRESSFUNCTION] = static function (
                $resource,
                $downloadSize,
                $downloaded,
                $uploadSize,
                $uploaded
            ) use ($onProgress): int {
                $onProgress();

                return 0;
            };
        }

        $curlResult = _mecabuddyCurl($prepared['url'], $curlOptions);
        if (!$curlResult['ok']) {
            $err = $curlResult['error'] !== ''
                ? $curlResult['error']
                : 'http_' . $curlResult['http_code'];

            return ['success' => false, 'error' => $err];
        }

        $decoded = json_decode($curlResult['body'], true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'invalid_http_json'];
        }

        $stepsContent = null;
        if ($type === 'ollama') {
            $stepsContent = _mecabuddyExtractOllamaContent($decoded, 'tutorial');
        } else {
            $choices = $decoded['choices'] ?? null;
            if (is_array($choices) && isset($choices[0]) && is_array($choices[0])) {
                $msg = $choices[0]['message'] ?? null;
                if (is_array($msg) && isset($msg['content']) && is_string($msg['content'])) {
                    $stepsContent = $msg['content'];
                }
            }
        }

        if ($stepsContent === null || trim($stepsContent) === '') {
            return ['success' => false, 'error' => 'empty_assistant'];
        }

        $preparedJson = tutorial_prepare_llm_json_for_decode($stepsContent);
        if ($preparedJson === null) {
            return [
                'success' => false,
                'error' => 'steps_generation_failed',
                'raw' => mb_substr($stepsContent, 0, 300),
            ];
        }

        $stepsJson = tutorial_try_decode_llm_json($preparedJson);
        if (isset($stepsJson['steps']) && is_array($stepsJson['steps'])) {
            $stepsJson = $stepsJson['steps'];
        }

        if (!is_array($stepsJson) || $stepsJson === [] || !isset($stepsJson[0])) {
            return [
                'success' => false,
                'error' => 'steps_generation_failed',
                'raw' => mb_substr($stepsContent, 0, 300),
            ];
        }

        return ['success' => true, 'steps' => $stepsJson];
    }

    /**
     * Parse et valide le JSON tutoriel renvoyé par le LLM.
     *
     * @return array{success: true, tutorial: array<string, mixed>}|array{success: false, error: string, raw?: string, keys_present?: list<string>, partial?: array<string, mixed>}
     */
    function tutorial_parse_llm_tutorial_json(string $rawContent): array
    {
        $prepared = tutorial_prepare_llm_json_for_decode($rawContent);
        if ($prepared === null) {
            return [
                'success' => false,
                'error' => 'json_parse_failed',
                'raw' => mb_substr($rawContent, 0, 500),
            ];
        }

        $parsed = tutorial_try_decode_llm_json($prepared);

        if (!is_array($parsed)) {
            error_log('[MecaBuddy] json_parse_failed. JSON error: ' . json_last_error_msg());
            error_log('[MecaBuddy] Raw LLM output (first 500 chars): ' . mb_substr($rawContent, 0, 500));

            return [
                'success' => false,
                'error' => 'json_parse_failed',
                'raw' => mb_substr($rawContent, 0, 500),
            ];
        }

        $title = $parsed['title'] ?? null;
        $steps = $parsed['steps'] ?? null;
        if (
            !is_string($title) || trim($title) === ''
            || !is_array($steps) || $steps === []
        ) {
            error_log(
                '[MecaBuddy] JSON valide mais structure incomplète : '
                . implode(', ', array_keys($parsed))
            );

            $failure = [
                'success' => false,
                'error' => 'json_structure_invalid',
                'keys_present' => array_keys($parsed),
                'raw' => mb_substr($rawContent, 0, 500),
            ];
            if (tutorial_is_missing_steps_only($parsed)) {
                $failure['partial'] = $parsed;
            }

            return $failure;
        }

        return [
            'success' => true,
            'tutorial' => $parsed,
        ];
    }

    /**
     * @param array<string, mixed> $provider
     * @param list<array<string, mixed>>|null $precomputedSearchResults Si fourni, saute la recherche web
     * @param callable|null $onProgress Appelé pendant le transfert cURL (heartbeats SSE)
     * @return array{success: bool, tutorial?: array<string, mixed>, vehicle_used?: bool, sources?: list<array{title: string, url: string}>, error?: string, raw?: string, http?: int, keys?: list<string>}
     */
    function callLlmForTutorial(
        string $actionType,
        ?array $vehicle,
        array $provider,
        ?array $precomputedSearchResults = null,
        ?callable $onProgress = null
    ): array {
        if (!defined('LLM_CHAT_LOADED')) {
            require_once __DIR__ . '/llm_chat.php';
        }

        $searchResults = $precomputedSearchResults !== null
            ? $precomputedSearchResults
            : tutorial_fetch_web_context($actionType, $vehicle);
        $isFailsafe = count($searchResults) === 0;
        $userMessage = tutorial_build_user_message($actionType, $vehicle, $searchResults, $isFailsafe);

        $type = (string) ($provider['type'] ?? 'ollama');
        $model = (string) ($provider['model'] ?? '');
        $baseUrl = rtrim((string) ($provider['base_url'] ?? ''), '/');

        if ($model === '' || !function_exists('curl_init')) {
            return ['success' => false, 'error' => 'invalid_provider'];
        }
        if ($type !== 'mistral' && $baseUrl === '') {
            return ['success' => false, 'error' => 'invalid_provider'];
        }

        $messages = [
            ['role' => 'system', 'content' => buildTutorialSystemPrompt()],
            ['role' => 'user', 'content' => $userMessage],
        ];

        if ($type === 'ollama') {
            $bodyExtras = [
                'format' => 'json',
                'think' => false,
                'options' => [
                    'num_predict' => 8192,
                    'temperature' => 0.3,
                    'num_ctx' => 8192,
                ],
            ];
        } else {
            $bodyExtras = [
                'max_tokens' => 4096,
                'temperature' => 0.3,
            ];
        }
        $prepared = _mecabuddyPrepareLlmCall($provider, $messages, $bodyExtras);
        if ($prepared === null) {
            return ['success' => false, 'error' => 'encode_failed'];
        }

        $curlOptions = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $prepared['body'],
            CURLOPT_HTTPHEADER => $prepared['headers'],
            CURLOPT_TIMEOUT => 600,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        if ($onProgress !== null) {
            $curlOptions[CURLOPT_NOPROGRESS] = false;
            $curlOptions[CURLOPT_PROGRESSFUNCTION] = static function (
                $resource,
                $downloadSize,
                $downloaded,
                $uploadSize,
                $uploaded
            ) use ($onProgress): int {
                $onProgress();

                return 0;
            };
        }

        $curlResult = _mecabuddyCurl($prepared['url'], $curlOptions);

        if (!$curlResult['ok']) {
            $err = $curlResult['error'] !== ''
                ? $curlResult['error']
                : 'http_' . $curlResult['http_code'];
            return ['success' => false, 'error' => $err];
        }

        $rawCurlResponse = $curlResult['body'];
        $httpCode = $curlResult['http_code'];

        error_log('[MecaBuddy][tutorial] HTTP ' . $httpCode);
        error_log(
            '[MecaBuddy][tutorial] Raw curl (500 chars) : '
            . mb_substr($rawCurlResponse, 0, 500)
        );

        $decoded = json_decode($rawCurlResponse, true);
        error_log(
            '[MecaBuddy][tutorial] Keys at root : '
            . implode(', ', array_keys($decoded ?? []))
        );

        if ($type === 'ollama') {
            error_log(
                '[MecaBuddy][tutorial] message key : '
                . json_encode($decoded['message'] ?? 'ABSENT', JSON_UNESCAPED_UNICODE)
            );
            $ollamaContent = is_array($decoded['message'] ?? null)
                ? ($decoded['message']['content'] ?? 'ABSENT')
                : 'ABSENT';
            error_log(
                '[MecaBuddy][tutorial] content : '
                . mb_substr((string) $ollamaContent, 0, 200)
            );
        }

        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'invalid_http_json'];
        }

        $content = null;
        if ($type === 'ollama') {
            $content = _mecabuddyExtractOllamaContent($decoded, 'tutorial');
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
            return [
                'success' => false,
                'error' => 'empty_assistant',
                'raw' => mb_substr($rawCurlResponse, 0, 500),
                'http' => $httpCode,
                'keys' => array_keys($decoded),
            ];
        }

        error_log(
            '[MecaBuddy][tutorial] Appel LLM → ' . $model . ' — passe 1 (metadata + tools)'
        );

        $parseResult = tutorial_parse_llm_tutorial_json($content);
        $llmPasses = 1;

        if (($parseResult['success'] ?? false) !== true && tutorial_needs_steps_pass2($parseResult)) {
            $partial = $parseResult['partial'];
            $tutorialTitle = trim((string) ($partial['title'] ?? ''));

            error_log(
                '[MecaBuddy][tutorial] JSON tronqué — steps manquants — lancement passe 2'
            );
            error_log(
                '[MecaBuddy][tutorial] Appel LLM → ' . $model . ' — passe 2 (steps uniquement)'
            );

            $stepsResult = tutorial_fetch_llm_steps($provider, $tutorialTitle, $onProgress);
            if (($stepsResult['success'] ?? false) !== true) {
                return [
                    'success' => false,
                    'error' => 'steps_generation_failed',
                    'raw' => mb_substr((string) ($stepsResult['raw'] ?? ''), 0, 300),
                ];
            }

            $partial['steps'] = $stepsResult['steps'];
            $mergedJson = json_encode($partial, JSON_UNESCAPED_UNICODE);
            if ($mergedJson === false) {
                return ['success' => false, 'error' => 'steps_generation_failed'];
            }

            $parseResult = tutorial_parse_llm_tutorial_json($mergedJson);
            $llmPasses = 2;

            if (($parseResult['success'] ?? false) !== true) {
                return [
                    'success' => false,
                    'error' => 'steps_generation_failed',
                    'raw' => mb_substr((string) ($parseResult['raw'] ?? $mergedJson), 0, 300),
                ];
            }
        } elseif (($parseResult['success'] ?? false) !== true) {
            return $parseResult;
        }

        $stepCount = count($parseResult['tutorial']['steps'] ?? []);
        error_log(
            '[MecaBuddy][tutorial] Tutoriel généré — ' . $stepCount
            . ' étapes (' . $llmPasses . ' passe' . ($llmPasses > 1 ? 's' : '') . ' LLM)'
        );

        return [
            'success' => true,
            'tutorial' => $parseResult['tutorial'],
            'vehicle_used' => $vehicle !== null,
            'sources' => tutorial_format_sources_for_response($searchResults),
            'failsafe' => $isFailsafe,
            'llm_passes' => $llmPasses,
        ];
    }

    /**
     * @param array<string, mixed> $tutorialData Tutoriel prêt pour la BDD (steps = tableaux PHP, champs scalaires)
     * @return array<string, mixed>
     */
    function parseTutorialResponse(array $tutorialData, ?int $vehicleId = null): array
    {
        return [
            'vehicle_id' => $vehicleId,
            'title' => (string) ($tutorialData['title'] ?? ''),
            'action_type' => (string) ($tutorialData['action_type'] ?? ''),
            'description' => (string) ($tutorialData['description'] ?? ''),
            'steps' => json_encode($tutorialData['steps'] ?? [], JSON_UNESCAPED_UNICODE),
            'tools_required' => json_encode($tutorialData['tools_required'] ?? [], JSON_UNESCAPED_UNICODE),
            'parts_required' => json_encode($tutorialData['parts_required'] ?? [], JSON_UNESCAPED_UNICODE),
            'danger_level' => (string) ($tutorialData['danger_level'] ?? 'none'),
            'global_warnings' => json_encode($tutorialData['global_warnings'] ?? [], JSON_UNESCAPED_UNICODE),
            'estimated_time' => (int) ($tutorialData['estimated_time'] ?? 0),
            'difficulty' => (string) ($tutorialData['difficulty'] ?? 'moyen'),
            'session_id' => session_id(),
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    function tutorial_normalize_llm_payload(array $raw): ?array
    {
        $title = trim((string) ($raw['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $stepsRaw = $raw['steps'] ?? null;
        if (!is_array($stepsRaw) || $stepsRaw === []) {
            return null;
        }

        $stepsNorm = [];
        foreach ($stepsRaw as $s) {
            if (!is_array($s)) {
                continue;
            }
            $st = trim((string) ($s['title'] ?? ''));
            $desc = trim((string) ($s['description'] ?? ''));
            $tip = $s['tip'] ?? null;
            $warn = $s['warning'] ?? null;

            if (is_string($tip)) {
                $tTrim = trim($tip);
                if ($tTrim !== '' && strcasecmp($tTrim, 'null') !== 0) {
                    $desc .= ($desc !== '' ? "\n\n" : '') . '💡 Conseil : ' . $tTrim;
                }
            }

            if (is_string($warn)) {
                $wTrim = trim($warn);
                if ($wTrim !== '' && strcasecmp($wTrim, 'null') !== 0) {
                    $desc .= ($desc !== '' ? "\n\n" : '') . '⚠️ ' . $wTrim;
                }
            }

            if ($st === '' && $desc === '') {
                continue;
            }

            $stepsNorm[] = [
                'title' => $st !== '' ? $st : 'Étape',
                'description' => $desc,
            ];
        }

        if ($stepsNorm === []) {
            return null;
        }

        $difficulties = ['facile', 'moyen', 'difficile', 'expert'];
        $d = (string) ($raw['difficulty'] ?? 'moyen');
        if (!in_array($d, $difficulties, true)) {
            $d = 'moyen';
        }

        $levels = ['none', 'low', 'medium', 'high'];
        $lvl = (string) ($raw['danger_level'] ?? 'none');
        if (!in_array($lvl, $levels, true)) {
            $lvl = 'none';
        }

        $tools = [];
        if (isset($raw['tools_required']) && is_array($raw['tools_required'])) {
            foreach ($raw['tools_required'] as $t) {
                if ($t === null) {
                    continue;
                }
                $s = trim((string) $t);
                if ($s !== '' && strcasecmp($s, 'null') !== 0) {
                    $tools[] = $s;
                }
            }
        }

        $parts = [];
        if (isset($raw['parts_required']) && is_array($raw['parts_required'])) {
            foreach ($raw['parts_required'] as $p) {
                if ($p === null) {
                    continue;
                }
                $s = trim((string) $p);
                if ($s !== '' && strcasecmp($s, 'null') !== 0) {
                    $parts[] = $s;
                }
            }
        }

        $gw = [];
        if (isset($raw['global_warnings']) && is_array($raw['global_warnings'])) {
            foreach ($raw['global_warnings'] as $g) {
                if ($g === null) {
                    continue;
                }
                $s = trim((string) $g);
                if ($s !== '' && strcasecmp($s, 'null') !== 0) {
                    $gw[] = $s;
                }
            }
        }

        return [
            'title' => $title,
            'description' => trim((string) ($raw['description'] ?? '')),
            'estimated_time' => max(0, (int) ($raw['estimated_time'] ?? 0)),
            'difficulty' => $d,
            'tools_required' => $tools,
            'parts_required' => $parts,
            'danger_level' => $lvl,
            'global_warnings' => $gw,
            'steps' => $stepsNorm,
        ];
    }
}

if (!defined('LLM_TUTORIAL_LOADED')) {
    define('LLM_TUTORIAL_LOADED', true);
}
