<?php
/**
 * MecaBuddy — Génération de tutoriel en streaming SSE (PHP natif)
 *
 * GET ?action_type=vidange
 * Événements : status | done | error
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/safety_layer.php';
require_once __DIR__ . '/../includes/demo_auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

ob_implicit_flush(true);
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

set_time_limit(0);

/**
 * @param array<string, mixed> $data
 */
function sse_event(string $type, array $data): void
{
    echo 'event: ' . $type . "\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    // Padding anti-buffering (nginx / reverse proxy) pour que le client reçoive les phases en direct.
    echo ': ' . str_repeat(' ', 2048) . "\n\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

define('TUTORIAL_API_ROUTER_DISABLED', true);
require_once __DIR__ . '/tutorial_api.php';
require_once __DIR__ . '/../includes/llm_tutorial.php';
require_once __DIR__ . '/../includes/vehicle_context.php';

$actionType = strtolower(trim((string) ($_GET['action_type'] ?? '')));
if ($actionType === '') {
    sse_event('error', ['message' => 'action_type manquant']);
    exit;
}

require_once __DIR__ . '/../includes/byok.php';

$provider = getEffectiveLlmProvider();

if ($provider === null) {
    sse_event('error', [
        'success' => false,
        'error' => 'no_llm_provider',
        'message' => 'Aucun provider LLM actif',
    ]);
    exit;
}

byok_assert_provider_usable_sse($provider);

demo_auth_consume_quota_start_sse('tutorial');

$vehContext = getCurrentVehicleContext();
$sessionVehicleId = isset($_SESSION['vehicle_id']) ? (int) $_SESSION['vehicle_id'] : null;
$vehicleRow = null;

if ($sessionVehicleId && $sessionVehicleId > 0) {
    if (USE_MOCK_DB) {
        $vehicleRow = MockDatabase::getVehicle($sessionVehicleId);
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM vehicles WHERE id = ?');
            $stmt->execute([$sessionVehicleId]);
            $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
            $vehicleRow = $fetched !== false ? $fetched : null;
        } catch (Throwable $e) {
            $vehicleRow = null;
        }
    }
}

$vehicleLabel = null;
if ($vehContext !== null) {
    $vehicleLabel = trim(($vehContext['brand'] ?? '') . ' ' . ($vehContext['model'] ?? ''));
    if ($vehicleLabel === '') {
        $vehicleLabel = null;
    }
}

$cleanActionPreview = function_exists('tutorial_clean_action_type')
    ? tutorial_clean_action_type($actionType)
    : $actionType;

sse_event('status', [
    'phase' => 'vehicle',
    'message' => 'Chargement du contexte véhicule...',
    'vehicle' => $vehicleLabel,
    'category' => $vehContext['category'] ?? 'car',
]);

sse_event('status', [
    'phase' => 'search',
    'message' => 'Recherche de documentation spécifique...',
    'vehicle' => $vehicleLabel,
    'category' => $vehContext['category'] ?? 'car',
    'search_query' => $cleanActionPreview,
]);

$searchMeta = tutorial_run_web_search($actionType, $vehContext);
$searchResults = $searchMeta['results'];
$queriesRun = $searchMeta['queries_run'];
$queryDetails = $searchMeta['query_details'];
$isFailsafe = $searchMeta['is_failsafe'];

$GLOBALS['_tutorial_last_search_query'] = $queriesRun[0] ?? null;
$GLOBALS['_tutorial_queries_run'] = $queriesRun;
$GLOBALS['_tutorial_query_details'] = $queryDetails;
$GLOBALS['_tutorial_is_failsafe'] = $isFailsafe;

$searchQueryPreview = $queriesRun[0] ?? $cleanActionPreview;

$webContextChars = $isFailsafe
    ? 0
    : mb_strlen(tutorial_build_web_context_block($searchResults));

$sourcesPreview = [];
foreach (array_slice($searchResults, 0, 4) as $r) {
    if (!is_array($r)) {
        continue;
    }
    $sourcesPreview[] = [
        'title' => trim((string) ($r['title'] ?? '')),
        'url' => trim((string) ($r['url'] ?? '')),
    ];
}

