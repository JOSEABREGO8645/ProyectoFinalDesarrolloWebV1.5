#!/usr/bin/env bash
set -euo pipefail

key=/data/configdb/libreria.key
if [ ! -s "$key" ]; then
  umask 077
  openssl rand -base64 756 > "$key"
fi
chown mongodb:mongodb "$key"
chmod 400 "$key"
exec /usr/local/bin/docker-entrypoint.sh "$@"
