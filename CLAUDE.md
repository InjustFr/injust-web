# Injust website — project guide for Claude

Website of Louis's music project **Injust** (protest punk / hard rock, English lyrics): the music home, then guitar lessons, production services and a beats store, each switchable in the admin. Public site in **English and French** (EN at `/…`, FR at `/fr/…`, no automatic redirect); admin UI in **English and French**. URLs, code, commits: **English**.

Architecture and conventions mirror `~/Sites/mossyleaf-studio/website` (read its `CLAUDE.md` when in doubt). The full product plan lives in `~/.claude/plans/i-want-to-build-dapper-quokka.md`.

## Code style

- **Never write comments**: no docblocks, no inline `//`/`#`, no `<!-- -->`, no `{# #}`. Only type-only PHPDoc the type system needs is allowed.
- **SOLID in every layer**: one use case per handler, one route per controller, one exception per file.
- **No setters**: entities change through intention-revealing methods; DTOs and value objects are `readonly`.
- Handlers are `final readonly` and end with `Transaction::commit()`.
- **Tests first**: write the failing test (PHPUnit or Vitest), see it red, then make it green.

## Stack

- Symfony 8.1, PHP 8.5 (FrankenPHP worker, GD for images, pcov in dev), Doctrine ORM 3, PostgreSQL 18, ULIDs, Symfony Mailer (Mailpit in dev, `null://` in tests).
- Vue 3 + Vite through `pentatrion/vite-bundle`, three entries: `site` (public pages, server-rendered Twig plus Vue islands), `admin` (SPA with vue-router under `/admin`), `auth` (sign-in error page).
- Tests: PHPUnit 13 (unit + functional, DAMA rollback), Vitest (`assets/**/*.test.js`, `bin/**/*.test.mjs`, config in `vite.config.js`), Playwright.

## Running things (always in Docker)

```bash
make up              # php + database + node (Vite :5178) + mock mossyleaf accounts (:8099) + Mailpit (:8028) → http://localhost:8098 (admin on /admin), Postgres on :5438
make install         # composer install + npm install
make db              # create + migrate dev DB
make fixtures        # reset the dev database with demo data
make migration       # generate a migration from mapping changes
make test            # PHPUnit; TEST_TOKEN=n uses its own database app_testn (parallel agents never share one)
make test-js / phpstan / deptrac / cs / cs-fix / audit   # keep all at 0
make e2e             # Playwright against php-e2e (APP_ENV=test)
make qa              # cs phpstan deptrac audit test test-js e2e
make ci              # full suite from a fresh checkout, as GitHub Actions runs it
make deploy          # run the image CI published to Docker Hub for HEAD on DEPLOY_HOST (.deploy.env)
```

## Backend (Onion) — `src/`

Layer-first: `src/{Domain,Application,Infrastructure,Presentation}/<Context>/`, enforced by `deptrac.yaml` (Domain depends on nothing, Application on Domain, Infrastructure and Presentation on both).

- `Domain/Shared/Exception`: `DomainException` (message key + parameters, translated from `translations/exceptions+intl-icu.*.yaml`), `NotFound`, `UnsupportedImage`.
- `Application`: use cases `<Context>/<UseCase>/{Command, Handler}`, views and ports. Shared ports: `Transaction`, `Shared\ImageStorage`, `Shared\ImageResizer` (+ `ResizedImage`).
- `Infrastructure`: Doctrine repositories and `DoctrineTransaction`, `Storage\GdImageResizer` (WebP, 1600 px max), `Storage\LocalImageStorage` (`var/share/images`), the Security/Accounts adapters.
- `Presentation`: controllers. Admin API goes under `/api/admin/…` with `MapRequestPayload` DTOs; `DomainExceptionListener` turns a domain exception into a 422 `problem+json`; `SameOriginGuard` refuses cross-origin writes on `/api/`; `RefreshAfterWrite` + `ApiPreload` return the refreshed GET payloads the admin asks for in `X-Refresh`; `AppShellController` serves the admin SPA on `/admin/{path}` and preloads its first API calls.
- Public pages: `Presentation/Web/Site/*Controller` render `templates/site/*.html.twig`, which extend `templates/site.html.twig` (SEO head: title, description, canonical, Open Graph, JSON-LD). `robots.txt` and `sitemap.xml` are controllers. Uploaded images are served by `MediaController` (`/media/images/{ulid}.webp`, immutable cache).

## Accounts & security

- Sign-in = mossyleaf accounts (Authentik at `accounts.mossyleaf.studio`, project `~/Sites/mossyleaf-studio/accounts`), OIDC code flow + PKCE, client id `injust`. Only accounts whose `groups` claim contains `OIDC_REQUIRED_GROUP` (`injust`) get in; dev leaves it empty.
- Dev and e2e use the mock OIDC server (`oidc` service, path `/injust`): type any username plus claims such as `{"email": "editor@injust.test", "groups": ["injust"]}`. PHPUnit uses `Tests\Support\FakeAccounts` (decorates `http_client`), `SignsInClient` and `ActsAsUser`.
- `/`, `/media`, `/robots.txt` and `/sitemap.xml` are public and start no session; `/admin` and `/api/` need `ROLE_USER`.

## Frontend — `assets/`

- `site.js` → public styles and islands (`vue/site/`).
- `admin.js` → `vue/admin/AdminShell.vue` + `router.js` → `vue/admin/pages/*` (Dashboard placeholder for now).
- `auth.js` → `vue/pages/auth/LoginPage.vue`.
- Shared UI in `vue/components/ui/` (thin Reka UI wrappers, Lucide icons); `composables/` hold the API and state (`useApi`, `useSession`, `useToast`); admin tokens and base styles in `styles/`.
- Never hardcode a user-visible string: `vue/i18n/<locale>/*.json`; `vue/i18n/locales.test.js` checks every key exists in both `en` and `fr`.

## Git

One commit per feature on `main`, conventional messages. No co-author lines.

## Done

A task is done when `make gate` (pre-commit hook) is green → commit on `main` → `git push origin main` → CI green on that commit → `make deploy`.
