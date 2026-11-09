#!/bin/sh
# One-shot local install: WordPress + DE/FR (Polylang) + AdventurePoint theme + AP Reservations + demo content.
# Usage: sh bin/setup.sh        (safe to re-run)
set -e
cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env
set -a; . ./.env; set +a

wp() { docker compose run --rm -T wpcli wp "$@"; }

echo "▶ Starting containers…"
docker compose up -d db wordpress mailpit

echo "▶ Waiting for WordPress files…"
i=0
until docker compose exec -T wordpress test -f /var/www/html/wp-config.php 2>/dev/null; do
	i=$((i+1)); [ $i -gt 60 ] && { echo "WordPress container did not come up"; exit 1; }
	sleep 2
done

if ! wp core is-installed 2>/dev/null; then
	echo "▶ Installing WordPress…"
	wp core install --url="$WP_URL" --title="$WP_TITLE" --admin_user="$WP_ADMIN_USER" \
		--admin_password="$WP_ADMIN_PASSWORD" --admin_email="$WP_ADMIN_EMAIL" --skip-email
fi

echo "▶ Languages & basic options…"
wp language core install de_DE fr_FR
wp site switch-language de_DE
wp option update timezone_string "Europe/Zurich"
wp option update date_format "j. F Y"
wp option update time_format "H:i"
wp option update start_of_week 1
wp option update blogdescription "Kanu- und Kajakvermietung"
wp rewrite structure "/%postname%/"

echo "▶ Compiling theme/plugin translations…"
for dir in wp-content/themes/adventurepoint/languages wp-content/plugins/ap-reservations/languages; do
	wp i18n make-mo "/var/www/html/$dir"
	wp i18n make-php "/var/www/html/$dir"
done

echo "▶ Plugins & theme…"
wp plugin is-installed polylang || wp plugin install polylang
wp plugin activate polylang
wp language plugin install polylang de_DE fr_FR || true
wp theme activate adventurepoint
wp plugin activate ap-reservations
wp plugin delete hello akismet 2>/dev/null || true

echo "▶ Demo content…"
wp eval-file /scripts/setup-content.php

wp rewrite flush --hard
wp cache flush

echo ""
echo "✅ Done"
echo "   Site (DE):  $WP_URL/de/"
echo "   Site (FR):  $WP_URL/fr/"
echo "   Admin:      $WP_URL/wp-admin  ($WP_ADMIN_USER / $WP_ADMIN_PASSWORD)"
echo "   Mails:      http://localhost:8025"
