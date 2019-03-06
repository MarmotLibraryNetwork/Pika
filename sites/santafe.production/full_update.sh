#!/bin/bash

# full_update.sh
# Mark Noble, Marmot Library Network
# James Staub, Nashville Public Library
# 20150219
# Script handles all aspects of a full index including extracting data from other systems.
# Should be called once per day from crontab
# For Pika discovery partners using Millennium 2011 1.6_3

# this version emails script output as a round finishes
EMAIL=pikaservers@marmot.org
PIKASERVER=santafe.production
PIKADBNAME=pika
OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/full_update_output.log"

MINFILE1SIZE=$((335000000))

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
function checkConflictingProcesses() {
	#Check to see if the conflict exists.
	countConflictingProcesses=$(ps aux | grep -v sudo | grep -c "$1")
	countConflictingProcesses=$((countConflictingProcesses-1))

	let numInitialConflicts=countConflictingProcesses
	#Wait until the conflict is gone.
	until ((${countConflictingProcesses} == 0)); do
		countConflictingProcesses=$(ps aux | grep -v sudo | grep -c "$1")
		countConflictingProcesses=$((countConflictingProcesses-1))
		#echo "Count of conflicting process" $1 $countConflictingProcesses
		sleep 300
	done
	#Return the number of conflicts we found initially.
	echo ${numInitialConflicts};
}

# Prohibited time ranges - for, e.g., ILS backup
# JAMES is currently giving all Nashville prohibited times a ten minute buffer
function checkProhibitedTimes() {
	start=$(date --date=$1 +%s)
	stop=$(date --date=$2 +%s)
	NOW=$(date +%H:%M:%S)
	NOW=$(date --date=$NOW +%s)

	hasConflicts=0
	if (( $start < $stop ))
	then
		if (( $NOW > $start && $NOW < $stop ))
		then
			#echo "Sleeping:" $(($stop - $NOW))
			sleep $(($stop - $NOW))
			hasConflicts = 1
		fi
	elif (( $start > $stop ))
	then
		if (( $NOW < $stop ))
		then
			sleep $(($stop - $NOW))
			hasConflicts = 1
		elif (( $NOW > $start ))
		then
			sleep $(($stop + 86400 - $NOW))
			hasConflicts = 1
		fi
	fi
	echo ${hasConflicts};
}

#First make sure that we aren't running at a bad time.  This is really here in case we run manually.
# since the run in cron is timed to avoid sensitive times.
# santafe has no prohibited times (yet)
#checkProhibitedTimes "23:50" "00:40"

#Check for any conflicting processes that we shouldn't do a full index during.
#Since we aren't running in a loop, check in the order they run.
checkConflictingProcesses "sierra_export.jar ${PIKASERVER}"
checkConflictingProcesses "overdrive_extract.jar ${PIKASERVER}"
checkConflictingProcesses "reindexer.jar ${PIKASERVER}"

# Back-up Solr Master Index
mysqldump ${PIKADBNAME} grouped_work_primary_identifiers > /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql
sleep 2m
tar -czf /data/vufind-plus/${PIKASERVER}/solr_master_backup.tar.gz /data/vufind-plus/${PIKASERVER}/solr_master/grouped/index/ /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql >> ${OUTPUT_FILE}
rm /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql

#truncate the output file so you don't spend a week debugging an error from a week ago!
: > $OUTPUT_FILE;

#Restart Solr
cd /usr/local/vufind-plus/sites/${PIKASERVER}; ./${PIKASERVER}.sh restart

## Side Loads ##

# OneClick digital Marc Updates
#/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} santafe/oneclickdigital oneclickdigital/santafe >> ${OUTPUT_FILE}
# santa fe no longer subscribes. pascal 6/11/2018

#RBdigital Magazines
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} santafe/rbdigitalmagazines rbdigitalmagazines/santafe >> ${OUTPUT_FILE}

#Extract from Hoopla
cd /usr/local/vufind-plus/vufind/cron;./GetHooplaFromMarmot.sh >> ${OUTPUT_FILE}

