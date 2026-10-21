# Test d'intégration jetable du plugin « SLA : première réponse » et des requêtes SQL.
#
#   pwsh contrib/sla-first-response/test/run.ps1 [-MariaDbImage mariadb:10.11] [-Keep]
#   pwsh contrib/sla-first-response/test/run.ps1 -MariaDbImage mysql:8.0
#
# Monte une instance osTicket de test dans des conteneurs Docker (réseau, base
# MariaDB ou MySQL et PHP CLI dédiés, tous nommés avec un suffixe aléatoire et
# supprimés à la fin ; aucun autre conteneur n'est touché), à partir du dernier
# commit (git archive, sans conversion CRLF : la signature du schéma doit rester
# valide) plus le plugin et les requêtes SQL tels qu'ils sont dans l'arbre de
# travail. Puis exécute :
#   1. phase1-sans-plugin.php        constats sur le cœur, contournements écartés
#   2. phase2a-installation-plugin   installation et refus de configurations invalides
#   3. phase2b-plugin-actif.php      comportement du plugin (réponse avec / sans e-mail)
#   4. phase3-sql.php                requêtes sql/ sur des tickets et des lignes sub: factices
# Aucun fichier du dépôt n'est modifié ; rien n'est envoyé hors de la machine
# (à part le téléchargement des images Docker et des paquets Debian du build).
# Lancer le script une fois par moteur : mariadb:10.11 ET mysql:8.0.
param(
    [Alias('DbImage')]
    [string]$MariaDbImage = 'mariadb:10.11',
    [switch]$Keep
)
$ErrorActionPreference = 'Stop'
$here = $PSScriptRoot
$repo = (git -C $here rev-parse --show-toplevel).Trim()
$suffix = Get-Random -Maximum 999999
$net = "ostsla$suffix"; $db = "ostsla-db-$suffix"; $web = "ostsla-web-$suffix"
$phpImage = "ost-sla-test-php:$suffix"
$tmp = Join-Path ([IO.Path]::GetTempPath()) "ostsla-$suffix"
$failed = $false
$isMysql = $MariaDbImage -like 'mysql*'
$client = if ($isMysql) { 'mysql' } else { 'mariadb' }

try {
    New-Item -ItemType Directory -Force "$tmp\app" | Out-Null
    git -C $repo -c core.autocrlf=false archive -o "$tmp\src.tar" HEAD
    tar -xf "$tmp\src.tar" -C "$tmp\app"
    New-Item -ItemType Directory -Force "$tmp\app\include\plugins\sla_first_response" | Out-Null
    Copy-Item "$here\..\plugin\sla_first_response\*" "$tmp\app\include\plugins\sla_first_response\"
    Copy-Item "$tmp\app\include\ost-sampleconfig.php" "$tmp\app\include\ost-config.php"

    docker build -q -t $phpImage $here | Out-Null
    docker network create $net | Out-Null
    if ($isMysql) {
        docker run -d --name $db --network $net -e MYSQL_ROOT_PASSWORD=rootpw -e MYSQL_DATABASE=ost $MariaDbImage `
            --default-authentication-plugin=mysql_native_password | Out-Null
    } else {
        docker run -d --name $db --network $net -e MARIADB_ROOT_PASSWORD=rootpw -e MARIADB_DATABASE=ost $MariaDbImage | Out-Null
    }
    docker run -d --name $web --network $net -v "$tmp\app:/app" -w /app $phpImage `
        php -d date.timezone=UTC -d memory_limit=512M -S 0.0.0.0:8000 -t /app | Out-Null
    # Attente par TCP : le serveur temporaire d'initialisation de l'image MySQL n'écoute pas sur le réseau
    for ($i = 0; $i -lt 90; $i++) {
        docker exec $db $client -h 127.0.0.1 -uroot -prootpw -e 'select 1' 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { break }
        Start-Sleep 1
    }
    "Moteur : " + (docker exec $db $client -h 127.0.0.1 -uroot -prootpw -N -e 'select version(), @@global.time_zone, @@global.sql_mode' | Out-String).Trim()
    docker exec $web sh -c 'chmod 666 /app/include/ost-config.php'
    foreach ($f in 'common.php', 'phase1-sans-plugin.php', 'phase2a-installation-plugin.php', 'phase2b-plugin-actif.php', 'phase3-sql.php', 'install-http.php') {
        docker cp "$here\$f" "${web}:/tmp/$f"
    }
    docker cp "$here\..\sql" "${web}:/tmp/sql"
    # install-http.php vise la base « ostsla-db » : l'alias réseau est le nom du conteneur
    docker exec $web sh -c "sed -i 's/ostsla-db/$db/' /tmp/install-http.php"
    $out = docker exec $web php /tmp/install-http.php 2>&1 | Out-String
    if ($out -notmatch 'INSTALL OK') { throw "Installation d'osTicket échouée : $out" }

    foreach ($f in 'phase1-sans-plugin.php', 'phase2a-installation-plugin.php', 'phase2b-plugin-actif.php', 'phase3-sql.php') {
        "===== $f"
        $res = docker exec $web php -d date.timezone=UTC -d memory_limit=512M "/tmp/$f" 2>&1 |
            Where-Object { $_ -notmatch 'sendmail' }
        $res
        if ($res -match '\[FAIL\]|ERREUR SQL|Fatal error|Parse error|Warning:|Uncaught') { $failed = $true }
    }
}
finally {
    if (-not $Keep) {
        docker rm -f $web $db 2>$null | Out-Null
        docker network rm $net 2>$null | Out-Null
        docker rmi $phpImage 2>$null | Out-Null
        Remove-Item -Recurse -Force $tmp -ErrorAction SilentlyContinue
    }
}
if ($failed) { Write-Error 'Au moins un test a échoué.'; exit 1 }
"Tous les tests sont passés."
