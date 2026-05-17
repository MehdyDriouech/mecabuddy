<?php
/**
 * MecaBuddy - API Tutoriel
 * 
 * Endpoints pour la génération et gestion des tutoriels :
 * - POST ?action=generate     → Génère un nouveau tutoriel
 * - GET  ?action=get&id=X     → Récupère un tutoriel existant
 * - GET  ?action=list         → Liste les tutoriels récents
 * - GET  ?action=suggestions  → Retourne les suggestions d'actions
 * 
 * Supporte le mode MOCK (sans base de données MySQL)
 */

// Configuration des headers pour API JSON (sauf inclusion SSE)
if (!defined('TUTORIAL_API_ROUTER_DISABLED')) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Chargement des dépendances
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/safety_layer.php';
require_once __DIR__ . '/../includes/demo_auth.php';

// Initialisation de la session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

/**
 * Envoie une réponse JSON
 */
function sendResponse(array $data, int $statusCode = 200): void {
    if (USE_MOCK_DB) {
        $data['_mock_mode'] = true;
    }
    
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Envoie une erreur JSON
 */
function sendError(string $message, int $statusCode = 400): void {
    sendResponse([
        'success' => false,
        'error' => $message
    ], $statusCode);
}

/**
 * Fusionne les avertissements globaux du JSON LLM brut après applySafetyLayer().
 *
 * @param array<string, mixed> $tutorial
 * @param array<string, mixed> $raw
 * @return array<string, mixed>
 */
function tutorial_merge_raw_global_warnings(array $tutorial, array $raw): array
{
    $extra = $raw['global_warnings'] ?? [];
    if (!is_array($extra)) {
        return $tutorial;
    }

    $gw = $tutorial['global_warnings'] ?? [];
    if (!is_array($gw)) {
        $gw = [];
    }

    foreach ($extra as $g) {
        if ($g === null) {
            continue;
        }
        $s = trim((string) $g);
        if ($s !== '' && strcasecmp($s, 'null') !== 0) {
            $gw[] = $s;
        }
    }

    $tutorial['global_warnings'] = array_values(array_unique($gw));

    return $tutorial;
}

/**
 * Persiste un tutoriel normalisé en base (SQLite/MySQL ou mock).
 *
 * @param array<string, mixed> $tutorial Steps en tableaux PHP
 * @return int|null ID inséré ou null en cas d'échec
 */
function saveTutorial(array $tutorial, string $actionType, ?int $dbVehicleId): ?int
{
    if (!defined('LLM_TUTORIAL_LOADED')) {
        require_once __DIR__ . '/../includes/llm_tutorial.php';
    }

    $tutorial['action_type'] = $actionType;

    if (USE_MOCK_DB) {
        return MockDatabase::saveTutorial([
            'vehicle_id' => $dbVehicleId,
            'title' => (string) ($tutorial['title'] ?? ''),
            'action_type' => $actionType,
            'description' => (string) ($tutorial['description'] ?? ''),
            'steps' => $tutorial['steps'] ?? [],
            'tools_required' => $tutorial['tools_required'] ?? [],
            'parts_required' => $tutorial['parts_required'] ?? [],
            'danger_level' => (string) ($tutorial['danger_level'] ?? 'none'),
            'global_warnings' => $tutorial['global_warnings'] ?? [],
            'estimated_time' => $tutorial['estimated_time'] ?? null,
            'difficulty' => (string) ($tutorial['difficulty'] ?? 'moyen'),
        ]);
    }

    try {
        $db = getDB();
        $row = parseTutorialResponse($tutorial, $dbVehicleId);
        $stmt = $db->prepare(
            'INSERT INTO tutorials
            (vehicle_id, title, action_type, description, steps, tools_required, parts_required,
             danger_level, global_warnings, estimated_time, difficulty, session_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $est = (int) ($row['estimated_time'] ?? 0);
        $stmt->execute([
            $row['vehicle_id'],
            $row['title'],
            $actionType,
            $row['description'],
            $row['steps'],
            $row['tools_required'],
            $row['parts_required'],
            $row['danger_level'],
            $row['global_warnings'],
            $est > 0 ? $est : null,
            $row['difficulty'],
            $row['session_id'],
        ]);

        return (int) $db->lastInsertId();
    } catch (Throwable $e) {
        error_log('saveTutorial: ' . $e->getMessage());

        return null;
    }
}

/**
 * Normalise, sécurise et sauvegarde un tutoriel issu du LLM.
 *
 * @param array<string, mixed> $rawLlmTutorial JSON brut parsé
 * @param array<string, mixed>|null $vehicleRow Ligne vehicles (SELECT *)
 * @param array<string, mixed>|null $vehContext Contexte session (getCurrentVehicleContext)
 * @return array{tutorial: array<string, mixed>, tutorial_id: int}|null
 */
function tutorial_finalize_llm_tutorial(
    array $rawLlmTutorial,
    string $actionType,
    ?array $vehicleRow,
    ?array $vehContext,
    ?int $sessionVehicleId
): ?array {
    if (!defined('LLM_TUTORIAL_LOADED')) {
        require_once __DIR__ . '/../includes/llm_tutorial.php';
    }

    $norm = tutorial_normalize_llm_payload($rawLlmTutorial);
    if ($norm === null) {
        return null;
    }

    $tutorial = $norm;
    if ($vehicleRow) {
        $tutorial['title'] .= ' - ' . $vehicleRow['brand'] . ' ' . $vehicleRow['model'];
        $tutorial['vehicle'] = [
            'brand' => $vehicleRow['brand'],
            'model' => $vehicleRow['model'],
            'year' => (int) ($vehicleRow['year'] ?? 0),
        ];
    }

    $tutorial = applySafetyLayer($tutorial);
    $tutorial = tutorial_merge_raw_global_warnings($tutorial, $rawLlmTutorial);

    $dbVehicleId = $vehContext !== null ? $sessionVehicleId : null;
    $tutorialId = saveTutorial($tutorial, $actionType, $dbVehicleId);
    if ($tutorialId === null) {
        return null;
    }

    $tutorial['id'] = $tutorialId;
    unset($tutorial['action_type']);

    return [
        'tutorial' => $tutorial,
        'tutorial_id' => $tutorialId,
    ];
}

// ============================================
// MOCK DATA: Tutoriels pré-définis
// ============================================

/**
 * Base de données mockée des tutoriels
 */
function getMockTutorials(): array {
    return [
        'vidange' => [
            'title' => 'Vidange moteur complète',
            'description' => 'Guide étape par étape pour effectuer une vidange d\'huile moteur et remplacer le filtre à huile.',
            'estimated_time' => 45,
            'difficulty' => 'facile',
            'tools_required' => [
                'Clé à filtre à huile',
                'Clé plate ou à douille (selon bouchon de vidange)',
                'Bac de récupération d\'huile',
                'Entonnoir',
                'Chiffons propres',
                'Gants de protection'
            ],
            'parts_required' => [
                'Huile moteur (quantité selon véhicule)',
                'Filtre à huile neuf',
                'Joint de bouchon de vidange'
            ],
            'steps' => [
                [
                    'title' => 'Préparation',
                    'description' => 'Placez le véhicule sur une surface plane. Si le moteur est froid, faites-le tourner 2-3 minutes pour fluidifier l\'huile. Coupez le moteur et laissez reposer 5 minutes.'
                ],
                [
                    'title' => 'Accès au bouchon de vidange',
                    'description' => 'Levez le véhicule avec un cric et sécurisez-le avec des chandelles. Localisez le bouchon de vidange sous le carter d\'huile.'
                ],
                [
                    'title' => 'Vidange de l\'huile usagée',
                    'description' => 'Placez le bac de récupération sous le bouchon. Dévissez le bouchon et laissez l\'huile s\'écouler complètement (environ 10-15 minutes). Attention, l\'huile peut être chaude !'
                ],
                [
                    'title' => 'Remplacement du filtre à huile',
                    'description' => 'Localisez le filtre à huile (généralement sur le côté du bloc moteur). Dévissez-le à la main ou avec une clé à filtre. Appliquez un peu d\'huile neuve sur le joint du nouveau filtre et vissez-le à la main.'
                ],
                [
                    'title' => 'Remontage du bouchon',
                    'description' => 'Remplacez le joint du bouchon de vidange si nécessaire. Revissez le bouchon fermement sans forcer (couple de serrage selon constructeur).'
                ],
                [
                    'title' => 'Remplissage d\'huile neuve',
                    'description' => 'Ouvrez le bouchon de remplissage sur le dessus du moteur. Versez l\'huile neuve avec un entonnoir. Vérifiez le niveau avec la jauge : il doit être entre MIN et MAX.'
                ],
                [
                    'title' => 'Vérification finale',
                    'description' => 'Démarrez le moteur et laissez tourner 1-2 minutes. Coupez le moteur, attendez 2 minutes, puis revérifiez le niveau. Complétez si nécessaire. Vérifiez l\'absence de fuites.'
                ]
            ]
        ],
        
        'plaquettes' => [
            'title' => 'Remplacement des plaquettes de frein avant',
            'description' => 'Tutoriel complet pour remplacer les plaquettes de frein avant de votre véhicule.',
            'estimated_time' => 60,
            'difficulty' => 'moyen',
            'tools_required' => [
                'Cric et chandelles',
                'Clé à roue',
                'Clés Allen ou Torx (selon étrier)',
                'Repousse-piston d\'étrier',
                'Nettoyant frein',
                'Graisse cuivre',
                'Gants de protection'
            ],
            'parts_required' => [
                'Jeu de plaquettes avant neuves (adaptées au véhicule)'
            ],
            'steps' => [
                [
                    'title' => 'Sécurisation du véhicule',
                    'description' => 'Desserrez les boulons de roue avant de lever le véhicule. Levez la voiture et placez-la sur chandelles. Retirez la roue.'
                ],
                [
                    'title' => 'Démontage de l\'étrier',
                    'description' => 'Localisez les vis de fixation de l\'étrier (généralement 2 vis à l\'arrière). Dévissez-les et suspendez l\'étrier avec un fil de fer pour ne pas tirer sur le flexible de frein.'
                ],
                [
                    'title' => 'Retrait des plaquettes usées',
                    'description' => 'Retirez les anciennes plaquettes du support d\'étrier. Notez leur position (intérieure/extérieure). Nettoyez le support avec du nettoyant frein.'
                ],
                [
                    'title' => 'Repoussage du piston',
                    'description' => 'Utilisez le repousse-piston pour rentrer le piston dans l\'étrier. Allez doucement et vérifiez le niveau de liquide de frein (il va remonter).'
                ],
                [
                    'title' => 'Installation des plaquettes neuves',
                    'description' => 'Appliquez de la graisse cuivre sur le dos des plaquettes (pas sur la surface de friction !). Installez les plaquettes dans le support.'
                ],
                [
                    'title' => 'Remontage de l\'étrier',
                    'description' => 'Repositionnez l\'étrier par-dessus les plaquettes. Revissez les vis de fixation au couple recommandé.'
                ],
                [
                    'title' => 'Remontage de la roue',
                    'description' => 'Remontez la roue et serrez les boulons en croix. Descendez le véhicule et finissez le serrage au couple.'
                ],
                [
                    'title' => 'Test du système de freinage',
                    'description' => 'Avant de démarrer, pompez plusieurs fois la pédale de frein pour rapprocher les plaquettes du disque. Testez les freins à basse vitesse dans un endroit sécurisé. Effectuez un rodage doux sur 200 km.'
                ]
            ]
        ],
        
        'purge frein' => [
            'title' => 'Purge du circuit de freinage',
            'description' => 'Procédure pour purger le circuit de freinage et éliminer l\'air du système.',
            'estimated_time' => 45,
            'difficulty' => 'moyen',
            'tools_required' => [
                'Clé de purge (8 ou 10mm selon véhicule)',
                'Tuyau transparent',
                'Bocal de récupération',
                'Liquide de frein neuf DOT4',
                'Lunettes de protection',
                'Gants en nitrile'
            ],
            'parts_required' => [
                'Liquide de frein DOT4 (environ 1L)'
            ],
            'steps' => [
                [
                    'title' => 'Préparation et sécurité',
                    'description' => 'Le liquide de frein est très corrosif ! Portez lunettes et gants. Protégez la carrosserie et les éléments en caoutchouc. Vérifiez le niveau dans le bocal de frein.'
                ],
                [
                    'title' => 'Ordre de purge',
                    'description' => 'La purge se fait du point le plus éloigné au plus proche du maître-cylindre : arrière droit, arrière gauche, avant droit, avant gauche.'
                ],
                [
                    'title' => 'Préparation de la vis de purge',
                    'description' => 'Localisez la vis de purge sur l\'étrier. Connectez le tuyau transparent dessus et plongez l\'autre extrémité dans un bocal avec un peu de liquide de frein.'
                ],
                [
                    'title' => 'Procédure de purge',
                    'description' => 'Un assistant appuie sur la pédale de frein et la maintient enfoncée. Vous ouvrez la vis de purge : du liquide (et des bulles) sort. Refermez la vis AVANT que l\'assistant relâche la pédale. Répétez jusqu\'à ce qu\'il n\'y ait plus de bulles.'
                ],
                [
                    'title' => 'Surveillance du niveau',
                    'description' => 'Vérifiez régulièrement le niveau dans le bocal de frein. Ne le laissez JAMAIS se vider complètement, sinon vous réintroduisez de l\'air !'
                ],
                [
                    'title' => 'Purge des autres roues',
                    'description' => 'Répétez l\'opération sur chaque roue dans l\'ordre indiqué. Comptez environ 10 pompages par roue minimum.'
                ],
                [
                    'title' => 'Vérification finale',
                    'description' => 'Complétez le niveau de liquide de frein jusqu\'au repère MAX. La pédale doit être ferme et ne pas s\'enfoncer. Testez les freins à basse vitesse.'
                ]
            ]
        ],
        
        'batterie' => [
            'title' => 'Remplacement de la batterie',
            'description' => 'Guide pour remplacer la batterie de votre véhicule en toute sécurité.',
            'estimated_time' => 20,
            'difficulty' => 'facile',
            'tools_required' => [
                'Clés plates 10mm et 13mm',
                'Brosse métallique',
                'Graisse diélectrique ou vaseline',
                'Gants de protection',
                'Lunettes de protection'
            ],
            'parts_required' => [
                'Batterie neuve (même dimensions et capacité)'
            ],
            'steps' => [
                [
                    'title' => 'Préparation et sécurité',
                    'description' => 'Coupez le moteur et retirez la clé de contact. Attendez quelques minutes avant de toucher à la batterie. Portez gants et lunettes de protection.'
                ],
                [
                    'title' => 'Débranchement de la borne négative',
                    'description' => 'TOUJOURS commencer par la borne négative (-) noire ! Dévissez l\'écrou et retirez la cosse. Écartez-la de la batterie.'
                ],
                [
                    'title' => 'Débranchement de la borne positive',
                    'description' => 'Dévissez l\'écrou de la borne positive (+) rouge et retirez la cosse.'
                ],
                [
                    'title' => 'Retrait de la batterie',
                    'description' => 'Retirez la fixation de maintien de la batterie. Soulevez la batterie avec précaution (poids 15-25 kg). Posez-la dans un endroit stable.'
                ],
                [
                    'title' => 'Nettoyage des cosses',
                    'description' => 'Nettoyez les cosses avec une brosse métallique pour éliminer toute trace d\'oxydation. Nettoyez également le support de batterie.'
                ],
                [
                    'title' => 'Installation de la nouvelle batterie',
                    'description' => 'Placez la nouvelle batterie dans son logement. Vérifiez que les bornes sont bien orientées. Fixez le système de maintien.'
                ],
                [
                    'title' => 'Branchement de la borne positive',
                    'description' => 'TOUJOURS commencer par la borne positive (+) lors du branchement ! Vissez l\'écrou fermement. Appliquez de la graisse diélectrique.'
                ],
                [
                    'title' => 'Branchement de la borne négative',
                    'description' => 'Branchez la borne négative (-) et serrez l\'écrou. Appliquez de la graisse diélectrique. Vérifiez que les branchements sont bien serrés.'
                ],
                [
                    'title' => 'Vérification finale',
                    'description' => 'Démarrez le véhicule pour vérifier le bon fonctionnement. Vous devrez peut-être reprogrammer l\'horloge et la radio.'
                ]
            ]
        ],
        
        'filtre air' => [
            'title' => 'Remplacement du filtre à air',
            'description' => 'Procédure simple pour remplacer le filtre à air du moteur.',
            'estimated_time' => 15,
            'difficulty' => 'facile',
            'tools_required' => [
                'Tournevis plat ou cruciforme (selon modèle)',
                'Chiffon propre'
            ],
            'parts_required' => [
                'Filtre à air neuf (référence adaptée au véhicule)'
            ],
            'steps' => [
                [
                    'title' => 'Localisation du boîtier de filtre à air',
                    'description' => 'Ouvrez le capot. Le boîtier de filtre à air est généralement une boîte noire rectangulaire située près du moteur, reliée à une gaine d\'admission d\'air.'
                ],
                [
                    'title' => 'Ouverture du boîtier',
                    'description' => 'Dévissez ou déclipsez les attaches du boîtier de filtre à air. Selon les modèles, ce peut être des vis, des clips métalliques ou des attaches rapides.'
                ],
                [
                    'title' => 'Retrait de l\'ancien filtre',
                    'description' => 'Soulevez le couvercle et retirez délicatement l\'ancien filtre. Notez son sens d\'installation.'
                ],
                [
                    'title' => 'Nettoyage du boîtier',
                    'description' => 'Nettoyez l\'intérieur du boîtier avec un chiffon propre pour retirer les poussières et débris. Ne laissez rien tomber dans le conduit d\'admission.'
                ],
                [
                    'title' => 'Installation du nouveau filtre',
                    'description' => 'Placez le nouveau filtre dans le bon sens (repère de sens si présent). Assurez-vous qu\'il est bien positionné et qu\'il ne dépasse pas.'
                ],
                [
                    'title' => 'Fermeture du boîtier',
                    'description' => 'Replacez le couvercle et refixez toutes les attaches. Vérifiez que le boîtier est bien étanche.'
                ]
            ]
        ],
        
        'bougies' => [
            'title' => 'Remplacement des bougies d\'allumage',
            'description' => 'Guide pour remplacer les bougies d\'allumage d\'un moteur essence.',
            'estimated_time' => 40,
            'difficulty' => 'moyen',
            'tools_required' => [
                'Clé à bougie (16mm ou 21mm selon modèle)',
                'Rallonge et cardan',
                'Jauge d\'écartement',
                'Soufflette ou compresseur',
                'Graisse anti-grippage'
            ],
            'parts_required' => [
                'Jeu de bougies neuves (type et écartement selon véhicule)'
            ],
            'steps' => [
                [
                    'title' => 'Préparation',
                    'description' => 'Travaillez sur un moteur froid. Débranchez la borne négative de la batterie pour éviter tout risque.'
                ],
                [
                    'title' => 'Accès aux bougies',
                    'description' => 'Retirez le cache moteur si présent. Localisez les bougies (sur le dessus du moteur, reliées par des câbles ou bobines individuelles).'
                ],
                [
                    'title' => 'Déconnexion des bobines/câbles',
                    'description' => 'Déconnectez les connecteurs électriques des bobines. Retirez les bobines individuelles ou les câbles d\'allumage en tirant bien droit.'
                ],
                [
                    'title' => 'Nettoyage autour des bougies',
                    'description' => 'Soufflez autour des puits de bougies pour éviter que des débris tombent dans les cylindres lors du démontage.'
                ],
                [
                    'title' => 'Démontage des bougies',
                    'description' => 'Dévissez chaque bougie avec la clé à bougie. Allez doucement pour ne pas forcer si ça résiste (appliquez du dégrippant et laissez agir).'
                ],
                [
                    'title' => 'Vérification des nouvelles bougies',
                    'description' => 'Vérifiez l\'écartement des électrodes avec une jauge (valeur dans le manuel du véhicule). Ajustez si nécessaire.'
                ],
                [
                    'title' => 'Installation des nouvelles bougies',
                    'description' => 'Appliquez un peu de graisse anti-grippage sur les filets. Vissez d\'abord à la main pour éviter le faux filetage, puis serrez au couple recommandé (généralement 20-25 Nm).'
                ],
                [
                    'title' => 'Remontage',
                    'description' => 'Remontez les bobines ou câbles dans le bon ordre. Reconnectez les connecteurs électriques. Rebranchez la batterie.'
                ],
                [
                    'title' => 'Test final',
                    'description' => 'Démarrez le moteur et vérifiez qu\'il tourne de façon régulière. Vérifiez l\'absence de ratés ou vibrations anormales.'
                ]
            ]
        ],
        
        'liquide refroidissement' => [
            'title' => 'Remplacement du liquide de refroidissement',
            'description' => 'Procédure pour vidanger et remplacer le liquide de refroidissement du moteur.',
            'estimated_time' => 60,
            'difficulty' => 'moyen',
            'tools_required' => [
                'Bac de récupération (10L minimum)',
                'Pince multiprise',
                'Entonnoir',
                'Testeur de liquide de refroidissement',
                'Gants de protection',
                'Lunettes de protection'
            ],
            'parts_required' => [
                'Liquide de refroidissement neuf (type selon véhicule, environ 6-8L)',
                'Eau déminéralisée (si mélange à faire)'
            ],
            'steps' => [
                [
                    'title' => 'Sécurité et préparation',
                    'description' => 'ATTENDEZ que le moteur soit complètement froid ! N\'ouvrez JAMAIS le bouchon du radiateur moteur chaud (risque de brûlure grave). Le liquide de refroidissement est toxique.'
                ],
                [
                    'title' => 'Ouverture du circuit',
                    'description' => 'Ouvrez le bouchon du vase d\'expansion pour casser la pression résiduelle.'
                ],
                [
                    'title' => 'Vidange du circuit',
                    'description' => 'Placez le bac sous le radiateur. Ouvrez le robinet de vidange (en bas du radiateur) ou déconnectez la durite inférieure. Laissez s\'écouler complètement.'
                ],
                [
                    'title' => 'Rinçage (optionnel mais recommandé)',
                    'description' => 'Refermez le circuit et remplissez avec de l\'eau claire. Faites tourner le moteur 5 minutes puis vidangez à nouveau.'
                ],
                [
                    'title' => 'Remplissage',
                    'description' => 'Refermez le robinet/reconnectez la durite. Remplissez lentement avec le liquide de refroidissement neuf par le vase d\'expansion jusqu\'au niveau MAX.'
                ],
                [
                    'title' => 'Purge du circuit',
                    'description' => 'Certains véhicules ont des vis de purge sur le circuit. Ouvrez-les et fermez quand le liquide coule sans bulles. Faites tourner le moteur avec le chauffage à fond.'
                ],
                [
                    'title' => 'Vérification et complément',
                    'description' => 'Laissez le moteur tourner jusqu\'à ce que le ventilateur se déclenche. Coupez, laissez refroidir, puis vérifiez et complétez le niveau si nécessaire.'
                ]
            ]
        ],
        
        'essuie-glaces' => [
            'title' => 'Remplacement des balais d\'essuie-glaces',
            'description' => 'Guide rapide pour changer les balais d\'essuie-glaces.',
            'estimated_time' => 10,
            'difficulty' => 'facile',
            'tools_required' => [
                'Aucun outil nécessaire'
            ],
            'parts_required' => [
                'Balais d\'essuie-glaces neufs (taille adaptée : avant et arrière)'
            ],
            'steps' => [
                [
                    'title' => 'Position de service',
                    'description' => 'Soulevez les bras d\'essuie-glaces et bloquez-les en position verticale. Sur certains véhicules, activez le mode service (contact coupé juste après usage des essuie-glaces).'
                ],
                [
                    'title' => 'Identification du système de fixation',
                    'description' => 'Identifiez le type de fixation : crochet (le plus courant), bouton poussoir, ou pince. Le type détermine la méthode de déclipsage.'
                ],
                [
                    'title' => 'Retrait de l\'ancien balai',
                    'description' => 'Pour un crochet : appuyez sur la languette de déverrouillage et faites glisser le balai vers le bas. Pour un bouton poussoir : appuyez et tirez.'
                ],
                [
                    'title' => 'Installation du nouveau balai',
                    'description' => 'Positionnez le nouveau balai sur le bras et faites-le glisser jusqu\'au clic de verrouillage. Vérifiez qu\'il est bien fixé.'
                ],
                [
                    'title' => 'Test de fonctionnement',
                    'description' => 'Rabaissez délicatement les bras sur le pare-brise. Testez les essuie-glaces avec du liquide lave-glace. Vérifiez qu\'il n\'y a pas de traces ni de bruits.'
                ]
            ]
        ]
    ];
}

// ============================================
// ROUTAGE DES ACTIONS
// ============================================

if (!defined('TUTORIAL_API_ROUTER_DISABLED')) {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($action === 'suggestions') {
        handleGetSuggestions();
        exit;
    }

    try {
        if (USE_MOCK_DB) {
            switch ($action) {
                case 'generate':
                    handleGenerateTutorialMock();
                    break;
                case 'get':
                    handleGetTutorialMock();
                    break;
                case 'list':
                    handleListTutorialsMock();
                    break;
                default:
                    sendError('Action non reconnue. Actions disponibles: generate, get, list, suggestions', 400);
            }
        } else {
            $db = getDB();

            switch ($action) {
                case 'generate':
                    handleGenerateTutorial($db);
                    break;
                case 'get':
                    handleGetTutorial($db);
                    break;
                case 'list':
                    handleListTutorials($db);
                    break;
                default:
                    sendError('Action non reconnue. Actions disponibles: generate, get, list, suggestions', 400);
            }
        }
    } catch (PDOException $e) {
        if (APP_DEBUG) {
            sendError('Erreur base de données: ' . $e->getMessage(), 500);
        } else {
            sendError('Erreur interne du serveur', 500);
        }
    } catch (Exception $e) {
        sendError($e->getMessage(), 400);
    }
}

// ============================================
// HANDLERS MODE MOCK
// ============================================

/**
 * [MOCK] Génère un tutoriel
 */
function handleGenerateTutorialMock(): void {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $actionType = strtolower(trim($input['action_type'] ?? ''));
    $vehicleId = isset($_SESSION['vehicle_id']) ? (int) $_SESSION['vehicle_id'] : null;
    
    if (empty($actionType)) {
        sendError('Le type d\'action est requis (ex: vidange, plaquettes, etc.)');
    }

    if (!function_exists('getEffectiveLlmProvider')) {
        require_once __DIR__ . '/../includes/byok.php';
    }
    $provider = getEffectiveLlmProvider();
    byok_assert_provider_usable($provider);

    demo_auth_consume_quota_start('tutorial');
    
    $vehicle = null;
    if ($vehicleId) {
        $vehicle = MockDatabase::getVehicle($vehicleId);
    }

    if (!defined('LLM_TUTORIAL_LOADED')) {
        require_once __DIR__ . '/../includes/llm_tutorial.php';
    }
    if (!function_exists('getCurrentVehicleContext')) {
        require_once __DIR__ . '/../includes/vehicle_context.php';
    }

    set_time_limit(300);
    if ($provider !== null) {
        $vehContext = getCurrentVehicleContext();
        $llmRes = callLlmForTutorial($actionType, $vehContext, $provider);
        if (($llmRes['success'] ?? false) === true) {
            $finalized = tutorial_finalize_llm_tutorial(
                $llmRes['tutorial'],
                $actionType,
                $vehicle,
                $vehContext,
                $vehicleId
            );
            if ($finalized !== null) {
                $response = [
                    'success' => true,
                    'tutorial' => $finalized['tutorial'],
                    'generated_by' => 'llm',
                ];
                if (!empty($llmRes['sources'])) {
                    $response['sources'] = $llmRes['sources'];
                }
                sendResponse($response, 201);
                return;
            }
            error_log('LLM tutorial (mock): normalisation impossible après parse JSON');
        } else {
            error_log('LLM tutorial failed (mock): ' . ($llmRes['error'] ?? 'unknown'));
        }
    }
    
    // Recherche le tutoriel
    $mockTutorials = getMockTutorials();
    $tutorial = null;
    
    foreach ($mockTutorials as $key => $tuto) {
        if (strpos($actionType, $key) !== false || strpos($key, $actionType) !== false) {
            $tutorial = $tuto;
            break;
        }
    }
    
    if (!$tutorial) {
        $tutorial = generateGenericTutorial($actionType);
    }
    
    // Personnalise avec le véhicule
    if ($vehicle) {
        $tutorial['title'] .= ' - ' . $vehicle['brand'] . ' ' . $vehicle['model'];
        $tutorial['vehicle'] = [
            'brand' => $vehicle['brand'],
            'model' => $vehicle['model'],
            'year' => (int) $vehicle['year']
        ];
    }
    
    // Applique la couche de sécurité
    $tutorial = applySafetyLayer($tutorial);
    
    // Sauvegarde en mock
    $tutorialId = MockDatabase::saveTutorial([
        'vehicle_id' => $vehicleId,
        'title' => $tutorial['title'],
        'action_type' => $actionType,
        'description' => $tutorial['description'],
        'steps' => $tutorial['steps'],
        'tools_required' => $tutorial['tools_required'] ?? [],
        'parts_required' => $tutorial['parts_required'] ?? [],
        'danger_level' => $tutorial['danger_level'],
        'global_warnings' => $tutorial['global_warnings'] ?? [],
        'estimated_time' => $tutorial['estimated_time'] ?? null,
        'difficulty' => $tutorial['difficulty'] ?? 'moyen'
    ]);
    
    $tutorial['id'] = $tutorialId;
    
    sendResponse([
        'success' => true,
        'tutorial' => $tutorial
    ], 201);
}

/**
 * [MOCK] Récupère un tutoriel par ID
 */
function handleGetTutorialMock(): void {
    $tutorialId = (int) ($_GET['id'] ?? 0);
    
    if (!$tutorialId) {
        sendError('L\'ID du tutoriel est requis');
    }
    
    $tutorial = MockDatabase::getTutorial($tutorialId);
    
    if (!$tutorial) {
        sendError('Tutoriel non trouvé', 404);
    }
    
    $result = [
        'id' => (int) $tutorial['id'],
        'title' => $tutorial['title'],
        'description' => $tutorial['description'],
        'action_type' => $tutorial['action_type'],
        'steps' => $tutorial['steps'],
        'tools_required' => $tutorial['tools_required'],
        'parts_required' => $tutorial['parts_required'],
        'danger_level' => $tutorial['danger_level'],
        'global_warnings' => $tutorial['global_warnings'],
        'estimated_time' => $tutorial['estimated_time'],
        'difficulty' => $tutorial['difficulty'],
        'created_at' => $tutorial['created_at']
    ];
    
    if ($tutorial['vehicle_id']) {
        $vehicle = MockDatabase::getVehicle($tutorial['vehicle_id']);
        if ($vehicle) {
            $result['vehicle'] = [
                'brand' => $vehicle['brand'],
                'model' => $vehicle['model'],
                'year' => (int) $vehicle['year']
            ];
        }
    }
    
    sendResponse([
        'success' => true,
        'tutorial' => $result
    ]);
}

/**
 * [MOCK] Liste les tutoriels récents
 */
function handleListTutorialsMock(): void {
    $limit = min((int) ($_GET['limit'] ?? 10), 50);
    
    $tutorials = MockDatabase::listTutorials($limit);
    
    sendResponse([
        'success' => true,
        'tutorials' => $tutorials,
        'count' => count($tutorials)
    ]);
}

// ============================================
// HANDLERS MODE NORMAL
// ============================================

/**
 * Génère un tutoriel générique pour les actions non reconnues
 */
function generateGenericTutorial(string $actionType): array {
    return [
        'title' => 'Tutoriel: ' . ucfirst($actionType),
        'description' => "Guide pour effectuer l'opération : " . $actionType . ". Ce tutoriel générique vous guidera à travers les étapes de base.",
        'estimated_time' => 30,
        'difficulty' => 'moyen',
        'tools_required' => [
            'Outillage standard automobile',
            'Équipements de protection (gants, lunettes)',
            'Manuel technique du véhicule'
        ],
        'parts_required' => [
            'Pièces spécifiques selon véhicule - consultez votre garagiste'
        ],
        'steps' => [
            [
                'title' => 'Préparation et sécurité',
                'description' => 'Avant toute intervention, assurez-vous que le véhicule est sur une surface stable. Portez vos équipements de protection. Consultez le manuel technique de votre véhicule pour les spécifications.'
            ],
            [
                'title' => 'Diagnostic initial',
                'description' => "Identifiez précisément la zone d'intervention pour : " . $actionType . ". Vérifiez l'état des composants concernés."
            ],
            [
                'title' => 'Intervention',
                'description' => "Effectuez l'opération de " . $actionType . " en suivant les préconisations du constructeur. Prenez des photos avant démontage si nécessaire."
            ],
            [
                'title' => 'Vérification finale',
                'description' => 'Vérifiez le bon fonctionnement après intervention. Faites un test en conditions réelles si applicable. En cas de doute, consultez un professionnel.'
            ]
        ]
    ];
}

/**
 * Génère un tutoriel basé sur l'action demandée
 */
function handleGenerateTutorial(PDO $db): void {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $actionType = strtolower(trim($input['action_type'] ?? ''));
    $vehicleId = isset($_SESSION['vehicle_id']) ? (int) $_SESSION['vehicle_id'] : null;
    
    if (empty($actionType)) {
        sendError('Le type d\'action est requis (ex: vidange, plaquettes, etc.)');
    }

    if (!function_exists('getEffectiveLlmProvider')) {
        require_once __DIR__ . '/../includes/byok.php';
    }
    $provider = getEffectiveLlmProvider();
    byok_assert_provider_usable($provider);

    demo_auth_consume_quota_start('tutorial');
    
    $vehicle = null;
    if ($vehicleId) {
        $stmt = $db->prepare("SELECT * FROM vehicles WHERE id = ?");
        $stmt->execute([$vehicleId]);
        $vehicle = $stmt->fetch();
    }

    if (!defined('LLM_TUTORIAL_LOADED')) {
        require_once __DIR__ . '/../includes/llm_tutorial.php';
    }
    if (!function_exists('getCurrentVehicleContext')) {
        require_once __DIR__ . '/../includes/vehicle_context.php';
    }

    set_time_limit(300);
    if ($provider !== null) {
        $vehContext = getCurrentVehicleContext();
        $llmRes = callLlmForTutorial($actionType, $vehContext, $provider);
        if (($llmRes['success'] ?? false) === true) {
            $finalized = tutorial_finalize_llm_tutorial(
                $llmRes['tutorial'],
                $actionType,
                $vehicle ?: null,
                $vehContext,
                $vehicleId
            );
            if ($finalized !== null) {
                $response = [
                    'success' => true,
                    'tutorial' => $finalized['tutorial'],
                    'generated_by' => 'llm',
                ];
                if (!empty($llmRes['sources'])) {
                    $response['sources'] = $llmRes['sources'];
                }
                sendResponse($response, 201);
                return;
            }
            error_log('LLM tutorial: normalisation impossible après parse JSON');
        } else {
            error_log('LLM tutorial failed: ' . ($llmRes['error'] ?? 'unknown'));
        }
    }
    
    $mockTutorials = getMockTutorials();
    $tutorial = null;
    
    foreach ($mockTutorials as $key => $tuto) {
        if (strpos($actionType, $key) !== false || strpos($key, $actionType) !== false) {
            $tutorial = $tuto;
            break;
        }
    }
    
    if (!$tutorial) {
        $tutorial = generateGenericTutorial($actionType);
    }
    
    if ($vehicle) {
        $tutorial['title'] .= ' - ' . $vehicle['brand'] . ' ' . $vehicle['model'];
        $tutorial['vehicle'] = [
            'brand' => $vehicle['brand'],
            'model' => $vehicle['model'],
            'year' => (int) $vehicle['year']
        ];
    }
    
    $tutorial = applySafetyLayer($tutorial);
    
    $stmt = $db->prepare("
        INSERT INTO tutorials 
        (vehicle_id, title, action_type, description, steps, tools_required, parts_required, 
         danger_level, global_warnings, estimated_time, difficulty, session_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $vehicleId,
        $tutorial['title'],
        $actionType,
        $tutorial['description'],
        json_encode($tutorial['steps'], JSON_UNESCAPED_UNICODE),
        json_encode($tutorial['tools_required'] ?? [], JSON_UNESCAPED_UNICODE),
        json_encode($tutorial['parts_required'] ?? [], JSON_UNESCAPED_UNICODE),
        $tutorial['danger_level'],
        json_encode($tutorial['global_warnings'] ?? [], JSON_UNESCAPED_UNICODE),
        $tutorial['estimated_time'] ?? null,
        $tutorial['difficulty'] ?? 'moyen',
        session_id()
    ]);
    
    $tutorialId = $db->lastInsertId();
    $tutorial['id'] = (int) $tutorialId;
    
    sendResponse([
        'success' => true,
        'tutorial' => $tutorial
    ], 201);
}

/**
 * Récupère un tutoriel existant par son ID
 */
function handleGetTutorial(PDO $db): void {
    $tutorialId = (int) ($_GET['id'] ?? 0);
    
    if (!$tutorialId) {
        sendError('L\'ID du tutoriel est requis');
    }
    
    $stmt = $db->prepare("
        SELECT t.*, v.brand, v.model, v.year
        FROM tutorials t
        LEFT JOIN vehicles v ON t.vehicle_id = v.id
        WHERE t.id = ?
    ");
    $stmt->execute([$tutorialId]);
    $tutorial = $stmt->fetch();
    
    if (!$tutorial) {
        sendError('Tutoriel non trouvé', 404);
    }
    
    $result = [
        'id' => (int) $tutorial['id'],
        'title' => $tutorial['title'],
        'description' => $tutorial['description'],
        'action_type' => $tutorial['action_type'],
        'steps' => json_decode($tutorial['steps'], true),
        'tools_required' => json_decode($tutorial['tools_required'], true),
        'parts_required' => json_decode($tutorial['parts_required'], true),
        'danger_level' => $tutorial['danger_level'],
        'global_warnings' => json_decode($tutorial['global_warnings'], true),
        'estimated_time' => $tutorial['estimated_time'],
        'difficulty' => $tutorial['difficulty'],
        'created_at' => $tutorial['created_at']
    ];
    
    if ($tutorial['brand']) {
        $result['vehicle'] = [
            'brand' => $tutorial['brand'],
            'model' => $tutorial['model'],
            'year' => (int) $tutorial['year']
        ];
    }
    
    sendResponse([
        'success' => true,
        'tutorial' => $result
    ]);
}

/**
 * Liste les tutoriels récents de la session
 */
function handleListTutorials(PDO $db): void {
    $limit = min((int) ($_GET['limit'] ?? 10), 50);
    
    $stmt = $db->prepare("
        SELECT t.id, t.title, t.action_type, t.difficulty, t.estimated_time, 
               t.danger_level, t.created_at, v.brand, v.model
        FROM tutorials t
        LEFT JOIN vehicles v ON t.vehicle_id = v.id
        WHERE t.session_id = ?
        ORDER BY t.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([session_id(), $limit]);
    $tutorials = $stmt->fetchAll();
    
    sendResponse([
        'success' => true,
        'tutorials' => $tutorials,
        'count' => count($tutorials)
    ]);
}

/**
 * Retourne les suggestions d'actions disponibles
 */
function handleGetSuggestions(): void {
    $suggestions = [
        ['id' => 'vidange', 'label' => 'Vidange moteur', 'icon' => '🛢️', 'description' => 'Changer l\'huile moteur et le filtre'],
        ['id' => 'plaquettes', 'label' => 'Plaquettes de frein', 'icon' => '🛑', 'description' => 'Remplacer les plaquettes de frein'],
        ['id' => 'purge frein', 'label' => 'Purge freins', 'icon' => '💧', 'description' => 'Purger le circuit de freinage'],
        ['id' => 'batterie', 'label' => 'Batterie', 'icon' => '🔋', 'description' => 'Remplacer la batterie'],
        ['id' => 'filtre air', 'label' => 'Filtre à air', 'icon' => '💨', 'description' => 'Changer le filtre à air'],
        ['id' => 'bougies', 'label' => 'Bougies', 'icon' => '⚡', 'description' => 'Remplacer les bougies d\'allumage'],
        ['id' => 'liquide refroidissement', 'label' => 'Liquide refroidissement', 'icon' => '❄️', 'description' => 'Vidanger le liquide de refroidissement'],
        ['id' => 'essuie-glaces', 'label' => 'Essuie-glaces', 'icon' => '🌧️', 'description' => 'Changer les balais d\'essuie-glaces']
    ];
    
    sendResponse([
        'success' => true,
        'suggestions' => $suggestions
    ]);
}
