<?php

/**
 * Messages de validation en français.
 *
 * Laravel ne livre que l'anglais. On ne traduit ici que les règles
 * effectivement utilisées par l'application, plutôt que de recopier un
 * fichier de deux cents lignes dont les neuf dixièmes ne serviront jamais.
 */
return [
    'accepted' => 'Le champ :attribute doit être accepté.',
    'after' => 'Le champ :attribute doit être une date postérieure au :date.',
    'after_or_equal' => 'Le champ :attribute doit être une date postérieure ou égale au :date.',
    'array' => 'Le champ :attribute doit être un tableau.',
    'before' => 'Le champ :attribute doit être une date antérieure au :date.',
    'before_or_equal' => 'Le champ :attribute doit être une date antérieure ou égale au :date.',
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => 'Le champ :attribute n\'est pas une date valide.',
    'date_format' => 'Le champ :attribute ne correspond pas au format :format.',
    'different' => 'Les champs :attribute et :other doivent être différents.',
    'email' => 'Le champ :attribute doit être une adresse électronique valide.',
    'exists' => 'La valeur sélectionnée pour :attribute est invalide.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'filled' => 'Le champ :attribute doit avoir une valeur.',
    'image' => 'Le champ :attribute doit être une image.',
    'in' => 'La valeur sélectionnée pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => [
        'array' => 'Le champ :attribute ne peut contenir plus de :max éléments.',
        'file' => 'Le champ :attribute ne peut dépasser :max kilo-octets.',
        'numeric' => 'Le champ :attribute ne peut être supérieur à :max.',
        'string' => 'Le champ :attribute ne peut dépasser :max caractères.',
    ],
    'mimes' => 'Le champ :attribute doit être un fichier de type : :values.',
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file' => 'Le champ :attribute doit faire au moins :min kilo-octets.',
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'not_in' => 'La valeur sélectionnée pour :attribute est invalide.',
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'present' => 'Le champ :attribute doit être présent.',
    'required' => 'Le champ :attribute est obligatoire.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'same' => 'Les champs :attribute et :other doivent être identiques.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique' => 'La valeur du champ :attribute est déjà utilisée.',
    'uploaded' => 'Le téléversement du fichier :attribute a échoué.',
    'uuid' => 'Le champ :attribute doit être un UUID valide.',

    /**
     * Le nom des champs tel qu'il apparaît dans les messages. Sans cela,
     * l'utilisateur lirait « Le champ matiereCouverte est obligatoire ».
     */
    'attributes' => [
        'matricule' => 'matricule',
        'password' => 'mot de passe',
        'coursId' => 'cours',
        'dateSeance' => 'date de la séance',
        'heureDebut' => 'heure de début',
        'heureFin' => 'heure de fin',
        'type' => 'nature de la séance',
        'matiereCouverte' => 'matière traitée',
        'effectifPresent' => 'effectif présent',
        'localId' => 'local',
        'observations' => 'observations',
        'motif' => 'motif de contestation',
        'date_seance' => 'date de la séance',
        'heure_debut' => 'heure de début',
        'heure_fin' => 'heure de fin',
        'matiere_couverte' => 'matière traitée',
        'seance' => 'séance',
    ],
];
