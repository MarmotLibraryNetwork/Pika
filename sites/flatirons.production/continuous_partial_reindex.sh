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
PIKASERVER=flatirons.production
ILSSERVER=nell.boulderlibrary.org
OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/continuous_partial_reindex_output.log"

# Check for conflicting processes currently running
function checkConflictingProcesses() {
	#Check to see if the conflict exists.
	countConflictingProcesses=$(ps aux | grep -v sudo | grep -c "$1")
	#subtract one to get rid of our grep command
	countConflictingProcesses=$((countConflictingProcesses-1))

	let numInitialConflicts=countConflictingProcesses
	#Wait until the conflict is gone.
	until ((${countConflictingProcesses} == 0)); do
		countConflictingProcesses=$(ps aux | grep -v sudo | grep -c "$1")
		#subtract one to get rid of our grep command
		countConflictingProcesses=$((countConflictingProcesses-1))
		#echo "Count of conflicting process" $1 $countConflictingProcesses
		sleep 300
	done
	#Return the number of conflicts we found initially.
	echo ${numInitialConflicts};
}

# Prohibited time ranges - for, e.g., ILS backup
function checkProhibitedTimes() {
	start=$(date --date=$1 +%s)
	stop=$(date --date=$2 +%s)
	NOW=$(date +%H:%M:%S)
	NOW=$(date --date=$NOW +%s)

	let hasConflicts=0
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

while true
do
	#####
	# Check to make sure this is a good time to run.
	#####
	hasConflicts=$(checkProhibitedTimes "01:00" "02:45")
	#If we did get a conflict, restart the loop to make sure that all tests run
	if (($? != 0)); then
		continue
	fi

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

	#export from sierra
	cd /usr/local/vufind-plus/vufind/sierra_export/;
	java -server -XX:+UseG1GC -jar sierra_export.jar ${PIKASERVER} >> ${OUTPUT_FILE}

	#export from overdrive
	#echo "Starting OverDrive Extract - `date`" >> ${OUTPUT_FILE}
	cd /usr/local/vufind-plus/vufind/overdrive_api_extract/
	java -server -XX:+UseG1GC -jar overdrive_extract.jar ${PIKASERVER} >> ${OUTPUT_FILE}

	#run reindex
	#echo "Starting Reindexing - `date`" >> ${OUTPUT_FILE}
	cd /usr/local/vufind-plus/vufind/reindexer
	java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} >> ${OUTPUT_FILE}
	checkForDBCrash $?


	# send notice of any issues
	sendEmail

		#end block
done
