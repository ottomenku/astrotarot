#!/bin/bash
set -euo pipefail
ASKPASS=/tmp/tarot-askpass.sh
cat > "$ASKPASS" <<'EOF'
#!/bin/sh
printf '%s\n' '111111'
EOF
chmod 700 "$ASKPASS"
export SUDO_ASKPASS="$ASKPASS"

echo "== COLUMNS =="
sudo -A mysql -N -e "SHOW COLUMNS FROM horoscope.users;"
echo "== ADMINS =="
sudo -A mysql -e "SELECT id, email, name, is_admin FROM horoscope.users WHERE is_admin=1 ORDER BY id;"
# grant select for future artisan seed
sudo -A mysql -e "GRANT SELECT ON horoscope.users TO 'tarot'@'localhost'; FLUSH PRIVILEGES;"
rm -f "$ASKPASS"
echo GRANT_OK
