# Déploiement de Kelasi sous Windows.
#
# Équivalent de deploy.sh, que PowerShell ne sait pas exécuter. Comme lui, il
# est fait pour être relancé sans crainte : chaque étape est soit idempotente,
# soit sans effet quand elle a déjà été faite.
#
# On ne met pas $ErrorActionPreference à 'Stop' : depuis PowerShell 7.3, cela
# fait lever une exception dès qu'une commande native sort en erreur, avant
# qu'on ait pu lire son code de retour et afficher un message utile. Les codes
# de sortie sont donc vérifiés explicitement.

Set-Location -Path $PSScriptRoot

function Etape($texte) { Write-Host "-> $texte" -ForegroundColor Cyan }
function Avis($texte)  { Write-Host "   $texte" -ForegroundColor Yellow }
function Echec($texte) { Write-Host $texte -ForegroundColor Red; exit 1 }

# --- 1. Configuration ------------------------------------------------------

if (-not (Test-Path '.env')) {
    Etape 'Création du fichier .env'
    Copy-Item '.env.example' '.env'
    Avis "Un .env a été créé. Remplacez-y DB_PASSWORD, puis relancez ce script."
    exit 0
}

$config = Get-Content '.env' -Raw

if ($config -notmatch '(?m)^DB_PASSWORD=.+') {
    Echec "DB_PASSWORD est vide dans .env : PostgreSQL refusera de démarrer."
}

if ($config -match '(?m)^DB_PASSWORD=kelasi-changez-ce-mot-de-passe') {
    Avis "DB_PASSWORD est encore celui de l'exemple. À remplacer avant toute mise en service."
}

# --- 2. Images -------------------------------------------------------------
# La construction vient avant tout le reste : sans image, la génération de la
# clé échouerait sur un message qui n'aurait rien à voir avec la cause.

# Un domaine déclaré veut dire HTTPS : Caddy se place devant nginx et obtient
# le certificat. Ce n'est pas un luxe -- hors de « localhost », un navigateur
# refuse d'enregistrer un service worker sur une origine en clair, et aucun
# téléphone ne proposera d'installer Kelasi.
$domaine = if ($config -match '(?m)^KELASI_DOMAINE=(\S+)') { $Matches[1] } else { '' }
$profils = if ($domaine -and $domaine -ne 'localhost') { @('--profile', 'https') } else { @() }

if ($profils.Count -gt 0) { Etape "Domaine déclaré : $domaine - le service HTTPS sera démarré" }

Etape 'Construction des images'
docker compose @profils build
if ($LASTEXITCODE -ne 0) { Echec "La construction a échoué. La sortie ci-dessus en donne la raison." }

# --- 3. Clé d'application --------------------------------------------------
# Sans elle, sessions et mots de passe deviennent illisibles au redémarrage.

if ($config -notmatch '(?m)^APP_KEY=base64:') {
    Etape "Génération de la clé d'application"
    docker compose run --rm app php artisan key:generate
    if ($LASTEXITCODE -ne 0) { Echec "La génération de la clé a échoué." }
}

# --- 4. Services -----------------------------------------------------------

Etape 'Démarrage des services'
docker compose @profils up -d
if ($LASTEXITCODE -ne 0) { Echec "Le démarrage a échoué. Voyez : docker compose logs" }

Etape 'Attente de la base'
$prete = $false
for ($essai = 1; $essai -le 30 -and -not $prete; $essai++) {
    Start-Sleep -Seconds 2
    docker compose exec -T db pg_isready -q 2>&1 | Out-Null
    $prete = ($LASTEXITCODE -eq 0)
}

if (-not $prete) { Echec "La base n'a pas démarré après une minute. Voyez : docker compose logs db" }

# --- 5. Base de données ----------------------------------------------------

Etape 'Migrations'
docker compose exec -T app php artisan migrate --force
if ($LASTEXITCODE -ne 0) { Echec "Les migrations ont échoué." }

Etape 'Mise en cache de la configuration'
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache

# --- 6. Compte rendu -------------------------------------------------------

Etape 'Vérification'
docker compose ps

$port    = if ($config -match '(?m)^KELASI_HTTP_PORT=(\d+)')    { $Matches[1] } else { '8093' }
$adminer = if ($config -match '(?m)^KELASI_ADMINER_PORT=(\d+)') { $Matches[1] } else { '8094' }

Write-Host ''
if ($profils.Count -gt 0) {
    Write-Host "Kelasi est en ligne sur https://$domaine" -ForegroundColor Green
    Write-Host "Le certificat peut demander une minute au premier démarrage : docker compose logs caddy"
} else {
    Write-Host "Kelasi est en ligne sur http://localhost:$port" -ForegroundColor Green
    Write-Host "Sans domaine ni HTTPS, l'application ne s'installera sur aucun téléphone. Voir mobile/README.md."
}
Write-Host "Adminer : http://localhost:$adminer"
Write-Host ''
Write-Host "Pour installer le jeu de démonstration (efface les données existantes) :" -ForegroundColor Gray
Write-Host "    docker compose exec app php artisan migrate:fresh --seed" -ForegroundColor Gray
