#!/bin/bash
set -euo pipefail

# Point production at the reverse-tunneled Ollama on this VPS loopback.
# The Windows PC must be running deploy/ollama-vps-tunnel.ps1.

cd /var/www/tarot

set_kv() {
  local key="$1"
  local value="$2"
  if grep -qE "^${key}=" .env; then
    sed -i -E "s|^${key}=.*|${key}=${value}|" .env
  else
    printf '\n%s=%s\n' "$key" "$value" >> .env
  fi
  echo "SET $key=$value"
}

set_kv LLM_DRIVER ollama
set_kv OPENAI_BASE_URL 'http://127.0.0.1:11434/v1'
set_kv OPENAI_MODEL qwen2.5:14b
set_kv OPENAI_TIMEOUT 300
set_kv LLM_FALLBACK_OPENAI true

php artisan config:clear
php artisan cache:clear

php artisan reading:check --ping

echo TUNNEL_LLM_OK
