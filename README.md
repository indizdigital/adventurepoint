# AdventurePoint – canoe rental on WordPress (DE/FR)

Local WordPress setup in Docker with:

- **Theme `adventurepoint`** – custom block theme (river/outdoor look), header with DE/FR switcher, translatable patterns.
- **Polylang** – German (default, `/de/`) and French (`/fr/`).
- **Plugin `ap-reservations`** – canoe reservations rentable per hour or per day, stock-based availability, bookable extras (life vests, dry bags, barrels, cool box, shuttle…), admin bookings/occupancy, emails in the customer's language, pluggable payment gateways (Payrexx-ready).

## Quick start

```sh
sh bin/setup.sh          # starts containers, installs WP, languages, Polylang, theme, plugin, demo content
```

| What        | URL                                   |
|-------------|----------------------------------------|
| Site DE     | http://localhost:8080/de/              |
| Site FR     | http://localhost:8080/fr/              |
| Admin       | http://localhost:8080/wp-admin (admin / admin, see `.env`) |
| Mails       | http://localhost:8025 (Mailpit catches all outgoing mail) |

`sh bin/setup.sh` is safe to re-run. WP-CLI: `./bin/wp <command>` (e.g. `./bin/wp plugin list`).
Stop: `docker compose down` · Reset everything: `docker compose down -v && sh bin/setup.sh`.

> The demo season is **April–October**, bookable up to 365 days ahead – pick a date in that window when testing.

The drive is FAT32, so the database and WordPress core live in Docker volumes; only the theme, the plugin and `mu-plugins` are bind-mounted from this folder.

## Deploy to the live server (SSH)

1. In your hosting panel: create an **empty database + user**, point the domain at an **empty web root**, enable **HTTPS**.
2. `cp .env.deploy.example .env.deploy` and fill in SSH target, web-root path, URL, database, admin e-mail, notification e-mail.
3. `sh bin/deploy.sh check` – verifies SSH, PHP ≥ 8.1, rsync, WP-CLI (installed to `~/.wp-cli` automatically if missing) and that the web root is empty.
4. `sh bin/deploy.sh install` – first deploy: WordPress (de_DE), FR language pack, Polylang, theme, plugin, pages/canoes/extras/menus. Prints the generated admin password **once**. If it aborts (e.g. network), just run it again – it resumes; on a finished site it refuses.
5. Later releases: `sh bin/deploy.sh update` – uploads only theme + plugin (removes deleted files), runs DB upgrades, flushes caches. Content and bookings are untouched.

After the first deploy: install an SMTP plugin (e.g. *WP Mail SMTP*) so booking mails don't land in spam, replace the placeholder address/phone/AGB/CGV, check *Reservations → Settings*. If media uploads fail, ask your host whether PHP runs as your SSH user (usual) – otherwise `wp-content/uploads` must be writable by the web server.

`.env.deploy` holds credentials and is in `.gitignore` – never commit or upload it.

## Layout

```
docker-compose.yml               WordPress (php8.3), MariaDB 11, WP-CLI, Mailpit
bin/setup.sh, setup-content.php  install + demo content (languages, pages, canoes, extras, menus)
bin/i18n.sh, bin/i18n/           regenerate .pot/.po/.mo/.l10n.php from the translation tables
wp-content/themes/adventurepoint block theme (theme.json, templates, parts, patterns, languages)
wp-content/plugins/ap-reservations
  includes/Period.php            validates hour/day periods (opening hours, season, lead time)
  includes/Availability.php      peak concurrent usage per canoe/extra incl. turnaround buffer
  includes/Quote.php             server-side pricing + availability
  includes/BookingService.php    creates bookings under a DB lock (no double booking)
  includes/Payments/             GatewayInterface, Registry, OnSiteGateway
  includes/Rest/Controller.php   POST /wp-json/ap/v1/quote, POST /wp-json/ap/v1/bookings
  includes/Admin/                bookings list/detail, occupancy view, settings, meta boxes
  templates/, assets/            booking wizard (vanilla JS), canoe grid/details
wp-content/mu-plugins/ap-local-mail.php   routes mail to Mailpit (local only)
```

## How it works

- **Canoes** (`Reservations → Canoes`): persons, stock, hourly/daily price, minimum hours, rentable per hour and/or day.
- **Extras** (`Reservations → Extras`): one-time + per-hour + per-day price, stock (0 = unlimited), max per booking, "suggest one per person".
- Prices/stock live on the German (default-language) post and are synced to translations, so DE and FR bookings share the same stock.
- Availability = stock − *peak* simultaneous usage in the requested period (plus the turnaround buffer). Statuses `pending`, `confirmed`, `completed` hold stock; `pending_payment` holds it for "payment hold" minutes.
- Shortcodes: `[ap_reservation]` (wizard, `?canoe=<id>` preselects), `[ap_canoes]` (grid), `[ap_canoe_details]` (single canoe template).
- Settings (`Reservations → Settings`): opening hours, season, slot interval, buffer, lead time, max days, currency, auto-confirm, payment method, notification email, terms page.
- Emails: customer gets "received" / "confirmed" / "cancelled" in the booking language; staff get a notification in the site language.

## Adding Payrexx later

The payment layer is ready; see the docblock in `includes/Payments/GatewayInterface.php`. In short:

1. Implement `GatewayInterface` in `Payments/PayrexxGateway.php` (`is_online()` → `true`).
2. `start()` creates a Payrexx gateway (amount, `referenceId` = booking ref, success/failed URLs from `Registry::return_url()`) and returns `PaymentResult::redirect( $link, $id )` – the wizard redirects the customer automatically.
3. `handle_webhook()` verifies the request and calls `BookingRepository::set_status( $id, 'confirmed', [ 'payment_status' => 'paid' ] )`. Webhook URL: `/wp-json/ap/v1/payment/payrexx/webhook`.
4. Register it via the `ap_reservations_gateways` filter and select it in the settings.

The return page (`?ap_booking=…&ap_key=…`) already shows the booking status.

## Translations

Source strings are English. Edit `bin/i18n/translations_plugin.py` / `translations_theme.py`, then run `sh bin/i18n.sh`.
Page content, canoes and extras are translated in WordPress via Polylang; the site tagline/date format via *Languages → Translations*.