#Extract Lexile Data
cd /data/vufind-plus/; curl --remote-name --remote-time --silent --show-error --compressed --time-cond /data/vufind-plus/lexileTitles.txt https://cassini.marmot.org/lexileTitles.txt

#Extract AR Data
cd /data/vufind-plus/accelerated_reader; curl --remote-name --remote-time --silent --show-error --compressed --time-cond /data/vufind-plus/accelerated_reader/RLI-ARDataTAB.txt https://cassini.marmot.org/RLI-ARDataTAB.txt

#Santa Fe does not use Volume Records
#Get the updated volume information
#cd /usr/local/vufind-plus/vufind/cron;
#nice -n -10 java -jar cron.jar ${PIKASERVER} ExportSierraData >> ${OUTPUT_FILE}


#Do a full extract from OverDrive just once a week to catch anything that doesn't
#get caught in the regular extract
DAYOFWEEK=$(date +"%u")
if [ "${DAYOFWEEK}" -eq 6 ];
then
	cd /usr/local/vufind-plus/vufind/overdrive_api_extract/
	nice -n -10 java -jar overdrive_extract.jar ${PIKASERVER} fullReload >> ${OUTPUT_FILE}
fi


#Extract from ILS
#Copy extracts from FTP Server
mount 10.1.2.7:/ftp/santafe /mnt/ftp
FILE1=$(find /mnt/ftp/ -name pika*.mrc -mtime -1 | sort -n | tail -1)

if [ -n "$FILE1" ]
then
		FILE1SIZE=$(wc -c <"$FILE1")
		if [ $FILE1SIZE -ge $MINFILE1SIZE ]; then

			echo "Latest file is " $FILE1 >> ${OUTPUT_FILE}
			DIFF=$(($FILE1SIZE - $MINFILE1SIZE))
			PERCENTABOVE=$((100 * $DIFF / $MINFILE1SIZE))
			echo "The export file is $PERCENTABOVE (%) larger than the minimum size check." >> ${OUTPUT_FILE}

			cp $FILE1 /data/vufind-plus/${PIKASERVER}/marc/fullexport.mrc

			#Delete full exports older than a week
			find /mnt/ftp/ -name pika*.mrc -mtime +7 -delete
			umount /mnt/ftp

			#Validate the export
			cd /usr/local/vufind-plus/vufind/cron; java -server -XX:+UseG1GC -jar cron.jar ${PIKASERVER} ValidateMarcExport >> ${OUTPUT_FILE}

			#Full Regroup
			cd /usr/local/vufind-plus/vufind/record_grouping; java -server -XX:+UseG1GC -Xmx2G -jar record_grouping.jar ${PIKASERVER} fullRegroupingNoClear >> ${OUTPUT_FILE}

			#Full Reindex
			cd /usr/local/vufind-plus/vufind/reindexer; java -server -XX:+UseG1GC -Xmx2G -jar reindexer.jar ${PIKASERVER} fullReindex >> ${OUTPUT_FILE}

			# Truncate Continous Reindexing list of changed items
			cat /dev/null >| /data/vufind-plus/${PIKASERVER}/marc/changed_items_to_process.csv

		NEWLEVEL=$(($FILE1SIZE * 97 / 100))
		echo "" >> ${OUTPUT_FILE}
		echo "Based on today's export file, a new minimum filesize check level should be set to $NEWLEVEL" >> ${OUTPUT_FILE}

		else
			umount /mnt/ftp
			echo $FILE1 " size " $FILE1SIZE "is less than minimum size :" $MINFILE1SIZE "; Export was not moved to data directory." >> ${OUTPUT_FILE}
		fi

else
	umount /mnt/ftp
	echo "Did not find a Sierra export file from the last 24 hours, Full Regrouping & Full Reindexing skipped." >> ${OUTPUT_FILE}
fi


#Remove all ITEM_UPDATE_EXTRACT_PIKA files so continuous_partial_reindex can start fresh
find /data/vufind-plus/${PIKASERVER}/marc -name 'ITEM_UPDATE_EXTRACT_PIKA*' -delete

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

