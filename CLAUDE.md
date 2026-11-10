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
make test            # PHPUnit; TEST_TOKEN=n uses its own database app_testn, cache var/cache/testn and share dir var/share/testn (parallel agents never share one)
make test-js / phpstan / deptrac / depcruise / cs / cs-fix / audit   # keep all at 0
make gate            # cs phpstan deptrac depcruise + coverage, CRAP <= 10 and mutation (Infection/Stryker, MSI >= 80) on changed files; GATE_BASE=<ref> picks the base (default HEAD); crap, mutation, crap-js, mutation-js run alone
make e2e             # Playwright against php-e2e (APP_ENV=test)
make qa              # gate audit e2e (the pre-commit hook runs make gate; make up sets core.hooksPath)
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

## M0 contexts and contracts

- `Domain/Shared`: `LocalizedText` {fr, en} (`in($locale)` falls back to the other side) and `Price` (cents, EUR).
- `Settings` context: single `Settings` row (sections Lessons/Production/Beats, online checkout, legal identity, VAT mentions, SEO, logo/hero image ULIDs, `searchIndexing` default off) and `LegalDocument` per `LegalKind` (versioned Markdown, FR + EN). Read through `Application\Settings\SettingsQueries`; a missing row means defaults (sections off). Checkout needs a complete legal identity (`LegalIdentityIncomplete`).
- Admin API: `/api/admin/settings[/sections|/checkout|/legal-identity|/general|/images/{logo|hero}]` and `/api/admin/legal[/{kind}]`; admin pages Settings and Legal texts.
- Default legal texts: `resources/legal/<kind>.<fr|en>.md` with `{placeholders}`, read through the `Application\Settings\LegalDefaults` port (`Infrastructure\Storage\MarkdownLegalDefaults`) and installed by `app:legal:install-defaults` (entrypoint, `make fixtures`, `make e2e`) because fixtures truncate `legal_document`. They are templates: a lawyer must review them before checkout goes live.
- Public site: localized routes `home`, `section`, `legal` (EN `/…`, FR `/fr/…`); `SectionGate::hiddenFromVisitors(Section)` gates each section (404 for visitors, banner for a signed-in admin); render through `SitePage::render`. Later sections replace `SectionController`. Islands live in `assets/vue/site/islands/<Name>.vue`, mounted from `[data-island]`.
- e2e specs use `e2e/tests/support/test.js`, which fails any test on a CSP violation.

## M1 Music

- `Domain/Music`: `Release` (English title, unique slug, `ReleaseType` single|ep|album, date, cover, localized description, `Track` and `PlatformLink` JSON lists, `smartLinkUrl`, `SpotifyEmbed`, one `featured` at most: `FeatureRelease` unfeatures the others), `Video` (`YoutubeId`, thumbnail fetched through the `VideoThumbnails` port, `Infrastructure\Youtube\HttpVideoThumbnails`, failure = no thumbnail), `SocialLink`, single-row `MusicText` (tagline, manifesto, bio, hooks, portrait). `HttpsUrl` lives in `Domain/Shared`.
- A release comes out at midnight `Release::TIMEZONE` (Europe/Paris): `releaseAt()` is that instant, `isUpcoming($now)` compares to it, `ReleaseView::releaseAt` (ATOM, with offset) is the countdown target.
- `Application\Music\MusicQueries`: `home($now)` returns `HomeView`; admin reads `releases($now)`, `videos()`, `socialLinks()`, `text()`. Order endpoints demand exactly the existing id set (`OrderMismatch`).
- Image files go through `Application\Shared\Images` (`store()` a resized image, `discard()` the previous one after commit).
- `musicForm.js` mirrors the `ReleaseType`, `Platform` and `SocialNetwork` cases; `AdminOptionsParityTest` fails when they drift.
- Admin API `/api/admin/music[/releases|/videos|/links|/text|/portrait]`, page `/admin/music` (`pages/MusicPage.vue`, logic in `musicForm.js`).
- Public home: `HomeController` renders `templates/site/home.html.twig` partials; JSON-LD through `Site\MusicStructuredData`. Islands `Countdown`, `SpotifyPlayer`, `VideoFacade`: embeds load only after a click (CSP `frame-src` youtube-nocookie + open.spotify.com, no third-party request on load).
- Tests: `Tests\Support\FakeYoutube` decorates `http_client`; e2e `e2e/tests/music.spec.js`.

