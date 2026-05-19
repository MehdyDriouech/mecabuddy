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
et d'entretien précis, structurés, adaptés au véhicule si fourni.

Tu réponds UNIQUEMENT avec un objet JSON valide, sans texte avant ni après,
sans backticks, sans markdown.

=== AVANT LES ÉTAPES DE DÉMONTAGE (obligatoire) ===

Ne pousse JAMAIS au remplacement direct si le diagnostic n'est pas suffisamment établi.
Avant toute procédure, le champ "preface" doit couvrir le diagnostic préalable.

Pour une intervention sur organe critique (freins, direction, pneus, suspension, airbag,
ceinture, carburant, haute tension hybride/électrique, distribution, embrayage, transmission,
refroidissement en surchauffe sévère) : recommande explicitement un professionnel si
l'utilisateur n'a pas l'expérience ou l'outillage adapté.

=== STRUCTURE JSON OBLIGATOIRE ===

{
  "title": "Titre court du tutoriel",
  "description": "Résumé en 1-2 phrases de l'intervention",
  "difficulty": "facile|moyen|difficile|expert",
  "estimated_time": <nombre entier de minutes>,
  "tools_required": ["outil1", "outil2"],
  "parts_required": ["pièce1", "pièce2"],
  "danger_level": "none|low|medium|high",
  "global_warnings": ["précautions de sécurité concrètes"],
  "preface": {
    "before_start": "Avant de commencer : prérequis, contexte, quand l'intervention est pertinente",
    "compatible_symptoms": ["symptôme typique 1", "symptôme 2"],
    "other_causes": ["autre cause possible 1", "cause 2"],
    "pre_checks": ["vérification simple avant démontage 1", "vérification 2"],
    "when_professional": "Quand consulter un professionnel (obligatoire si organe critique)"
  },
  "steps": [
    {
      "step": 1,
      "title": "Titre de l'étape",
      "description": "Instructions détaillées (équivalent instruction)",
      "warning": "Avertissement spécifique ou null",
      "tip": "Conseil pratique ou null",
      "needs_visual": false,
      "visual_type": null,
      "visual_purpose": null,
      "visual_search_queries": [],
      "visual_specificity": "unknown",
      "visual_disclaimer": null
    }
  ]
}

=== VISUELS WEB RECOMMANDÉS (le LLM ne génère JAMAIS d'image ni d'URL d'image) ===

Pour chaque étape, décide si un visuel d'aide serait utile (localisation pièce, connecteur,
clip, sens de démontage, outil, schéma sécurité, etc.).

Si needs_visual = true :
- visual_purpose : pourquoi un visuel aiderait (1 phrase).
- visual_type : part_location | engine_view | connector | clip_fastener | fastener |
  removal_direction | tool_in_use | before_after | safety_diagram | fluid_level_leak | generic
- visual_search_queries : 2 à 4 requêtes MAX, ultra ciblées, SANS URL inventée.
  * Au moins une requête en français et une en anglais si pertinent.
  * Utiliser marque, modèle, motorisation, année, nom de pièce FR/EN, location/removal.
  * Ne PAS inventer de code moteur (ex. DADA) si non fourni dans le contexte véhicule.
  * Éviter les requêtes trop génériques (« capteur voiture »).
- visual_specificity : generic | vehicle_family | vehicle_specific | unknown
- visual_disclaimer : rappel court que l'emplacement peut varier (ou null).

Interdit : fournir image_url, thumbnail, ou lien vers une image précise inventée.

La dernière étape DOIT être intitulée « Contrôle après intervention » et décrire les
vérifications post-travail (fuites, bruits, voyants, essai statique prudent si applicable).

Si une information est inconnue, utilise null (pas de chaîne vide). Tableaux vides : [].

=== PRÉCISION TECHNIQUE (NON NÉGOCIABLE) ===

- Valeurs numériques incertaines (couples, volumes, dimensions) : « voir manuel constructeur ».
- Pièces d'accès (carénages, selles, réservoirs…) = étapes numérotées distinctes.
- Ordre chronologique exact. Couples de serrage au retour de montage.
- Motos : béquille centrale ou paddock stand si nécessaire.

=== EXEMPLE (capteur PMH) ===

