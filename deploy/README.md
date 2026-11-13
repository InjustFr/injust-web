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
| `STRIPE_SECRET_KEY` | Stripe secret key (`sk_test_…` first, `sk_live_…` only once the legal texts are reviewed); only in the server `.env`, never in git |
| `STRIPE_WEBHOOK_SECRET` | Signing secret (`whsec_…`) of the webhook endpoint below; empty refuses every event |
| `MAILER_SENDER` | From address of contact mails, on a domain verified at the mail provider (default `Injust <noreply@injustmusic.com>`) |

Then `docker compose up -d`. The entrypoint waits for the database, runs the migrations, installs the missing default legal texts (`app:legal:install-defaults`), deletes contact messages older than three years (`app:contact:purge`) and expired, unpaid checkouts older than 30 days (`app:sales:purge`).

## Contact form

The public form (`POST /api/contact`, 5 messages per 10 minutes per IP, an IPv6 /64 counting as one) stores every message first, then mails the address set in the admin (Settings, contact email; skipped when empty) and sends a fixed acknowledgement to the visitor (at most 3 a day per address and 100 a day in all; past that the message is still stored and notified). A mail failure is logged without the address and never loses the message: read them in `/admin/messages`.

Messages are kept three years, as the privacy text says: the entrypoint runs `app:contact:purge` on every start, and a daily cron on the host keeps it enforced between deploys:

```cron
17 4 * * * cd "$DEPLOY_DIR" && docker compose exec -T app php bin/console app:contact:purge
19 4 * * * cd "$DEPLOY_DIR" && docker compose exec -T app php bin/console app:sales:purge
23 4 * * * cd "$DEPLOY_DIR" && docker compose exec -T app php bin/console app:music:refresh-links
```

## Release smart links (`/listen/<slug>`)

