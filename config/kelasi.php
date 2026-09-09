<?php

/**
 * Ce qui relie Kelasi aux téléphones.
 *
 * Le reste de la configuration de l'application vit dans les fichiers
 * habituels de Laravel ; ce fichier ne rassemble que ce qui n'a de sens
 * qu'une fois l'application posée sur un appareil ou derrière un domaine.
 */

return [

    /*
    |---------------------------------------------------------------------
    | Mandataires de confiance
    |---------------------------------------------------------------------
    |
    | Les adresses derrière lesquelles l'application accepte de croire les
    | en-têtes X-Forwarded-* -- c'est-à-dire de considérer qu'une requête
    | arrivée en clair sur le réseau interne était en HTTPS côté visiteur.
    |
    | Par défaut : les trois plages privées. Elles couvrent les réseaux de
    | Docker et celui de l'université, et rien de ce qui vient d'Internet.
    |
    */

    'mandataires' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('MANDATAIRES_DE_CONFIANCE', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16')),
    ))),

    /*
    |---------------------------------------------------------------------
    | Application Android
    |---------------------------------------------------------------------
    |
    | Le paquet Android publié sur le Play Store n'embarque pas de code :
    | il ouvre ce site en plein écran (« Trusted Web Activity »). Chrome
    | n'accepte de masquer la barre d'adresse que si le site confirme qu'il
    | reconnaît ce paquet -- c'est le rôle de /.well-known/assetlinks.json,
    | servi à partir de ces deux valeurs.
    |
    | L'empreinte est celle de la clé qui SIGNE le paquet installé. Il en
    | faut donc souvent deux : celle de la clé de développement, et celle
    | que Google régénère lorsqu'il resigne l'application pour le Store
    | (Play Console > Intégrité de l'app > Signature d'application).
    |
    | Sans empreinte configurée, la route répond 404 : mieux vaut pas de
    | fichier qu'un fichier vide, qui ferait échouer la vérification sans
    | rien dire de pourquoi.
    |
    */

    'android' => [
        'paquet' => env('ANDROID_PAQUET', 'cd.kelasi.app'),

        'empreintes' => array_values(array_filter(array_map(
            static fn (string $empreinte): string => strtoupper(trim($empreinte)),
            explode(',', (string) env('ANDROID_EMPREINTES_SHA256', '')),
        ))),
    ],

];
