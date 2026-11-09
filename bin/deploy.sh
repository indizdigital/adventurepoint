#!/bin/sh
# Deploy AdventurePoint to a server via SSH.
#
#   sh bin/deploy.sh check     – test SSH, PHP, WP-CLI, rsync, target folder (changes nothing)
#   sh bin/deploy.sh install   – FIRST deploy on an EMPTY web root: WordPress + DE/FR + Polylang + theme + plugin + demo content
#   sh bin/deploy.sh update    – later releases: upload theme + plugin only, run DB upgrades, flush caches
#
# Config: .env.deploy (see .env.deploy.example)
set -eu
cd "$(dirname "$0")/.."

MODE="${1:-}"
[ -f .env.deploy ] || { echo "Missing .env.deploy – copy .env.deploy.example and fill it in."; exit 1; }
set -a; . ./.env.deploy; set +a
: "${SSH_TARGET:?}" "${REMOTE_PATH:?}" "${SITE_URL:?}"
SSH_PORT="${SSH_PORT:-22}"
REMOTE_PHP="${REMOTE_PHP:-php}"

SSH_OPTS="${SSH_OPTS:-}"
SSH="ssh -p $SSH_PORT -o ServerAliveInterval=30 $SSH_OPTS $SSH_TARGET"
# Normal web permissions (the FAT32 drive reports 700 for everything, which a web server running as another user cannot read).
RSYNC_OPTS="--exclude=._* --exclude=.DS_Store --exclude=*.pot --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r"

# Run a bash script on the server with the deploy config exported.
remote() {
	{
		printf 'set -euo pipefail\n'
		for v in REMOTE_PATH SITE_URL SITE_TITLE DB_NAME DB_USER DB_PASSWORD DB_HOST ADMIN_USER ADMIN_EMAIL ADMIN_PASSWORD NOTIFY_EMAIL REMOTE_PHP; do
			eval "val=\${$v:-}"
			printf "%s=%s\n" "$v" "'$(printf %s "$val" | sed "s/'/'\\\\''/g")'"
		done
		# WP-CLI: use the server's "wp" or a private copy in ~/.wp-cli
		cat <<'EOS'
export WP_CLI_PHP_ARGS="-d memory_limit=512M"
if command -v wp >/dev/null 2>&1; then WP="wp"; else
	mkdir -p "$HOME/.wp-cli"
	[ -f "$HOME/.wp-cli/wp-cli.phar" ] || curl -fsSL -o "$HOME/.wp-cli/wp-cli.phar" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	WP="$REMOTE_PHP -d memory_limit=512M $HOME/.wp-cli/wp-cli.phar"
fi
wp() { (cd "$REMOTE_PATH" 2>/dev/null || cd "$HOME"; $WP --path="$REMOTE_PATH" "$@"); }   # cd: picks up wp-cli.yml
retry() { for i in 1 2 3; do "$@" && return 0; echo "  …retrying ($i)"; sleep 5; done; return 1; }
EOS
		cat
	} | $SSH bash -s
}

upload_code() {
	echo "▶ Uploading theme and plugin…"
	$SSH "mkdir -p '$REMOTE_PATH/wp-content/themes' '$REMOTE_PATH/wp-content/plugins'"
	# --delete only inside our own two folders
	rsync -rlptz --delete $RSYNC_OPTS -e "ssh -p $SSH_PORT $SSH_OPTS" wp-content/themes/adventurepoint/ "$SSH_TARGET:$REMOTE_PATH/wp-content/themes/adventurepoint/"
	rsync -rlptz --delete $RSYNC_OPTS -e "ssh -p $SSH_PORT $SSH_OPTS" wp-content/plugins/ap-reservations/ "$SSH_TARGET:$REMOTE_PATH/wp-content/plugins/ap-reservations/"
}

case "$MODE" in

check)
	remote <<'EOS'
echo "Host:        $(hostname)"
echo "PHP:         $($REMOTE_PHP -r 'echo PHP_VERSION;')  (need ≥ 8.1)"
echo "Extensions:  $($REMOTE_PHP -m | grep -Ei '^(mysqli|curl|mbstring|zip|gd|imagick|intl)$' | tr '\n' ' ')"
echo "rsync:       $(command -v rsync || echo MISSING)"
echo "WP-CLI:      $($WP --version 2>/dev/null || echo 'not available')"
if [ -d "$REMOTE_PATH" ]; then
	echo "Web root:    $REMOTE_PATH ($(ls -A "$REMOTE_PATH" | wc -l | tr -d ' ') entries, writable: $([ -w "$REMOTE_PATH" ] && echo yes || echo NO))"
	ls -A "$REMOTE_PATH" | head -10 | sed 's/^/             /'
else
	echo "Web root:    $REMOTE_PATH does not exist yet"
fi
if [ -f "$REMOTE_PATH/wp-config.php" ]; then echo "WordPress:   already installed ($(wp core version 2>/dev/null || echo '?'))"; else echo "WordPress:   not installed"; fi
EOS
	;;

