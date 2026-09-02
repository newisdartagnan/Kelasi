<?php

/**
 * Programmes des cours, format LMD applique en Republique democratique du
 * Congo depuis la reforme de 2021.
 *
 * Structure : faculte -> departement -> niveau -> unites d'enseignement, dont
 * chacune se decompose en cours (les elements constitutifs).
 *
 * Chaque semestre totalise exactement 30 credits, comme l'imposent les
 * instructions academiques du MINESU. Le test ProgrammeTest verifie cette
 * regle a chaque execution -- une maquette qui ne tombe pas juste est un bug,
 * pas une approximation.
 *
 * Les volumes horaires ne figurent pas ici : ils se deduisent des credits par
 * la regle du VolumeHoraire (1 credit = 25 h de travail etudiant, deux tiers
 * encadres). La cle "parts" ventile ces heures encadrees entre CMI, TD et TP ;
 * en son absence, tout le volume est en cours magistral.
 *
 * PROVENANCE DES DONNEES -- a lire avant de s'en servir en production.
 *
 * Les intitules de la premiere annee de polytechnique proviennent de la liste
 * publiee par la faculte polytechnique de l'Universite de Lubumbashi. La
 * structure en unites CSS / RCH / IDP de la premiere annee de medecine, ainsi
 * que son total de 750 heures pour 30 credits, proviennent de la maquette LMD
 * de la faculte de medecine de la meme universite. Le placement du droit
 * constitutionnel en deuxieme annee suit l'usage des facultes de droit
 * congolaises sous le nouveau programme.
 *
 * Le reste -- decoupage precis des UE, repartition des credits cours par
 * cours, codes -- est une reconstruction coherente avec ces sources et avec
 * les maquettes ministerielles par domaine, pas une transcription officielle.
 * Avant tout deploiement reel, ces maquettes doivent etre remplacees par
 * celles que le secretariat academique de l'etablissement aura validees.
 */

