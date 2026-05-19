<?php
/**
 * Vérification statique des prompts Diagnostic (Buddy) et Tutoriel.
 * Usage : php scripts/verify_prompts.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/llm_chat.php';
require_once __DIR__ . '/../includes/llm_tutorial.php';

$failed = 0;

/**
 * @param list<string> $needles
 */
function assert_contains_all(string $haystack, array $needles, string $label): void
{
    global $failed;
    foreach ($needles as $needle) {
        if (!str_contains($haystack, $needle)) {
            echo "FAIL [{$label}] manque : {$needle}\n";
            $failed++;
        }
    }
}

$diagnostic = buildSystemPrompt();
assert_contains_all($diagnostic, [
    'au maximum 3 pistes',
    'Symptômes associés à vérifier',
    'Signes qui renforcent',
    'Signes qui l\'affaiblissent',
    'Signes qui changeraient le diagnostic',
    'Niveau de risque',
    'Faible',
    'Moyen',
    'Élevé',
    'Critique',
    'Confiance',
    'faible | moyen | élevé',
    'À éviter',
    'Prochaine action recommandée',
    'Test simple',
    'Ne JAMAIS affirmer',
    'remplacement direct',
    'freinage',
    'surchauffe moteur',
    'capteur PMH',
    '[SOURCES]',
], 'diagnostic');

$tutorial = buildTutorialSystemPrompt();
assert_contains_all($tutorial, [
    '"preface"',
    'compatible_symptoms',
    'other_causes',
    'pre_checks',
    'when_professional',
    'Contrôle après intervention',
    'remplacement direct',
    'compatible_symptoms',
    'autres causes',
    'vérifications',
    'professionnel',
    'freins',
    'direction',
    'capteur PMH',
    'needs_visual',
    'visual_search_queries',
    'visual_type',
    'visual_purpose',
    'visual_specificity',
    'ne génère JAMAIS d\'image',
], 'tutoriel');

if (!preg_match('/3 pistes/i', $diagnostic) && !preg_match('/au maximum 3/i', $diagnostic)) {
    echo "FAIL [diagnostic] limite 3 pistes non détectée\n";
    $failed++;
}

if ($failed === 0) {
    echo "OK — tous les contrôles de prompt sont passés (" . strlen($diagnostic) . " + " . strlen($tutorial) . " octets).\n";
    exit(0);
}

echo "\n{$failed} contrôle(s) en échec.\n";
exit(1);
