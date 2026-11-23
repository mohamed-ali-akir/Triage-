<?php

return [
    // What an AI call was made for (column "purpose" of the AI log).
    'purposes' => [
        'classification' => 'Tri des tickets',
        'reply' => 'Réponses suggérées',
        'duplicates' => 'Détection des doublons',
        'evaluation' => 'Évaluation',
        'check' => 'Vérification',
    ],

    // Short error codes written to the AI log.
    'errors' => [
        'timeout' => 'Délai dépassé',
        'unreachable' => 'Serveur injoignable',
        'rate_limited' => 'Quota atteint',
        'overloaded' => 'Service surchargé',
        'authentication' => 'Clé refusée',
        'not_configured' => 'Clé absente',
        'refusal' => 'Refus du modèle',
        'max_tokens' => 'Réponse tronquée',
        'empty_answer' => 'Réponse vide',
        'invalid_output' => 'Réponse inutilisable',
    ],
];
