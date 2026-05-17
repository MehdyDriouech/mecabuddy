<?php
/**
 * MecaBuddy - API Diagnostic (Buddy Mode)
 * 
 * Endpoints pour le chat avec le buddy mécano :
 * - POST ?action=ask      → Envoie un message et reçoit une réponse
 * - GET  ?action=history  → Récupère l'historique des conversations
 * - POST ?action=clear    → Efface l'historique de la session
 * 
 * Structure conçue pour être facilement remplacée par un LLM
 * Supporte le mode MOCK (sans base de données MySQL)
 */

// Configuration des headers pour API JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
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
 * Ajoute les champs debug LLM au payload (action=ask) si APP_DEBUG.
 */
function appendLlmDebugPayload(array $payload, ?array $llmResult): array
{
    if (!APP_DEBUG || $llmResult === null) {
        return $payload;
    }

    $queryDetails = $llmResult['query_details'] ?? [];
    $queriesRun = $llmResult['queries_run'] ?? [];
    $sourcesCount = count($llmResult['sources'] ?? []);

    $payload['debug'] = [
        'web_searched' => (bool) ($llmResult['web_searched'] ?? false),
        'failsafe' => (bool) ($llmResult['failsafe'] ?? false),
        'serper_key_present' => !empty(getEffectiveSettings()['serper_api_key']),
        'sources_raw_count' => $sourcesCount,
        'provider_used' => $llmResult['provider_used'] ?? null,
        'sources_type' => $llmResult['sources_type'] ?? null,
        'search_provider' => $llmResult['search_provider'] ?? 'none',
        'queries_run' => $queriesRun,
        'query_details' => $queryDetails,
        'queries_attempted' => count($queriesRun) > 0 ? count($queriesRun) : 3,
    ];

    if (!function_exists('byok_debug_fields')) {
        require_once __DIR__ . '/../includes/byok.php';
    }
    $payload['debug'] = array_merge($payload['debug'], byok_debug_fields());

    return $payload;
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

// ============================================
// BUDDY RESPONSES - Réponses simulées du mécano
// ============================================

/**
 * Base de connaissances du Buddy pour les réponses simulées
 */
class BuddyBrain {
    
    private static array $patterns = [
        // Salutations
        'salut|bonjour|hello|hey|coucou|salutation' => [
            "Salut l'ami ! 👋 C'est MecaBuddy, ton pote mécano. Qu'est-ce qui t'amène aujourd'hui ?",
            "Hey ! Bienvenue dans le garage ! 🔧 Comment je peux t'aider ?",
            "Yo ! MecaBuddy à ton service ! Dis-moi ce qui ne va pas avec ta caisse ! 🚗"
        ],
        
        // Au revoir
        'au revoir|bye|a plus|ciao|à bientôt|merci' => [
            "À plus l'ami ! N'hésite pas à revenir si t'as d'autres questions ! 👋🔧",
            "De rien ! Bonne route et fais gaffe sur la route ! 🚗",
            "À la prochaine ! Et n'oublie pas : un véhicule bien entretenu, c'est un véhicule heureux ! 😄"
        ],
        
        // Problèmes de freins
        'frein|freinage|pédale molle|grince|crisse|plaquette' => [
            "Ah, les freins ! C'est SUPER important ça ! ⚠️\n\nSi ta pédale est molle, ça peut être :\n• De l'air dans le circuit → purge nécessaire\n• Plaquettes usées → à changer vite\n• Liquide de frein bas → à vérifier\n\nSi ça grince, c'est souvent les plaquettes qui sont mortes. Tu veux que je te génère un tuto ?",
            "Les freins qui font du bruit, faut pas rigoler avec ça ! 🛑\n\nVérifie d'abord l'épaisseur des plaquettes (minimum 3mm). Si elles sont bonnes, ça peut être de la rouille sur les disques (normal après la pluie) ou un problème d'étrier.\n\nTu veux plus de détails ?",
            "Question freins, je suis là ! Décris-moi ton souci plus précisément :\n• La pédale est dure ou molle ?\n• Ça fait du bruit au freinage ?\n• La voiture tire d'un côté ?\n\nChaque symptôme m'aide à cibler le problème ! 🔍"
        ],
        
        // Problèmes moteur
        'moteur|cale|démarre pas|démarrage|ratés|vibration|tremble' => [
            "Problème de moteur ? Pas de panique ! 🔧\n\nQuelques questions pour diagnostiquer :\n• Ça démarre et ça cale, ou ça démarre pas du tout ?\n• Tu entends un bruit particulier ?\n• Le voyant moteur est allumé ?\n\nDis-moi tout, je suis là pour t'aider !",
            "Aïe, le moteur fait des siennes ! 😬\n\nSi ça ne démarre pas :\n• Vérifie la batterie (voyants tableau de bord faibles ?)\n• Écoute le démarreur (clic-clic = batterie, silence total = démarreur ?)\n\nSi ça cale :\n• Possible encrassement, bougies usées, ou capteur HS\n\nT'as plus d'infos ?",
            "Un moteur qui vibre ou qui a des ratés, ça peut venir de plein de trucs ! 🤔\n\n• Bougies fatiguées (facile à changer)\n• Bobine d'allumage HS\n• Injecteur encrassé\n• Support moteur usé (pour les vibrations)\n\nDepuis quand ça fait ça ?"
        ],
        
        // Problèmes d'huile
        'huile|vidange|niveau huile|consomme huile|fuite huile' => [
            "L'huile, c'est le sang du moteur ! 🩸\n\nSi ton niveau baisse vite :\n• Vérifie s'il y a des taches sous la voiture (fuite)\n• Fumée bleue à l'échappement = consommation interne\n• Joints de cache-culasse à vérifier\n\nTu fais ta vidange tous les combien ?",
            "Une vidange régulière, c'est la base ! 💯\n\nGénéralement, c'est tous les 15 000-20 000 km ou 1 an. Mais vérifie ton carnet d'entretien !\n\nTu veux que je te génère un tuto vidange personnalisé pour ta voiture ?",
            "Fuite d'huile ? Faut trouver d'où ça vient ! 🔍\n\n• Joint de carter (le plus fréquent)\n• Joint de cache-culasse\n• Joint de boîte de vitesses\n• Turbo (si équipé)\n\nMets un carton sous la voiture cette nuit pour localiser la fuite !"
        ],
        
        // Problèmes de batterie
        'batterie|charge pas|voyant batterie|électrique' => [
            "Souci de batterie ? C'est souvent simple ! 🔋\n\n• Batterie de plus de 4-5 ans ? Elle est peut-être morte\n• Voyant batterie allumé moteur tournant = alternateur suspect\n• Beaucoup de petits trajets = batterie qui se décharge\n\nTu peux faire tester ta batterie gratuitement dans les centres auto !",
            "Les problèmes électriques, c'est souvent la batterie ! 😅\n\nSi la voiture démarre mal le matin froid :\n• Batterie fatiguée probable\n• Vérifie les cosses (oxydation ?)\n• Teste la tension (12.6V = OK, <12V = à charger ou changer)\n\nBesoin d'un tuto pour changer la batterie ?",
            "Le voyant batterie allumé moteur tournant, c'est généralement l'alternateur qui ne charge plus ! ⚠️\n\nVérifie :\n• La courroie d'alternateur (pas cassée ? bien tendue ?)\n• Les connexions\n\nSi l'alternateur est HS, la batterie va se vider progressivement. Rentre vite à la maison !"
        ],
        
        // Problèmes de refroidissement
        'chauffe|surchauffe|température|radiateur|refroidissement|ventilateur' => [
            "Moteur qui chauffe ? STOP ! 🌡️⚠️\n\nSi l'aiguille monte dans le rouge, ARRÊTE-TOI immédiatement ! Un moteur en surchauffe peut se détruire en quelques minutes.\n\nVérifie une fois refroidi :\n• Niveau de liquide de refroidissement\n• Fuites sous la voiture\n• Ventilateur qui tourne ?",
            "La température qui monte, faut pas traîner ! 🔥\n\nCauses fréquentes :\n• Manque de liquide de refroidissement\n• Thermostat bloqué fermé\n• Ventilateur HS\n• Radiateur bouché\n• Joint de culasse 😱 (cas grave)\n\nC'est arrivé une fois ou c'est récurrent ?",
            "Le système de refroidissement, c'est vital ! ❄️\n\nPour l'entretien préventif :\n• Change le liquide tous les 2-4 ans\n• Vérifie les durites (pas craquelées ?)\n• Nettoie le radiateur (moustiques, débris)\n\nTu veux un tuto pour la vidange du liquide de refroidissement ?"
        ],
        
        // Problèmes de pneus
        'pneu|roue|crevaison|usure|parallélisme|équilibrage' => [
            "Les pneus, c'est ta seule connexion avec la route ! 🛞\n\nVérifie régulièrement :\n• Pression (tous les mois)\n• Usure (témoin à 1.6mm minimum)\n• Usure irrégulière = parallélisme à faire\n\nQuel est ton souci exactement ?",
            "Usure anormale des pneus ? 🤔\n\n• Usure au centre = surgonflage\n• Usure sur les bords = sous-gonflage\n• Usure d'un seul côté = parallélisme HS\n• Usure en vagues = équilibrage ou amortisseurs\n\nQuand as-tu fait le parallélisme pour la dernière fois ?",
            "Pour les pneus, quelques règles d'or ! ✨\n\n• Permute-les tous les 10 000 km (avant ↔ arrière)\n• Vérifie la pression à froid\n• Attention à l'âge (max 6-8 ans même s'ils ont l'air neufs)\n\nQu'est-ce qui t'inquiète avec tes pneus ?"
        ],
        
        // Bruit suspect
        'bruit|claque|claquement|grince|siffle|couine' => [
            "Un bruit suspect ? Décris-le-moi ! 🔊\n\n• Ça vient d'où ? (avant, arrière, moteur, roues ?)\n• Quand ça se produit ? (freinage, virage, accélération ?)\n• C'est quel type de bruit ? (métallique, sourd, aigu ?)\n\nChaque bruit raconte une histoire ! 🕵️",
            "Les bruits, c'est le véhicule qui parle ! 👂\n\nQuelques classiques :\n• Couinement au freinage = plaquettes usées\n• Claquement en virage = cardan ou rotule\n• Sifflement courroie = tension ou usure\n• Grondement = roulement de roue\n\nÇa ressemble à quoi ton bruit ?",
            "Bruit de claquement ? Ça peut être plusieurs trucs ! 🔨\n\n• En roulant sur bosses = amortisseurs ou silentblocs\n• En accélérant = supports moteur\n• En tournant = cardans ou biellettes\n• Au freinage = plaquettes ou étrier\n\nDis-moi quand exactement ça claque !"
        ],
        
        // Fumée échappement
        'fumée|fume|échappement|blanc|bleu|noir' => [
            "De la fumée ? La couleur nous dit tout ! 💨\n\n• Fumée blanche = Normal à froid, sinon joint de culasse 😰\n• Fumée bleue = Huile qui brûle (segments, joint de queue de soupape)\n• Fumée noire = Mélange trop riche (injection, filtre à air)\n\nElle est de quelle couleur ta fumée ?",
            "Fumée à l'échappement, voyons ça ! 🌫️\n\n• Au démarrage à froid uniquement = condensation, normal !\n• Fumée persistante = problème à investiguer\n• Forte odeur d'essence = injection à vérifier\n\nC'est quand que ça fume le plus ?",
            "La fumée bleue, c'est de l'huile qui passe dans les cylindres ! 🛢️\n\n• Moteur fatigué (segments usés)\n• Joints de queue de soupape\n• Turbo qui prend du jeu (si équipé)\n\nTa voiture consomme de l'huile entre les vidanges ?"
        ],
        
        // Questions générales
        'quoi faire|que faire|conseil|aide|comment' => [
            "Je suis là pour t'aider ! 🙌\n\nDécris-moi ton problème et je ferai de mon mieux pour t'orienter. Tu peux aussi me demander de générer un tutoriel pour une opération d'entretien !\n\nAlors, c'est quoi le souci ?",
            "MecaBuddy à ton service ! 🔧\n\nJe peux t'aider avec :\n• Diagnostiquer un problème\n• Générer un tutoriel d'entretien\n• Donner des conseils mécanique\n\nDis-moi ce dont tu as besoin !",
            "Pas de souci, on va trouver une solution ! 💪\n\nExplique-moi :\n• Le problème que tu rencontres\n• Depuis quand ça se produit\n• Si tu as remarqué d'autres symptômes\n\nPlus tu me donnes d'infos, mieux je peux t'aider !"
        ],
        
        // Entretien général
        'entretien|révision|maintenance|vérifier|contrôle' => [
            "L'entretien régulier, c'est la clé d'une voiture fiable ! 🔑\n\nLes basiques à ne pas oublier :\n• Vidange huile : tous les 15-20 000 km\n• Freins : vérifier tous les 20 000 km\n• Pneus : pression mensuelle, permutation tous les 10 000 km\n• Liquides : niveau tous les mois\n\nTu veux des détails sur un point ?",
            "Une révision type, ça comprend : 📋\n\n• Vidange + filtre à huile\n• Filtre à air\n• Filtre à habitacle\n• Vérification des freins\n• Contrôle des niveaux\n• Diagnostic visuel\n\nTu fais ça toi-même ou en garage ?",
            "Bon réflexe de t'intéresser à l'entretien ! 👍\n\nUne voiture bien entretenue :\n• Consomme moins\n• Dure plus longtemps\n• A moins de pannes\n• Se revend mieux\n\nJe peux te générer des tutoriels pour n'importe quelle opération !"
        ]
    ];
    
    private static array $defaultResponses = [
        "Hmm, je suis pas sûr de bien comprendre... 🤔 Tu peux me réexpliquer ton problème ? Ou si tu veux, décris-moi les symptômes : bruits bizarres, voyants allumés, comportement anormal...",
        "Je capte pas trop là ! 😅 Dis-moi plus précisément ce qui se passe avec ta voiture. Genre : quand ça arrive, quel bruit ça fait, où ça se situe...",
        "Euh, tu peux préciser ? 🔧 Je suis MecaBuddy, ton pote mécano ! Parle-moi de ton problème auto et je ferai de mon mieux pour t'aider !",
        "J'ai pas tout compris... 🤷 Mais si tu me parles de freins, moteur, huile, batterie ou autre, je suis ton homme ! Enfin... ton buddy ! 😄"
    ];
    
    /**
     * Génère une réponse basée sur le message de l'utilisateur
     */
    public static function generateResponse(string $message, ?array $vehicle = null, array $history = []): array {
        $messageLower = mb_strtolower(trim($message), 'UTF-8');
        
        foreach (self::$patterns as $pattern => $responses) {
            if (preg_match('/(' . $pattern . ')/ui', $messageLower)) {
                $response = $responses[array_rand($responses)];
                
                if ($vehicle) {
                    $vehicleInfo = $vehicle['brand'] . ' ' . $vehicle['model'] . ' de ' . $vehicle['year'];
                    $response .= "\n\n📝 Je note que tu as une " . $vehicleInfo . " !";
                }
                
                return [
                    'response' => $response,
                    'detected_topic' => $pattern,
                    'confidence' => 0.85
                ];
            }
        }
        
        $response = self::$defaultResponses[array_rand(self::$defaultResponses)];
        
        return [
            'response' => $response,
            'detected_topic' => null,
            'confidence' => 0.3
        ];
    }
}

// ============================================
// ROUTAGE DES ACTIONS
// ============================================

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    // Mode MOCK
    if (USE_MOCK_DB) {
        switch ($action) {
            case 'ask':
                handleAskMock();
                break;
            case 'history':
                handleHistoryMock();
                break;
            case 'clear':
                handleClearMock();
                break;
            default:
                sendError('Action non reconnue. Actions disponibles: ask, history, clear', 400);
        }
    }
    // Mode NORMAL
    else {
        $db = getDB();
        
        switch ($action) {
            case 'ask':
                handleAsk($db);
                break;
            case 'history':
                handleHistory($db);
                break;
            case 'clear':
                handleClear($db);
                break;
            default:
                sendError('Action non reconnue. Actions disponibles: ask, history, clear', 400);
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

// ============================================
// HANDLERS MODE MOCK
// ============================================

/**
 * [MOCK] Traite un message utilisateur
 */
function handleAskMock(): void {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $message = trim($input['message'] ?? '');
    
    if (empty($message)) {
        sendError('Le message est requis');
    }
    
    if (mb_strlen($message) > 1000) {
        sendError('Le message est trop long (max 1000 caractères)');
    }

    if (!function_exists('getEffectiveLlmProvider')) {
        require_once __DIR__ . '/../includes/byok.php';
    }
    $provider = getEffectiveLlmProvider();
    byok_assert_provider_usable($provider);

    demo_auth_consume_quota_start('buddy');
    
    $vehicle = null;
    $vehicleId = null;
    
    if (isset($_SESSION['vehicle_id'])) {
        $vehicle = MockDatabase::getVehicle($_SESSION['vehicle_id']);
        $vehicleId = (int) $_SESSION['vehicle_id'];
    }
    
    $historyRows = [];
    if (isset($_SESSION['mock_db']['diagnostic_conversations']) && is_array($_SESSION['mock_db']['diagnostic_conversations'])) {
        $sessionId = session_id();
        $filtered = array_values(array_filter(
            $_SESSION['mock_db']['diagnostic_conversations'],
            static fn ($c) => is_array($c) && ($c['session_id'] ?? '') === $sessionId
        ));
        usort($filtered, static function ($a, $b) {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });
        $filtered = array_slice($filtered, 0, 10);
        usort($filtered, static function ($a, $b) {
            return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
        });
        $historyRows = $filtered;
    }
    
    $chatHistory = [];
    foreach ($historyRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $um = (string) ($row['user_message'] ?? '');
        $br = (string) ($row['buddy_response'] ?? '');
        $chatHistory[] = ['role' => 'user', 'content' => $um];
        $chatHistory[] = ['role' => 'assistant', 'content' => $br];
    }
    if (count($chatHistory) > 20) {
        $chatHistory = array_slice($chatHistory, -20);
    }
    
    $llmResult = null;
    
    if ($provider === null) {
        $result = BuddyBrain::generateResponse($message, $vehicle, $historyRows);
    } else {
        if (!defined('LLM_CHAT_LOADED')) {
            require_once __DIR__ . '/../includes/llm_chat.php';
        }
        require_once __DIR__ . '/../includes/vehicle_context.php';
        $llmVehicleContext = getCurrentVehicleContext();
        $llmResult = callLlmChat($chatHistory, $message, $provider, $llmVehicleContext);
        if (!($llmResult['success'] ?? false)) {
            error_log('diagnostic_api LLM (mock): ' . ($llmResult['error'] ?? 'unknown'));
            $llmResult = null;
            $result = BuddyBrain::generateResponse($message, $vehicle, $historyRows);
        } else {
            $result = [
                'response' => (string) ($llmResult['reply'] ?? ''),
                'detected_topic' => null,
                'confidence' => null,
            ];
        }
    }
    
    // Vérifie les éléments dangereux
    $safetyWarning = null;
    if (isDangerousOperation($message)) {
        $safetyWarning = getGeneralSafetyMessage($message);
    }
    
    $context = [
        'vehicle' => $vehicle,
        'detected_topic' => $result['detected_topic'],
        'confidence' => $result['confidence'],
    ];
    if ($llmResult !== null) {
        if (!empty($llmResult['sources']) && is_array($llmResult['sources'])) {
            $context['sources'] = $llmResult['sources'];
        }
        if (!empty($llmResult['failsafe'])) {
            $context['failsafe'] = true;
        }
    }
    
    // Sauvegarde la conversation
    $conversationId = MockDatabase::saveConversation([
        'vehicle_id' => $vehicleId,
        'user_message' => $message,
        'buddy_response' => $result['response'],
        'context' => $context,
    ]);
    
    $payload = [
        'success' => true,
        'conversation_id' => $conversationId,
        'message' => $message,
        'response' => $result['response'],
        'safety_warning' => $safetyWarning,
        'detected_topic' => $result['detected_topic'],
        'vehicle' => $vehicle ? [
            'brand' => $vehicle['brand'],
            'model' => $vehicle['model'],
            'year' => (int) $vehicle['year']
        ] : null
    ];
    if ($llmResult !== null) {
        $payload['sources'] = $llmResult['sources'] ?? [];
        $payload['provider'] = (string) ($llmResult['provider_used'] ?? '');
        $payload['failsafe'] = (bool) ($llmResult['failsafe'] ?? false);
    }

    sendResponse(appendLlmDebugPayload($payload, $llmResult));
}

/**
 * [MOCK] Récupère l'historique
 */
function handleHistoryMock(): void {
    $limit = min((int) ($_GET['limit'] ?? 20), 100);
    
    $conversations = MockDatabase::getConversationHistory($limit);
    
    $byId = [];
    if (isset($_SESSION['mock_db']['diagnostic_conversations']) && is_array($_SESSION['mock_db']['diagnostic_conversations'])) {
        foreach ($_SESSION['mock_db']['diagnostic_conversations'] as $raw) {
            if (is_array($raw) && isset($raw['id'])) {
                $byId[(int) $raw['id']] = $raw;
            }
        }
    }
    
    foreach ($conversations as &$conv) {
        if (!is_array($conv)) {
            continue;
        }
        $id = (int) ($conv['id'] ?? 0);
        $ctx = $byId[$id]['context'] ?? null;
        $sources = [];
        $failsafe = false;
        if (is_array($ctx)) {
            if (!empty($ctx['sources']) && is_array($ctx['sources'])) {
                $sources = $ctx['sources'];
            }
            $failsafe = !empty($ctx['failsafe']);
        }
        $conv['sources'] = $sources;
        $conv['failsafe'] = $failsafe;
    }
    unset($conv);
    
    sendResponse([
        'success' => true,
        'conversations' => $conversations,
        'count' => count($conversations)
    ]);
}

/**
 * [MOCK] Efface l'historique
 */
function handleClearMock(): void {
    $deleted = MockDatabase::clearConversations();
    
    sendResponse([
        'success' => true,
        'message' => 'Historique effacé',
        'deleted_count' => $deleted
    ]);
}

// ============================================
// HANDLERS MODE NORMAL
// ============================================

/**
 * Traite un message utilisateur et génère une réponse
 */
function handleAsk(PDO $db): void {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $message = trim($input['message'] ?? '');
    
    if (empty($message)) {
        sendError('Le message est requis');
    }
    
    if (mb_strlen($message) > 1000) {
        sendError('Le message est trop long (max 1000 caractères)');
    }

    if (!function_exists('getEffectiveLlmProvider')) {
        require_once __DIR__ . '/../includes/byok.php';
    }
    $provider = getEffectiveLlmProvider();
    byok_assert_provider_usable($provider);

    demo_auth_consume_quota_start('buddy');
    
    $vehicle = null;
    $vehicleId = null;
    
    if (isset($_SESSION['vehicle_id'])) {
        $stmt = $db->prepare("SELECT * FROM vehicles WHERE id = ?");
        $stmt->execute([$_SESSION['vehicle_id']]);
        $vehicle = $stmt->fetch();
        $vehicleId = (int) $_SESSION['vehicle_id'];
    }
    
    $stmt = $db->prepare("
        SELECT user_message, buddy_response 
        FROM diagnostic_conversations 
        WHERE session_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([session_id()]);
    $historyRows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $chatHistory = [];
    foreach ($historyRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $chatHistory[] = ['role' => 'user', 'content' => (string) ($row['user_message'] ?? '')];
        $chatHistory[] = ['role' => 'assistant', 'content' => (string) ($row['buddy_response'] ?? '')];
    }
    if (count($chatHistory) > 20) {
        $chatHistory = array_slice($chatHistory, -20);
    }
    
    $llmResult = null;
    
    if ($provider === null) {
        $result = BuddyBrain::generateResponse($message, $vehicle, $historyRows);
    } else {
        if (!defined('LLM_CHAT_LOADED')) {
            require_once __DIR__ . '/../includes/llm_chat.php';
        }
        require_once __DIR__ . '/../includes/vehicle_context.php';
        $llmVehicleContext = getCurrentVehicleContext();
        $llmResult = callLlmChat($chatHistory, $message, $provider, $llmVehicleContext);
        if (!($llmResult['success'] ?? false)) {
            error_log('diagnostic_api LLM: ' . ($llmResult['error'] ?? 'unknown'));
            $llmResult = null;
            $result = BuddyBrain::generateResponse($message, $vehicle, $historyRows);
        } else {
            $result = [
                'response' => (string) ($llmResult['reply'] ?? ''),
                'detected_topic' => null,
                'confidence' => null,
            ];
        }
    }
    
    $safetyWarning = null;
    if (isDangerousOperation($message)) {
        $safetyWarning = getGeneralSafetyMessage($message);
    }
    
    $stmt = $db->prepare("
        INSERT INTO diagnostic_conversations 
        (vehicle_id, user_message, buddy_response, context, session_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $context = [
        'vehicle' => $vehicle,
        'detected_topic' => $result['detected_topic'],
        'confidence' => $result['confidence'],
    ];
    if ($llmResult !== null) {
        if (!empty($llmResult['sources']) && is_array($llmResult['sources'])) {
            $context['sources'] = $llmResult['sources'];
        }
        if (!empty($llmResult['failsafe'])) {
            $context['failsafe'] = true;
        }
    }
    
    $stmt->execute([
        $vehicleId,
        $message,
        $result['response'],
        json_encode($context, JSON_UNESCAPED_UNICODE),
        session_id()
    ]);
    
    $conversationId = $db->lastInsertId();
    
    $payload = [
        'success' => true,
        'conversation_id' => (int) $conversationId,
        'message' => $message,
        'response' => $result['response'],
        'safety_warning' => $safetyWarning,
        'detected_topic' => $result['detected_topic'],
        'vehicle' => $vehicle ? [
            'brand' => $vehicle['brand'],
            'model' => $vehicle['model'],
            'year' => (int) $vehicle['year']
        ] : null
    ];
    if ($llmResult !== null) {
        $payload['sources'] = $llmResult['sources'] ?? [];
        $payload['provider'] = (string) ($llmResult['provider_used'] ?? '');
        $payload['failsafe'] = (bool) ($llmResult['failsafe'] ?? false);
    }

    sendResponse(appendLlmDebugPayload($payload, $llmResult));
}

/**
 * Récupère l'historique des conversations
 */
function handleHistory(PDO $db): void {
    $limit = min((int) ($_GET['limit'] ?? 20), 100);
    
    $stmt = $db->prepare("
        SELECT id, user_message, buddy_response, created_at, context
        FROM diagnostic_conversations
        WHERE session_id = ?
        ORDER BY created_at ASC
        LIMIT ?
    ");
    $stmt->execute([session_id(), $limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $conversations = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $ctx = $row['context'] ?? null;
        if (is_string($ctx)) {
            $decoded = json_decode($ctx, true);
            $ctx = is_array($decoded) ? $decoded : null;
        }
        $sources = [];
        $failsafe = false;
        if (is_array($ctx)) {
            if (!empty($ctx['sources']) && is_array($ctx['sources'])) {
                $sources = $ctx['sources'];
            }
            $failsafe = !empty($ctx['failsafe']);
        }
        $conversations[] = [
            'id' => $row['id'],
            'user_message' => $row['user_message'],
            'buddy_response' => $row['buddy_response'],
            'created_at' => $row['created_at'],
            'sources' => $sources,
            'failsafe' => $failsafe,
        ];
    }
    
    sendResponse([
        'success' => true,
        'conversations' => $conversations,
        'count' => count($conversations)
    ]);
}

/**
 * Efface l'historique des conversations de la session
 */
function handleClear(PDO $db): void {
    $stmt = $db->prepare("DELETE FROM diagnostic_conversations WHERE session_id = ?");
    $stmt->execute([session_id()]);
    $deleted = $stmt->rowCount();
    
    sendResponse([
        'success' => true,
        'message' => 'Historique effacé',
        'deleted_count' => $deleted
    ]);
}