## M2 Lessons and Contact

- `Domain/Lessons`: `LessonOffer` (localized title and description, `OfferKind` single|pack, `sessions` 1 for a single and >= 2 for a pack, `LessonFormat` at-home|at-teacher|online, `minutes` 15..240 by 15, `Price`, position; the `LessonOfferDetails` VO validates), single-row `LessonsPage` (Markdown intro and FAQ). Ordered lists (releases, videos, links, offers) share `Domain\Shared\Positions` (`Positioned`, `OrderMismatch`) and `Presentation\Api\Shared\OrderPayload`.
- `Domain/Contact`: `ContactMessage` (name, email, `ContactTopic` general|lessons|production|beats, `offerRef` + `offerLabel` snapshot, body 10..5000 without control characters, locale en|fr, `receivedAt`, `handledAt`); kept three years (`app:contact:purge`, run by the entrypoint and a daily cron).
- `SendContactMessageHandler` stores and commits first, then the `ContactMails` port (`Infrastructure\Mail\SymfonyContactMails`, `templates/email/*`, sender `MAILER_SENDER`) notifies `Settings.contactEmail` (reply-to the visitor, skipped when empty) and acknowledges the visitor in their locale with a fixed text (no name or body echoed; limiters `contact_recipient` 3 a day per address and `contact_acknowledgement` 100 a day). A mail failure is logged with the message id and exception class only, never loses the message.
- Public `POST /api/contact` (no session, `SameOriginGuard`): `website` honeypot (201, nothing stored), rate limit 5 per 10 minutes per IP, an IPv6 /64 as one (`cache.contact_limiter`, a filesystem pool with `lock.factory`; `make e2e` clears it) -> 429 problem+json.
- Admin API `/api/admin/lessons[/page|/offers[/order]]` and `/api/admin/messages[/{id}/handled]`; pages `/admin/lessons` (`lessonsForm.js`) and `/admin/messages` (`messagesList.js`, unread badge).
- Public pages `/lessons` (`LessonsController`, gated by `SectionGate`, offers grouped by format, FAQ, JSON-LD `Service`) and `/contact` (always on, `ContactForm` island prefilled from `?topic=lessons&offer=<id>`). Buy buttons are "Contact me" links unless online checkout is on (M4).
- Dev fixtures add demo offers, lessons texts and two messages; e2e `e2e/tests/contact.spec.js` (the test mailer is `null://`, so mail is not asserted there).

## M3 Production

