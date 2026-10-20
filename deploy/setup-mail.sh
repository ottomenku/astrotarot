#!/bin/bash
# Install Postfix + Dovecot + OpenDKIM + Roundcube for astrotarot.hu
# Run on the VPS as root:  sudo bash deploy/setup-mail.sh
set -euo pipefail

DOMAIN="astrotarot.hu"
MAIL_HOST="mail.${DOMAIN}"
WEBMAIL_HOST="webmail.${DOMAIN}"
CERT_DIR="/etc/letsencrypt/live/www.astrotarot.hu"
MAIL_ROOT="/var/vmail"
PASSWD_FILE="/etc/dovecot/passwd"
VMAILBOX_FILE="/etc/postfix/vmailbox"
ACCOUNTS_FILE="/root/astrotarot-mail-accounts.txt"
ACCOUNTS_USER="/home/otto/astrotarot-mail-accounts.txt"
MAILBOXES=(info admin)
RESET_MAIL_PASSWORDS="${RESET_MAIL_PASSWORDS:-0}"

if [[ $(id -u) -ne 0 ]]; then
  echo "Run as root: sudo bash $0" >&2
  exit 1
fi

if [[ ! -f "${CERT_DIR}/fullchain.pem" || ! -f "${CERT_DIR}/privkey.pem" ]]; then
  echo "TLS cert missing in ${CERT_DIR}" >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
debconf-set-selections <<EOF
postfix postfix/mailname string ${MAIL_HOST}
postfix postfix/main_mailer_type select Internet Site
roundcube-core roundcube/dbconfig-install boolean true
roundcube-core roundcube/database-type select sqlite3
roundcube-core roundcube/reconfigure-webserver multiselect apache2
EOF

apt-get update
# PHP 8.4 unbundled ext-imap; Debian Trixie has no php8.4-imap.
# Roundcube 1.6 uses its own IMAP client and does not need the C extension.
apt-get install -y \
  postfix postfix-pcre \
  dovecot-core dovecot-imapd dovecot-lmtpd \
  opendkim opendkim-tools \
  roundcube roundcube-plugins roundcube-sqlite3 \
  php-intl php-mbstring php-xml php-zip php-sqlite3 php-gd \
  certbot python3-certbot-apache

id vmail >/dev/null 2>&1 || useradd --system --home "${MAIL_ROOT}" --shell /usr/sbin/nologin --uid 5000 --user-group vmail
mkdir -p "${MAIL_ROOT}"
chown vmail:vmail "${MAIL_ROOT}"
chmod 750 "${MAIL_ROOT}"

: > "${VMAILBOX_FILE}"
touch "${PASSWD_FILE}"
if [[ "${RESET_MAIL_PASSWORDS}" == "1" ]]; then
  : > "${PASSWD_FILE}"
fi
chmod 640 "${PASSWD_FILE}"
chown root:dovecot "${PASSWD_FILE}"
umask 077
: > "${ACCOUNTS_FILE}"
chmod 600 "${ACCOUNTS_FILE}"

for localpart in "${MAILBOXES[@]}"; do
  mkdir -p "${MAIL_ROOT}/${DOMAIN}/${localpart}/Maildir"/{cur,new,tmp}
  echo "${localpart}@${DOMAIN} ${DOMAIN}/${localpart}/Maildir/" >> "${VMAILBOX_FILE}"
  if [[ "${RESET_MAIL_PASSWORDS}" != "1" ]] && grep -q "^${localpart}@${DOMAIN}:" "${PASSWD_FILE}"; then
    echo "Mailbox ${localpart}@${DOMAIN} already has a password hash, keeping it."
    continue
  fi
  password="$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)"
  hash="$(doveadm pw -s BLF-CRYPT -p "${password}")"
  echo "${localpart}@${DOMAIN}:${hash}" >> "${PASSWD_FILE}"
  echo "${localpart}@${DOMAIN}  ${password}" >> "${ACCOUNTS_FILE}"
done
chown -R vmail:vmail "${MAIL_ROOT}"
chmod 750 "${MAIL_ROOT}" "${MAIL_ROOT}/${DOMAIN}"
postmap "${VMAILBOX_FILE}"
if [[ -s "${ACCOUNTS_FILE}" ]] && id otto >/dev/null 2>&1; then
  install -o otto -g otto -m 600 "${ACCOUNTS_FILE}" "${ACCOUNTS_USER}"
fi

hostnamectl set-hostname "${MAIL_HOST}" || true

cat >/etc/postfix/main.cf <<EOF
smtpd_banner = \$myhostname ESMTP
biff = no
append_dot_mydomain = no
readme_directory = no
compatibility_level = 3.6