Set the UPC of a release in the admin (Music): the server looks the release up on Deezer and Apple Music (no key needed) and, when configured, Spotify and Tidal (by UPC) and YouTube Music and YouTube (by title search), then stores the links and shows them on `/listen/<slug>`. `app:music:refresh-links` (the cron above) refreshes every due release daily: recent or upcoming releases every day, older ones weekly while a platform is still missing. It is not run on start-up. Amazon Music, Bandcamp and SoundCloud have no lookup: paste them as manual links. YouTube links are matched by title (the release's track on the auto-generated "Injust - Topic" channel; the API never returns album playlists, and an album whose title is not also a track title is not found): check them in the admin (badge "Found by search"), hide a wrong one or paste a manual link, which always wins. Renaming a release drops its YouTube links and searches again at the next cron. The channel is matched by name only, so another artist also called Injust with a release of the same title would match: that is what the badge is for. Empty keys mean the platform is skipped.

| Variable | Purpose |
| --- | --- |
| `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET` | Spotify app (client credentials, catalogue search by UPC) |
| `TIDAL_CLIENT_ID`, `TIDAL_CLIENT_SECRET` | TIDAL developer app (client credentials, album lookup by barcode) |
| `YOUTUBE_API_KEY` | Google Cloud API key for the YouTube Data API v3 (video search by title, YouTube Music and YouTube) |

Spotify: sign in at developer.spotify.com/dashboard (the account owner needs Spotify Premium to use an app), Create app, any redirect URI (for example `https://injustmusic.com`), tick Web API. Development Mode is enough: copy the Client ID and Client secret.

TIDAL: register an app at developer.tidal.com, create a client, copy the Client ID and Client secret (no user scopes needed).

YouTube: in console.cloud.google.com create (or pick) a project, APIs & Services > Library > enable **YouTube Data API v3**, then APIs & Services > Credentials > Create credentials > API key. Edit the key: API restrictions > Restrict key > YouTube Data API v3 only (no application restriction is needed for a server key; an IP restriction to the server address is fine). Put it in `YOUTUBE_API_KEY`. Quota: each refresh of a release costs 100 units (one search answers both YouTube Music and YouTube) out of the free 10,000 a day, so about 100 refreshes a day; the daily cron only refreshes due releases. Over quota the API answers 403 quotaExceeded: the refresh reports YouTube as failed and keeps the previous links until the quota resets (midnight Pacific time).

## Online payments (Stripe)

Checkout uses Stripe Checkout (card). Start with a Stripe test-mode account and its test keys, check a full purchase (payment, invoice, confirmation mail in the admin under Orders), then swap in the live keys.

Register this webhook endpoint in Stripe (Developers, Webhooks): `https://injustmusic.com/webhooks/stripe`, with exactly these events:

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `checkout.session.expired`
- `charge.refunded`
- `charge.refund.updated`

Copy its signing secret into `STRIPE_WEBHOOK_SECRET`. The endpoint verifies the `Stripe-Signature` header (5 minute tolerance). A failed mail makes it answer 500 so Stripe retries; a refund of a payment the site has not recorded yet answers 503 for the same reason; a payment whose amount differs from the order answers 422 and stays unpaid. Sessions are card only (Apple Pay, Google Pay and Link still work), because invoices say "paid by card". Refunds: the admin asks Stripe for the refund (Orders, Refund); the `charge.refunded` event then makes the site record it, issue the credit note (same numbering as invoices) and email it with its PDF. A refund made in the Stripe dashboard follows the same path. Only succeeded refunds are credited; a pending one is credited when `charge.refund.updated` reports it succeeded. Visitors withdraw on `/withdrawal` within 14 days of payment (extended to the next working day after a weekend or public holiday); the owner then refunds from the admin (pro-rata if the service started). Online checkout must not be switched on before the lawyer has reviewed the legal texts and `vatNumber` is set if invoicing businesses.

Online checkout stays off until it is switched on in the admin (Settings), which needs a complete legal identity.

## Who can sign in to `/admin`

Sign-in goes through mossyleaf accounts (`accounts.mossyleaf.studio`). An account needs the **`injust`** group in Authentik: the application is bound to it, and the app checks the `groups` claim again (`OIDC_REQUIRED_GROUP`).

## Search engines

Indexing is off by default: the site sends `noindex` and `robots.txt` disallows everything. Switch it on in the admin (Settings, Search engine indexing) when the site is ready; there is no server setting for it.

### Link-in-bio and Search Console

The links live on the home page, right under the hero: `https://<domain>/#links`; manage them in the admin (Links) and put that URL in the social bios in place of Linktree. Clicks are counted without cookies.

Search Console: add the domain as a property, choose the HTML tag method, paste only the `content` value of the `google-site-verification` meta tag in the admin (Settings, general), verify, then submit `https://<domain>/sitemap.xml`.

## Reverse proxy

- `compose.override.yaml` puts the app on the reverse proxy network (`PROXY_NETWORK`) with the alias `injust-web`, and opens no host port.
- The site's server block of the nginx configuration proxies to `http://injust-web` and needs `client_max_body_size 25M` for image uploads, plus `X-Forwarded-Host`/`X-Forwarded-Port` like the other app blocks, and `proxy_set_header X-Forwarded-For $remote_addr;`. The app trusts `X-Forwarded-For` from private addresses, so without that line nginx passes on whatever the visitor sent and the contact rate limit can be dodged.
- Uploaded images live in the `app_share` volume (`var/share/images`), the data in `database_data`.

### Beat uploads

Beat files are large (stems up to 2 GB), so only the admin route that receives them gets a bigger limit; every other location keeps `client_max_body_size 25M`. PHP writes a whole upload to disk before the app can check who sent it, so nginx turns away requests without a session cookie and allows three uploads at a time per address (one per file kind). Declare the zone once at `http` level (in `nginx.conf` or a `conf.d/*.conf` file):

```nginx
limit_conn_zone $binary_remote_addr zone=injust_uploads:1m;
```

Then add this block next to the site's main `location /`, with the same `proxy_set_header` lines:

```nginx
location ~ ^/api/admin/beats/.+/files/ {
    if ($http_cookie !~ "(PHPSESSID|REMEMBERME)=") {
        return 401;
    }
    limit_conn injust_uploads 3;
    client_max_body_size 2g;
    proxy_request_buffering off;
    proxy_read_timeout 1800;
    proxy_send_timeout 1800;
    proxy_pass http://injust-web;
}
```

`proxy_request_buffering off` streams the upload to the app instead of writing a second copy on the proxy's disk. The app accepts uploads up to 2 GB (`upload_max_filesize`, `post_max_size` 2100M, `max_input_time` 1800, so about 10 Mbit/s of upload is enough for the largest file) and writes them to `var/share/tmp` on the `app_share` volume, so a finished upload is moved into place, never copied. The container empties `var/share/tmp` when it starts, which removes what an interrupted upload left. Limits per file: preview MP3 20 MB, MP3 50 MB, WAV 500 MB, stems ZIP 2 GB (`config/packages/beats.yaml`). Keep at least 10 GB free on the disk that holds the `app_share` volume (`docker system df -v`, `df -h /var/lib/docker`): three uploads in progress plus the files already stored.

- Public previews: `var/share/audio`, served on `/media/audio/<id>.mp3`.
- Sold files (MP3, WAV, stems): `var/share/private`, never served by a public route. Keep it in the backups below.

## Updating

From a dev machine, with `DEPLOY_HOST` (`user@server`), `DEPLOY_DIR` and optionally `REMOTE_DOCKER` (default `docker`) set in a gitignored `.deploy.env` at the repository root, push to `main`: GitHub Actions runs the full test suite, then publishes `docker.io/injust/injust-web` tagged with the short commit and `latest`. Once it is green, `make deploy` points the server `.env` at that tag, pulls it and restarts. `make push` builds and pushes by hand (after `make qa` and `docker login`).

## Backups

```bash
docker compose exec -T database pg_dump -U app app | gzip > backup-$(date +%F).sql.gz
docker run --rm -v injust-web_app_share:/share -v "$PWD":/backup alpine tar czf /backup/share-$(date +%F).tgz -C /share .
```

## Beat downloads

`GET /download/...` links are signed with the app secret (`APP_SECRET`) and checked against the scheme and host of the request: the proxy must pass `X-Forwarded-Host` and `X-Forwarded-Proto` so the host the buyer clicks equals the host used to sign (webhook, admin). Rotating `APP_SECRET` invalidates links already sent. Large downloads are streamed by PHP from the `app_share` volume: the controller lifts PHP's `max_execution_time` (30 s in production) for the download, and nginx needs a `location /download/` with `proxy_read_timeout 1800;` and `proxy_buffering off;` (with the same `proxy_set_header` lines as `location /`) so a 2 GB stems ZIP on a slow connection is neither cut off nor buffered to the proxy’s disk:

```nginx
location /download/ {
    proxy_pass http://injust-web;
    proxy_read_timeout 1800;
    proxy_buffering off;
}
```
