#!/bin/bash
# Script handles all aspects of a full index including extracting data from other systems.
# Should be called once per day.  Will interrupt partial reindexing.
#
# At the end of the index will email users with the results.
EMAIL=root
PIKASERVER=sacramento.production
PIKADBNAME=pika
OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/full_update_output.log"
USE_SIERRA_API_EXTRACT=0

MINFILE1SIZE=$((1010000000))

# Check if full_update is already running
#TODO: Verify that the PID file doesn't get log-rotated
PIDFILE="/var/log/vufind-plus/${PIKASERVER}/full_update.pid"
if [ -f $PIDFILE ]
then
	PID=$(cat $PIDFILE)
	ps -p $PID > /dev/null 2>&1
	if [ $? -eq 0 ]
	then
		mail -s "Full Extract and Reindexing - ${PIKASERVER}" $EMAIL <<< "$0 is already running"
		exit 1
	else
		## Process not found assume not running
		echo $$ > $PIDFILE
		if [ $? -ne 0 ]
		then
			mail -s "Full Extract and Reindexing - ${PIKASERVER}" $EMAIL <<< "Could not create PID file for $0"
			exit 1
		fi
	fi
else
	echo $$ > $PIDFILE
	if [ $? -ne 0 ]
	then
		mail -s "Full Extract and Reindexing - ${PIKASERVER}" $EMAIL <<< "Could not create PID file for $0"
		exit 1
	fi
fi

# Check for conflicting processes currently running
source "/usr/local/vufind-plus/vufind/bash/checkConflicts.sh"

#Check for any conflicting processes that we shouldn't do a full index during.
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

if [ $USE_SIERRA_API_EXTRACT -ne 1 ]; then
	#Extract from ILS this normally won't update anything since they don't have scheduler, but it could be used manually from time to time.
	/usr/local/vufind-plus/sites/${PIKASERVER}/copySierraExport.sh >> ${OUTPUT_FILE}
fi

#Get the updated volume information not needed for LION since they don't have volumes
#cd /usr/local/vufind-plus/vufind/cron;
#nice -n -10 java -jar cron.jar ${PIKASERVER} ExportSierraData >> ${OUTPUT_FILE}

#Extract from Hoopla, this just needs to be done once a day
cd /usr/local/vufind-plus/vufind/cron;./GetHooplaFromMarmot.sh >> ${OUTPUT_FILE}

## Side Loads ##

#Book Flix
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} sacramento/bookflix/spl bookflix/spl >> ${OUTPUT_FILE}

#Gale
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} sacramento/gale_ebook gale_ebook/spl >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} sacramento/gale_econtent gale_econtent/spl >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} sacramento/gale/folsom gale/folsom >> ${OUTPUT_FILE}

#Ebsco
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} sacramento/ebsco/folsom ebsco/folsom >> ${OUTPUT_FILE}

#RbDigital Magazines
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} sacramento/rbdigital_magazine/spl rbdigital_magazine/spl >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} sacramento/rbdigital_magazine/folsom rbdigital_magazine/folsom >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} sacramento/rbdigital_magazine/woodland rbdigital_magazine/woodland >> ${OUTPUT_FILE}

#Enki
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} sacramento/enki/spl enki/spl >> ${OUTPUT_FILE}


#Extract Lexile Data
cd /data/vufind-plus/; curl --remote-name --remote-time --silent --show-error --compressed --time-cond /data/vufind-plus/lexileTitles.txt https://cassini.marmot.org/lexileTitles.txt

#Extract AR Data
cd /data/vufind-plus/accelerated_reader; curl --remote-name --remote-time --silent --show-error --compressed --time-cond /data/vufind-plus/accelerated_reader/RLI-ARDataTAB.txt https://cassini.marmot.org/RLI-ARDataTAB.txt

#Do a full extract from OverDrive just once a week to catch anything that doesn't
#get caught in the regular extract
DAYOFWEEK=$(date +"%u")
if [[ "${DAYOFWEEK}" -eq 7 ]]; then
	echo $(date +"%T") "Starting Overdrive fullReload." >> ${OUTPUT_FILE}
	cd /usr/local/vufind-plus/vufind/overdrive_api_extract/
	nice -n -10 java -server -XX:+UseG1GC -jar overdrive_extract.jar ${PIKASERVER} fullReload >> ${OUTPUT_FILE}
	echo $(date +"%T") "Completed Overdrive fullReload." >> ${OUTPUT_FILE}
fi

FILE=$(find /data/vufind-plus/${PIKASERVER}/marc/ -name fullexport.mrc -mtime -1 | sort -n | tail -1)

if [ -n "$FILE" ]
then
  #check file size
	FILE1SIZE=$(wc -c <"$FILE")
	if [ $FILE1SIZE -ge $MINFILE1SIZE ]; then

		echo "Latest export file is " $FILE >> ${OUTPUT_FILE}
		DIFF=$(($FILE1SIZE - $MINFILE1SIZE))
		PERCENTABOVE=$((100 * $DIFF / $MINFILE1SIZE))
		echo "The export file is $PERCENTABOVE (%) larger than the minimum size check." >> ${OUTPUT_FILE}

		#Validate the export
		cd /usr/local/vufind-plus/vufind/cron; java -server -XX:+UseG1GC -jar cron.jar ${PIKASERVER} ValidateMarcExport >> ${OUTPUT_FILE}

		#Full Regroup
		cd /usr/local/vufind-plus/vufind/record_grouping; java -server -XX:+UseG1GC -jar record_grouping.jar ${PIKASERVER} fullRegroupingNoClear >> ${OUTPUT_FILE}

		cd /usr/local/vufind-plus/vufind/reindexer; nice -n -3 java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} fullReindex >> ${OUTPUT_FILE}

		# Truncate Continuous Reindexing list of changed items
		cat /dev/null >| /data/vufind-plus/${PIKASERVER}/marc/changed_items_to_process.csv

		NEWLEVEL=$(($FILE1SIZE * 97 / 100))
		echo "" >> ${OUTPUT_FILE}
		echo "Based on today's export file, a new minimum filesize check level should be set to $NEWLEVEL" >> ${OUTPUT_FILE}

		# Wait 2 minutes for solr replication to finish; then delete the inactive solr indexes folders older than 48 hours
		# Note: Running in the full update because we know there is a freshly created index.
		sleep 2m
		find /data/vufind-plus/${PIKASERVER}/solr_searcher/grouped/ -name "index.*" -type d -mmin +2880 -exec rm -rf {} \; >> ${OUTPUT_FILE}

	else
		echo $FILE " size " $FILE1SIZE "is less than minimum size :" $MINFILE1SIZE "; Export was not moved to data directory, Full Regrouping & Full Reindexing skipped." >> ${OUTPUT_FILE}
	fi
else
	echo "Did not find a export file from the last 24 hours, Full Regrouping & Full Reindexing skipped." >> ${OUTPUT_FILE}
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

# Now that script is completed, remove the PID file
rm $PIDFILE