myhostname = ${MAIL_HOST}
mydomain = ${DOMAIN}
myorigin = \$mydomain
mydestination = localhost
mynetworks = 127.0.0.0/8 [::1]/128
inet_interfaces = all
inet_protocols = ipv4
home_mailbox = Maildir/

smtpd_tls_cert_file = ${CERT_DIR}/fullchain.pem
smtpd_tls_key_file = ${CERT_DIR}/privkey.pem
smtpd_tls_security_level = may
smtp_tls_security_level = may
smtpd_tls_auth_only = yes
smtpd_tls_loglevel = 1
smtpd_use_tls = yes

smtpd_sasl_type = dovecot
smtpd_sasl_path = private/auth
smtpd_sasl_auth_enable = yes
smtpd_recipient_restrictions = permit_mynetworks, permit_sasl_authenticated, reject_unauth_destination
smtpd_relay_restrictions = permit_mynetworks, permit_sasl_authenticated, reject_unauth_destination

virtual_mailbox_domains = ${DOMAIN}
virtual_mailbox_base = ${MAIL_ROOT}
virtual_mailbox_maps = hash:${VMAILBOX_FILE}
virtual_minimum_uid = 5000
virtual_uid_maps = static:5000
virtual_gid_maps = static:5000
virtual_transport = lmtp:unix:private/dovecot-lmtp

milter_default_action = accept
milter_protocol = 6
smtpd_milters = inet:127.0.0.1:8891
non_smtpd_milters = inet:127.0.0.1:8891
message_size_limit = 26214400
EOF

cat >/etc/postfix/master.cf <<'EOF'
smtp      inet  n       -       y       -       -       smtpd
submission inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_tls_auth_only=yes
  -o smtpd_relay_restrictions=permit_sasl_authenticated,reject
  -o smtpd_recipient_restrictions=permit_sasl_authenticated,reject
smtps     inet  n       -       y       -       -       smtpd
  -o syslog_name=postfix/smtps
  -o smtpd_tls_wrappermode=yes
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_relay_restrictions=permit_sasl_authenticated,reject
  -o smtpd_recipient_restrictions=permit_sasl_authenticated,reject
pickup    unix  n       -       y       60      1       pickup
cleanup   unix  n       -       y       -       0       cleanup
qmgr      unix  n       -       n       300     1       qmgr
tlsmgr    unix  -       -       y       1000?   1       tlsmgr
rewrite   unix  -       -       y       -       -       trivial-rewrite
bounce    unix  -       -       y       -       0       bounce
defer     unix  -       -       y       -       0       bounce
trace     unix  -       -       y       -       0       bounce
verify    unix  -       -       y       -       1       verify
flush     unix  n       -       y       1000?   0       flush
proxymap  unix  -       -       n       -       -       proxymap
proxywrite unix -       -       n       -       1       proxymap
smtp      unix  -       -       y       -       -       smtp
relay     unix  -       -       y       -       -       smtp
showq     unix  n       -       y       -       -       showq
error     unix  -       -       y       -       -       error
retry     unix  -       -       y       -       -       error
discard   unix  -       -       y       -       -       discard
local     unix  -       n       n       -       -       local
virtual   unix  -       n       n       -       -       virtual
lmtp      unix  -       -       y       -       -       lmtp
anvil     unix  -       -       y       -       1       anvil
scache    unix  -       -       y       -       1       scache
maildrop  unix  -       n       n       -       -       pipe flags=DRhu user=vmail argv=/usr/bin/maildrop -d ${recipient}
uucp      unix  -       n       n       -       -       pipe flags=Fqhu user=uucp argv=uux -r -n -z -a$sender - $nexthop!rmail ($recipient)
ifmail    unix  -       n       n       -       -       pipe flags=F user=ftn argv=/usr/lib/ifmail/ifmail -r $nexthop ($recipient)
bsmtp     unix  -       n       n       -       -       pipe flags=Fq. user=bsmtp argv=/usr/lib/bsmtp/bsmtp -t$nexthop -f$sender $recipient
scalemail-backend unix - n n - - pipe flags=R user=scalemail argv=/usr/lib/scalemail/bin/scalemail-store ${nexthop} ${user} ${extension}
mailman   unix  -       n       n       -       -       pipe flags=FR user=list argv=/usr/lib/mailman/bin/postfix-to-mailman.py ${nexthop} ${user}
EOF

# Debian Trixie ships Dovecot 2.4 (incompatible with 2.3 syntax).
cp /usr/share/dovecot/conf.d/10-master.conf /etc/dovecot/conf.d/10-master.conf
cp /usr/share/dovecot/conf.d/15-lda.conf /etc/dovecot/conf.d/15-lda.conf

