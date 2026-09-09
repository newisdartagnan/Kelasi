# Kelasi sur Android et sur iPhone

Kelasi est une application web installable. Posée sur l'écran d'accueil, elle
s'ouvre en plein écran, sans barre d'adresse, garde la session ouverte,
fonctionne hors connexion et reçoit les rappels du matin. Pour un chef de
promotion qui saisit ses séances debout entre deux amphis, c'est exactement le
comportement attendu d'une application.

Il y a donc trois façons de la mettre sur un téléphone, et elles ne se valent
pas.

|                             | Depuis le navigateur | Play Store (Android) | App Store (iOS)      |
| --------------------------- | -------------------- | -------------------- | -------------------- |
| Délai                       | immédiat             | 1 à 3 jours d'examen | 1 à 2 semaines       |
| Coût                        | rien                 | 25 $ une fois        | 99 $ par an          |
| Matériel                    | rien                 | rien                 | **un Mac**           |
| Notifications poussées      | oui                  | oui                  | **non** (voir plus bas) |
| Mise à jour du code         | au rechargement      | au rechargement      | au rechargement      |
| Risque de refus             | aucun                | faible               | **réel**             |

La première voie fonctionne aujourd'hui, sur les deux systèmes, sans compte
d'éditeur ni examen d'aucune sorte. Les deux autres n'ajoutent qu'une chose :
la présence dans un magasin d'applications. C'est une chose qui compte pour
une université — on y cherche « Kelasi » et on la trouve — mais ce n'est que
cela, et elle se paie.

---

## Ce qu'il faut d'abord, dans tous les cas : HTTPS

Hors de `localhost`, un navigateur refuse d'enregistrer un service worker sur
une origine servie en clair. Sans service worker : pas de mode hors ligne,
pas de notification poussée, et **aucune proposition d'installation**. Une
application Android construite sur un site en HTTP ne se lance pas non plus.

Le certificat est automatique :

```sh
# .env
KELASI_DOMAINE=kelasi.mon-universite.cd     # exemple : mettre VOTRE domaine
APP_URL=https://kelasi.mon-universite.cd

docker compose --profile https up -d
```

`kelasi.mon-universite.cd` n'est qu'un exemple. Le domaine doit être un nom que
l'université possède et qui **pointe sur la machine où tourne Kelasi** : une
entrée DNS de type A, créée par l'administrateur du domaine de l'université, ou
un nom acheté pour l'occasion. Tant que cette entrée n'existe pas, l'adresse ne
mène nulle part, quel que soit l'état du serveur.

Les ports 80 et 443 doivent parvenir à la machine : le 443 pour le service, le
80 pour la validation du certificat et la redirection. Un serveur interne,
injoignable depuis Internet, ne peut pas obtenir de certificat public ; il faut
alors celui de l'université, déclaré dans `Caddyfile` :

```
tls /etc/caddy/kelasi.crt /etc/caddy/kelasi.key
```

---

## Essayer sur son téléphone, sans domaine

Attendre une entrée DNS pour voir à quoi ressemble l'installation serait
dommage. Deux chemins existent, tout de suite, sur le réseau local.

### Android, par le câble — cinq minutes, rien à installer

Chrome sait faire passer un port du téléphone vers l'ordinateur. Comme le
téléphone voit alors l'application sur `localhost`, qui est toujours considéré
comme une origine sûre, **tout fonctionne sans le moindre certificat** :
service worker, installation, mode hors ligne.

1. sur le téléphone : *Paramètres › Options pour les développeurs › Débogage
   USB* ;
2. brancher le câble, accepter l'autorisation qui s'affiche ;
3. sur l'ordinateur, dans Chrome : `chrome://inspect/#devices`, cocher
   **Port forwarding**, ajouter `8093` → `localhost:8093` ;
4. sur le téléphone, ouvrir `http://localhost:8093`.

L'invite d'installation apparaît comme elle le fera en production.

### Android et iPhone, par le Wi-Fi — avec le certificat de Caddy

Sans câble, il faut un certificat, et donc faire reconnaître au téléphone
l'autorité que Caddy fabrique lui-même. Caddy s'en charge dès qu'on lui donne
une adresse IP plutôt qu'un domaine :

```sh
# .env — l'adresse de l'ordinateur sur le réseau local
KELASI_DOMAINE=192.168.1.20
APP_URL=https://192.168.1.20

docker compose --profile https up -d
```

Reste à extraire l'autorité et à la porter sur le téléphone :

```sh
docker compose cp caddy:/data/caddy/pki/authorities/local/root.crt kelasi-ca.crt
```

- **Android** : *Paramètres › Sécurité › Chiffrement et identifiants ›
  Installer un certificat › Certificat CA*.
- **iPhone** : envoyer le fichier au téléphone, l'ouvrir pour installer le
  profil, puis — c'est l'étape qu'on oublie — *Réglages › Général ›
  Informations › Réglages des certificats de confiance* et activer la
  confiance. Sans elle, Safari refuse toujours.

Ce chemin sert à essayer, pas à mettre en service : chaque téléphone de
l'université devrait recevoir ce certificat à la main. Pour la mise en service,
il faut un vrai domaine.

Un certificat auto-signé sans autorité reconnue, lui, ne convient dans aucun
cas : les téléphones le refusent, et le service worker avec lui.

---

## Voie 1 — l'installation depuis le navigateur

C'est celle que l'application propose d'elle-même. Une bande apparaît en haut
de l'écran, une seule fois, et disparaît pour un mois si on l'écarte.

**Android.** Chrome signale l'application installable ; la bande affiche un
bouton *Installer*, et le système fait le reste. L'icône se pose sur l'écran
d'accueil, dans le tiroir d'applications, et Kelasi apparaît dans la liste des
applications récentes comme n'importe quelle autre.