- `Domain/Production`: `ProductionService` (localized title and description, `ServiceKind` mix|master|mix-master|production|arrangement|recording|other, `Pricing` fixed|quote, `Price` (fixed must be > 0, a quote may be 0 = "on quote"), `deliveryDays` 1..120 and `revisions` 0..10 both optional, position; `ProductionServiceDetails` validates), `PortfolioItem` (title, artist, at least one role, year 1990..2100, optional https listen link and cover image ULID, position; `PortfolioItemDetails` validates), single-row `ProductionPage` (Markdown intro, process, FAQ). Same ordering contract as Lessons.
- `Application\Contact\ContactOffers` resolves `?topic=&offer=` for lessons (`LessonsQueries`) and production (`ProductionQueries::service`); the controller prefill and `SendContactMessageHandler` both use it. A replaced or removed cover file is deleted after commit.
- Admin API `/api/admin/production[/page|/services[/order]|/portfolio[/order]|/portfolio/{id}/cover]`; page `/admin/production` (`productionForm.js`, `components/production/*`; a cover can only be uploaded when editing an existing item).
- Public `/production` (`ProductionController`, gated by `SectionGate`, `ProductionCards`, JSON-LD `Service` with one `Offer` per service via `ProductionStructuredData`, process steps, portfolio, FAQ shared with Lessons through `_faq.html.twig`). Shared hero/card/FAQ styles live in `services.css`. Fixed services show "Contact me", quotes "Request a quote" (price hidden at 0 EUR), linking to `/contact?topic=production&offer=<id>` (fixed services become "Book & pay" when online checkout is on, M4).
- Dev fixtures add demo services, texts and three portfolio items (Injust's own releases); e2e `e2e/tests/production.spec.js`.

## M4 Checkout (Stripe)

- `Domain/Sales`: `Order` (table `sales_order`; `ProductType` lesson|production|beat with `requiredConsents()`, `cgvKind()`, `section()`; `OrderStatus` pending|paid|expired|withdrawn|refunded; reference `INJ-XXXXXX` from the ULID; snapshots of label, price, format, locale, consent sentences and the accepted CGV (`Terms` embeddable: version, FR/EN body, date), which the confirmation mail attaches; `pay(PaymentDetails)` is idempotent and throws `PaymentMismatch` on another amount or currency), `Invoice` (immutable, number `YYYY-NNNN` gapless per Paris year through `InvoiceSequence` locked `FOR UPDATE` in the payment transaction, seller and buyer snapshots, `VatCase` consumer|eu-business|non-eu-business with the mention from Settings), `Customer`, `Consent`, `FiscalYear`. Only lesson offers and fixed-price production services are sold; beats are modelled but not purchasable yet (M6/M7).
- `Application\Sales`: `StartCheckout` (rules `CheckoutClosed`, `SectionClosed`, `ProductUnavailable`, `MissingConsent`, `TermsChanged` when the CGV version shown on the page is no longer current; order committed first, then the gateway; a gateway failure expires the order and raises `PaymentUnavailable`), `ConfirmPayment` (lock the order, read the payment, pay, invoice, commit; then a second locked transaction sends the confirmation mail once, so overlapping deliveries never mail twice, and a mail failure propagates so Stripe retries), `ExpireCheckout`, `RecordRefund` (M5), `ResendConfirmation`, `PurgeAbandonedCheckouts` (`app:sales:purge`, entrypoint and host cron: expired orders older than 30 days); `SalesQueries` (orders, invoice, revenue book, totals with the EU digital and online-lesson thresholds). Ports: `PaymentGateway`, `SalesMails`, `SalesDocuments`, `ConsentTexts`, `CheckoutUrls`, `CheckoutProducts`, `Atomic` (implemented by `DoctrineTransaction`).
- Adapters: `Infrastructure\Payments\HttpStripeGateway` (HttpClient, no SDK, card only, sessions expire after 31 minutes), `Infrastructure\Documents\DompdfSalesDocuments` (invoice and CGV PDFs, `templates/pdf/*`, labels in the `documents` translation domain), `Infrastructure\Mail\Sales\SymfonySalesMails` (confirmation with consents, invoice and CGV attached), `Infrastructure\Sales\TranslatedConsentTexts` (`sales.consents.<kind>` in `messages`) and `RoutedCheckoutUrls`.
- Public: `POST /api/checkout` (no session, `SameOriginGuard`, 10 per 10 minutes per IP, `cache.checkout_limiter`) -> 201 `{url}`; `POST /webhooks/stripe` (raw body, `Webhooks\StripeSignature` verifies `Stripe-Signature`, events completed / async_payment_succeeded / expired / charge.refunded / charge.refund.updated); pages `/checkout/{productType}/{productId}` (noindex, 404 when checkout or the section is off or the product is not sellable; `CheckoutForm` island with three unticked consents and "Order with obligation to pay"), `/checkout/success?order=`, `/checkout/cancel?order=`. Buy buttons read "Book & pay" when checkout is on (quote services keep "Request a quote"), "Contact me" otherwise.
- Admin: `/api/admin/orders[/{id}|/{id}/invoice|/{id}/resend|/export|/totals]`, page `/admin/orders` (`ordersView.js`, `components/orders/*`); the export is the revenue book CSV (BOM, `;`).
- Stripe keys: `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` from env, empty in `.env`, never in git (`deploy/README.md` lists the webhook URL and events). Dev and test use `Tests\Support\Payments\FakeStripe` (decorates `http_client`, so dev never reaches real Stripe) and the dev/test-only pages `/_fake-checkout/{session}` (Pay signs a `checkout.session.completed` event and sends it through the real webhook).
- Dev fixtures (dev env only) add a complete fake legal identity ("Demo Artist"), switch lessons, production and online checkout on, and place two paid demo orders with invoices; e2e `e2e/tests/checkout.spec.js` (the test mailer is `null://`, so the confirmation is asserted through the admin "Sent on").

## M5 Withdrawal, refunds and credit notes

- Withdrawal (L221-18): `Order::withdraw` only from `paid`, lessons and production (beats waive it), until the end of the 14th day after the Paris payment date (`withdrawalLastDay()`); exceptions `WithdrawalNotAllowed`, `WithdrawalPeriodOver`. `WithdrawalRequest` (table `withdrawal_request`, one per order). Use cases `PreviewWithdrawal` (changes nothing), `RequestWithdrawal` (order found by reference AND email, else the generic `NoMatchingOrder`; commit, then acknowledgement to the customer and notice to `contactEmail`; a mail failure keeps the withdrawal and leaves `acknowledgedAt` null), `ResendWithdrawalAcknowledgement`.
- Public `/withdrawal` and `/fr/withdrawal` (`WithdrawalController`, island `WithdrawalForm.vue`: form, confirmation, done with the receipt time); `POST /api/withdrawal/preview` and `/api/withdrawal` (no session, `SameOriginGuard`, `withdrawal` limiter 5 per 10 minutes per IP shared by both, `cache.withdrawal_limiter`, applied through `Presentation\Api\IpRateLimit` like the checkout limiter, 429 messages in the `exceptions` domain; `^/api/withdrawal(/preview)?$` is PUBLIC_ACCESS in `security.yaml`). The footer link "Withdraw from a contract here" is always shown, so recent buyers keep it when checkout is switched off. The period end (`Order::withdrawalLastDay`) is paid day + 14, moved to the next working day when it falls on a weekend or a French public holiday (`Domain\Sales\WorkingDay`, Easter computed in PHP, no ext-calendar).
- Refunds: `RefundOrder` (admin, paid|withdrawn, 0 < amount <= remaining) only asks Stripe (`PaymentGateway::refund`, idempotency key `orderId-refundedCents-amount`); the `charge.refunded` and `charge.refund.updated` webhooks run `RecordRefund`, the single source of truth (also covers dashboard refunds): lock the order, for each `succeeded` Stripe refund not yet credited (pending ones wait for `charge.refund.updated`) call `Order::refund` and issue a `CreditNote` (number from the same `InvoiceSequence`, idempotent by `stripeRefundId`), then mail it with its PDF (a mail failure answers 500 so Stripe retries; a refund before the payment is recorded answers 503). The webhook fetches refunds through `PaymentGateway::refunds`. `Order.refundedCents`; status `refunded` only when fully refunded. Credit note amounts are stored positive and printed negative.
- `LegalIdentity.vatNumber` (optional, shown on invoices and credit notes). The Terms snapshot freezes the CGV with placeholders filled at checkout.
- Admin: `POST /api/admin/orders/{id}/refunds` (202 `{refundId}`), `GET /api/admin/orders/{id}/credit-notes/{creditNoteId}` (PDF), `POST /api/admin/orders/{id}/withdrawal/resend`; order detail shows the withdrawal, credit notes and a refund modal with a pro-rata helper; the CSV export has a `kind` column (Facture|Avoir, credit notes negative).
- `FakeStripe` answers `POST` and `GET /v1/refunds` and itself sends the signed `charge.refunded` to the webhook. Dev fixtures add a withdrawn lesson order and a half-refunded production order with a credit note. e2e: `checkout.spec.js` (buy, withdraw from the footer link, admin refund, credit note PDF).

## M6 Beats

- `Domain/Beats`: `Beat` (English title <= 120, unique editable slug, bpm 40..300, `MusicalKey` (24), up to 8 tags, optional cover image ULID, public preview, private `mp3`/`wav`/`stems` files as `BeatFile` {id, originalName, size, mimeType}, `visible` default false, `soldExclusivelyAt`, position), `LicenceTier` (localized name and Markdown summary, price > 0, at least one `Deliverable`, optional stream/copy/video caps, `exclusive`; only one exclusive tier, `OneExclusiveTier`), single-row `BeatsPage` (Markdown intro and FAQ). A beat is listed when visible, not sold exclusively and it has a preview; it is purchasable for a tier when it also has every deliverable of that tier.
- Files: `Application\Shared\PrivateFileStorage` (`LocalPrivateFileStorage`, `%kernel.share_dir%/private`, never served) and `AudioStorage` (`LocalAudioStorage`, `%kernel.share_dir%/audio`, served by `AudioMediaController` at `/media/audio/{ulid}.mp3` with Range support). Magic bytes and size limits are checked by `UploadCheck` (`config/packages/beats.yaml`: preview 20 MB, mp3 50 MB, wav 500 MB, stems 2 GB). Uploads move the file, a failed commit deletes the new file, a replaced or deleted file goes after commit. The JSON never exposes private paths or ids.
- Large uploads: `docker/php/app.ini` raises the PHP limits (2G, 600 s), `prod.ini` puts `upload_tmp_dir` on the share volume; the nginx location for `/api/admin/beats/{id}/files/` is documented in `deploy/README.md` (the server is not touched by the repo).
- Admin: `/admin/beats` (`BeatsPage.vue`, `components/beats/*`, `beatsForm.js`, `uploadState.js`, `composables/useFileUpload.js` with XHR progress, cancel and a beforeunload guard); API `/api/admin/beats[/page|/order|/tiers[/order|/{id}]|/{id}[/cover|/preview|/files/{mp3|wav|stems}]]`.
- Public `/beats` and `/fr/beats` (`BeatsController`, `BeatFilter` server-side filters, `BeatTable` island with one shared `<audio>`, comparison table of tiers, FAQ, JSON-LD `MusicRecording`; CSP `media-src 'self'`). The section stays off in production until the SACEM answer. Every Buy button is "Contact me" to `/contact?topic=beats&offer=<beatId>` (`ContactOffers` resolves listed beats); online purchase is M7.
- Dev fixtures (`dev` only, no binary in git): 3 tiers (Basic MP3 29 EUR, Premium WAV 59 EUR, Exclusive stems 499 EUR) and 4 visible beats with a generated silent MP3 preview; private files only on some beats so the purchasable states vary. The beats section is switched on in dev.
- e2e `e2e/tests/beats.spec.js`: tier and beat created, preview and mp3 uploaded, section on, the visitor plays the preview and reaches the prefilled contact form, private URLs are not reachable.
- Dashboard `sectionRows` counts beats.

## M4b Links and SEO

- `Domain/Links`: `BioLink` (localized label <= 80, `HttpsUrl`, optional `SocialNetwork` icon, highlighted, visible, `clicks`, position). Shown on the home page right after the hero (`templates/site/_links.html.twig`, `#links`, "Listen & follow"): visible bio links as large buttons (highlighted = red primary) then the M1 social links as an icon row, hidden when both are empty; the hero has a "Links" button to `#links`. There is no separate links page (`/links` is a 404; the admin Links page shows `${origin}/#links` to share). Every button points to `GET /go/{id}` (`Site\GoController`), which runs `CountBioLinkClick` (one atomic `UPDATE ... clicks = clicks + 1 ... RETURNING url`, no entity load, no session, no cookie, no IP) and answers 302 to the stored https URL only; a `HEAD` answers the same redirect without counting; hidden or unknown id is a 404. `/go/` is disallowed in `robots.txt` and answers `X-Robots-Tag: noindex`.
- `Domain/Seo`: `PageSeo` per `PageKey` (lessons|production|contact; title <= 70, description <= 170, optional share image). Home keeps `Settings.seoTitle`/`seoDescription`. `Site\SiteLayout` picks the override by route name, so renaming those routes drops it. `Settings.googleSiteVerification` is the token only (`/^[A-Za-z0-9_-]{10,100}$/`).
- Head: canonical, hreflang en/fr/x-default, Open Graph with sized absolute image (default `public/og-default.png`), JSON-LD `MusicGroup`, `WebSite` (home), `BreadcrumbList`. Sitemap has alternates, legal `lastmod` and release covers. 404 uses the full site layout (`ErrorPageLocale` sets the locale from the path); other errors use a DB-free `error.html.twig`.
- Admin: `/admin/links` (`LinksPage.vue`, `linksForm.js`) and `/admin/seo` (`SeoPage.vue`, `seoForm.js`); API `/api/admin/links[/order|/{id}]`, `/api/admin/seo[/{key}[/image]]`.
- e2e `e2e/tests/links.spec.js` (the visitor clicks from the home page). ErrorPageTest boots a non-debug kernel: clear `var/cache/test<TOKEN>` after changing a service constructor.

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