cat >/etc/dovecot/conf.d/10-mail.conf <<EOF
mail_driver = maildir
mail_home = ${MAIL_ROOT}/%{user | domain}/%{user | username}
mail_path = ${MAIL_ROOT}/%{user | domain}/%{user | username}/Maildir
mail_uid = vmail
mail_gid = vmail
mail_privileged_group = vmail
first_valid_uid = 5000
last_valid_uid = 5000
namespace inbox {
  inbox = yes
  separator = /
}
EOF

cat >/etc/dovecot/conf.d/10-auth.conf <<'EOF'
auth_allow_cleartext = no
auth_mechanisms = plain login
auth_username_format = %{user | lower}
!include auth-passwdfile.conf.ext
EOF

cat >/etc/dovecot/conf.d/auth-passwdfile.conf.ext <<EOF
passdb passwd-file {
  default_password_scheme = BLF-CRYPT
  passwd_file_path = ${PASSWD_FILE}
}
userdb static {
  fields {
    uid = vmail
    gid = vmail
    home = ${MAIL_ROOT}/%{user | domain}/%{user | username}
  }
}
EOF

cat >/etc/dovecot/conf.d/10-ssl.conf <<EOF
ssl = required
ssl_server_cert_file = ${CERT_DIR}/fullchain.pem
ssl_server_key_file = ${CERT_DIR}/privkey.pem
ssl_min_protocol = TLSv1.2
EOF

cat >/etc/dovecot/conf.d/99-postfix.conf <<'EOF'
service lmtp {
  unix_listener /var/spool/postfix/private/dovecot-lmtp {
    mode = 0600
    user = postfix
    group = postfix
  }
}
service auth {
  unix_listener /var/spool/postfix/private/auth {
    mode = 0660
    user = postfix
    group = postfix
  }
  unix_listener auth-userdb {
    mode = 0600
    user = vmail
  }
}
protocol lmtp {
  auth_username_format = %{user | lower}
}
EOF

chmod 644 /etc/dovecot/conf.d/99-postfix.conf
# Debian LMTP default strips @domain; keep the full address for virtual users.
sed -i 's/^\([[:space:]]*\)auth_username_format = %{user | username | lower}/\1# auth_username_format = %{user | username | lower}/' /etc/dovecot/conf.d/20-lmtp.conf || true

if ! doveconf -n >/dev/null; then
  echo "Dovecot configuration is invalid:" >&2
  doveconf -n >&2 || true
  exit 1
fi

mkdir -p /etc/opendkim/keys/${DOMAIN}
if [[ ! -f /etc/opendkim/keys/${DOMAIN}/mail.private ]]; then
  opendkim-genkey -D /etc/opendkim/keys/${DOMAIN} -d ${DOMAIN} -s mail
fi
chown -R opendkim:opendkim /etc/opendkim
chmod 600 /etc/opendkim/keys/${DOMAIN}/mail.private

cat >/etc/opendkim.conf <<EOF
Syslog                  yes
SyslogSuccess           yes
Canonicalization        relaxed/simple
Mode                    sv
SubDomains              no
AutoRestart             yes
Background              yes
DNSTimeout              5
SignatureAlgorithm      rsa-sha256
Socket                  inet:8891@127.0.0.1
PidFile                 /run/opendkim/opendkim.pid
OversignHeaders         From
TrustAnchorFile         /usr/share/dns/root.key
UserID                  opendkim
KeyTable                /etc/opendkim/key.table
SigningTable            refile:/etc/opendkim/signing.table
InternalHosts           /etc/opendkim/trusted.hosts
ExternalIgnoreList      /etc/opendkim/trusted.hosts
EOF

echo "mail._domainkey.${DOMAIN} ${DOMAIN}:mail:/etc/opendkim/keys/${DOMAIN}/mail.private" >/etc/opendkim/key.table
echo "*@${DOMAIN} mail._domainkey.${DOMAIN}" >/etc/opendkim/signing.table
cat >/etc/opendkim/trusted.hosts <<EOF
127.0.0.1
localhost
${DOMAIN}
*.${DOMAIN}
EOF

