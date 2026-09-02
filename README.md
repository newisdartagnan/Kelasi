# Kelasi

Le suivi du déroulement des enseignements dans une université, faculté par
faculté et promotion par promotion.

Kelasi répond à une seule question, mais la pose partout : **où en est
réellement ce cours, et de combien est-il en retard ?**

## Le principe

L'objet central n'est ni l'étudiant ni la note : c'est **la séance
réellement tenue**. Autour d'elle, une chaîne de responsabilité en deux
temps :

> **Le chef de promotion saisit ce qui s'est passé dans la salle.
> L'enseignant contresigne.**

Cette séparation est ce qui donne au chiffre sa valeur probante. Une séance
saisie mais non contresignée n'entre pas dans l'avancement : elle apparaît à
part, en attente. Et la personne qui saisit ne peut jamais valider sa propre
déclaration — la règle est appliquée par le service, et testée.

Le dispositif tient sans aucune infrastructure sur le campus : pas de
badgeuse, pas de salle connectée. L'étudiant est le capteur, l'enseignant le
validateur, le doyen le lecteur.

## Les rôles

| Rôle | Ce qu'il fait |
|---|---|
| **Étudiant** | Consulte l'avancement de sa promotion, le calendrier, les documents |
| **CP / CPA** | Saisit chaque séance, crée les activités de sa promotion |
| **Enseignant** | Contresigne ou conteste, dépose la documentation, demande une modification du programme |
| **DF / DFA** | Pilote sa faculté, désigne et suspend les chefs de promotion |
| **VDE** | Détient le programme, arbitre les demandes de modification, voit toute l'université |
| **Administrateur** | Dépose les listes d'inscrits, administre les comptes |

## Le modèle des données

Le programme suit le format **LMD tel qu'appliqué en RDC** depuis la réforme
de 2021 : une promotion suit des **unités d'enseignement** réparties sur deux
semestres de 30 crédits, chaque UE se décomposant en **cours** — les éléments
constitutifs — dont le volume est ventilé en CMI, TD, TP et TPE.

Les volumes horaires ne sont jamais saisis à la main. Ils se déduisent des
crédits par la règle ministérielle, codée dans `App\Support\VolumeHoraire` :

- **1 crédit = 25 heures** de travail étudiant ;
- **deux tiers encadrées**, un tiers de travail personnel ;
- seules les heures encadrées se déroulent en salle, et ce sont donc les
  seules que les séances viennent consommer.

Le contrôle tombe juste : 30 crédits donnent 750 heures, exactement le
chiffre publié par la faculté de médecine de l'Université de Lubumbashi.
Corriger une maquette, c'est changer un nombre de crédits — jamais
recalculer des heures.

## Provenance des données livrées

`database/data/programmes.php` contient cinq maquettes réelles :
polytechnique L1, médecine L1, droit L1 et L2, sciences économiques L1 —
69 cours, chaque semestre à exactement 30 crédits.

**Ce qui vient d'une source publiée :**

