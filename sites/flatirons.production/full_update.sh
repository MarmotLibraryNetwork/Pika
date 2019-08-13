#!/bin/bash

# full_update.sh
# Mark Noble, Marmot Library Network
# James Staub, Nashville Public Library
# 20150219
# Script handles all aspects of a full index including extracting data from other systems.
# Should be called once per day from crontab

# this version emails script output
EMAIL=pikaservers@marmot.org
PIKASERVER=flatirons.production
PIKADBNAME=pika
OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/full_update_output.log"

MINFILE1SIZE=$((1070000000))

# Check for conflicting processes currently running
source "/usr/local/vufind-plus/vufind/bash/checkConflicts.sh"

#First make sure that we aren't running at a bad time.  This is really here in case we run manually.
# since the run in cron is timed to avoid sensitive times.
# Flatirons has no prohibited times (yet)
#checkProhibitedTimes "23:50" "00:40"

#Check for any conflicting processes that we shouldn't do a full index during.
#Since we aren't running in a loop, check in the order they run.
checkConflictingProcesses "sierra_export_api.jar ${PIKASERVER}" >> ${OUTPUT_FILE}
checkConflictingProcesses "sierra_export.jar ${PIKASERVER}" >> ${OUTPUT_FILE}
checkConflictingProcesses "overdrive_extract.jar ${PIKASERVER}" >> ${OUTPUT_FILE}
checkConflictingProcesses "reindexer.jar ${PIKASERVER}" >> ${OUTPUT_FILE}

#truncate the output file so you don't spend a week debugging an error from a week ago!
: > $OUTPUT_FILE;

# Back-up Solr Master Index
mysqldump ${PIKADBNAME} grouped_work_primary_identifiers > /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql
sleep 2m
tar -czf /data/vufind-plus/${PIKASERVER}/solr_master_backup.tar.gz /data/vufind-plus/${PIKASERVER}/solr_master/grouped/index/ /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql >> ${OUTPUT_FILE}
rm /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql

#Restart Solr
cd /usr/local/vufind-plus/sites/${PIKASERVER}; ./${PIKASERVER}.sh restart

#Extract from Hoopla
#cd /usr/local/vufind-plus/vufind/cron;./HOOPLA.sh ${PIKASERVER} >> ${OUTPUT_FILE}
cd /usr/local/vufind-plus/vufind/cron;./GetHooplaFromMarmot.sh >> ${OUTPUT_FILE}

#Extract Lexile Data
cd /data/vufind-plus/; curl --remote-name --remote-time --silent --show-error --compressed --time-cond /data/vufind-plus/lexileTitles.txt https://cassini.marmot.org/lexileTitles.txt >> ${OUTPUT_FILE}

#Extract AR Data
#cd /data/vufind-plus/accelerated_reader; wget -N --no-verbose https://cassini.marmot.org/RLI-ARDataTAB.txt
cd /data/vufind-plus/accelerated_reader; curl --remote-name --remote-time --silent --show-error --compressed --time-cond /data/vufind-plus/accelerated_reader/RLI-ARDataTAB.txt https://cassini.marmot.org/RLI-ARDataTAB.txt >> ${OUTPUT_FILE}

#Zinio Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} flatirons_sideload/zinio/shared zinio/shared >> ${OUTPUT_FILE}

#OneClick Digital Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} flatirons_sideload/oneclickdigital/longmont oneclickdigital/longmont >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} flatirons_sideload/oneclickdigital/loveland oneclickdigital/loveland >> ${OUTPUT_FILE}

#Ebrary Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} flatirons_sideload/ebrary/boulder ebrary/bpl >> ${OUTPUT_FILE}

/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} flatirons_sideload/ebrary/broomfield ebrary/mde >> ${OUTPUT_FILE}

#Kanopy Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} flatirons_sideload/kanopy/boulder kanopy/boulder >> ${OUTPUT_FILE}

#Creative Bug
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} flatirons_sideload/creativebug creative_bug/flatirons_sideload >> ${OUTPUT_FILE}

#Colorado State Goverment Documents Updates
curl --remote-time --show-error --compressed -o /data/vufind-plus/colorado_gov_docs/marc/fullexport.mrc https://cassini.marmot.org/colorado_state_docs.mrc

#Do a full extract from OverDrive just once a week to catch anything that doesn't
#get caught in the regular extract
DAYOFWEEK=$(date +"%u")
if [[ "${DAYOFWEEK}" -eq 7 ]]; then
	echo $(date +"%T") "Starting Overdrive fullReload." >> ${OUTPUT_FILE}
	cd /usr/local/vufind-plus/vufind/overdrive_api_extract/
	nice -n -10 java -server -XX:+UseG1GC -jar overdrive_extract.jar ${PIKASERVER} fullReload >> ${OUTPUT_FILE}
	echo $(date +"%T") "Completed Overdrive fullReload." >> ${OUTPUT_FILE}
