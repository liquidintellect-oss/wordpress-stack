# WordPress Docker — High-Performance Setup

## Stack
- **Nginx** (reverse proxy + FastCGI page cache + static file serving)
- **WordPress PHP 8.3-FPM Alpine** (OPcache enabled)
- **MariaDB 11** (tuned InnoDB settings)
- **Redis** (object cache via WP Redis plugin)

## Directory Structure
```
.
├── docker-compose.yml
├── .env.example          ← copy to .env and fill in values
├── docker/
│   └── Dockerfile        ← extends wordpress:php8.3-fpm-alpine; sets upload limits
├── nginx/
│   └── default.conf      ← Nginx site config + FastCGI cache rules
├── php/
│   ├── opcache.ini       ← OPcache settings
│   ├── uploads.ini       ← Execution time & memory limits
│   └── zz-fpm.conf       ← PHP-FPM pool tuning
├── mysql/
│   └── my.cnf            ← MariaDB InnoDB tuning
└── wordpress/
    └── mu-plugins/       ← Must-use plugins, mounted read-only into wp-content/mu-plugins
```

## Quick Start

```bash
# 1. Clone / copy this directory
cp .env.example .env
# Edit .env — at minimum set WP_DB_PASSWORD, WP_DB_ROOT_PASSWORD, and WP_URL

# 2. Create the FastCGI cache directory
mkdir -p nginx/fastcgi_cache

# 3. Add TLS certs (or remove SSL blocks from nginx/default.conf for local dev)
mkdir -p certs
# Copy fullchain.pem and privkey.pem into ./certs/
# Or use Certbot — see "TLS with Certbot" below

# 4. Update server_name in nginx/default.conf

# 5. Build and start
docker compose up -d --build

# 6. Check logs
docker compose logs -f
```

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `WP_STACK_NAME` | `my-wordpress-stack` | Docker Compose project name |
| `WP_URL` | `http://localhost` | WordPress site URL (WP_HOME / WP_SITEURL) |
| `WP_HTTP_PORT` | `80` | Host port mapped to Nginx HTTP |
| `WP_HTTPS_PORT` | `443` | Host port mapped to Nginx HTTPS |
| `WP_MAX_UPLOAD` | `64M` | PHP `post_max_size` (build arg) |
| `WP_MAX_FILESIZE` | `64M` | PHP `upload_max_filesize` (build arg) |
| `WP_DB_NAME` | `wordpress` | Database name |
| `WP_DB_USER` | `wordpress` | Database user |
| `WP_DB_PASSWORD` | *(required)* | Database password |
| `WP_DB_ROOT_PASSWORD` | *(required)* | MariaDB root password |
| `WP_TABLE_PREFIX` | `wp_` | WordPress table prefix |

> **Note:** `WP_MAX_UPLOAD` and `WP_MAX_FILESIZE` are Docker build args — changing
> them requires a rebuild (`docker compose up -d --build`). Also update
> `client_max_body_size` in `nginx/default.conf` to match.

## After WordPress Installs

Install these plugins for the full performance stack:

| Plugin | Purpose |
|---|---|
| **Redis Object Cache** | Connects WP to the Redis container |
| **Nginx Cache** or **NGINX Helper** | Purges FastCGI cache on publish |
| **Smush** or **Imagify** | Image optimization |

In Redis Object Cache settings, the host is `redis` and port is `6379` (already configured via `WORDPRESS_CONFIG_EXTRA`).

## FastCGI Caching vs. Set-Cookie (Meta for WooCommerce)

nginx's `fastcgi_cache` module refuses to store any response that carries a
`Set-Cookie` header. If you run the **Meta for WooCommerce**
(`facebook-for-woocommerce`) plugin, its Conversions API integration sets a
`Set-Cookie: _fbp=...` (and sometimes `_fbc=...`) header on essentially every
anonymous page view — which silently defeats FastCGI caching for a
storefront that gets a steady stream of first-time or ad-click visitors,
even though `fastcgi_cache_valid` is configured for a long TTL.

`wordpress/mu-plugins/hold-fb-signals-for-cache.php` fixes this at the
plugin level rather than the nginx level: it hooks the plugin's own
`facebook_signals_held` filter (an integration point the plugin ships but
does not wire up by default) to suppress the server-side cookie write, but
**only** for anonymous `GET` requests that carry no
`wordpress_logged_in_*` or `wp_woocommerce_session_*` cookie. The instant a
real session exists (login, cart, checkout), the filter backs off and the
plugin behaves exactly as it does today — those requests are already
excluded from the FastCGI cache via the `$skip_cache` rules in
`nginx/templates/default.conf.template`. Client-side Facebook Pixel
tracking (`fbq()`) is unaffected either way; only the server-side CAPI
cookie seeding is deferred until a session exists.

This intentionally avoids `fastcgi_ignore_headers Set-Cookie` /
`fastcgi_hide_header Set-Cookie` at the nginx layer, which would apply
site-wide and risk stripping legitimate cookies (login, WooCommerce
session) from cached responses.

## TLS with Certbot (Production)

```bash
# Run Certbot standalone (stop Nginx first)
docker compose stop nginx
docker run --rm -p 80:80 -v $(pwd)/certs:/etc/letsencrypt certbot/certbot certonly \
  --standalone -d example.com -d www.example.com --email you@example.com --agree-tos
docker compose start nginx
```

Or use Cloudflare in front and terminate TLS there — then remove the SSL blocks
from `nginx/default.conf` and change port 443 → 80 only.

## Tuning for Your Server Size

### PHP-FPM (`php/zz-fpm.conf`)
```
max_children ≈ RAM allocated to PHP / ~40MB per worker
```
- 512MB → ~12 workers
- 1GB   → ~20 workers
- 2GB   → ~40 workers

### MariaDB (`mysql/my.cnf`)
```
innodb_buffer_pool_size ≈ 70% of RAM allocated to DB container
```

### Redis (`docker-compose.yml`)
Adjust `--maxmemory` based on your object cache size. 256MB handles most sites;
large WooCommerce catalogues may need 512MB+.

## OPcache — Dev vs Production

In `php/opcache.ini`:

| Setting | Production | Development |
|---|---|---|
| `validate_timestamps` | `0` | `1` |
| `revalidate_freq` | `0` | `2` |

With `validate_timestamps=0`, PHP never re-reads files from disk — restart the
`wordpress` container after deploying code changes:
```bash
docker compose restart wordpress
```

## Performance Layers Summary

```
Request
  └─ Nginx (static files served here — PHP never touched)
       └─ FastCGI cache HIT → return cached page instantly
            └─ FastCGI cache MISS → PHP-FPM
                 └─ OPcache (compiled bytecode, no disk reads)
                      └─ Redis object cache HIT → return WP object
                           └─ Redis MISS → MariaDB query (InnoDB buffer pool)
```
