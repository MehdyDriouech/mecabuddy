<?php
/**
 * MecaBuddy — Pont LLM (Ollama / OpenAI-compatible) et recherche Serper
 */

require_once __DIR__ . '/../config/config.php';

/**
 * @return array{http_code: int, body: string, curl_error: ?string}
 */
function llm_bridge_curl_post(string $url, array $headers, string $body, int $timeout): array
{
    if (!function_exists('curl_init')) {
        return ['http_code' => 0, 'body' => '', 'curl_error' => 'curl_unavailable'];
    }

    $ch = curl_init($url);
    $hdr = array_merge(['Content-Type: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $hdr,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $bodyOut = curl_exec($ch);
    $cerr = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'http_code' => $code,
        'body' => is_string($bodyOut) ? $bodyOut : '',
        'curl_error' => $bodyOut === false && $cerr !== '' ? $cerr : ($bodyOut === false ? 'curl_exec_failed' : null),
    ];
}

/**
 * @return array<int, string>
 */
function searchWebForVehicle(string $plate): array
{
    $key = trim((string) (getEffectiveSettings()['serper_api_key'] ?? ''));
    if ($key === '' || !function_exists('curl_init')) {
        return [];
    }

    $payload = json_encode([
        'q' => 'véhicule immatriculé ' . $plate . ' marque modèle',
        'gl' => 'fr',
        'hl' => 'fr',
        'num' => 5,
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return [];
    }

    $res = llm_bridge_curl_post(
        'https://google.serper.dev/search',
        ['X-API-KEY: ' . $key],
        $payload,
        5
    );

    if ($res['curl_error'] !== null || $res['http_code'] !== 200) {
        return [];
    }

    $json = json_decode($res['body'], true);
    if (!is_array($json) || !isset($json['organic']) || !is_array($json['organic'])) {
        return [];
    }

    $snippets = [];
    foreach ($json['organic'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (!empty($row['snippet']) && is_string($row['snippet'])) {
            $snippets[] = $row['snippet'];
        }
    }

    return $snippets;
}

/**
 * @param array<int, string> $searchSnippets
 * @param array<string, mixed> $provider
 * @return array{
 *   found: bool,
 *   source: string,
 *   brand: mixed,
 *   model: mixed,
 *   year: mixed,
 *   engine: mixed,
 *   fuel: mixed,
 *   transmission: mixed,
 *   error?: string
 * }
 */
function queryLlmForVehicle(string $plate, array $searchSnippets, array $provider): array
{
    $base = [
        'found' => false,
        'source' => 'llm_inference',
        'brand' => null,
        'model' => null,
        'year' => null,
        'engine' => null,
        'fuel' => null,
        'transmission' => null,
    ];

    if (!function_exists('curl_init')) {
        return array_merge($base, ['error' => 'curl_unavailable']);
    }

    $snippetsJson = json_encode($searchSnippets, JSON_UNESCAPED_UNICODE);
    if ($snippetsJson === false) {
        $snippetsJson = '[]';
    }

    $prompt = "Tu es un assistant automobile. À partir de ces résultats de recherche sur le véhicule\n"
        . "immatriculé {$plate}, extrais les informations suivantes en JSON uniquement,\n"
        . "sans texte avant ni après, sans backticks :\n"
        . "{ brand, model, year, engine, fuel, transmission }\n"
        . "Utilise null pour les champs introuvables.\n"
        . 'Résultats de recherche : ' . $snippetsJson;

    if (!defined('LLM_CHAT_LOADED')) {
        require_once __DIR__ . '/llm_chat.php';
    }

    $type = (string) ($provider['type'] ?? 'ollama');
    $messages = [['role' => 'user', 'content' => $prompt]];
    $extras = $type === 'ollama' ? [] : ['temperature' => 0];
    $prepared = _mecabuddyPrepareLlmCall($provider, $messages, $extras);
    if ($prepared === null) {
        return array_merge($base, ['error' => 'invalid_provider']);
    }

    $res = _mecabuddyCurl($prepared['url'], [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $prepared['body'],
        CURLOPT_HTTPHEADER => $prepared['headers'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    if (!$res['ok']) {
        $err = $res['error'] !== '' ? $res['error'] : 'http_' . $res['http_code'];
        return array_merge($base, ['error' => $err]);
    }

    $decoded = json_decode($res['body'], true);
    if (!is_array($decoded)) {
        return array_merge($base, ['error' => 'invalid_llm_json']);
    }

    $text = llm_bridge_extract_assistant_text($decoded, $type);
    if ($text === null || trim($text) === '') {
        return array_merge($base, ['error' => 'empty_assistant']);
    }

    $parsed = llm_bridge_parse_json_object_from_text($text);
    if (!is_array($parsed)) {
        return array_merge($base, ['error' => 'invalid_assistant_json']);
    }

    $brand = $parsed['brand'] ?? null;
    $model = $parsed['model'] ?? null;

    $base['brand'] = $brand;
    $base['model'] = $model;
    $base['year'] = $parsed['year'] ?? null;
    $base['engine'] = $parsed['engine'] ?? null;
    $base['fuel'] = $parsed['fuel'] ?? null;
    $base['transmission'] = $parsed['transmission'] ?? null;

    $base['found'] = $brand !== null && $brand !== '' && $model !== null && $model !== '';

    return $base;
}

/**
 * @param array<string, mixed> $decoded
 */
function llm_bridge_extract_assistant_text(array $decoded, string $type): ?string
{
    if ($type === 'ollama') {
        $msg = $decoded['message'] ?? null;
        if (is_array($msg) && isset($msg['content']) && is_string($msg['content'])) {
            return $msg['content'];
        }
        return null;
    }

    $choices = $decoded['choices'] ?? null;
    if (!is_array($choices) || !isset($choices[0]) || !is_array($choices[0])) {
        return null;
    }
    $m = $choices[0]['message'] ?? null;
    if (is_array($m) && isset($m['content']) && is_string($m['content'])) {
        return $m['content'];
    }
    return null;
}

function llm_bridge_parse_json_object_from_text(string $text): ?array
{
    $t = trim($text);
    if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $t, $m)) {
        $t = trim($m[1]);
    }
    $try = json_decode($t, true);
    if (is_array($try)) {
        return $try;
    }
    if (preg_match('/\{[\s\S]*\}/', $t, $m2)) {
        $try2 = json_decode($m2[0], true);
        if (is_array($try2)) {
            return $try2;
        }
    }
    return null;
}

/**
 * Test minimal : envoie un message court et retourne latence + texte assistant.
 *
 * @param array<string, mixed> $provider
 * @return array{ok: bool, latency_ms: int, response: string, error?: string}
 */
function testLlmProviderPrompt(array $provider, string $userMessage = 'Réponds juste OK'): array
{
    $fail = static function (string $err, int $latencyMs = 0): array {
        return [
            'ok' => false,
            'latency_ms' => $latencyMs,
            'response' => '',
            'error' => $err,
        ];
    };

    if (!function_exists('curl_init')) {
        return $fail('curl_unavailable');
    }

    if (!defined('LLM_CHAT_LOADED')) {
        require_once __DIR__ . '/llm_chat.php';
    }

    $type = (string) ($provider['type'] ?? 'ollama');
    $messages = [['role' => 'user', 'content' => $userMessage]];
    $extras = $type === 'ollama' ? [] : ['temperature' => 0];
    $prepared = _mecabuddyPrepareLlmCall($provider, $messages, $extras);
    if ($prepared === null) {
        return $fail('invalid_provider');
    }

    $t0 = microtime(true);
    $res = _mecabuddyCurl($prepared['url'], [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $prepared['body'],
        CURLOPT_HTTPHEADER => $prepared['headers'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $latency = (int) round((microtime(true) - $t0) * 1000);

    if (!$res['ok']) {
        $err = _mecabuddyInterpretLlmHttpError(
            $res['http_code'],
            $res['body'],
            $res['error']
        );
        return $fail($err, $latency);
    }

    $decoded = json_decode($res['body'], true);
    if (!is_array($decoded)) {
        return $fail('invalid_llm_json', $latency);
    }

    $text = llm_bridge_extract_assistant_text($decoded, $type);
    if ($text === null || trim($text) === '') {
        return $fail('empty_assistant', $latency);
    }

    $out = [
        'ok' => true,
        'latency_ms' => $latency,
        'response' => $text,
    ];
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $out['request_url'] = $prepared['url'];
    }

    return $out;
}
