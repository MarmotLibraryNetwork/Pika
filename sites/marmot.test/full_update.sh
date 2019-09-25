#!/bin/bash
# Script handles all aspects of a full index including extracting data from other systems.
# Should be called once per day.  Will interrupt partial reindexing.
#
# At the end of the index will email users with the results.
EMAIL=root@titan
PIKASERVER=marmot.test
PIKADBNAME=pika
OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/full_update_output.log"
USE_SIERRA_API_EXTRACT=1

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

#echo "Finished checking conflicting process"

#truncate the output file so you don't spend a week debugging an error from a week ago!
: > $OUTPUT_FILE;

# Back-up Solr Master Index
mysqldump ${PIKADBNAME} grouped_work_primary_identifiers > /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql
sleep 2m
tar -czf /data/vufind-plus/${PIKASERVER}/solr_master_backup.tar.gz /data/vufind-plus/${PIKASERVER}/solr_master/grouped/index/ /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql >> ${OUTPUT_FILE}
rm /data/vufind-plus/${PIKASERVER}/grouped_work_primary_identifiers.sql

echo "Finished index backups" >> ${OUTPUT_FILE}

#Restart Solr
cd /usr/local/vufind-plus/sites/${PIKASERVER}; ./${PIKASERVER}.sh restart

echo "Finished solr restart" >> ${OUTPUT_FILE}

	#If a fresh full export file for the ILS has been sent, it should get processed
	/usr/local/vufind-plus/sites/${PIKASERVER}/copySierraExport.sh >> ${OUTPUT_FILE}
#	echo "Finished export copy" >> ${OUTPUT_FILE}


#Extract from Hoopla
cd /usr/local/vufind-plus/vufind/cron;./HOOPLA.sh ${PIKASERVER} >> ${OUTPUT_FILE}

## Side Loads ##

# Ebrary Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} ccu/ebrary ebrary/ccu >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} western/ebrary ebrary/western >> ${OUTPUT_FILE}

#Adams Ebrary DDA files
/usr/local/vufind-plus/vufind/cron/moveFullExport.sh adams/ebrary/DDA ebrary/adams ${PIKASERVER} >> ${OUTPUT_FILE}

# CCU Alexander Street Press Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} ccu/alexanderStreetPress alexanderstreetpress/ccu >> ${OUTPUT_FILE}

# Ebsco Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} adams/ebsco ebsco/adams >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} ccu/ebsco ebsco/ccu >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} cmc/ebsco ebsco/cmc >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} fortlewis_sideload/EBSCO_Academic ebsco/fortlewis >> ${OUTPUT_FILE}

# CCU Biblioboard Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} ccu/biblioboard biblioboard/ccu >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} bemis/biblioboard biblioboard/bemis >> ${OUTPUT_FILE}

# CMC Overdrive sideload
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} cmc/overdrive overdrive/cmc >> ${OUTPUT_FILE}

# Englewood Axis 360 Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} englewood/axis360 axis360/englewood >> ${OUTPUT_FILE}

# Marmot RBDigital (magazine) Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} marmot/rbdigital zinio >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} marmot/rbdigitalBackIssues zinio/backIssues >> ${OUTPUT_FILE}

# Marmot RBDigital (magazine) Marc Updates Test Sites
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER}  marmot/rbdigtial_active_test rbdigital_active_test/marmot >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} marmot/rbdigtial_backissue_test rbdigital_backissue_test/marmot >> ${OUTPUT_FILE}

# Western Oxford Reference Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} western/oxfordReference oxfordReference/western >> ${OUTPUT_FILE}

# Western Springer Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} western/springer springer/western >> ${OUTPUT_FILE}

# CCU Gale Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} ccu/gale gale/ccu >> ${OUTPUT_FILE}

# Kanopy Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} western/kanopy kanopy/western >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} budwerner/kanopy kanopy/budwerner >> ${OUTPUT_FILE}

# SD51 Mackin VIA Marc Updates
#/usr/local/vufind-plus/vufind/cron/moveFullExport.sh sd51/mackinvia/mvcp mackinvia/mvcp >> ${OUTPUT_FILE}
#/usr/local/vufind-plus/vufind/cron/moveFullExport.sh sd51/mackinvia/mvem mackinvia/mvem >> ${OUTPUT_FILE}
#/usr/local/vufind-plus/vufind/cron/moveFullExport.sh sd51/mackinvia/mvrr mackinvia/mvrr >> ${OUTPUT_FILE}
#/usr/local/vufind-plus/vufind/cron/moveFullExport.sh sd51/mackinvia/mvtm mackinvia/mvtm >> ${OUTPUT_FILE}

# Learning Express Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} budwerner/learning_express learning_express/steamboatsprings >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} garfield/learning_express learning_express/garfield >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} mesa/learning_express learning_express/mesa >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} vail/learning_express learning_express/vail >> ${OUTPUT_FILE}

#Films On Demand
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} cmc/filmsondemand filmsondemand/cmc >> ${OUTPUT_FILE}

#Federal Government Documents Marc Updates (Western)
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} western/federalGovDocs federal_gov_docs/western >> ${OUTPUT_FILE}

# Colorado State Gov Docs Marc Updates
#/usr/local/vufind-plus/vufind/cron/moveFullExport.sh  marmot/coloGovDocs colorado_gov_docs >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} marmot/coloGovDocs colorado_gov_docs/marmot >> ${OUTPUT_FILE}