- la liste des treize facultés de l'Université de Kinshasa ;
- les intitulés de première année de polytechnique (faculté polytechnique de
  l'Université de Lubumbashi) ;
- la structure en unités CSS / RCH / IDP de la première année de médecine et
  son total de 750 heures pour 30 crédits (même université) ;
- le placement du droit constitutionnel en deuxième année de licence ;
- la règle de conversion crédits / heures des instructions académiques du
  MINESU.

**Ce qui est une reconstruction cohérente :** le découpage précis des UE, la
répartition des crédits cours par cours, et les codes. Ces maquettes sont
plausibles et vérifiées arithmétiquement, mais **elles ne sont pas
officielles**. Avant tout déploiement réel, elles doivent être remplacées par
celles que le secrétariat académique de l'établissement aura validées.

## Ce qui a été durci par rapport au cahier des charges d'origine

- **L'inscription n'est plus libre.** Le cahier des charges prévoyait un
  « sign up » ouvert : n'importe qui aurait pu se déclarer étudiant d'une
  promotion, et l'avancement n'aurait plus rien attesté. L'inscription
  s'adosse désormais à une liste de matricules déposée par le secrétariat
  (`inscriptions_autorisees`) : on n'ouvre pas un compte, on active une ligne
  qui existe déjà.
- **Le hors ligne est une exigence de départ**, pas une amélioration. Un chef
  de promotion saisit dans un amphi sans réseau.
- **L'avancement se lit par semestre.** Mélanger un premier semestre achevé
  avec un second à peine commencé donne un chiffre qui ne veut rien dire.

## Le mode hors ligne

La saisie part dans une file **IndexedDB** locale et remonte dès que la
connexion revient (`resources/js/offline.js`). L'**uuid est généré sur
l'appareil** : c'est lui qui rend la remontée rejouable. Un chef de promotion
dont la connexion coupe en plein envoi relance sans rien dupliquer.

Le serveur traite chaque ligne du lot pour elle-même et répond en trois
listes — `acceptees`, `ignorees`, `refusees` — pour que le client sache
exactement ce qu'il peut retirer de sa file.

## La PWA

Installable sur Android et sur iOS, à partir d'une seule base de code qui
sert aussi le poste de bureau des doyens.

- **Android / Chrome** : installation complète, invite automatique.
- **iOS / Safari 16.4+** : installation manuelle (Partager → Sur l'écran
  d'accueil). Il n'existe pas d'invite automatique, et le push web n'y
  fonctionne qu'une fois l'application installée. Pas de Background Sync :
  c'est pourquoi la synchronisation se déclenche à l'événement `online` et au
  chargement, jamais en tâche de fond.

Le service worker sert les fichiers statiques depuis le cache, mais demande
toujours **les pages au réseau d'abord**. Un avancement périmé induirait un
doyen en erreur : mieux vaut une page plus lente qu'un chiffre faux. Les
écritures ne passent jamais par le cache — la file hors ligne s'en charge.

## Pile technique

Reprise de celle déjà en production sur le projet hospitalier, pour que
l'équipe n'ait ni nouvel outillage ni nouvelle exploitation à apprendre.

- **Laravel 12** / PHP 8.3+
- **Livewire 3**, Alpine, **Tailwind 4**, Vite
- **PostgreSQL** en production (SQLite pour les tests)
- `spatie/laravel-permission` pour les rôles, `spatie/laravel-activitylog`
  pour la traçabilité

## Installation

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite      # ou configurer PostgreSQL dans .env
php artisan migrate --seed

npm run build
php artisan serve
```

Le seeder installe les treize facultés, les cinq maquettes, et un jeu de
démonstration avec un semestre déjà entamé — dont un cours sur cinq en
décrochage net, la situation qu'un doyen doit repérer d'un coup d'œil.

**Comptes de démonstration**, mot de passe `kelasi2026` :

| Matricule | Rôle |
|---|---|
| `VDE-001` | Vice-recteur chargé de l'enseignement |
| `DF-DROIT` | Doyen de la faculté de droit |
| `CP-1` | Chef de promotion |
| `ENS-1` | Enseignant |

## Tests

```bash
php artisan test
```

Les tests couvrent ce qui doit tenir : les maquettes tombent à 30 crédits par
semestre, un semestre vaut 750 heures, celui qui saisit ne peut pas valider,
une séance contestée sort de l'avancement, et un lot renvoyé deux fois ne
crée pas de doublon.

## Ce qui reste à faire

- Messagerie interne (les tables existent, l'interface non)
- Dépôt et partage de documents par les enseignants
- Activités : examens, interrogations, visites guidées
- Demandes de modification du programme et arbitrage par le VDE
- Notifications quotidiennes et push web
- Export Excel de l'avancement pour le VDE
- Import des listes d'inscrits par le secrétariat
