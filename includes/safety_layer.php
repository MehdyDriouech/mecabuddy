<?php
/**
 * MecaBuddy - Safety Layer (Couche de sécurité mécanique)
 * 
 * Ce fichier contient la logique de vérification de sécurité pour les
 * tutoriels mécaniques. Il identifie les opérations dangereuses et
 * ajoute les avertissements appropriés.
 */

/**
 * Liste des mots-clés associés à des opérations dangereuses
 * Chaque entrée contient le mot-clé et le message d'avertissement associé
 */
const DANGER_KEYWORDS = [
    // Système de freinage
    'frein' => [
        'level' => 'high',
        'message' => '⚠️ Opération sur le système de freinage - Sécurité critique ! Vérifiez toujours le serrage et purgez le circuit.'
    ],
    'plaquette' => [
        'level' => 'high',
        'message' => '⚠️ Changement de plaquettes - Assurez-vous que le véhicule est bien calé et utilisez un cric adapté.'
    ],
    'disque' => [
        'level' => 'high',
        'message' => '⚠️ Intervention sur les disques de frein - Manipulation lourde, attention aux bords tranchants.'
    ],
    'purge' => [
        'level' => 'high',
        'message' => '⚠️ Purge du circuit - Le liquide de frein est corrosif ! Portez des gants et lunettes de protection.'
    ],
    
    // Moteur et huile
    'vidange' => [
        'level' => 'medium',
        'message' => '⚠️ Vidange - Attention, l\'huile peut être très chaude ! Laissez refroidir le moteur.'
    ],
    'huile' => [
        'level' => 'medium',
        'message' => '⚠️ Manipulation d\'huile - Évitez tout contact prolongé avec la peau, l\'huile moteur est nocive.'
    ],
    'filtre à huile' => [
        'level' => 'low',
        'message' => '⚠️ Changement de filtre - Prévoyez un bac de récupération pour éviter les éclaboussures.'
    ],
    
    // Système électrique
    'batterie' => [
        'level' => 'high',
        'message' => '⚠️ Manipulation de batterie - Risque de court-circuit et d\'acide ! Débranchez toujours le négatif en premier.'
    ],
    'électrique' => [
        'level' => 'medium',
        'message' => '⚠️ Système électrique - Débranchez la batterie avant toute intervention pour éviter les courts-circuits.'
    ],
    'fusible' => [
        'level' => 'low',
        'message' => '⚠️ Fusibles - Utilisez toujours un fusible de même ampérage pour le remplacement.'
    ],
    
    // Suspension et direction
    'suspension' => [
        'level' => 'high',
        'message' => '⚠️ Système de suspension - Les ressorts sont sous tension ! Utilisez un compresseur de ressort.'
    ],
    'amortisseur' => [
        'level' => 'high',
        'message' => '⚠️ Amortisseurs - Pièces lourdes, travaillez à deux et utilisez des chandelles.'
    ],
    'direction' => [
        'level' => 'high',
        'message' => '⚠️ Système de direction - Sécurité critique ! Faites vérifier le parallélisme après intervention.'
    ],
    
    // Refroidissement
    'liquide de refroidissement' => [
        'level' => 'high',
        'message' => '⚠️ Liquide de refroidissement - JAMAIS ouvrir le bouchon moteur chaud ! Risque de brûlure grave.'
    ],
    'radiateur' => [
        'level' => 'medium',
        'message' => '⚠️ Radiateur - Le liquide peut être très chaud et sous pression. Laissez refroidir.'
    ],
    'thermostat' => [
        'level' => 'medium',
        'message' => '⚠️ Thermostat - Vidangez partiellement le circuit avant l\'intervention.'
    ],
    
    // Carburant
    'carburant' => [
        'level' => 'high',
        'message' => '⚠️ Système carburant - Inflammable ! Travaillez dans un endroit ventilé, pas de flamme ni étincelle.'
    ],
    'essence' => [
        'level' => 'high',
        'message' => '⚠️ Essence - Extrêmement inflammable ! Pas de cigarette, éloignez toute source d\'ignition.'
    ],
    'injecteur' => [
        'level' => 'high',
        'message' => '⚠️ Injecteurs - Système sous haute pression. Dépressurisez le circuit avant intervention.'
    ],
    
    // Levage
    'cric' => [
        'level' => 'high',
        'message' => '⚠️ Utilisation du cric - Ne JAMAIS travailler sous un véhicule maintenu uniquement par un cric !'
    ],
    'chandelle' => [
        'level' => 'medium',
        'message' => '⚠️ Chandelles - Placez-les sur une surface plane et stable, vérifiez la charge maximale.'
    ],
    
    // Échappement
    'échappement' => [
        'level' => 'medium',
        'message' => '⚠️ Ligne d\'échappement - Peut être très chaude ! Laissez refroidir avant intervention.'
    ],
    'pot' => [
        'level' => 'medium',
        'message' => '⚠️ Pot d\'échappement - Attention aux fixations rouillées, utilisez du dégrippant.'
    ],
    
    // Embrayage
    'embrayage' => [
        'level' => 'high',
        'message' => '⚠️ Embrayage - Opération complexe nécessitant souvent la dépose de la boîte de vitesses.'
    ],
    
    // Climatisation
    'climatisation' => [
        'level' => 'high',
        'message' => '⚠️ Climatisation - Le gaz réfrigérant nécessite un équipement spécial et une certification.'
    ],
    'clim' => [
        'level' => 'high',
        'message' => '⚠️ Circuit de clim - Ne jamais ouvrir le circuit sans équipement adapté.'
    ],
    
    // Airbags
    'airbag' => [
        'level' => 'high',
        'message' => '⚠️ AIRBAG - Système pyrotechnique ! Débranchez la batterie et attendez 10 min minimum.'
    ]
];

