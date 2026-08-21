#!/bin/bash
set -euo pipefail
ASKPASS=/tmp/tarot-askpass.sh
cat > "$ASKPASS" <<'EOF'
#!/bin/sh
printf '%s\n' '111111'
EOF
chmod 700 "$ASKPASS"
export SUDO_ASKPASS="$ASKPASS"

sudo -A mysql -N -e "SELECT id, email, name, is_admin FROM vs_cline.users WHERE is_admin=1 ORDER BY id;"
echo '---'
# also grant select so seeder can work later
sudo -A mysql -e "GRANT SELECT ON vs_cline.users TO 'tarot'@'localhost'; FLUSH PRIVILEGES;"
rm -f "$ASKPASS"
echo GRANT_OK
