#!/bin/bash
# Reset info@ and admin@ mailbox passwords. Run as root:
#   sudo bash /home/otto/reset-mail-passwords.sh
set -euo pipefail

DOMAIN="astrotarot.hu"
PASSWD_FILE="/etc/dovecot/passwd"
ACCOUNTS_ROOT="/root/astrotarot-mail-accounts.txt"
ACCOUNTS_USER="/home/otto/astrotarot-mail-accounts.txt"
MAILBOXES=(info admin)

if [[ $(id -u) -ne 0 ]]; then
  echo "Run as root: sudo bash $0" >&2
  exit 1
fi

umask 077
tmp="$(mktemp)"
: > "${ACCOUNTS_ROOT}"
chmod 600 "${ACCOUNTS_ROOT}"

for localpart in "${MAILBOXES[@]}"; do
  password="$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)"
  hash="$(doveadm pw -s BLF-CRYPT -p "${password}")"
  echo "${localpart}@${DOMAIN}:${hash}" >> "${tmp}"
  echo "${localpart}@${DOMAIN}  ${password}" >> "${ACCOUNTS_ROOT}"
done

cat "${tmp}" > "${PASSWD_FILE}"
rm -f "${tmp}"
chmod 640 "${PASSWD_FILE}"
chown root:dovecot "${PASSWD_FILE}"

if id otto >/dev/null 2>&1; then
  install -o otto -g otto -m 600 "${ACCOUNTS_ROOT}" "${ACCOUNTS_USER}"
fi

systemctl reload dovecot 2>/dev/null || systemctl restart dovecot

echo "==== Mailbox passwords ===="
cat "${ACCOUNTS_ROOT}"
echo
echo "Also saved to ${ACCOUNTS_USER}"
echo
echo "==== DKIM TXT  mail._domainkey.${DOMAIN} ===="
python3 - <<'PY'
from pathlib import Path
import re
p = Path("/etc/opendkim/keys/astrotarot.hu/mail.txt")
text = p.read_text()
parts = re.findall(r'"([^"]+)"', text)
print("".join(parts) if parts else text)
PY
echo
echo "Webmail: https://webmail.astrotarot.hu/"
echo "Login with the full address, e.g. info@astrotarot.hu"