Utilisateur : « Tuto pour changer le capteur PMH »
→ preface : rappeler que le remplacement n'est pertinent que si symptômes/codes compatibles ;
symptômes typiques (démarrage chaud difficile, coupures, ralenti instable, compte-tours à zéro) ;
autres causes (alimentation, allumage, prise d'air) ; vérifications OBD, connectique, référence
constructeur avant achat de pièce.
→ steps : démontage/remontage + dernière étape contrôle après intervention.
→ needs_visual sur étapes de localisation/dépose ; requêtes du type
  « emplacement capteur PMH Skoda 1.5 TSI » et « Skoda 1.5 TSI crankshaft position sensor location ».
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
            . 'IMPORTANT : remplis obligatoirement "preface" (symptômes compatibles, autres causes, '
            . 'vérifications avant démontage, quand consulter un pro) avant les étapes. '
            . 'Ne recommande pas un remplacement sans rappeler les contrôles préalables. '
            . 'Sois spécifique à ce modèle. Pièces d\'accès = étapes distinctes. '
            . "Valeurs incertaines : 'voir manuel constructeur'. "
            . 'Dernière étape : « Contrôle après intervention ». '
            . 'Remplis needs_visual / visual_search_queries sur les étapes où un schéma aiderait '
            . '(sans inventer d\'URL ni de code moteur).';
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

        if ($repaired === '') {
            return $repaired;
        }

        $stack = [];
        $inStr = false;
        $len = strlen($repaired);
        for ($i = 0; $i < $len; $i++) {
            $c = $repaired[$i];
            if ($c === '"' && ($i === 0 || $repaired[$i - 1] !== '\\')) {
                $inStr = !$inStr;

                continue;
            }
            if ($inStr) {
                continue;
            }
            if ($c === '{') {
                $stack[] = '}';
            } elseif ($c === '[') {
                $stack[] = ']';
            } elseif ($c === '}' || $c === ']') {
                $top = $stack !== [] ? $stack[count($stack) - 1] : null;
                if ($top === $c) {
                    array_pop($stack);
                }
            }
        }

        if ($inStr) {
            $repaired .= '"';
        }

        while ($stack !== []) {
            $repaired .= array_pop($stack);
        }

        return $repaired;
    }

    /**
     * Tente d'extraire un objet partiel (titre + métadonnées) quand le JSON est tronqué.
     *
     * @return array<string, mixed>|null
     */
    function tutorial_extract_partial_tutorial(string $prepared): ?array
    {
        $repaired = tutorial_normalize_json_layout(tutorial_repair_truncated_json($prepared));
        $parsed = json_decode($repaired, true);
        if (is_array($parsed)) {
            $title = $parsed['title'] ?? null;
            if (is_string($title) && trim($title) !== '') {
                return $parsed;
            }
        }

        if (preg_match('/"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $prepared, $matches)) {
            return ['title' => stripcslashes($matches[1])];
        }

        return null;
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

            $failure = [
                'success' => false,
                'error' => 'json_parse_failed',
                'raw' => mb_substr($rawContent, 0, 500),
            ];
            $partial = tutorial_extract_partial_tutorial($prepared);
            if ($partial !== null && tutorial_is_missing_steps_only($partial)) {
                $failure['partial'] = $partial;
            }

            return $failure;
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
                'max_tokens' => 8192,
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
            $httpCode = (int) $curlResult['http_code'];

            return array_merge(
                [
                    'success' => false,
                    'error' => 'llm_provider_error',
                    'http' => $httpCode,
                    'provider_status' => $httpCode,
                    'curl_error' => $curlResult['error'],
                    'body' => $curlResult['body'],
                ],
                [
                    'message' => mecabuddy_public_llm_error_message([
                        'http' => $httpCode,
                        'curl_error' => $curlResult['error'],
                        'body' => $curlResult['body'],
                        'error' => 'llm_provider_error',
                    ], $provider),
                ]
            );
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
    function tutorial_normalize_llm_payload(array $raw, ?array $vehicle = null): ?array
    {
        $GLOBALS['_tutorial_normalize_vehicle'] = $vehicle;

        $title = trim((string) ($raw['title'] ?? ''));
        if ($title === '') {
            unset($GLOBALS['_tutorial_normalize_vehicle']);

            return null;
        }

        $stepsRaw = $raw['steps'] ?? null;
        if (!is_array($stepsRaw) || $stepsRaw === []) {
            unset($GLOBALS['_tutorial_normalize_vehicle']);

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

            $stepOut = [
                'title' => $st !== '' ? $st : 'Étape',
                'description' => $desc,
            ];

            if (!defined('TUTORIAL_VISUAL_SEARCH_LOADED')) {
                require_once __DIR__ . '/tutorial_visual_search.php';
            }
            $vehicleCtx = $GLOBALS['_tutorial_normalize_vehicle'] ?? null;
            $stepOut = array_merge(
                $stepOut,
                tutorial_visual_normalize_step_fields($s, is_array($vehicleCtx) ? $vehicleCtx : null)
            );

            $stepsNorm[] = $stepOut;
        }

        if ($stepsNorm === []) {
            unset($GLOBALS['_tutorial_normalize_vehicle']);

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

        $preface = null;
        if (isset($raw['preface']) && is_array($raw['preface'])) {
            $preface = tutorial_normalize_preface($raw['preface']);
        }

        $out = [
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
        if ($preface !== null) {
            $out['preface'] = $preface;
        }

        unset($GLOBALS['_tutorial_normalize_vehicle']);

        return $out;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>|null
     */
    function tutorial_normalize_preface(array $raw): ?array
    {
        $before = trim((string) ($raw['before_start'] ?? ''));
        $whenPro = trim((string) ($raw['when_professional'] ?? ''));

        $listFields = ['compatible_symptoms', 'other_causes', 'pre_checks'];
        $lists = [];
        foreach ($listFields as $key) {
            $items = [];
            if (isset($raw[$key]) && is_array($raw[$key])) {
                foreach ($raw[$key] as $item) {
                    if ($item === null) {
                        continue;
                    }
                    $s = trim((string) $item);
                    if ($s !== '' && strcasecmp($s, 'null') !== 0) {
                        $items[] = $s;
                    }
                }
            }
            $lists[$key] = $items;
        }

        if (
            $before === ''
            && $whenPro === ''
            && $lists['compatible_symptoms'] === []
            && $lists['other_causes'] === []
            && $lists['pre_checks'] === []
        ) {
            return null;
        }

        return [
            'before_start' => $before,
            'compatible_symptoms' => $lists['compatible_symptoms'],
            'other_causes' => $lists['other_causes'],
            'pre_checks' => $lists['pre_checks'],
            'when_professional' => $whenPro,
        ];
    }
}

if (!defined('LLM_TUTORIAL_LOADED')) {
    define('LLM_TUTORIAL_LOADED', true);
}