/**
 * Analyse un texte et détecte les opérations dangereuses
 * 
 * @param string $text Texte à analyser
 * @return array Liste des dangers détectés avec leurs niveaux et messages
 */
function detectDangers(string $text): array {
    $dangers = [];
    $textLower = mb_strtolower($text, 'UTF-8');
    
    foreach (DANGER_KEYWORDS as $keyword => $info) {
        if (mb_strpos($textLower, mb_strtolower($keyword, 'UTF-8')) !== false) {
            $dangers[$keyword] = $info;
        }
    }
    
    return $dangers;
}

/**
 * Applique la couche de sécurité à un tutoriel
 * Ajoute les badges de danger et les avertissements appropriés
 * 
 * @param array $tutorial Tutoriel à sécuriser (avec 'title', 'description', 'steps')
 * @return array Tutoriel enrichi avec les informations de sécurité
 */
function applySafetyLayer(array $tutorial): array {
    // Analyse le titre et la description globale
    $globalDangers = detectDangers($tutorial['title'] ?? '');
    $globalDangers = array_merge($globalDangers, detectDangers($tutorial['description'] ?? ''));
    
    // Détermine le niveau de danger global
    $maxLevel = 'none';
    $globalWarnings = [];
    
    foreach ($globalDangers as $keyword => $info) {
        $globalWarnings[] = $info['message'];
        if ($info['level'] === 'high') {
            $maxLevel = 'high';
        } elseif ($info['level'] === 'medium' && $maxLevel !== 'high') {
            $maxLevel = 'medium';
        } elseif ($info['level'] === 'low' && $maxLevel === 'none') {
            $maxLevel = 'low';
        }
    }
    
    $tutorial['danger_level'] = $maxLevel;
    $tutorial['global_warnings'] = array_unique($globalWarnings);
    
    // Analyse chaque étape
    if (isset($tutorial['steps']) && is_array($tutorial['steps'])) {
        foreach ($tutorial['steps'] as $index => $step) {
            $stepText = ($step['title'] ?? '') . ' ' . ($step['description'] ?? '');
            $stepDangers = detectDangers($stepText);
            
            $tutorial['steps'][$index]['danger'] = !empty($stepDangers);
            $tutorial['steps'][$index]['danger_level'] = 'none';
            $tutorial['steps'][$index]['warnings'] = [];
            
            foreach ($stepDangers as $keyword => $info) {
                $tutorial['steps'][$index]['warnings'][] = $info['message'];
                
                if ($info['level'] === 'high') {
                    $tutorial['steps'][$index]['danger_level'] = 'high';
                } elseif ($info['level'] === 'medium' && $tutorial['steps'][$index]['danger_level'] !== 'high') {
                    $tutorial['steps'][$index]['danger_level'] = 'medium';
                } elseif ($info['level'] === 'low' && $tutorial['steps'][$index]['danger_level'] === 'none') {
                    $tutorial['steps'][$index]['danger_level'] = 'low';
                }
            }
            
            $tutorial['steps'][$index]['warnings'] = array_unique($tutorial['steps'][$index]['warnings']);
        }
    }
    
    return $tutorial;
}

/**
 * Génère un message de sécurité général pour une opération
 * 
 * @param string $operation Type d'opération (ex: 'vidange', 'frein')
 * @return string Message de sécurité
 */
function getGeneralSafetyMessage(string $operation): string {
    $dangers = detectDangers($operation);
    
    if (empty($dangers)) {
        return "ℹ️ Opération standard - Respectez toujours les consignes de sécurité générales.";
    }
    
    // Retourne le message du danger le plus critique
    $highestLevel = 'low';
    $message = '';
    
    foreach ($dangers as $info) {
        if ($info['level'] === 'high') {
            return $info['message'];
        } elseif ($info['level'] === 'medium' && $highestLevel !== 'high') {
            $highestLevel = 'medium';
            $message = $info['message'];
        } elseif ($message === '') {
            $message = $info['message'];
        }
    }
    
    return $message;
}

/**
 * Vérifie si une opération est considérée comme dangereuse
 * 
 * @param string $operation Description de l'opération
 * @return bool True si l'opération est dangereuse
 */
function isDangerousOperation(string $operation): bool {
    $dangers = detectDangers($operation);
    
    foreach ($dangers as $info) {
        if ($info['level'] === 'high' || $info['level'] === 'medium') {
            return true;
        }
    }
    
    return false;
}

