FROM nginx:alpine
# openssl is not included in nginx:alpine but is needed by the
# 01-generate-cert.sh entrypoint script to produce a self-signed TLS cert
# when real certs are not yet present.
RUN apk add --no-cache openssl
