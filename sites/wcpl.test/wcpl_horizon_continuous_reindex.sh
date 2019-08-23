#!/bin/bash

FTPSERVER_IP=10.1.2.7
OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/horizon_continuous_reindex_output.log"

source "/usr/local/vufind-plus/vufind/bash/checkConflicts.sh"
source "/usr/local/vufind-plus/vufind/bash/continuousFunctions.sh"

while true
do
	# Make sure we are not running a Full Record Group/Reindex process
	hasConflicts=$(checkConflictingProcesses "full_update.sh" 30)
	#If we did get a conflict, restart the loop to make sure that all tests run
	if (($? != 0)); then
		continue
	fi

	#####
	# Start of the actual indexing code
	#####

	# reset the output file each round
	: > $OUTPUT_FILE;

	#Fetch partial updates from FTP server
	mount ${FTPSERVER_IP}:/ftp/wcpl /mnt/ftp >> ${OUTPUT_FILE}
	find /mnt/ftp/continuous_exports -maxdepth 1 -mmin -30 -name *.mrc| while FILES= read FILE; do
		#Above find is for test only. Copy any partial exports from the last 30 minutes because of the moving out the partials is only done in production

		#find /mnt/ftp/continuous_exports -maxdepth 1 -name *.mrc| while FILES= read FILE; do
		#Above find is for production only. Copy any partial exports from the last 30 minutes
		# Note: the space after the equals is important in  "while FILES= read FILE;"
		if test "`find $FILE -mmin -1`"; then
#			echo "$FILE was modified less than 1 minute ago, waiting to copy " >> ${OUTPUT_FILE}
			continue
		else
			cp $FILE /data/vufind-plus/${PIKASERVER}/marc_updates/ >> ${OUTPUT_FILE}

			#	# Move to processed (Production Only does this)
			#	mv $FILE /mnt/ftp/continuous_exports/processed/ >> ${OUTPUT_FILE}
			#	echo "mv $FILE /mnt/ftp/continuous_exports/processed/"
		fi
	done
	umount /mnt/ftp >> ${OUTPUT_FILE}

	if test "`find /data/vufind-plus/${PIKASERVER}/marc_updates/ -name "*.mrc" -mtime +1`"; then
		echo "Partial Exports older than a day found in marc_updates folder. Deleting." >> ${OUTPUT_FILE}
		echo "" >> ${OUTPUT_FILE}
		find /data/vufind-plus/${PIKASERVER}/marc_updates/ -name "*.mrc" -mtime +1 >> ${OUTPUT_FILE}

		#Delete any partial exports older than a day
		find /data/vufind-plus/${PIKASERVER}/marc_updates/ -name "*.mrc" -mtime +1 -delete >> ${OUTPUT_FILE}
	fi

	#merge the changes with the full extract
	cd /usr/local/vufind-plus/vufind/horizon_export/
	java -server -XX:+UseG1GC -jar horizon_export.jar ${PIKASERVER} >> ${OUTPUT_FILE}


	# Pause if another reindexer is running; check in 10 second intervals
	paused=$(checkConflictingProcesses "reindexer.jar" 10)
	# push output into a variable to avoid so it doesn't echo out of the script

	#run reindex
	cd /usr/local/vufind-plus/vufind/reindexer
	nice -n -5 java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} >> ${OUTPUT_FILE}
	checkForDBCrash $?

	# send notice of any issues
	sendEmail "Horizon Continuous Extract and Reindexing - ${PIKASERVER}"

		#end block
done