sse_event('status', [
    'phase' => 'search_done',
    'message' => $isFailsafe
        ? 'Aucune source fiable — génération depuis la mémoire du modèle.'
        : 'Documentation trouvée.',
    'sources_count' => count($searchResults),
    'sources' => $sourcesPreview,
    'search_query' => $searchQueryPreview,
    'queries_run' => $queriesRun,
    'query_details' => $queryDetails,
    'failsafe' => $isFailsafe,
    'context_chars' => $webContextChars,
]);

$modelLabel = (string) ($provider['model'] ?? $provider['name'] ?? 'IA');

sse_event('status', [
    'phase' => 'llm',
    'message' => 'Génération du tutoriel en cours...',
    'model' => $modelLabel,
    'failsafe' => $isFailsafe,
]);

$lastHeartbeat = time();
$llmRes = callLlmForTutorial(
    $actionType,
    $vehContext,
    $provider,
    $searchResults,
    static function () use (&$lastHeartbeat): void {
        if (time() - $lastHeartbeat < 15) {
            return;
        }
        echo ": heartbeat\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        $lastHeartbeat = time();
    }
);

if (($llmRes['success'] ?? false) !== true) {
    if (!defined('LLM_CHAT_LOADED')) {
        require_once __DIR__ . '/../includes/llm_chat.php';
    }

    $errorCode = (string) ($llmRes['error'] ?? 'Erreur LLM');
    if ($errorCode === 'json_structure_invalid') {
        sse_event('error', [
            'success' => false,
            'error' => 'json_structure_invalid',
            'message' => 'Structure JSON incomplète pour le tutoriel.',
            'keys_present' => $llmRes['keys_present'] ?? [],
            'raw_preview' => mb_substr((string) ($llmRes['raw'] ?? ''), 0, 300),
        ]);
    } elseif ($errorCode === 'json_parse_failed') {
        $raw = (string) ($llmRes['raw'] ?? '');
        sse_event('error', [
            'success' => false,
            'error' => 'json_parse_failed',
            'message' => strlen($raw) === 0
                ? 'Réponse LLM vide — modèle peut-être surchargé.'
                : 'Réponse LLM invalide (JSON attendu).',
            'raw_preview' => mb_substr($raw, 0, 500),
        ]);
    } elseif ($errorCode === 'empty_assistant') {
        $payload = mecabuddy_build_llm_provider_error_payload($llmRes, $provider);
        $payload['error'] = 'empty_assistant';
        $payload['message'] = (string) ($llmRes['message'] ?? $payload['message']);
        if (defined('APP_DEBUG') && APP_DEBUG) {
            $payload['raw_preview'] = mb_substr((string) ($llmRes['raw'] ?? ''), 0, 500);
            $payload['http_code'] = $llmRes['http'] ?? null;
        }
        sse_event('error', $payload);
    } elseif (mecabuddy_is_llm_provider_transport_failure($llmRes)) {
        sse_event('error', mecabuddy_build_llm_provider_error_payload($llmRes, $provider));
    } else {
        sse_event('error', [
            'success' => false,
            'error' => $errorCode,
            'message' => (string) ($llmRes['message'] ?? 'Erreur lors de la génération du tutoriel.'),
        ]);
    }
    exit;
}

sse_event('status', [
    'phase' => 'saving',
    'message' => 'Sauvegarde du tutoriel...',
]);

$finalized = tutorial_finalize_llm_tutorial(
    $llmRes['tutorial'],
    $actionType,
    $vehicleRow,
    $vehContext,
    $sessionVehicleId
);

if ($finalized === null) {
    sse_event('error', ['message' => 'Impossible de normaliser ou sauvegarder le tutoriel']);
    exit;
}

$sources = !empty($llmRes['sources'])
    ? $llmRes['sources']
    : tutorial_format_sources_for_response($searchResults);

sse_event('done', [
    'tutorial_id' => $finalized['tutorial_id'],
    'tutorial' => $finalized['tutorial'],
    'sources' => $sources,
    'generated_by' => 'llm',
    'failsafe' => $isFailsafe,
    'vehicle_used' => $vehContext !== null,
]);
