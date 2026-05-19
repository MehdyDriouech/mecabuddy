<?php
/**
 * Vérifications POC visuels tutoriel (prompt + normalisation + filtres).
 * Usage : php scripts/verify_tutorial_visuals.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/llm_tutorial.php';
require_once __DIR__ . '/../includes/tutorial_visual_search.php';

$failed = 0;

function assert_true(bool $cond, string $msg): void
{
    global $failed;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failed++;
    }
}

$prompt = buildTutorialSystemPrompt();
foreach ([
    'needs_visual',
    'visual_search_queries',
    'visual_type',
    'visual_purpose',
    'visual_specificity',
    'ne génère JAMAIS d\'image',
    'visual_search_queries : 2 à 4',
] as $needle) {
    if (!str_contains($prompt, $needle)) {
        echo "FAIL [prompt] manque : {$needle}\n";
        $failed++;
    }
}

$vehicle = [
    'brand' => 'Skoda',
    'model' => 'Octavia',
    'engine_type' => '1.5 TSI Essence',
    'engine_size' => '1.5L',
];

$raw = [
    'title' => 'Test PMH',
    'difficulty' => 'moyen',
    'danger_level' => 'low',
    'steps' => [
        [
            'title' => 'Localiser le capteur',
            'description' => 'Repère le capteur.',
            'needs_visual' => true,
            'visual_type' => 'part_location',
            'visual_purpose' => 'Identifier l\'emplacement.',
            'visual_search_queries' => [
                'emplacement capteur PMH Skoda 1.5 TSI',
                'Skoda 1.5 TSI crankshaft position sensor location',
                'Skoda 1.5 TSI engine code DADA crankshaft sensor location',
                'Skoda Octavia 1.5 TSI PMH emplacement moteur',
                'requête en trop',
            ],
            'visual_specificity' => 'vehicle_family',
        ],
    ],
];

$norm = tutorial_normalize_llm_payload($raw, $vehicle);
assert_true($norm !== null, 'normalisation tutoriel');
$step = $norm['steps'][0] ?? null;
assert_true(is_array($step), 'étape présente');
assert_true(($step['needs_visual'] ?? false) === true, 'needs_visual conservé');
assert_true(count($step['visual_search_queries'] ?? []) <= 4, 'max 4 requêtes');
assert_true(
    !in_array('Skoda 1.5 TSI engine code DADA crankshaft sensor location', $step['visual_search_queries'] ?? [], true),
    'code moteur DADA filtré si absent du véhicule'
);

$enriched = tutorial_enrich_steps_with_visuals($norm['steps'], $vehicle, 'changer capteur PMH');
$step2 = $enriched[0];
assert_true(isset($step2['visual_search_status']), 'statut recherche présent');
assert_true(is_array($step2['visual_manual_links'] ?? null), 'liens manuels présents');
assert_true(
    ($step2['visual_search_status'] === 'ok' || $step2['visual_search_status'] === 'no_results'),
    'tutoriel lisible sans images (statut ok ou no_results)'
);

$critical = tutorial_visual_is_critical_context('changer plaquettes de frein', $vehicle, 'Dépose étrier');
assert_true($critical, 'organe critique détecté (frein)');

if ($failed === 0) {
    echo "OK — vérifications visuels tutoriel passées.\n";
    exit(0);
}

echo "\n{$failed} échec(s).\n";
exit(1);
