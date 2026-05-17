<?php
/**
 * Test local URL Gemini + appel API optionnel (GEMINI_API_KEY en variable d'environnement).
 * Usage : set GEMINI_API_KEY=... && php scripts/test_gemini_llm.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/llm_chat.php';
require_once __DIR__ . '/../includes/llm_bridge.php';

$provider = [
    'type' => 'openai_compatible',
    'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
    'chat_path' => '/chat/completions',
    'model' => getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash-lite',
    'api_key' => getenv('GEMINI_API_KEY') ?: '',
];

$expected = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
$prepared = _mecabuddyPrepareLlmCall($provider, [['role' => 'user', 'content' => 'Réponds juste OK']], ['temperature' => 0.3]);
$url = $prepared['url'] ?? '';
echo "URL attendue : {$expected}\n";
echo "URL obtenue  : {$url}\n";
echo ($url === $expected ? "URL OK\n" : "URL FAIL\n");

$variants = [
    ['base' => 'https://generativelanguage.googleapis.com/v1beta/openai/', 'path' => 'chat/completions'],
    ['base' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'path' => '/chat/completions'],
];
foreach ($variants as $v) {
    $built = _mecabuddyBuildChatCompletionsUrl($v['base'], $v['path']);
    echo "Variant {$v['base']} + {$v['path']} => {$built}" . ($built === $expected ? " OK\n" : " FAIL\n");
}

if ($provider['api_key'] === '') {
    echo "GEMINI_API_KEY non définie — test API ignoré.\n";
    exit($url === $expected ? 0 : 1);
}

$result = testLlmProviderPrompt($provider, 'Réponds juste OK');
echo 'API ok=' . (($result['ok'] ?? false) ? 'true' : 'false') . "\n";
echo 'latency_ms=' . (int) ($result['latency_ms'] ?? 0) . "\n";
echo 'response=' . substr((string) ($result['response'] ?? ''), 0, 120) . "\n";
if (!empty($result['error'])) {
    echo 'error=' . $result['error'] . "\n";
}

exit(($url === $expected && ($result['ok'] ?? false)) ? 0 : 1);
