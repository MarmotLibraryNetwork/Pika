#!/bin/bash

OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/koha_continuous_reindex_output.log"

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

	#export from koha (items, holds)
	cd /usr/local/vufind-plus/vufind/koha_export/
	nice -n -10 java -server -XX:+UseG1GC -jar koha_export.jar ${PIKASERVER} >> ${OUTPUT_FILE}

	# Pause if another reindexer is running; check in 10 second intervals
	paused=$(checkConflictingProcesses "reindexer.jar" 10)
	# push output into a variable to avoid so it doesn't echo out of the script

	#run reindex
	cd /usr/local/vufind-plus/vufind/reindexer
	nice -n -5 java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} >> ${OUTPUT_FILE}
	checkForDBCrash $?

	# send notice of any issues
	sendEmail "Koha Continuous Extract and Reindexing - ${PIKASERVER}"

		#end block
done
