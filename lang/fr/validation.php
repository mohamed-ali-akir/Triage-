<?php

// Only the rules used by the application are translated here.
// Missing keys fall back to the English messages shipped with Laravel.

return [
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'enum' => 'La valeur choisie pour :attribute est invalide.',
    'exists' => 'La valeur choisie pour :attribute est invalide.',
    'in' => 'La valeur choisie pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => [
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'min' => [
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un symbole.',
        'uncompromised' => 'Ce mot de passe est apparu dans une fuite de données. Choisissez-en un autre.',
    ],
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être un texte.',
    'unique' => 'Cette valeur est déjà utilisée pour :attribute.',

    'attributes' => [
        'body' => 'message',
        'category_id' => 'catégorie',
        'code' => 'code',
        'current_password' => 'mot de passe actuel',
        'department_id' => 'service',
        'description' => 'description',
        'email' => 'adresse e-mail',
        'is_active' => 'actif',
        'name' => 'nom',
        'password' => 'mot de passe',
        'priority' => 'priorité',
        'role' => 'rôle',
        'status' => 'statut',
        'title' => 'titre',
    ],
];
