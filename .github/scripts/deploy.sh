#!/usr/bin/env bash
#
# Non-interactive deployment of Pika to the server this script runs on.
# CI/CD equivalent of settings/bash/code_deploy.sh — runs on each Pika
# server via its self-hosted GitHub Actions runner.
#
# Usage: deploy.sh <PikaServer> <ref>
#   eg:  deploy.sh marmot.test 2026.02.0
#   eg:  deploy.sh marmot.production v2026.02.1
#
set -euo pipefail

PIKA_SERVER="${1:?Usage: deploy.sh <PikaServer> <ref>   eg: deploy.sh marmot.test 2026.02.0}"
REF="${2:?Usage: deploy.sh <PikaServer> <ref>   eg: deploy.sh marmot.test 2026.02.0}"

PIKA_ROOT=/usr/local/pika
SETTINGS_DIR=/usr/share/php/pika/settings
SETTINGS_BRANCH="${SETTINGS_BRANCH:-ubuntu}"
COMPOSER_VENDOR=/usr/share/composer/vendor

step(){ echo ""; echo "==> $*"; }

#--------------------------------------------------------------------------
step "Sanity checks"
#--------------------------------------------------------------------------
[ -d "$PIKA_ROOT" ]            || { echo "Pika root not found: $PIKA_ROOT"; exit 1; }
[ -d "$SETTINGS_DIR" ]         || { echo "Settings dir not found: $SETTINGS_DIR"; exit 1; }
[ -e "$PIKA_ROOT/sites/$PIKA_SERVER" ] || { echo "Unknown Pika server: $PIKA_SERVER (no sites/$PIKA_SERVER)"; exit 1; }

#--------------------------------------------------------------------------
step "Updating Pika code to $REF"
#--------------------------------------------------------------------------
cd "$PIKA_ROOT"
git fetch --tags --prune origin
if git show-ref --verify --quiet "refs/remotes/origin/$REF"; then
	# Branch deploy: check the branch out and bring it up to date. Prefer a
	# fast-forward; fall back to the historical "-X theirs" merge that
	# code_deploy.sh uses when servers carry local commits.
	git checkout "$REF" 2>/dev/null || git checkout -b "$REF" "origin/$REF"
	git merge --ff-only "origin/$REF" \
		|| git merge -s recursive -X theirs --no-edit "origin/$REF"
else
	# Tag or commit deploy (production releases): detached checkout.
	git checkout -f "$REF"
fi
echo "Deployed commit: $(git rev-parse --short HEAD)"

#--------------------------------------------------------------------------
step "Updating Pika settings repository ($SETTINGS_BRANCH)"
#--------------------------------------------------------------------------
cd "$SETTINGS_DIR"
git fetch origin "$SETTINGS_BRANCH"
git merge --ff-only "origin/$SETTINGS_BRANCH" \
	|| git merge -s recursive -X theirs --no-edit "origin/$SETTINGS_BRANCH"

#--------------------------------------------------------------------------
step "Installing Composer packages"
#--------------------------------------------------------------------------
cd "$PIKA_ROOT/install"
composer install --no-interaction --no-progress
echo "Copying DataObject to vendor directory"
cp -rf "$PIKA_ROOT/install/PEAR/DB/"* "$COMPOSER_VENDOR/pear/db/DB"

#--------------------------------------------------------------------------
step "Clearing Smarty compile and cache folders"
#--------------------------------------------------------------------------
SMARTY_COMPILE_DIR="$PIKA_ROOT/vufind/web/interface/compile"
SMARTY_CACHE_DIR="$PIKA_ROOT/vufind/web/interface/cache"
if [ -d "$SMARTY_COMPILE_DIR" ]; then rm -rf "${SMARTY_COMPILE_DIR:?}"/*; fi
if [ -d "$SMARTY_CACHE_DIR" ];   then rm -rf "${SMARTY_CACHE_DIR:?}"/*;   fi

#--------------------------------------------------------------------------
step "Reloading Apache"
#--------------------------------------------------------------------------
# Requires a sudoers entry for the runner user — see documentation/ci-cd.md
sudo -n /usr/bin/systemctl reload apache2

#--------------------------------------------------------------------------
step "Flushing memcache"
#--------------------------------------------------------------------------
if echo flush_all | nc -w 2 127.0.0.1 11211 | grep -q OK; then
	echo "Memcache flushed"
else
	echo "WARNING: could not flush memcache (continuing)"
fi

#--------------------------------------------------------------------------
step "Running database maintenance updates"
#--------------------------------------------------------------------------
php "$PIKA_ROOT/vufind/web/runDatabaseUpdates.php" "$PIKA_SERVER"

#--------------------------------------------------------------------------
step "Smoke test"
#--------------------------------------------------------------------------
SITE_URL=$(awk -F= '/^url[[:space:]]*=/ {gsub(/[[:space:]]/,"",$2); print $2; exit}' \
	"$PIKA_ROOT/sites/$PIKA_SERVER/conf/config.ini")
if [ -z "$SITE_URL" ]; then
	echo "WARNING: no url found in sites/$PIKA_SERVER/conf/config.ini — skipping smoke test"
	exit 0
fi
for path in "/" "/Search/Results?lookfor=test"; do
	STATUS=000
	for attempt in 1 2 3; do
		STATUS=$(curl -sk -o /dev/null -w "%{http_code}" --max-time 30 "${SITE_URL}${path}") && break
		echo "curl attempt $attempt failed for ${SITE_URL}${path}; retrying"
		sleep 5
	done
	if [ "$STATUS" != "200" ]; then
		echo "::error::Smoke test failed: ${SITE_URL}${path} returned HTTP $STATUS"
		exit 1
	fi
	echo "OK ($STATUS): ${SITE_URL}${path}"
done

echo ""
echo "Deployment of $REF to $PIKA_SERVER complete."
