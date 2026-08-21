#!/bin/bash
set -euo pipefail
ASKPASS=/tmp/tarot-askpass.sh
cat > "$ASKPASS" <<'EOF'
#!/bin/sh
printf '%s\n' '111111'
EOF
chmod 700 "$ASKPASS"
export SUDO_ASKPASS="$ASKPASS"

echo "== DATABASES =="
sudo -A mysql -N -e "SHOW DATABASES;"
echo "== LOOK FOR USERS TABLES =="
sudo -A mysql -N -e "SELECT table_schema, table_name FROM information_schema.tables WHERE table_name LIKE '%user%' ORDER BY table_schema, table_name;"
echo "== ASTRO DIRS =="
ls -d /var/www/* 2>/dev/null || true
rm -f "$ASKPASS"