install)
	: "${DB_NAME:?}" "${DB_USER:?}" "${DB_PASSWORD:?}" "${ADMIN_EMAIL:?}"
	if [ -z "${ADMIN_PASSWORD:-}" ]; then
		ADMIN_PASSWORD="$(LC_ALL=C tr -dc 'A-Za-z0-9!#%+=' </dev/urandom | head -c 24)"
		GENERATED_PW=1
	fi
	echo "▶ Installing WordPress on $SSH_TARGET:$REMOTE_PATH …"
	remote <<'EOS'
mkdir -p "$REMOTE_PATH"
# Lets WP-CLI write .htaccess rewrite rules on Apache hosts (harmless on nginx).
[ -f "$REMOTE_PATH/wp-cli.yml" ] || printf 'apache_modules:\n  - mod_rewrite\n' > "$REMOTE_PATH/wp-cli.yml"
if [ -f "$REMOTE_PATH/wp-config.php" ] && wp core is-installed 2>/dev/null; then
	if [ "$(wp option get ap_deploy_installed 2>/dev/null || true)" = "1" ]; then
		echo "This site was already installed – use 'update'."; exit 1
	fi
	echo "WordPress already present from an interrupted install – resuming."
	wp user update "$ADMIN_USER" --user_pass="$ADMIN_PASSWORD" --skip-email >/dev/null
else
	if [ ! -f "$REMOTE_PATH/wp-config.php" ]; then
		leftover=$(ls -A "$REMOTE_PATH" | grep -Ev '^(\.well-known|cgi-bin|\.htaccess|index\.html|favicon\.ico|default\.php|wp-cli\.yml)$' || true)
		if [ -n "$leftover" ]; then
			echo "Web root is not empty:"; echo "$leftover" | sed 's/^/  /'; echo "Remove these files first (or point REMOTE_PATH elsewhere)."; exit 1
		fi
		rm -f "$REMOTE_PATH/index.html" "$REMOTE_PATH/default.php"
		wp core download --locale=de_DE
		wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" --dbhost="$DB_HOST" --locale=de_DE --extra-php <<'PHP'
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'DISALLOW_FILE_EDIT', true );
PHP
	fi
	wp core install --url="$SITE_URL" --title="$SITE_TITLE" --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASSWORD" --admin_email="$ADMIN_EMAIL" --skip-email
fi
EOS
	upload_code
	echo "▶ Uploading demo content script…"
	chmod 644 bin/setup-content.php 2>/dev/null || true
	scp -q -P "$SSH_PORT" $SSH_OPTS bin/setup-content.php "$SSH_TARGET:$REMOTE_PATH/ap-setup-content.php"
	remote <<'EOS'
retry wp language core install de_DE fr_FR
wp site switch-language de_DE
wp option update timezone_string "Europe/Zurich"
wp option update date_format "j. F Y"
wp option update time_format "H:i"
wp option update start_of_week 1
wp option update blogdescription "Kanu- und Kajakvermietung"
wp rewrite structure "/%postname%/"
wp plugin is-installed polylang || retry wp plugin install polylang
wp plugin activate polylang
retry wp language plugin install polylang de_DE fr_FR || true
wp theme activate adventurepoint
wp plugin activate ap-reservations
wp plugin delete hello akismet 2>/dev/null || true
wp eval-file "$REMOTE_PATH/ap-setup-content.php"
rm -f "$REMOTE_PATH/ap-setup-content.php"
# Reservation notifications go to NOTIFY_EMAIL
wp option update ap_reservations_settings --format=json "$(wp option get ap_reservations_settings --format=json | $REMOTE_PHP -r '$s=json_decode(stream_get_contents(STDIN),true); $s["admin_email"]=$argv[1]; echo json_encode($s);' "$NOTIFY_EMAIL")"
wp rewrite flush --hard
wp cache flush
wp option update ap_deploy_installed 1 >/dev/null
EOS
	echo ""
	echo "✅ Installed: $SITE_URL/de/  ·  $SITE_URL/fr/"
	echo "   Admin:   $SITE_URL/wp-admin  user: $ADMIN_USER"
	[ "${GENERATED_PW:-}" = 1 ] && echo "   Password (generated, shown once – store it now): $ADMIN_PASSWORD"
	echo "   Next: configure outgoing mail (SMTP) so booking emails arrive – see README."
	;;

update)
	upload_code
	remote <<'EOS'
[ -f "$REMOTE_PATH/wp-config.php" ] || { echo "WordPress not installed – run 'install' first."; exit 1; }
wp plugin activate ap-reservations >/dev/null   # no-op if active; runs DB table upgrades via plugins_loaded
wp eval 'AP\Reservations\Installer::maybe_upgrade();'
wp rewrite flush --hard
wp cache flush
echo "Theme $(wp theme get adventurepoint --field=version) / plugin $(wp plugin get ap-reservations --field=version) live."
EOS
	echo "✅ Updated $SITE_URL"
	;;

*)
	sed -n '2,9p' "$0" | sed 's/^# \{0,1\}//'
	exit 1
	;;
esac
