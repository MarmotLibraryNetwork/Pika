#!/usr/bin/env bash
# Runtime half of PikaSettings bash/pika-server-setup/pika-pika-install.sh:
# create the per-site log and data directories in the mounted volumes, then run Apache.
set -euo pipefail

PIKASERVER="${PIKA_SITE:?PIKA_SITE must be set}"
APACHE_USER=www-data
WEB_ROOT=/usr/local/pika/vufind/web

if [[ ! -e "/usr/local/pika/sites/${PIKASERVER}/conf/config.ini" ]]; then
	echo "WARNING: /usr/local/pika/sites/${PIKASERVER}/conf/config.ini not found." >&2
	echo "         Link the site from the settings repository (bash/installSettings.sh ${PIKASERVER})." >&2
fi

# Log directories
mkdir -p "/var/log/pika/${PIKASERVER}"
chown -R "${APACHE_USER}:${APACHE_USER}" "/var/log/pika/${PIKASERVER}"

# Data directories
mkdir -p /data/pika/accelerated_reader /data/pika/hoopla/{marc,marc_recs}
if [[ ! -d "/data/pika/${PIKASERVER}" ]]; then
	# First start for this site only: the tree can get large (covers), so it is not re-walked on every start
	mkdir -p "/data/pika/${PIKASERVER}"/{covers/{small,medium,large,original},genealogy,marc,marc_recs,marc_updates,qrcodes,reports,sql_backup}
	chgrp -R "${APACHE_USER}" "/data/pika/${PIKASERVER}"
	chmod -R 774 "/data/pika/${PIKASERVER}"
fi

# Smarty template dirs (named volumes mounted over the checkout)
mkdir -p "${WEB_ROOT}/interface/compile" "${WEB_ROOT}/interface/cache"
chown "${APACHE_USER}:${APACHE_USER}" "${WEB_ROOT}/interface/compile" "${WEB_ROOT}/interface/cache"
chmod 774 "${WEB_ROOT}/interface/compile" "${WEB_ROOT}/interface/cache"

# Anything other than the default command (e.g. `docker compose run web bash`)
if [[ $# -gt 0 ]]; then
	exec "$@"
fi

# Send Apache's logs to `docker compose logs web`. The log paths come from the site's own
# vhost file, so each one is replaced by a link to the container's stdout or stderr.
VHOST=/etc/apache2/sites-enabled/pika.conf
if [[ -f "$VHOST" ]]; then
	while read -r directive path _; do
		path="${path//\"/}"
		[[ "$path" == /var/log/* ]] || continue   # leave piped loggers and odd paths alone
		mkdir -p "$(dirname "$path")"
		if [[ "$directive" == ErrorLog ]]; then
			ln -sfT /dev/stderr "$path"
		else
			ln -sfT /dev/stdout "$path"
		fi
	done < <(grep -E '^[[:space:]]*(ErrorLog|CustomLog)[[:space:]]' "$VHOST" || true)
fi

# envvars reads variables that are normally unset, so nounset has to be off while it is sourced
set +u
# shellcheck disable=SC1091
source /etc/apache2/envvars
set -u
mkdir -p "${APACHE_RUN_DIR}" "${APACHE_LOCK_DIR}" "${APACHE_LOG_DIR}"
rm -f "${APACHE_PID_FILE}"
exec apache2 -DFOREGROUND
