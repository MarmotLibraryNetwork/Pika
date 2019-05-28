#!/bin/bash
# Mark Noble, Marmot Library Network
# James Staub, Nashville Public Library
# 20150218
# Script executes continuous re-indexing.
# Millennium 1.6_3

# CONFIGURATION
# PLEASE SET CONFLICTING PROCESSES AND PROHIBITED TIMES IN FUNCTION CALLS IN SCRIPT MAIN DO LOOP
# this version emails script output as a round finishes
EMAIL=root@amalthea.marmot.org
PIKASERVER=wcpl.production
OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/extract_and_reindex_output.log"

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

	# Do not run while the export from symphony is running to prevent inconsistencies with MARC records
	# export starts at 10 pm the file is copied to the FTP server at about 11:40
	#TODO verify this is correct
	hasConflicts=$(checkProhibitedTimes "21:50" "23:40")
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

	#Fetch partial updates from FTP server
	mount 10.1.2.7:/ftp/wcpl /mnt/ftp >> ${OUTPUT_FILE}
	#find /mnt/ftp/continuous_exports -maxdepth 1 -mmin -30 -name *.mrc| while FILES= read FILE; do
	#Above find is for test only. Copy any partial exports from the last 30 minutes because of the moving out the partials is only done in production

	find /mnt/ftp/continuous_exports -maxdepth 1 -name *.mrc| while FILES= read FILE; do
	#Above find is for production only. Copy any partial exports from the last 30 minutes
	# Note: the space after the equals is important in  "while FILES= read FILE;"

		if test "`find $FILE -mmin -1`"; then
			echo "$FILE was modified less than 1 minute ago, waiting to copy "
		else
	        cp $FILE /data/vufind-plus/${PIKASERVER}/marc_updates/ >> ${OUTPUT_FILE}
	#        echo "cp $FILE /data/vufind-plus/${PIKASERVER}/marc_updates/"

	#        # Move to processed (Production Only does this)
	        mv $FILE /mnt/ftp/continuous_exports/processed/ >> ${OUTPUT_FILE}
	#        echo "mv $FILE /mnt/ftp/continuous_exports/processed/"
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


	#export from overdrive
	#echo "Starting OverDrive Extract - `date`" >> ${OUTPUT_FILE}
	cd /usr/local/vufind-plus/vufind/overdrive_api_extract/
	nice -n -10 java -server -XX:+UseG1GC -jar overdrive_extract.jar ${PIKASERVER} >> ${OUTPUT_FILE}

	#run reindex
	#echo "Starting Reindexing - `date`" >> ${OUTPUT_FILE}
	cd /usr/local/vufind-plus/vufind/reindexer
	nice -n -5 java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} >> ${OUTPUT_FILE}
	checkForDBCrash $?


	# send notice of any issues
	sendEmail

		#end block
done
