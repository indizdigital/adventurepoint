#!/bin/sh
# Regenerate .pot, DE/FR .po (from bin/i18n/translations_*.py) and compiled .mo/.l10n.php files.
# Usage: sh bin/i18n.sh
set -e
cd "$(dirname "$0")/.."
wp() { docker compose run --rm -T wpcli wp "$@" 2>&1 | grep -v ' Container '; }
THEME=wp-content/themes/adventurepoint/languages
PLUGIN=wp-content/plugins/ap-reservations/languages

wp i18n make-pot /var/www/html/wp-content/plugins/ap-reservations /var/www/html/$PLUGIN/ap-reservations.pot --exclude=assets
wp i18n make-pot /var/www/html/wp-content/themes/adventurepoint /var/www/html/$THEME/adventurepoint.pot

python3 -I bin/i18n/build_po.py $PLUGIN/ap-reservations.pot bin/i18n/translations_plugin.py $PLUGIN/ap-reservations
# Theme translation files are named by locale only (de_DE.po).
python3 -I bin/i18n/build_po.py $THEME/adventurepoint.pot bin/i18n/translations_theme.py $THEME/tmp
mv $THEME/tmp-de_DE.po $THEME/de_DE.po
mv $THEME/tmp-fr_FR.po $THEME/fr_FR.po

for dir in $THEME $PLUGIN; do
	wp i18n make-mo /var/www/html/$dir
	wp i18n make-php /var/www/html/$dir
done
