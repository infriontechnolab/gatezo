#!/usr/bin/env bash
# First 10 minutes on a fresh Ubuntu 24.04 VPS (written for Contabo, fine anywhere).
# Run as root, over the console or the root SSH session the provider gave you:
#
#   curl -fsSL https://raw.githubusercontent.com/infriontechnolab/gatezo/main/infra/provision.sh -o provision.sh
#   bash provision.sh deploy "ssh-ed25519 AAAA... you@laptop"
#
# Creates a sudo user with that key, locks down SSH (no root, no passwords), turns on the
# firewall and fail2ban, adds swap (budget VPS images ship without any), installs Docker,
# and enables unattended security upgrades. Idempotent: safe to re-run.
set -euo pipefail

# On AWS/GCP images the login user already exists with your key (ubuntu, ec2-user);
# pass that name and the script keeps the key it has.
USER_NAME="${1:-deploy}"
SSH_KEY="${2:-}"
SWAP_GB="${SWAP_GB:-2}"

[[ $EUID -eq 0 ]] || { echo "Run as root." >&2; exit 1; }

# The key is the only way back in once password login is off, so make sure there is one:
# either given on the command line, already on the target user (EC2/GCP images do this),
# or on root (Contabo and other password-first images).
EXISTING_KEYS="/home/$USER_NAME/.ssh/authorized_keys"
if [[ -z "$SSH_KEY" && ! -s "$EXISTING_KEYS" && ! -s /root/.ssh/authorized_keys ]]; then
    echo "No SSH key given and none found to copy. Pass your public key:" >&2
    echo "  bash provision.sh $USER_NAME \"ssh-ed25519 AAAA... you@laptop\"" >&2
    exit 1
fi

echo "==> packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq ca-certificates curl git ufw fail2ban unattended-upgrades >/dev/null

echo "==> user: $USER_NAME"
if ! id -u "$USER_NAME" >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" "$USER_NAME"
fi
usermod -aG sudo "$USER_NAME"
install -d -m 700 -o "$USER_NAME" -g "$USER_NAME" "/home/$USER_NAME/.ssh"
if [[ -n "$SSH_KEY" ]]; then
    echo "$SSH_KEY" >> "/home/$USER_NAME/.ssh/authorized_keys"
elif [[ -s "$EXISTING_KEYS" ]]; then
    echo "    (user already has a key, keeping it)"
else
    # Cloud images often put a forced-command banner in root's file; keep only real keys.
    grep -o 'ssh-[a-z0-9-]* [A-Za-z0-9+/=]*.*' /root/.ssh/authorized_keys >> "/home/$USER_NAME/.ssh/authorized_keys"
fi
sort -u "/home/$USER_NAME/.ssh/authorized_keys" -o "/home/$USER_NAME/.ssh/authorized_keys"
chmod 600 "/home/$USER_NAME/.ssh/authorized_keys"
chown "$USER_NAME:$USER_NAME" "/home/$USER_NAME/.ssh/authorized_keys"
# Passwordless sudo: the deploy script restarts containers from CI/cron.
echo "$USER_NAME ALL=(ALL) NOPASSWD:ALL" > "/etc/sudoers.d/90-$USER_NAME"
chmod 440 "/etc/sudoers.d/90-$USER_NAME"

echo "==> swap (${SWAP_GB}G)"
# Budget VPS images have no swap. Without it, a memory spike OOM-kills MySQL mid-event.
if ! swapon --show | grep -q .; then
    fallocate -l "${SWAP_GB}G" /swapfile
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    # Prefer RAM, but use swap rather than killing a process.
    sysctl -qw vm.swappiness=10
    grep -q '^vm.swappiness' /etc/sysctl.conf || echo 'vm.swappiness=10' >> /etc/sysctl.conf
fi

echo "==> firewall"
ufw allow OpenSSH >/dev/null
ufw allow 80/tcp >/dev/null
ufw allow 443/tcp >/dev/null
ufw allow 443/udp >/dev/null   # HTTP/3
ufw --force enable >/dev/null

echo "==> ssh hardening"
cat > /etc/ssh/sshd_config.d/99-gatezo.conf <<'EOF'
# Keys only, no root login. Set by infra/provision.sh.
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
ChallengeResponseAuthentication no
EOF
sshd -t
systemctl reload ssh || systemctl reload sshd

echo "==> fail2ban"
systemctl enable --now fail2ban >/dev/null

echo "==> unattended security upgrades"
dpkg-reconfigure -f noninteractive unattended-upgrades >/dev/null 2>&1 || true

echo "==> docker"
if ! command -v docker >/dev/null; then
    curl -fsSL https://get.docker.com | sh >/dev/null
fi
usermod -aG docker "$USER_NAME"
systemctl enable --now docker >/dev/null

cat <<EOF

Done. Open a NEW terminal and check you can get in before closing this one:

  ssh $USER_NAME@$(hostname -I | awk '{print $1}')

Then follow infra/SERVER.md to bring up the app.
EOF
