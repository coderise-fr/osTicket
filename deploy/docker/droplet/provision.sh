#!/bin/sh
# One-time preparation of a fresh Ubuntu/Debian Droplet for the osTicket stack.
# Run as root:
#
#   SUPPORT_DOMAIN=support.coderise.fr sh provision.sh [--harden-ssh]
#
# Does: system updates + unattended security upgrades, 2 GB swap, UFW (22/80/443 only),
# Docker Engine + Compose, Caddy (installed but NOT started: see README), a `deploy`
# user (docker group, root's SSH keys), the deploy/docker checkout in /opt/osticket,
# the daily backup timer. Safe to re-run.
#
# --harden-ssh  disable root login and password login over SSH. Test `ssh deploy@...`
#               in a second terminal first: the script refuses if deploy has no key.
set -eu

[ "$(id -u)" -eq 0 ] || { echo "run as root" >&2; exit 1; }
DOMAIN="${SUPPORT_DOMAIN:?set SUPPORT_DOMAIN, e.g. support.coderise.fr}"
REPO_URL="${REPO_URL:-https://github.com/coderise-fr/osTicket.git}"
BRANCH="${BRANCH:-main}"
APP=/opt/osticket
export DEBIAN_FRONTEND=noninteractive

. /etc/os-release
case "$ID" in ubuntu|debian) ;; *) echo "unsupported OS: $ID" >&2; exit 1 ;; esac

echo 'DPkg::Lock::Timeout "600";' > /etc/apt/apt.conf.d/99-lock-timeout   # first boot: cloud-init may hold the lock
echo "== packages"
apt-get update
apt-get -y upgrade
apt-get -y install ca-certificates curl gnupg git ufw unattended-upgrades apt-transport-https debian-keyring debian-archive-keyring
dpkg-reconfigure -f noninteractive unattended-upgrades

echo "== swap (2 GB)"
if ! swapon --show | grep -q .; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
echo 'vm.swappiness=10' > /etc/sysctl.d/99-osticket.conf
sysctl -q -p /etc/sysctl.d/99-osticket.conf

echo "== firewall (UFW)"
ufw default deny incoming
ufw default allow outgoing
ufw limit 22/tcp
ufw allow 80/tcp
ufw allow 443
ufw --force enable

echo "== Docker Engine"
if ! command -v docker >/dev/null 2>&1; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL "https://download.docker.com/linux/$ID/gpg" -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/$ID $VERSION_CODENAME stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update
    apt-get -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
systemctl enable --now docker

echo "== Caddy"
if ! command -v caddy >/dev/null 2>&1; then
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor --yes -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' > /etc/apt/sources.list.d/caddy-stable.list
    apt-get update
    apt-get -y install caddy
fi
# Not started yet: while the setup wizard runs it would publish it on the domain.
systemctl disable --now caddy || true

echo "== deploy user"
id deploy >/dev/null 2>&1 || adduser --disabled-password --gecos "" deploy
usermod -aG docker deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
if [ -s /root/.ssh/authorized_keys ] && [ ! -s /home/deploy/.ssh/authorized_keys ]; then
    install -m 600 -o deploy -g deploy /root/.ssh/authorized_keys /home/deploy/.ssh/authorized_keys
fi

echo "== application checkout"
install -d -o deploy -g deploy "$APP"
if [ -d "$APP/.git" ]; then
    sudo -u deploy git -C "$APP" pull --ff-only || echo "WARNING: git pull failed in $APP"
else
    sudo -u deploy git clone --depth 1 --branch "$BRANCH" --filter=blob:none --sparse "$REPO_URL" "$APP" \
        && sudo -u deploy git -C "$APP" sparse-checkout set deploy/docker \
        || echo "WARNING: clone failed (private repository? use a deploy key or a token). Clone $REPO_URL into $APP as deploy."
fi
STACK="$APP/deploy/docker"
if [ -f "$STACK/droplet/env.example" ]; then
    [ -f "$STACK/.env" ] || sudo -u deploy cp "$STACK/droplet/env.example" "$STACK/.env"
    install -d -m 700 -o deploy -g deploy "$APP/secrets" /var/backups/osticket
    sudo -u deploy "$STACK/scripts/gen-secrets.sh" "$APP/secrets"
    sed "s/SUPPORT_DOMAIN/$DOMAIN/" "$STACK/droplet/Caddyfile" > /etc/caddy/Caddyfile
    install -m 644 "$STACK/droplet/osticket-backup.service" "$STACK/droplet/osticket-backup.timer" /etc/systemd/system/
    systemctl daemon-reload
    systemctl enable --now osticket-backup.timer
fi

if [ "${1:-}" = "--harden-ssh" ]; then
    [ -s /home/deploy/.ssh/authorized_keys ] || { echo "refusing --harden-ssh: deploy has no SSH key" >&2; exit 1; }
    cat > /etc/ssh/sshd_config.d/10-osticket.conf <<'CONF'
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
CONF
    sshd -t && systemctl reload ssh 2>/dev/null || systemctl reload sshd
    echo "SSH: root login and passwords disabled. Use: ssh deploy@<ip>"
fi

echo
echo "Provisioning done. Next: $STACK/droplet/README.md (log in as deploy, first install)."
