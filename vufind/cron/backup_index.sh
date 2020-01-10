#!/bin/bash
# Script backs up the nightly index and is saved as tar archive file so that the index can be restored if needed
#
# Requires .my.cnf settings for mysqldump
if [ $# = 2 ];then
	PIKASERVER=$1
	PIKADBNAME=$2

	# Back-up Solr Master Index
	mysqldump ${PIKADBNAME} grouped_work_primary_identifiers > /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql
	sleep 2m
	tar -czf /data/vufind-plus/${PIKASERVER}/solr_master_backup.tar.gz /data/vufind-plus/${PIKASERVER}/solr_master/grouped/index/ /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql
	rm /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql

else
  echo ""
  echo "Usage:  $0 {Pika Sites Directory Name for this instance} {main Pika database name}"
  echo "eg: $0 pika.test pika"
  echo ""
  exit 1
fi
