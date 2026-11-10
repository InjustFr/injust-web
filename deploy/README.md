# Injust website — running on a server

This folder is all a server needs: `compose.yaml` runs the app image (FrankenPHP, assets built in) and a PostgreSQL 18 database. No source checkout, no PHP or Node on the host.

## First install

```bash
mkdir -p "$DEPLOY_DIR" && cd "$DEPLOY_DIR"
# copy compose.yaml, compose.override.yaml, .env.dist and README.md here (`make deploy-files` from a dev machine does it)
cp .env.dist .env
chmod 600 .env
```

Fill `.env`:

| Variable | Value |
|---|---|
| `IMAGE` / `TAG` | Image to run; `make deploy` sets `TAG` to the commit it deploys |
| `DEFAULT_URI` | Public address of the site, used for absolute links |
| `PROXY_NETWORK` | External Docker network of the nginx reverse proxy (`docker network ls`) |
| `APP_SECRET` | `openssl rand -hex 16` |
| `POSTGRES_PASSWORD` | `openssl rand -hex 24` |
| `OIDC_CLIENT_SECRET` | Secret of the `injust` provider in mossyleaf accounts (Authentik) |
| `MAILER_DSN` | SMTP transport for outgoing mail (Resend SMTP) |

Then `docker compose up -d`. The entrypoint waits for the database and runs the migrations.

## Who can sign in to `/admin`

Sign-in goes through mossyleaf accounts (`accounts.mossyleaf.studio`). An account needs the **`injust`** group in Authentik: the application is bound to it, and the app checks the `groups` claim again (`OIDC_REQUIRED_GROUP`).

## Reverse proxy

- `compose.override.yaml` puts the app on the reverse proxy network (`PROXY_NETWORK`) with the alias `injust-web`, and opens no host port.
- The site's server block of the nginx configuration proxies to `http://injust-web` and needs `client_max_body_size 22M` for image uploads, plus `X-Forwarded-Host`/`X-Forwarded-Port` like the other app blocks.
- Uploaded images live in the `app_share` volume (`var/share/images`), the data in `database_data`.

## Updating

From a dev machine, with `DEPLOY_HOST` (`user@server`), `DEPLOY_DIR` and optionally `REMOTE_DOCKER` (default `docker`) set in a gitignored `.deploy.env` at the repository root, push to `main`: GitHub Actions runs the full test suite, then publishes `docker.io/injust/injust-web` tagged with the short commit and `latest`. Once it is green, `make deploy` points the server `.env` at that tag, pulls it and restarts. `make push` builds and pushes by hand (after `make qa` and `docker login`).

## Backups

```bash
docker compose exec -T database pg_dump -U app app | gzip > backup-$(date +%F).sql.gz
docker run --rm -v injust-web_app_share:/share -v "$PWD":/backup alpine tar czf /backup/share-$(date +%F).tgz -C /share .
```