# Naxos
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} fortlewis_sideload/naxos naxos/fortlewis >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} fortlewis_sideload/naxos_jazz naxos/jazz/fortlewis >> ${OUTPUT_FILE}
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} ccu/naxos naxos/ccu >> ${OUTPUT_FILE}

# Mango Language
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} mesa/mango mango/mesa >> ${OUTPUT_FILE}

# Docuseek Marc Updates
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} cmc/docuseek docuseek/cmc >> ${OUTPUT_FILE}

# Creative Bug
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} bemis/creative_bug creative_bug/bemis >> ${OUTPUT_FILE}

# Elsevier
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} western/elsevier elsevier/western >> ${OUTPUT_FILE}

# Gale In Context (Mesa)
/usr/local/vufind-plus/vufind/cron/fetch_sideload_data.sh ${PIKASERVER} mesa/gale_virtual gale_virtual/mesa >> ${OUTPUT_FILE}

#echo "finished sideload fetching"

#Extracts for sideloaded eContent; settings defined in config.pwd.ini [Sideload]
cd /usr/local/vufind-plus/vufind/cron; ./sideload.sh ${PIKASERVER}

# Not needed on marmot test site
##Extract Lexile Data
#cd /data/vufind-plus/; curl --remote-name --remote-time --silent --show-error --compressed --time-cond /data/vufind-plus/lexileTitles.txt https://cassini.marmot.org/lexileTitles.txt
#
##Extract AR Data
#cd /data/vufind-plus/accelerated_reader; curl --remote-name --remote-time --silent --show-error --compressed --time-cond /data/vufind-plus/accelerated_reader/RLI-ARDataTAB.txt https://cassini.marmot.org/RLI-ARDataTAB.txt


#Do a full extract from OverDrive just once a week to catch anything that doesn't
#get caught in the regular extract
DAYOFWEEK=$(date +"%u")
if [[ "${DAYOFWEEK}" -eq 7 ]]; then
	echo $(date +"%T") "Starting Overdrive fullReload." >> ${OUTPUT_FILE}
	cd /usr/local/vufind-plus/vufind/overdrive_api_extract/
	nice -n -10 java -server -XX:+UseG1GC -jar overdrive_extract.jar ${PIKASERVER} fullReload >> ${OUTPUT_FILE}
	echo $(date +"%T") "Completed Overdrive fullReload." >> ${OUTPUT_FILE}
fi

#Note, no need to extract from Lexile for this server since it is the master

#Validate the export
cd /usr/local/vufind-plus/vufind/cron; java -server -XX:+UseG1GC -jar cron.jar ${PIKASERVER} ValidateMarcExport >> ${OUTPUT_FILE}

#Full Regroup
cd /usr/local/vufind-plus/vufind/record_grouping; java -server -XX:+UseG1GC -jar record_grouping.jar ${PIKASERVER} fullRegroupingNoClear >> ${OUTPUT_FILE}

#Full Reindex - since this takes so long, just run the full index once a week and let Sierra Export keep it up to date the rest of the time. 
#if [ "${DAYOFWEEK}" -eq 5 ]; then
	cd /usr/local/vufind-plus/vufind/reindexer; java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} fullReindex >> ${OUTPUT_FILE}
#else
#	echo "Not running full re-index today, only a round of regular reindexing" >> ${OUTPUT_FILE}
#	cd /usr/local/vufind-plus/vufind/reindexer; nice -n -3 java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} >> ${OUTPUT_FILE}
#fi

# Truncate Continuous Reindexing list of changed items
cat /dev/null >| /data/vufind-plus/${PIKASERVER}/marc/changed_items_to_process.csv

# Wait 2 minutes for solr replication to finish; then delete the inactive solr indexes folders older than 48 hours
# Note: Running in the full update because we know there is a freshly created index.
sleep 2m
find /data/vufind-plus/${PIKASERVER}/solr_searcher/grouped/ -name "index.*" -type d -mmin +2880 -exec rm -rf {} \; >> ${OUTPUT_FILE}

# Clean-up Solr Logs
find /usr/local/vufind-plus/sites/default/solr/jetty/logs -name "solr_log_*" -mtime +7 -delete
find /usr/local/vufind-plus/sites/default/solr/jetty/logs -name "solr_gc_log_*" -mtime +7 -delete

#Restart Solr
cd /usr/local/vufind-plus/sites/${PIKASERVER}; ./${PIKASERVER}.sh restart

# Check that the complete DPLA feed file is up to date
OLDDPLAFEED=$(find /usr/local/vufind-plus/vufind/web -name "dplaFeed.json" -mtime +30)
if [ -n "$OLDDPLAFEED" ]
then
	echo "The DPLA feed file is older than 30 days : " >> ${OUTPUT_FILE}
	echo "$OLDDPLAFEED" >> ${OUTPUT_FILE}
fi


#Email results
FILESIZE=$(stat -c%s ${OUTPUT_FILE})
if [[ ${FILESIZE} > 0 ]]
then
	# send mail
	mail -s "Full Extract and Reindexing - ${PIKASERVER}" $EMAIL < ${OUTPUT_FILE}
fi

# Now that script is completed, remove the PID file
rm $PIDFILE