**iPhone.** iOS n'a pas d'équivalent : rien ne peut ouvrir le menu de partage
à la place de l'utilisateur. La bande décrit donc les deux gestes —
*Partager*, puis *Sur l'écran d'accueil*. Il faut passer par **Safari** :
depuis Chrome ou Firefox sur iPhone, l'entrée n'existe pas ou ne produit qu'un
marque-page.

Les notifications poussées fonctionnent sur iPhone **à partir d'iOS 16.4, et
seulement une fois l'application posée sur l'écran d'accueil**. Ouverte dans un
onglet Safari, elle n'en reçoit aucune. C'est une raison de plus d'installer.

---

## Voie 2 — le Play Store

Google accepte explicitement les applications web empaquetées : le format
s'appelle *Trusted Web Activity*, et le paquet ne contient pas de code, il
ouvre le site en plein écran dans un Chrome sans barre d'adresse. Tout ce que
fait Kelasi — hors ligne, notifications, mises à jour — continue de
fonctionner, puisque c'est le même site.

Il faut un compte Play Console (25 $, une fois) et le domaine servi en HTTPS.

```sh
cd mobile/android
./preparer.sh kelasi.mon-universite.cd

npx @bubblewrap/cli@latest build
npx @bubblewrap/cli@latest fingerprint list
```

`build` demande de créer une clé de signature au premier passage. **Cette clé
ne se remplace pas** : qui la perd ne peut plus publier de mise à jour, jamais,
et qui la détient peut en publier à la place de l'université. Elle se garde
hors ligne, avec son mot de passe. Le `.gitignore` du dossier l'exclut du
dépôt ; ce n'est pas une précaution superflue.

`fingerprint list` affiche l'empreinte SHA-256 de cette clé. Elle se reporte
dans le `.env` du serveur :

```sh
ANDROID_PAQUET=cd.kelasi.app
ANDROID_EMPREINTES_SHA256=AA:BB:CC:...
```

L'application sert alors `/.well-known/assetlinks.json`, que Chrome lit au
premier lancement pour vérifier que le site reconnaît bien ce paquet. Sans
cela, Android affiche une barre d'adresse par-dessus Kelasi — l'application
fonctionne, mais l'enveloppe se voit.

Google resigne les paquets qu'il distribue. Après le premier envoi, relever
l'empreinte affichée dans **Play Console › Intégrité de l'app › Signature
d'application** et l'ajouter à la liste, séparée par une virgule : la clé
locale sert aux essais, celle de Google aux téléphones réels, et les deux
doivent être reconnues.

Le fichier se vérifie d'un coup d'œil :

```sh
curl https://kelasi.mon-universite.cd/.well-known/assetlinks.json
```

---

## Voie 3 — l'App Store

C'est la voie la plus coûteuse et la moins sûre, et il faut le dire avant de
s'y engager.

**Ce qu'elle exige.** Un Mac avec Xcode — il n'existe aucun moyen légal de
construire une application iOS ailleurs. Un compte Apple Developer à 99 $ par
an, à renouveler, faute de quoi l'application disparaît du magasin. Un examen
d'une à deux semaines à chaque version.

**Ce qu'elle fait perdre.** Une enveloppe iOS affiche le site dans une
`WKWebView`. Or les notifications poussées d'iOS ne fonctionnent **que** pour
les applications web posées sur l'écran d'accueil, pas dans une `WKWebView`.
Publier Kelasi sur l'App Store, tel quel, ferait donc perdre les rappels du
matin sur iPhone — précisément ce que la voie 1 donne gratuitement. Les
retrouver demanderait de brancher APNs : un certificat Apple, un
`@capacitor/push-notifications`, et un second expéditeur à écrire côté serveur,
en plus du push web déjà en place.

**Le risque de refus.** La règle 4.2 des directives d'Apple écarte les
applications qui ne sont qu'un site web empaqueté. Une application de gestion
réservée à une université passe parfois, en expliquant qu'elle s'adresse à un
public fermé ; elle est aussi refusée, régulièrement. Il faut le savoir avant
de payer.

**Les deux voies plus sûres**, si la présence dans l'App Store n'est pas une
exigence en soi :

- l'installation depuis Safari (voie 1) — immédiate, gratuite, avec les
  notifications ;
- **Apple Business Manager**, qui distribue une application privée à une
  organisation, sans passage par le magasin public. Toujours 99 $ par an et
  toujours un Mac, mais l'examen y est plus favorable à une application
  interne.

Si le choix est fait malgré tout, le nécessaire est ici :

```sh
cd mobile/ios
./preparer.sh kelasi.mon-universite.cd
npx cap open ios
```

`limitsNavigationsToAppBoundDomains` est actif dans la configuration : la
`WKWebView` n'ira nulle part ailleurs que sur le domaine de Kelasi. Reste, dans
Xcode, à choisir l'équipe de signature, à régler le numéro de version, puis
*Product › Archive*.

---

## Ce qui a été fait côté application

- l'invite d'installation (`resources/js/installation.js`, la bande de
  `resources/views/composants/invite-installation.blade.php`) ;
- les marges de l'encoche et de l'indicateur d'accueil de l'iPhone, sans quoi
  le logo passe sous l'heure et la dernière ligne sous la barre du bas ;
- la barre d'état en texte sombre : l'interface est claire, du texte blanc y
  serait illisible ;
- le manifeste : identifiant stable, captures d'écran pour la boîte
  d'installation d'Android, reprise de la fenêtre existante au clic sur une
  notification ;
- `/.well-known/assetlinks.json`, servi depuis la configuration ;
- le profil HTTPS de la pile Docker.
