# Test d'intégration jetable du plugin « SLA : première réponse ».
#
#   pwsh contrib/sla-first-response/test/run.ps1 [-MariaDbImage mariadb:10.11] [-Keep]
#
# Monte une instance osTicket de test dans des conteneurs Docker (réseau, base
# MariaDB et PHP CLI dédiés, tous supprimés à la fin), à partir du dernier commit
# (git archive, sans conversion CRLF : la signature du schéma doit rester valide)
# plus le plugin tel qu'il est dans l'arbre de travail. Puis exécute :
#   1. phase1-sans-plugin.php        constats sur le cœur, contournements écartés
#   2. phase2a-installation-plugin   installation et refus de configurations invalides
#   3. phase2b-plugin-actif.php      comportement du plugin
# Aucun fichier du dépôt n'est modifié ; rien n'est envoyé hors de la machine
# (à part le téléchargement des images Docker et des paquets Debian du build).
param(
    [string]$MariaDbImage = 'mariadb:10.11',
    [switch]$Keep
)
$ErrorActionPreference = 'Stop'
$here = $PSScriptRoot
$repo = (git -C $here rev-parse --show-toplevel).Trim()
$suffix = Get-Random -Maximum 99999
$net = "ostsla$suffix"; $db = "ostsla-db-$suffix"; $web = "ostsla-web-$suffix"
$tmp = Join-Path ([IO.Path]::GetTempPath()) "ostsla-$suffix"
$failed = $false

try {
    New-Item -ItemType Directory -Force "$tmp\app" | Out-Null
    git -C $repo -c core.autocrlf=false archive -o "$tmp\src.tar" HEAD
    tar -xf "$tmp\src.tar" -C "$tmp\app"
    New-Item -ItemType Directory -Force "$tmp\app\include\plugins\sla_first_response" | Out-Null
    Copy-Item "$here\..\plugin\sla_first_response\*" "$tmp\app\include\plugins\sla_first_response\"
    Copy-Item "$tmp\app\include\ost-sampleconfig.php" "$tmp\app\include\ost-config.php"

    docker build -q -t ost-sla-test-php:tmp $here | Out-Null
    docker network create $net | Out-Null
    docker run -d --name $db --network $net -e MARIADB_ROOT_PASSWORD=rootpw -e MARIADB_DATABASE=ost $MariaDbImage | Out-Null
    docker run -d --name $web --network $net -v "$tmp\app:/app" -w /app ost-sla-test-php:tmp `
        php -d date.timezone=UTC -d memory_limit=512M -S 0.0.0.0:8000 -t /app | Out-Null
    for ($i = 0; $i -lt 60; $i++) {
        docker exec $db mariadb -uroot -prootpw -e 'select 1' 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { break }
        Start-Sleep 1
    }
    docker exec $web sh -c 'chmod 666 /app/include/ost-config.php'
    foreach ($f in 'common.php', 'phase1-sans-plugin.php', 'phase2a-installation-plugin.php', 'phase2b-plugin-actif.php', 'install-http.php') {
        docker cp "$here\$f" "${web}:/tmp/$f"
    }
    # install-http.php vise la base « ostsla-db » : l'alias réseau est le nom du conteneur
    docker exec $web sh -c "sed -i 's/ostsla-db/$db/' /tmp/install-http.php"
    $out = docker exec $web php /tmp/install-http.php 2>&1 | Out-String
    if ($out -notmatch 'INSTALL OK') { throw "Installation d'osTicket échouée : $out" }

    foreach ($f in 'phase1-sans-plugin.php', 'phase2a-installation-plugin.php', 'phase2b-plugin-actif.php') {
        "===== $f"
        $res = docker exec $web php -d date.timezone=UTC -d memory_limit=512M "/tmp/$f" 2>&1 |
            Where-Object { $_ -notmatch 'sendmail' }
        $res
        if ($res -match '\[FAIL\]') { $failed = $true }
    }
}
finally {
    if (-not $Keep) {
        docker rm -f $web $db 2>$null | Out-Null
        docker network rm $net 2>$null | Out-Null
        Remove-Item -Recurse -Force $tmp -ErrorAction SilentlyContinue
    }
}
if ($failed) { Write-Error 'Au moins un test a échoué.'; exit 1 }
"Tous les tests sont passés."
