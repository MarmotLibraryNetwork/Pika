#!/bin/bash

OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/sierra_continuous_reindex_output.log"

source "/usr/local/vufind-plus/vufind/bash/checkConflicts.sh"
source "/usr/local/vufind-plus/vufind/bash/continuousFunctions.sh"

while true
do
	#####
	# Check to make sure this is a good time to run.
	#####

	# Make sure we are not running a Full Record Group/Reindex process
	hasConflicts=$(checkConflictingProcesses "full_update.sh" 30)
	#If we did get a conflict, restart the loop to make sure that all tests run
	if (($? != 0)); then
		continue
	fi

##TODO: Does this matter with the sierra api extract now?
#	# Do not run while the export from Sierra is running to prevent inconsistencies with MARC records
#	# export starts at 10 pm the file is copied to the FTP server at about 11:40
#	hasConflicts=$(checkProhibitedTimes "21:50" "23:40")
#	#If we did get a conflict, restart the loop to make sure that all tests run
#	if (($? != 0)); then
#		continue
#	fi

	#####
	# Start of the actual indexing code
	#####

	# reset the output file each round
	: > $OUTPUT_FILE;

	if [ $USE_SIERRA_API_EXTRACT -eq 1 ]; then
		#export from sierra (items, holds, and orders)
		#echo "Starting Sierra Export - `date`" >> ${OUTPUT_FILE}
		cd /usr/local/vufind-plus/vufind/sierra_export_api/
		nice -n -10 java -server -XX:+UseG1GC -jar sierra_export_api.jar ${PIKASERVER} >> ${OUTPUT_FILE}
	else
		#export from sierra (items, holds, and orders)
		#echo "Starting Sierra Export - `date`" >> ${OUTPUT_FILE}
		cd /usr/local/vufind-plus/vufind/sierra_export/
		nice -n -10 java -server -XX:+UseG1GC -jar sierra_export.jar ${PIKASERVER} >> ${OUTPUT_FILE}
	fi

	# Pause if another reindexer is running; check in 10 second intervals
	paused=$(checkConflictingProcesses "reindexer.jar" 10)
	# push output into a variable to avoid so it doesn't echo out of the script

	#run reindex
	cd /usr/local/vufind-plus/vufind/reindexer
	nice -n -5 java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} >> ${OUTPUT_FILE}
	checkForDBCrash $?

	# send notice of any issues
	sendEmail "Sierra Continuous Extract and Reindexing - ${PIKASERVER}"

		#end block
done
