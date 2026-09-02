<?php

/**
 * Programmes des cours, format LMD applique en Republique democratique du
 * Congo depuis la réforme de 2021.
 *
 * Structure : faculté -> departement -> niveau -> unites d'enseignement, dont
 * chacune se decompose en cours (les éléments constitutifs).
 *
 * Chaque semestre totalise exactement 30 crédits, comme l'imposent les
 * instructions académiques du MINESU. Le test ProgrammeTest verifie cette
 * regle a chaque execution -- une maquette qui ne tombe pas juste est un bug,
 * pas une approximation.
 *
 * Les volumes horaires ne figurent pas ici : ils se deduisent des crédits par
 * la regle du VolumeHoraire (1 crédit = 25 h de travail étudiant, deux tiers
 * encadres). La cle "parts" ventile ces heures encadrees entre CMI, TD et TP ;
 * en son absence, tout le volume est en cours magistral.
 *
 * PROVENANCE DES DONNEES -- a lire avant de s'en servir en production.
 *
 * Les intitules de la première année de polytechnique proviennent de la liste
 * publiee par la faculté polytechnique de l'Université de Lubumbashi. La
 * structure en unites CSS / RCH / IDP de la première année de médecine, ainsi
 * que son total de 750 heures pour 30 crédits, proviennent de la maquette LMD
 * de la faculté de médecine de la meme université. Le placement du droit
 * constitutionnel en deuxième année suit l'usage des facultés de droit
 * congolaises sous le nouveau programme.
 *
 * Le reste -- decoupage precis des UE, répartition des crédits cours par
 * cours, codes -- est une reconstruction coherente avec ces sources et avec
 * les maquettes ministerielles par domaine, pas une transcription officielle.
 * Avant tout deploiement réel, ces maquettes doivent etre remplacees par
 * celles que le secrétariat académique de l'etablissement aura validées.
 */