return [
    'POLY' => [
        'GC' => [
            'L1' => [
                'intitule' => 'Premiere annee de licence en sciences de l\'ingenieur',
                'unites' => [
                    [
                        'code' => 'UE1', 'intitule' => 'Mathematiques fondamentales I', 'semestre' => 1, 'credits' => 12,
                        'cours' => [
                            ['code' => 'MAT101', 'intitule' => 'Analyse infinitesimale', 'credits' => 6, 'parts' => ['td' => 0.3]],
                            ['code' => 'MAT102', 'intitule' => 'Algebre lineaire et calcul vectoriel', 'credits' => 6, 'parts' => ['td' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE2', 'intitule' => 'Sciences physiques et chimiques', 'semestre' => 1, 'credits' => 8,
                        'cours' => [
                            ['code' => 'PHY101', 'intitule' => 'Physique generale I : mecanique', 'credits' => 5, 'parts' => ['td' => 0.2, 'tp' => 0.2]],
                            ['code' => 'CHI101', 'intitule' => 'Chimie generale', 'credits' => 3, 'parts' => ['tp' => 0.25]],
                        ],
                    ],
                    [
                        'code' => 'UE3', 'intitule' => 'Outils de l\'ingenieur', 'semestre' => 1, 'credits' => 6,
                        'cours' => [
                            ['code' => 'INF101', 'intitule' => 'Informatique 1', 'credits' => 3, 'parts' => ['tp' => 0.5]],
                            ['code' => 'DES101', 'intitule' => 'Geometrie descriptive et dessin technique', 'credits' => 3, 'parts' => ['tp' => 0.5]],
                        ],
                    ],
                    [
                        'code' => 'UE4', 'intitule' => 'Langues et methodologie', 'semestre' => 1, 'credits' => 4,
                        'cours' => [
                            ['code' => 'ANG101', 'intitule' => 'Anglais technique', 'credits' => 2, 'parts' => ['td' => 0.5]],
                            ['code' => 'LOG101', 'intitule' => 'Logique et expression ecrite et orale', 'credits' => 2, 'parts' => ['td' => 0.5]],
                        ],
                    ],
                    [
                        'code' => 'UE5', 'intitule' => 'Mathematiques fondamentales II', 'semestre' => 2, 'credits' => 11,
                        'cours' => [
                            ['code' => 'MAT103', 'intitule' => 'Analyse mathematique II', 'credits' => 6, 'parts' => ['td' => 0.3]],
                            ['code' => 'MAT104', 'intitule' => 'Geometrie analytique et differentielle', 'credits' => 5, 'parts' => ['td' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE6', 'intitule' => 'Physique appliquee', 'semestre' => 2, 'credits' => 8,
                        'cours' => [
                            ['code' => 'PHY102', 'intitule' => 'Physique generale II : electricite et magnetisme', 'credits' => 5, 'parts' => ['td' => 0.2, 'tp' => 0.2]],
                            ['code' => 'MEC101', 'intitule' => 'Mecanique rationnelle', 'credits' => 3, 'parts' => ['td' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE7', 'intitule' => 'Informatique et methodes de recherche', 'semestre' => 2, 'credits' => 6,
                        'cours' => [
                            ['code' => 'INF102', 'intitule' => 'Informatique 2 : algorithmique', 'credits' => 3, 'parts' => ['tp' => 0.5]],
                            ['code' => 'MET101', 'intitule' => 'Introduction a la recherche scientifique', 'credits' => 3, 'parts' => ['td' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE8', 'intitule' => 'Formation generale', 'semestre' => 2, 'credits' => 5,
                        'cours' => [
                            ['code' => 'CIT101', 'intitule' => 'Education a la citoyennete', 'credits' => 2],
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
                'intitule' => 'Premiere annee de licence en sciences biomedicales',
                'unites' => [
                    [
                        'code' => 'UE1-CSS', 'intitule' => 'Connaissances scientifiques de support I', 'semestre' => 1, 'credits' => 21,
                        'cours' => [
                            ['code' => 'ANA101', 'intitule' => 'Anatomie humaine generale', 'credits' => 6, 'parts' => ['tp' => 0.3]],
                            ['code' => 'BIO101', 'intitule' => 'Biologie cellulaire et moleculaire', 'credits' => 5, 'parts' => ['tp' => 0.25]],
                            ['code' => 'CHI102', 'intitule' => 'Chimie generale et organique', 'credits' => 5, 'parts' => ['tp' => 0.25]],
                            ['code' => 'PHY103', 'intitule' => 'Physique medicale', 'credits' => 5, 'parts' => ['td' => 0.2, 'tp' => 0.2]],
                        ],
                    ],
                    [
                        'code' => 'UE3-RCH', 'intitule' => 'Initiation a la recherche', 'semestre' => 1, 'credits' => 7,
                        'cours' => [
                            ['code' => 'BST101', 'intitule' => 'Biostatistique', 'credits' => 4, 'parts' => ['td' => 0.35]],
                            ['code' => 'INF103', 'intitule' => 'Informatique medicale', 'credits' => 3, 'parts' => ['tp' => 0.5]],
                        ],
                    ],
                    [
                        'code' => 'UE6-IDP', 'intitule' => 'Identite et deontologie professionnelles', 'semestre' => 1, 'credits' => 2,
                        'cours' => [
                            ['code' => 'HIS101', 'intitule' => 'Histoire de la medecine et deontologie', 'credits' => 2],
                        ],
                    ],
                    [
                        'code' => 'UE2-CSS', 'intitule' => 'Connaissances scientifiques de support II', 'semestre' => 2, 'credits' => 20,
                        'cours' => [
                            ['code' => 'PHS101', 'intitule' => 'Physiologie humaine', 'credits' => 6, 'parts' => ['tp' => 0.25]],
                            ['code' => 'HIT101', 'intitule' => 'Histologie et embryologie', 'credits' => 5, 'parts' => ['tp' => 0.35]],
                            ['code' => 'BCH101', 'intitule' => 'Biochimie medicale', 'credits' => 5, 'parts' => ['tp' => 0.3]],
                            ['code' => 'ANA102', 'intitule' => 'Anatomie des systemes', 'credits' => 4, 'parts' => ['tp' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE4-SP', 'intitule' => 'Sante publique', 'semestre' => 2, 'credits' => 6,
                        'cours' => [
                            ['code' => 'SPU101', 'intitule' => 'Sante publique et communautaire', 'credits' => 4, 'parts' => ['td' => 0.25]],
                            ['code' => 'EPI101', 'intitule' => 'Epidemiologie generale', 'credits' => 2, 'parts' => ['td' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE5-IDP', 'intitule' => 'Formation generale', 'semestre' => 2, 'credits' => 4,
                        'cours' => [
                            ['code' => 'CIT102', 'intitule' => 'Education a la citoyennete', 'credits' => 2],
                            ['code' => 'ANG103', 'intitule' => 'Anglais medical', 'credits' => 2, 'parts' => ['td' => 0.5]],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'DROIT' => [
        'DPJ' => [
            'L1' => [
                'intitule' => 'Premiere annee de licence en droit',
                'unites' => [
                    [
                        'code' => 'UE1', 'intitule' => 'Fondements du droit', 'semestre' => 1, 'credits' => 12,
                        'cours' => [
                            ['code' => 'DRT101', 'intitule' => 'Introduction generale a l\'etude du droit', 'credits' => 6, 'parts' => ['td' => 0.25]],
                            ['code' => 'DRT102', 'intitule' => 'Droit civil : les personnes et la famille', 'credits' => 6, 'parts' => ['td' => 0.25]],
                        ],
                    ],
                    [
                        'code' => 'UE2', 'intitule' => 'Sciences sociales et economiques', 'semestre' => 1, 'credits' => 9,
                        'cours' => [
                            ['code' => 'ECO101', 'intitule' => 'Economie politique', 'credits' => 5, 'parts' => ['td' => 0.2]],
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
                        'code' => 'UE4', 'intitule' => 'Methodologie et langues', 'semestre' => 1, 'credits' => 4,
                        'cours' => [
                            ['code' => 'MET102', 'intitule' => 'Methodologie du travail universitaire', 'credits' => 2, 'parts' => ['td' => 0.5]],
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
                        'code' => 'UE7', 'intitule' => 'Droit penal', 'semestre' => 2, 'credits' => 6,
                        'cours' => [
                            ['code' => 'DRT107', 'intitule' => 'Droit penal general', 'credits' => 6, 'parts' => ['td' => 0.25]],
                        ],
                    ],
                    [
                        'code' => 'UE8', 'intitule' => 'Formation generale', 'semestre' => 2, 'credits' => 4,
                        'cours' => [
                            ['code' => 'CIT103', 'intitule' => 'Education a la citoyennete', 'credits' => 2],
                            ['code' => 'INF104', 'intitule' => 'Informatique appliquee au droit', 'credits' => 2, 'parts' => ['tp' => 0.5]],
                        ],
                    ],
                ],
            ],
            'L2' => [
                'intitule' => 'Deuxieme annee de licence en droit',
                'unites' => [
                    [
                        'code' => 'UE1', 'intitule' => 'Droit public', 'semestre' => 1, 'credits' => 11,
                        'cours' => [
                            ['code' => 'DRT201', 'intitule' => 'Droit constitutionnel congolais', 'credits' => 6, 'parts' => ['td' => 0.25]],
                            ['code' => 'DRT202', 'intitule' => 'Droit administratif general', 'credits' => 5, 'parts' => ['td' => 0.2]],
                        ],
                    ],
                    [
                        'code' => 'UE2', 'intitule' => 'Droit prive', 'semestre' => 1, 'credits' => 11,
                        'cours' => [
                            ['code' => 'DRT203', 'intitule' => 'Droit civil : les contrats speciaux', 'credits' => 6, 'parts' => ['td' => 0.25]],
                            ['code' => 'DRT204', 'intitule' => 'Droit commercial general', 'credits' => 5, 'parts' => ['td' => 0.2]],
                        ],
                    ],
                    [
                        'code' => 'UE3', 'intitule' => 'Droit penal approfondi', 'semestre' => 1, 'credits' => 5,
                        'cours' => [
                            ['code' => 'DRT205', 'intitule' => 'Droit penal special', 'credits' => 5, 'parts' => ['td' => 0.2]],
                        ],
                    ],
                    [
                        'code' => 'UE4', 'intitule' => 'Methodes juridiques', 'semestre' => 1, 'credits' => 3,
                        'cours' => [
                            ['code' => 'MET201', 'intitule' => 'Methodologie juridique et recherche documentaire', 'credits' => 3, 'parts' => ['td' => 0.4]],
                        ],
                    ],
                    [
                        'code' => 'UE5', 'intitule' => 'Procedures', 'semestre' => 2, 'credits' => 11,
                        'cours' => [
                            ['code' => 'DRT206', 'intitule' => 'Procedure civile', 'credits' => 6, 'parts' => ['td' => 0.25]],
                            ['code' => 'DRT207', 'intitule' => 'Procedure penale', 'credits' => 5, 'parts' => ['td' => 0.25]],
                        ],
                    ],
                    [
                        'code' => 'UE6', 'intitule' => 'Droit du travail et de la securite sociale', 'semestre' => 2, 'credits' => 9,
                        'cours' => [
                            ['code' => 'DRT208', 'intitule' => 'Droit du travail', 'credits' => 5, 'parts' => ['td' => 0.2]],
                            ['code' => 'DRT209', 'intitule' => 'Droit de la securite sociale', 'credits' => 4],
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
                'intitule' => 'Premiere annee de licence en sciences economiques',
                'unites' => [
                    [
                        'code' => 'UE1', 'intitule' => 'Analyse economique I', 'semestre' => 1, 'credits' => 11,
                        'cours' => [
                            ['code' => 'ECO111', 'intitule' => 'Economie generale I : microeconomie', 'credits' => 6, 'parts' => ['td' => 0.3]],
                            ['code' => 'ECO112', 'intitule' => 'Histoire des faits economiques', 'credits' => 5],
                        ],
                    ],
                    [
                        'code' => 'UE2', 'intitule' => 'Methodes quantitatives I', 'semestre' => 1, 'credits' => 11,
                        'cours' => [
                            ['code' => 'MAT111', 'intitule' => 'Mathematiques generales', 'credits' => 6, 'parts' => ['td' => 0.35]],
                            ['code' => 'STA111', 'intitule' => 'Statistique descriptive', 'credits' => 5, 'parts' => ['td' => 0.3]],
                        ],
                    ],
                    [
                        'code' => 'UE3', 'intitule' => 'Comptabilite', 'semestre' => 1, 'credits' => 5,
                        'cours' => [
                            ['code' => 'CPT111', 'intitule' => 'Comptabilite generale I (SYSCOHADA)', 'credits' => 5, 'parts' => ['td' => 0.4]],
                        ],
                    ],
                    [
                        'code' => 'UE4', 'intitule' => 'Langues et methodologie', 'semestre' => 1, 'credits' => 3,
                        'cours' => [
                            ['code' => 'ANG111', 'intitule' => 'Anglais des affaires', 'credits' => 3, 'parts' => ['td' => 0.5]],
                        ],
                    ],
                    [
                        'code' => 'UE5', 'intitule' => 'Analyse economique II', 'semestre' => 2, 'credits' => 11,
                        'cours' => [
                            ['code' => 'ECO113', 'intitule' => 'Economie generale II : macroeconomie', 'credits' => 6, 'parts' => ['td' => 0.3]],
                            ['code' => 'ECO114', 'intitule' => 'Economie du developpement', 'credits' => 5],
                        ],
                    ],
                    [
                        'code' => 'UE6', 'intitule' => 'Methodes quantitatives II', 'semestre' => 2, 'credits' => 10,
                        'cours' => [
                            ['code' => 'MAT112', 'intitule' => 'Mathematiques financieres', 'credits' => 5, 'parts' => ['td' => 0.4]],
                            ['code' => 'STA112', 'intitule' => 'Probabilites et statistique inferentielle', 'credits' => 5, 'parts' => ['td' => 0.35]],
                        ],
                    ],
                    [
                        'code' => 'UE7', 'intitule' => 'Comptabilite approfondie', 'semestre' => 2, 'credits' => 6,
                        'cours' => [
                            ['code' => 'CPT112', 'intitule' => 'Comptabilite generale II', 'credits' => 6, 'parts' => ['td' => 0.4]],
                        ],
                    ],
                    [
                        'code' => 'UE8', 'intitule' => 'Formation generale', 'semestre' => 2, 'credits' => 3,
                        'cours' => [
                            ['code' => 'CIT111', 'intitule' => 'Education a la citoyennete', 'credits' => 3],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
