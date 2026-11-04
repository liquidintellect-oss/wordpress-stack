#!/bin/sh
# /docker-entrypoint.d/01-generate-cert.sh
#
# Runs before nginx starts (via the nginx base-image entrypoint).
# If real TLS certificates are not present, generates a self-signed cert so
# the stack can start without manual cert provisioning.
#
# To use real certs (e.g. from Certbot), place them at:
#   ./certs/fullchain.pem
#   ./certs/privkey.pem
# and restart the stack — this script will detect them and skip generation.

set -e

CERT_DIR="/etc/nginx/certs"
CERT_FILE="${CERT_DIR}/fullchain.pem"
KEY_FILE="${CERT_DIR}/privkey.pem"

if [ -f "${CERT_FILE}" ] && [ -f "${KEY_FILE}" ]; then
    echo "01-generate-cert.sh: TLS certificates found, skipping self-signed generation."
    exit 0
fi

echo "01-generate-cert.sh: No TLS certificates found — generating a self-signed certificate."
echo "01-generate-cert.sh: Replace ./certs/fullchain.pem and ./certs/privkey.pem with real certs for production."

mkdir -p "${CERT_DIR}"

openssl req -x509 -nodes -days 3650 \
    -newkey rsa:2048 \
    -keyout "${KEY_FILE}" \
    -out "${CERT_FILE}" \
    -subj "/CN=localhost/O=WordPress Stack/C=US" \
    2>&1

echo "01-generate-cert.sh: Self-signed certificate generated (valid 10 years)."