return [
    'POLY' => [
        'GC' => [
            'L1' => [
                'intitule' => 'Première année de licence en sciences de l\'ingénieur',
                'unites' => [
                    [
                        'code' => 'UE1', 'intitule' => 'Mathématiques fondamentales I', 'semestre' => 1, 'credits' => 12,
                        'cours' => [
                            ['code' => 'MAT101', 'intitule' => 'Analyse infinitésimale', 'credits' => 6, 'parts' => ['td' => 0.3]],
                            ['code' => 'MAT102', 'intitule' => 'Algèbre linéaire et calcul vectoriel', 'credits' => 6, 'parts' => ['td' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE2', 'intitule' => 'Sciences physiques et chimiques', 'semestre' => 1, 'credits' => 8,
                        'cours' => [
                            ['code' => 'PHY101', 'intitule' => 'Physique générale I : mécanique', 'credits' => 5, 'parts' => ['td' => 0.2, 'tp' => 0.2]],
                            ['code' => 'CHI101', 'intitule' => 'Chimie générale', 'credits' => 3, 'parts' => ['tp' => 0.25]],
                        ],
                    ],
                    [
                        'code' => 'UE3', 'intitule' => 'Outils de l\'ingenieur', 'semestre' => 1, 'credits' => 6,
                        'cours' => [
                            ['code' => 'INF101', 'intitule' => 'Informatique 1', 'credits' => 3, 'parts' => ['tp' => 0.5]],
                            ['code' => 'DES101', 'intitule' => 'Géométrie descriptive et dessin technique', 'credits' => 3, 'parts' => ['tp' => 0.5]],
                        ],
                    ],
                    [
                        'code' => 'UE4', 'intitule' => 'Langues et méthodologie', 'semestre' => 1, 'credits' => 4,
                        'cours' => [
                            ['code' => 'ANG101', 'intitule' => 'Anglais technique', 'credits' => 2, 'parts' => ['td' => 0.5]],
                            ['code' => 'LOG101', 'intitule' => 'Logique et expression écrite et orale', 'credits' => 2, 'parts' => ['td' => 0.5]],
                        ],
                    ],
                    [
                        'code' => 'UE5', 'intitule' => 'Mathématiques fondamentales II', 'semestre' => 2, 'credits' => 11,
                        'cours' => [
                            ['code' => 'MAT103', 'intitule' => 'Analyse mathematique II', 'credits' => 6, 'parts' => ['td' => 0.3]],
                            ['code' => 'MAT104', 'intitule' => 'Géométrie analytique et différentielle', 'credits' => 5, 'parts' => ['td' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE6', 'intitule' => 'Physique appliquée', 'semestre' => 2, 'credits' => 8,
                        'cours' => [
                            ['code' => 'PHY102', 'intitule' => 'Physique générale II : électricité et magnétisme', 'credits' => 5, 'parts' => ['td' => 0.2, 'tp' => 0.2]],
                            ['code' => 'MEC101', 'intitule' => 'Mécanique rationnelle', 'credits' => 3, 'parts' => ['td' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE7', 'intitule' => 'Informatique et méthodes de recherche', 'semestre' => 2, 'credits' => 6,
                        'cours' => [
                            ['code' => 'INF102', 'intitule' => 'Informatique 2 : algorithmique', 'credits' => 3, 'parts' => ['tp' => 0.5]],
                            ['code' => 'MET101', 'intitule' => 'Introduction a la recherche scientifique', 'credits' => 3, 'parts' => ['td' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE8', 'intitule' => 'Formation générale', 'semestre' => 2, 'credits' => 5,
                        'cours' => [
                            ['code' => 'CIT101', 'intitule' => 'Éducation a la citoyenneté', 'credits' => 2],
                            ['code' => 'ANG102', 'intitule' => 'Anglais technique II', 'credits' => 3, 'parts' => ['td' => 0.5]],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'MED' => [
        'BIOMED' => [
            'L1' => [
                'intitule' => 'Première année de licence en sciences biomédicales',
                'unites' => [
                    [
                        'code' => 'UE1-CSS', 'intitule' => 'Connaissances scientifiques de support I', 'semestre' => 1, 'credits' => 21,
                        'cours' => [
                            ['code' => 'ANA101', 'intitule' => 'Anatomie humaine générale', 'credits' => 6, 'parts' => ['tp' => 0.3]],
                            ['code' => 'BIO101', 'intitule' => 'Biologie cellulaire et moléculaire', 'credits' => 5, 'parts' => ['tp' => 0.25]],
                            ['code' => 'CHI102', 'intitule' => 'Chimie générale et organique', 'credits' => 5, 'parts' => ['tp' => 0.25]],
                            ['code' => 'PHY103', 'intitule' => 'Physique médicale', 'credits' => 5, 'parts' => ['td' => 0.2, 'tp' => 0.2]],
                        ],
                    ],
                    [
                        'code' => 'UE3-RCH', 'intitule' => 'Initiation a la recherche', 'semestre' => 1, 'credits' => 7,
                        'cours' => [
                            ['code' => 'BST101', 'intitule' => 'Biostatistique', 'credits' => 4, 'parts' => ['td' => 0.35]],
                            ['code' => 'INF103', 'intitule' => 'Informatique médicale', 'credits' => 3, 'parts' => ['tp' => 0.5]],
                        ],
                    ],
                    [
                        'code' => 'UE6-IDP', 'intitule' => 'Identité et déontologie professionnelles', 'semestre' => 1, 'credits' => 2,
                        'cours' => [
                            ['code' => 'HIS101', 'intitule' => 'Histoire de la médecine et déontologie', 'credits' => 2],
                        ],
                    ],
                    [
                        'code' => 'UE2-CSS', 'intitule' => 'Connaissances scientifiques de support II', 'semestre' => 2, 'credits' => 20,
                        'cours' => [
                            ['code' => 'PHS101', 'intitule' => 'Physiologie humaine', 'credits' => 6, 'parts' => ['tp' => 0.25]],
                            ['code' => 'HIT101', 'intitule' => 'Histologie et embryologie', 'credits' => 5, 'parts' => ['tp' => 0.35]],
                            ['code' => 'BCH101', 'intitule' => 'Biochimie médicale', 'credits' => 5, 'parts' => ['tp' => 0.3]],
                            ['code' => 'ANA102', 'intitule' => 'Anatomie des systèmes', 'credits' => 4, 'parts' => ['tp' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE4-SP', 'intitule' => 'Santé publique', 'semestre' => 2, 'credits' => 6,
                        'cours' => [
                            ['code' => 'SPU101', 'intitule' => 'Santé publique et communautaire', 'credits' => 4, 'parts' => ['td' => 0.25]],
                            ['code' => 'EPI101', 'intitule' => 'Épidémiologie générale', 'credits' => 2, 'parts' => ['td' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE5-IDP', 'intitule' => 'Formation générale', 'semestre' => 2, 'credits' => 4,
                        'cours' => [
                            ['code' => 'CIT102', 'intitule' => 'Éducation a la citoyenneté', 'credits' => 2],
                            ['code' => 'ANG103', 'intitule' => 'Anglais médical', 'credits' => 2, 'parts' => ['td' => 0.5]],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'DROIT' => [
        'DPJ' => [
            'L1' => [
                'intitule' => 'Première année de licence en droit',
                'unites' => [
                    [
                        'code' => 'UE1', 'intitule' => 'Fondements du droit', 'semestre' => 1, 'credits' => 12,
                        'cours' => [
                            ['code' => 'DRT101', 'intitule' => 'Introduction générale a l\'étude du droit', 'credits' => 6, 'parts' => ['td' => 0.25]],
                            ['code' => 'DRT102', 'intitule' => 'Droit civil : les personnes et la famille', 'credits' => 6, 'parts' => ['td' => 0.25]],
                        ],
                    ],
                    [
                        'code' => 'UE2', 'intitule' => 'Sciences sociales et économiques', 'semestre' => 1, 'credits' => 9,
                        'cours' => [
                            ['code' => 'ECO101', 'intitule' => 'Économie politique', 'credits' => 5, 'parts' => ['td' => 0.2]],
                            ['code' => 'SOC101', 'intitule' => 'Sociologie juridique', 'credits' => 4],
                        ],
                    ],
                    [
                        'code' => 'UE3', 'intitule' => 'Histoire et institutions', 'semestre' => 1, 'credits' => 5,
                        'cours' => [
                            ['code' => 'HIS102', 'intitule' => 'Histoire du droit et des institutions', 'credits' => 5],
                        ],
                    ],
                    [
                        'code' => 'UE4', 'intitule' => 'Méthodologie et langues', 'semestre' => 1, 'credits' => 4,
                        'cours' => [
                            ['code' => 'MET102', 'intitule' => 'Méthodologie du travail universitaire', 'credits' => 2, 'parts' => ['td' => 0.5]],
                            ['code' => 'ANG104', 'intitule' => 'Anglais juridique', 'credits' => 2, 'parts' => ['td' => 0.5]],
                        ],
                    ],
                    [
                        'code' => 'UE5', 'intitule' => 'Droit civil', 'semestre' => 2, 'credits' => 10,
                        'cours' => [
                            ['code' => 'DRT103', 'intitule' => 'Droit civil : les biens', 'credits' => 5, 'parts' => ['td' => 0.25]],
                            ['code' => 'DRT104', 'intitule' => 'Droit civil : les obligations', 'credits' => 5, 'parts' => ['td' => 0.25]],
                        ],
                    ],
                    [
                        'code' => 'UE6', 'intitule' => 'Institutions politiques et administratives', 'semestre' => 2, 'credits' => 10,
                        'cours' => [
                            ['code' => 'DRT105', 'intitule' => 'Institutions politiques', 'credits' => 6, 'parts' => ['td' => 0.2]],
                            ['code' => 'DRT106', 'intitule' => 'Grands services publics', 'credits' => 4],
                        ],
                    ],
                    [
                        'code' => 'UE7', 'intitule' => 'Droit pénal', 'semestre' => 2, 'credits' => 6,
                        'cours' => [
                            ['code' => 'DRT107', 'intitule' => 'Droit pénal général', 'credits' => 6, 'parts' => ['td' => 0.25]],
                        ],
                    ],
                    [
                        'code' => 'UE8', 'intitule' => 'Formation générale', 'semestre' => 2, 'credits' => 4,
                        'cours' => [
                            ['code' => 'CIT103', 'intitule' => 'Éducation a la citoyenneté', 'credits' => 2],
                            ['code' => 'INF104', 'intitule' => 'Informatique appliquée au droit', 'credits' => 2, 'parts' => ['tp' => 0.5]],
                        ],
                    ],
                ],
            ],
            'L2' => [
                'intitule' => 'Deuxième année de licence en droit',
                'unites' => [
                    [
                        'code' => 'UE1', 'intitule' => 'Droit public', 'semestre' => 1, 'credits' => 11,
                        'cours' => [
                            ['code' => 'DRT201', 'intitule' => 'Droit constitutionnel congolais', 'credits' => 6, 'parts' => ['td' => 0.25]],
                            ['code' => 'DRT202', 'intitule' => 'Droit administratif général', 'credits' => 5, 'parts' => ['td' => 0.2]],
                        ],
                    ],
                    [
                        'code' => 'UE2', 'intitule' => 'Droit privé', 'semestre' => 1, 'credits' => 11,
                        'cours' => [
                            ['code' => 'DRT203', 'intitule' => 'Droit civil : les contrats spéciaux', 'credits' => 6, 'parts' => ['td' => 0.25]],
                            ['code' => 'DRT204', 'intitule' => 'Droit commercial général', 'credits' => 5, 'parts' => ['td' => 0.2]],
                        ],
                    ],
                    [
                        'code' => 'UE3', 'intitule' => 'Droit pénal approfondi', 'semestre' => 1, 'credits' => 5,
                        'cours' => [
                            ['code' => 'DRT205', 'intitule' => 'Droit pénal spécial', 'credits' => 5, 'parts' => ['td' => 0.2]],
                        ],
                    ],
                    [
                        'code' => 'UE4', 'intitule' => 'Méthodes juridiques', 'semestre' => 1, 'credits' => 3,
                        'cours' => [
                            ['code' => 'MET201', 'intitule' => 'Méthodologie juridique et recherche documentaire', 'credits' => 3, 'parts' => ['td' => 0.4]],
                        ],
                    ],
                    [
                        'code' => 'UE5', 'intitule' => 'Procedures', 'semestre' => 2, 'credits' => 11,
                        'cours' => [
                            ['code' => 'DRT206', 'intitule' => 'Procédure civile', 'credits' => 6, 'parts' => ['td' => 0.25]],
                            ['code' => 'DRT207', 'intitule' => 'Procédure pénale', 'credits' => 5, 'parts' => ['td' => 0.25]],
                        ],
                    ],
                    [
                        'code' => 'UE6', 'intitule' => 'Droit du travail et de la sécurité sociale', 'semestre' => 2, 'credits' => 9,
                        'cours' => [
                            ['code' => 'DRT208', 'intitule' => 'Droit du travail', 'credits' => 5, 'parts' => ['td' => 0.2]],
                            ['code' => 'DRT209', 'intitule' => 'Droit de la sécurité sociale', 'credits' => 4],
                        ],
                    ],
                    [
                        'code' => 'UE7', 'intitule' => 'Droit international', 'semestre' => 2, 'credits' => 6,
                        'cours' => [
                            ['code' => 'DRT210', 'intitule' => 'Droit international public', 'credits' => 6, 'parts' => ['td' => 0.2]],
                        ],
                    ],
                    [
                        'code' => 'UE8', 'intitule' => 'Langues', 'semestre' => 2, 'credits' => 4,
                        'cours' => [
                            ['code' => 'ANG201', 'intitule' => 'Anglais juridique II', 'credits' => 4, 'parts' => ['td' => 0.5]],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'FASEG' => [
        'ECO' => [
            'L1' => [
                'intitule' => 'Première année de licence en sciences économiques',
                'unites' => [
                    [
                        'code' => 'UE1', 'intitule' => 'Analyse économique I', 'semestre' => 1, 'credits' => 11,
                        'cours' => [
                            ['code' => 'ECO111', 'intitule' => 'Économie générale I : microéconomie', 'credits' => 6, 'parts' => ['td' => 0.3]],
                            ['code' => 'ECO112', 'intitule' => 'Histoire des faits économiques', 'credits' => 5],
                        ],
                    ],
                    [
                        'code' => 'UE2', 'intitule' => 'Méthodes quantitatives I', 'semestre' => 1, 'credits' => 11,
                        'cours' => [
                            ['code' => 'MAT111', 'intitule' => 'Mathématiques generales', 'credits' => 6, 'parts' => ['td' => 0.35]],
                            ['code' => 'STA111', 'intitule' => 'Statistique descriptive', 'credits' => 5, 'parts' => ['td' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE3', 'intitule' => 'Comptabilité', 'semestre' => 1, 'credits' => 5,
                        'cours' => [
                            ['code' => 'CPT111', 'intitule' => 'Comptabilité générale I (SYSCOHADA)', 'credits' => 5, 'parts' => ['td' => 0.4]],
                        ],
                    ],
                    [
                        'code' => 'UE4', 'intitule' => 'Langues et méthodologie', 'semestre' => 1, 'credits' => 3,
                        'cours' => [
                            ['code' => 'ANG111', 'intitule' => 'Anglais des affaires', 'credits' => 3, 'parts' => ['td' => 0.5]],
                        ],
                    ],
                    [
                        'code' => 'UE5', 'intitule' => 'Analyse économique II', 'semestre' => 2, 'credits' => 11,
                        'cours' => [
                            ['code' => 'ECO113', 'intitule' => 'Économie générale II : macroéconomie', 'credits' => 6, 'parts' => ['td' => 0.3]],
                            ['code' => 'ECO114', 'intitule' => 'Économie du développement', 'credits' => 5],
                        ],
                    ],
                    [
                        'code' => 'UE6', 'intitule' => 'Méthodes quantitatives II', 'semestre' => 2, 'credits' => 10,
                        'cours' => [
                            ['code' => 'MAT112', 'intitule' => 'Mathématiques financières', 'credits' => 5, 'parts' => ['td' => 0.4]],
                            ['code' => 'STA112', 'intitule' => 'Probabilités et statistique inférentielle', 'credits' => 5, 'parts' => ['td' => 0.35]],
                        ],
                    ],
                    [
                        'code' => 'UE7', 'intitule' => 'Comptabilité approfondie', 'semestre' => 2, 'credits' => 6,
                        'cours' => [
                            ['code' => 'CPT112', 'intitule' => 'Comptabilité générale II', 'credits' => 6, 'parts' => ['td' => 0.4]],
                        ],
                    ],
                    [
                        'code' => 'UE8', 'intitule' => 'Formation générale', 'semestre' => 2, 'credits' => 3,
                        'cours' => [
                            ['code' => 'CIT111', 'intitule' => 'Éducation a la citoyenneté', 'credits' => 3],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
