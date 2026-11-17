# Sourced by the mariadb image's entrypoint the first time the data volume is
# initialised. Container equivalent of PikaSettings
# bash/pika-server-setup/pika-db-setup.sql plus loading a schema.
#
# The image has already created the pika database and the pika user from
# MARIADB_DATABASE / MARIADB_USER / MARIADB_PASSWORD.
#
#   /pika-schema  docker/db/sql/ in the Pika repository: the current schema of both
#                 databases and the db_update rows that say which DB Maintenance
#                 updates that schema already includes.
#   /pika-sql     bash/pika-server-setup/sql/ in PikaSettings: starter data (roles,
#                 variables, materials request statuses, spelling words).

docker_process_sql <<-EOSQL
	CREATE DATABASE IF NOT EXISTS econtent;
	GRANT ALL PRIVILEGES ON pika.* TO '${MARIADB_USER}'@'%';
	GRANT ALL PRIVILEGES ON econtent.* TO '${MARIADB_USER}'@'%';
	FLUSH PRIVILEGES;
EOSQL

pika_load() {
	local database="$1" pattern="$2" file
	for file in ${pattern}; do
		[ -f "$file" ] || continue
		mysql_note "Pika: loading ${file} into ${database}"
		docker_process_sql --database="${database}" < "$file"
	done
}

pika_load pika     '/pika-schema/pika_schema.sql'
pika_load pika     '/pika-schema/pika_db_update_data.sql'
pika_load econtent '/pika-schema/econtent_schema.sql'

pika_load pika     '/pika-sql/pika_other_data_*.sql'
pika_load pika     '/pika-sql/pika_spelling_words_data_*.sql'
