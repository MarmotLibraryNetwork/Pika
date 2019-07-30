#!/bin/bash
# Mark Noble, Marmot Library Network
# James Staub, Nashville Public Library
# Script executes continuous re-indexing.

# CONFIGURATION
# PLEASE SET CONFLICTING PROCESSES AND PROHIBITED TIMES IN FUNCTION CALLS IN SCRIPT MAIN DO LOOP
# this version emails script output as a round finishes
EMAIL=root@titan
PIKASERVER=marmot.test
#OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/continuous_partial_reindex_output.log"
USE_SIERRA_API_EXTRACT=1
# set to USE_SIERRA_API_EXTRACT to 1 enable

source "/usr/local/vufind-plus/vufind/bash/checkConflicts.sh"

function sendEmail() {
	# add any logic wanted for when to send the emails here. (eg errors only)
	FILESIZE=$(stat -c%s ${OUTPUT_FILE})
	if [[ ${FILESIZE} -gt 0 ]]
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

# Kick-off Sierra Extract/Reindex loop
./sierra_continuous_reindex.sh &

# Kick-off Overdrive Extract/Reindex loop
./overdrive_continuous_reindex.sh &