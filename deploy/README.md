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
| `DEFAULT_URI` | Public address of the site (`https://injustmusic.com`), used for absolute links |
| `PROXY_NETWORK` | External Docker network of the nginx reverse proxy (`docker network ls`) |
| `APP_SECRET` | `openssl rand -hex 16` |
| `POSTGRES_PASSWORD` | `openssl rand -hex 24` |
| `OIDC_CLIENT_SECRET` | Secret of the `injust` provider in mossyleaf accounts (Authentik) |
| `MAILER_DSN` | Mail transport, e.g. Resend SMTP: `smtp://resend:<API_KEY>@smtp.resend.com:465` (the key stays in the server `.env`) |
| `MAILER_SENDER` | From address of contact mails, on a domain verified at the mail provider (default `Injust <noreply@injustmusic.com>`) |

Then `docker compose up -d`. The entrypoint waits for the database, runs the migrations, installs the missing default legal texts (`app:legal:install-defaults`) and deletes contact messages older than three years (`app:contact:purge`).

## Contact form

The public form (`POST /api/contact`, 5 messages per 10 minutes per IP, an IPv6 /64 counting as one) stores every message first, then mails the address set in the admin (Settings, contact email; skipped when empty) and sends a fixed acknowledgement to the visitor (at most 3 a day per address and 100 a day in all; past that the message is still stored and notified). A mail failure is logged without the address and never loses the message: read them in `/admin/messages`.

Messages are kept three years, as the privacy text says: the entrypoint runs `app:contact:purge` on every start, and a daily cron on the host keeps it enforced between deploys:

```cron
17 4 * * * cd "$DEPLOY_DIR" && docker compose exec -T app php bin/console app:contact:purge
```

## Who can sign in to `/admin`

Sign-in goes through mossyleaf accounts (`accounts.mossyleaf.studio`). An account needs the **`injust`** group in Authentik: the application is bound to it, and the app checks the `groups` claim again (`OIDC_REQUIRED_GROUP`).

## Search engines

Indexing is off by default: the site sends `noindex` and `robots.txt` disallows everything. Switch it on in the admin (Settings, Search engine indexing) when the site is ready; there is no server setting for it.

## Reverse proxy

- `compose.override.yaml` puts the app on the reverse proxy network (`PROXY_NETWORK`) with the alias `injust-web`, and opens no host port.
- The site's server block of the nginx configuration proxies to `http://injust-web` and needs `client_max_body_size 25M` for image uploads, plus `X-Forwarded-Host`/`X-Forwarded-Port` like the other app blocks, and `proxy_set_header X-Forwarded-For $remote_addr;`. The app trusts `X-Forwarded-For` from private addresses, so without that line nginx passes on whatever the visitor sent and the contact rate limit can be dodged.
- Uploaded images live in the `app_share` volume (`var/share/images`), the data in `database_data`.

## Updating

From a dev machine, with `DEPLOY_HOST` (`user@server`), `DEPLOY_DIR` and optionally `REMOTE_DOCKER` (default `docker`) set in a gitignored `.deploy.env` at the repository root, push to `main`: GitHub Actions runs the full test suite, then publishes `docker.io/injust/injust-web` tagged with the short commit and `latest`. Once it is green, `make deploy` points the server `.env` at that tag, pulls it and restarts. `make push` builds and pushes by hand (after `make qa` and `docker login`).

## Backups

```bash
docker compose exec -T database pg_dump -U app app | gzip > backup-$(date +%F).sql.gz
docker run --rm -v injust-web_app_share:/share -v "$PWD":/backup alpine tar czf /backup/share-$(date +%F).tgz -C /share .
```