ROUNDCUBE_CONF=/etc/roundcube/config.inc.php
ROUNDCUBE_LOCAL=/etc/roundcube/astrotarot.inc.php
cat >"${ROUNDCUBE_LOCAL}" <<'PHPEOF'
<?php
$config['imap_host'] = 'tls://127.0.0.1:143';
$config['smtp_host'] = 'tls://127.0.0.1:587';
$config['smtp_user'] = '%u';
$config['smtp_pass'] = '%p';
$config['username_domain'] = 'astrotarot.hu';
$config['product_name'] = 'AstroTarot Mail';
$config['imap_conn_options'] = array(
  'ssl' => array(
    'verify_peer' => false,
    'verify_peer_name' => false,
    'allow_self_signed' => true,
  ),
);
$config['smtp_conn_options'] = array(
  'ssl' => array(
    'verify_peer' => false,
    'verify_peer_name' => false,
    'allow_self_signed' => true,
  ),
);
PHPEOF
chmod 640 "${ROUNDCUBE_LOCAL}"
chown root:www-data "${ROUNDCUBE_LOCAL}" || true
if [[ -f ${ROUNDCUBE_CONF} ]] && ! grep -q "astrotarot.inc.php" "${ROUNDCUBE_CONF}"; then
  printf "\ninclude('/etc/roundcube/astrotarot.inc.php');\n" >> "${ROUNDCUBE_CONF}"
fi

if [[ -d /var/lib/roundcube/public_html ]]; then
  RC_DOCROOT=/var/lib/roundcube/public_html
else
  RC_DOCROOT=/usr/share/roundcube
fi

cat >/etc/apache2/sites-available/webmail.conf <<EOF
<VirtualHost *:80>
    ServerName ${WEBMAIL_HOST}
    Redirect permanent / https://${WEBMAIL_HOST}/
</VirtualHost>
EOF

cat >/etc/apache2/sites-available/webmail-le-ssl.conf <<EOF
<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName ${WEBMAIL_HOST}
    DocumentRoot ${RC_DOCROOT}

    <Directory ${RC_DOCROOT}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Directory /usr/share/roundcube/config>
        Require all denied
    </Directory>

    <FilesMatch \\.php\$>
        SetHandler "proxy:unix:/run/php/php8.4-fpm.sock|fcgi://localhost/"
    </FilesMatch>

    ErrorLog \${APACHE_LOG_DIR}/webmail_error.log
    CustomLog \${APACHE_LOG_DIR}/webmail_access.log combined

    Include /etc/letsencrypt/options-ssl-apache.conf
    SSLCertificateFile ${CERT_DIR}/fullchain.pem
    SSLCertificateKeyFile ${CERT_DIR}/privkey.pem
</VirtualHost>
</IfModule>
EOF

a2enmod proxy_fcgi setenvif ssl rewrite >/dev/null
a2ensite webmail.conf webmail-le-ssl.conf >/dev/null
apache2ctl configtest
systemctl reload apache2

if command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
  ufw allow 25/tcp
  ufw allow 465/tcp
  ufw allow 587/tcp
  ufw allow 993/tcp
fi

systemctl enable --now postfix dovecot opendkim
systemctl restart postfix dovecot opendkim apache2 php8.4-fpm

certbot --apache --expand --non-interactive --agree-tos --register-unsafely-without-email \
  -d astrotarot.hu -d www.astrotarot.hu -d astrotarot.cloud -d www.astrotarot.cloud -d ${WEBMAIL_HOST} \
  || echo "WARN: certbot expand failed; webmail may use the existing cert until DNS/SAN is updated."

echo
echo "==== DNS to set at Forpsi ===="
echo "MX   ${DOMAIN}            10 ${MAIL_HOST}."
echo "A    ${MAIL_HOST}         194.182.83.30   (or keep CNAME to ${DOMAIN})"
echo "A    ${WEBMAIL_HOST}      194.182.83.30   (already CNAME)"
echo "TXT  ${DOMAIN}            v=spf1 a mx ip4:194.182.83.30 -all"
echo "TXT  _dmarc.${DOMAIN}     v=DMARC1; p=quarantine; rua=mailto:admin@${DOMAIN}"
echo "TXT  mail._domainkey.${DOMAIN} :"
python3 - <<PY
from pathlib import Path
import re
p = Path("/etc/opendkim/keys/${DOMAIN}/mail.txt")
text = p.read_text()
parts = re.findall(r'"([^"]+)"', text)
print("".join(parts) if parts else text)
PY
echo
echo "Also set PTR / reverse DNS for 194.182.83.30 to ${MAIL_HOST} in the Forpsi/Aruba panel."
echo
echo "Webmail: https://${WEBMAIL_HOST}/"
echo "IMAP: ${MAIL_HOST} 993 SSL"
echo "SMTP: ${MAIL_HOST} 587 STARTTLS"
if [[ -s "${ACCOUNTS_FILE}" ]]; then
  echo "Accounts written to ${ACCOUNTS_FILE} and ${ACCOUNTS_USER}"
  cat "${ACCOUNTS_FILE}"
else
  echo "No plaintext passwords this run (hashes already existed)."
  echo "Reset them with: sudo bash /home/otto/reset-mail-passwords.sh"
fi
