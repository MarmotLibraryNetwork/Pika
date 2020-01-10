#!/bin/bash
# Mark Noble, Marmot Library Network
# James Staub, Nashville Public Library
# 20150218
# Script executes continuous re-indexing.
# Millennium 1.6_3

# CONFIGURATION
# PLEASE SET CONFLICTING PROCESSES AND PROHIBITED TIMES IN FUNCTION CALLS IN SCRIPT MAIN DO LOOP
# this version emails script output as a round finishes
EMAIL=pikaservers@marmot.org
PIKASERVER=anythink.production
OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/continuous_partial_reindex_output.log"

source "/usr/local/vufind-plus/vufind/bash/checkConflicts.sh"

function sendEmail() {
	# add any logic wanted for when to send the emails here. (eg errors only)
	FILESIZE=$(stat -c%s ${OUTPUT_FILE})
	if [[ ${FILESIZE} > 0 ]]
	then
			# send mail
			mail -s "Continuous Extract and Reindexing - ${PIKASERVER}" $EMAIL < ${OUTPUT_FILE}
	fi
}

function checkForDBCrash() {
# Pass this function the exit code ($?) of pika java programs.
# If the exit code is zero that indicates that the pika database is down or unreachable,
# so we will pause our operations here
	EXITCODE=$1
	if [ $EXITCODE -eq 2 ];then
		sleep 180
		echo "Received database connection lost error, paused for 180 seconds" >> ${OUTPUT_FILE}
	fi
}

while true
do
	#####
	# Check to make sure this is a good time to run.
	#####

	# Make sure we are not running a Full Record Group/Reindex process
	hasConflicts=$(checkConflictingProcesses "full_update.sh")
	#If we did get a conflict, restart the loop to make sure that all tests run
	if (($? != 0)); then
		continue
	fi

	#####
	# Start of the actual indexing code
	#####

	#truncate the file
	: > $OUTPUT_FILE;

	#echo "Starting new extract and index - `date`" > ${OUTPUT_FILE}
	# reset the output file each round

	#merge the changes with the full extract
	cd /usr/local/vufind-plus/vufind/horizon_export/
	java -server -XX:+UseG1GC -jar horizon_export.jar ${PIKASERVER} >> ${OUTPUT_FILE}

	#export from overdrive
	#echo "Starting OverDrive Extract - `date`" >> ${OUTPUT_FILE}
	cd /usr/local/vufind-plus/vufind/overdrive_api_extract/
	java -server -XX:+UseG1GC -jar overdrive_extract.jar ${PIKASERVER} >> ${OUTPUT_FILE}

	#run reindex
	#echo "Starting Reindexing - `date`" >> ${OUTPUT_FILE}
	cd /usr/local/vufind-plus/vufind/reindexer
	java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} >> ${OUTPUT_FILE}

	# add any logic wanted for when to send the emails here. (eg errors only)
	FILESIZE=$(stat -c%s ${OUTPUT_FILE})
	if [[ ${FILESIZE} > 0 ]]
	then
			# send mail
			mail -s "Continuous Extract and Reindexing - ${PIKASERVER}" $EMAIL < ${OUTPUT_FILE}
	fi

	#pause for a bit to make sure we don't run too frequently
	sleep 5m

		#end block
done
