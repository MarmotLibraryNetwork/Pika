#!/bin/bash

OUTPUT_FILE="/var/log/pika/${PIKASERVER}/overdrive_continuous_reindex_output.log"

source "/usr/local/pika/vufind/bash/checkConflicts.sh"
source "/usr/local/pika/vufind/bash/continuousFunctions.sh"

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

	#export from overdrive
	cd /usr/local/pika/vufind/overdrive_api_extract/
	nice -n -10 java -server -XX:+UseG1GC -jar overdrive_extract.jar ${PIKASERVER} >> ${OUTPUT_FILE}

	# Pause if another reindexer is running; check in 11 second intervals
	# (this is 1 sec longer than the sierra continuous so that process will take priority if both finished at the same time.)
	paused=$(checkConflictingProcesses "reindexer.jar" 11)
	# push output into a variable to avoid so it doesn't echo out of the script

	#run reindex
	cd /usr/local/pika/vufind/reindexer
	nice -n -5 java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} >> ${OUTPUT_FILE}
	checkForDBCrash $?

	# send notice of any issues
	sendEmail "Overdrive Continuous Extract and Reindexing - ${PIKASERVER}"

		#end block
done