fi

# should test for new bib extract file
# should copy old bib extract file

# Extract from ILS #

# Date For Backup filename
TODAY=$(date +"%m_%d_%Y")

#Extract from ILS
#Copy extracts from FTP Server
mount 10.1.2.7:/ftp/flatirons_marc_export /mnt/ftp
FILE=$(find /mnt/ftp -name "script.MARC.*" -mtime -1 | sort -n | tail -1)

if [ -n "$FILE" ]; then
  #check file size
	FILE1SIZE=$(wc -c <"$FILE")
	if [ $FILE1SIZE -ge $MINFILE1SIZE ]; then

		echo "Latest export file is " $FILE >> ${OUTPUT_FILE}
		DIFF=$(($FILE1SIZE - $MINFILE1SIZE))
		PERCENTABOVE=$((100 * $DIFF / $MINFILE1SIZE))
		echo "The export file is $PERCENTABOVE (%) larger than the minimum size check." >> ${OUTPUT_FILE}

		# Copy to data directory to process
		cp --update --preserve=timestamps $FILE /data/vufind-plus/${PIKASERVER}/marc/fullexport.mrc

		# Delete old exports on the ftp server
		find /mnt/ftp -mindepth 1 -maxdepth 1 -name "script.MARC.*" -type f -mtime +7 -delete
		umount /mnt/ftp

		# Copy to marc_export to keep as a backup
		cp /data/vufind-plus/${PIKASERVER}/marc/fullexport.mrc /data/vufind-plus/${PIKASERVER}/marc_export/pika.$TODAY.mrc

		#Validate the export
		cd /usr/local/vufind-plus/vufind/cron; java -server -XX:+UseG1GC -jar cron.jar ${PIKASERVER} ValidateMarcExport >> ${OUTPUT_FILE}

		#Full Regroup
		cd /usr/local/vufind-plus/vufind/record_grouping; java -server -Xmx6G -XX:+UseG1GC -jar record_grouping.jar ${PIKASERVER} fullRegroupingNoClear >> ${OUTPUT_FILE}

		#Full Reindex
		cd /usr/local/vufind-plus/vufind/reindexer; java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} fullReindex >> ${OUTPUT_FILE}

		# Truncate Continous Reindexing list of changed items
		cat /dev/null >| /data/vufind-plus/${PIKASERVER}/marc/changed_items_to_process.csv

		# Delete any exports over 7 days
		find /data/vufind-plus/flatirons.production/marc_export/ -mindepth 1 -maxdepth 1 -name *.mrc -type f -mtime +7 -delete

		NEWLEVEL=$(($FILE1SIZE * 97 / 100))
		echo "" >> ${OUTPUT_FILE}
		echo "Based on today's export file, a new minimum filesize check level should be set to $NEWLEVEL" >> ${OUTPUT_FILE}

		# Wait 2 minutes for solr replication to finish; then delete the inactive solr indexes folders older than 48 hours
		# Note: Running in the full update because we know there is a freshly created index.
		sleep 2m
		find /data/vufind-plus/${PIKASERVER}/solr_searcher/grouped/ -name "index.*" -type d -mmin +2880 -exec rm -rf {} \; >> ${OUTPUT_FILE}

	else
		echo $FILE " size " $FILE1SIZE "is less than minimum size :" $MINFILE1SIZE "; Export was not moved to data directory, Full Regrouping & Full Reindexing skipped." >> ${OUTPUT_FILE}
		umount /mnt/ftp
	fi
else
	echo "Did not find a Sierra export file from the last 24 hours, Full Regrouping & Full Reindexing skipped." >> ${OUTPUT_FILE}
	umount /mnt/ftp
fi

# Clean-up Solr Logs
find /usr/local/vufind-plus/sites/default/solr/jetty/logs -name "solr_log_*" -mtime +7 -delete
find /usr/local/vufind-plus/sites/default/solr/jetty/logs -name "solr_gc_log_*" -mtime +7 -delete

#Restart Solr
cd /usr/local/vufind-plus/sites/${PIKASERVER}; ./${PIKASERVER}.sh restart

#Email results
FILESIZE=$(stat -c%s ${OUTPUT_FILE})
if [[ ${FILESIZE} > 0 ]]
then
	# send mail
	mail -s "Full Extract and Reindexing - ${PIKASERVER}" $EMAIL < ${OUTPUT_FILE}
fi

