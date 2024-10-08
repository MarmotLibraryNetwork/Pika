#!/bin/bash

FTPSERVER_IP=10.1.2.7
OUTPUT_FILE="/var/log/pika/${PIKASERVER}/symphony_continuous_reindex_output.log"

source "/usr/local/pika/vufind/bash/checkConflicts.sh"
source "/usr/local/pika/vufind/bash/continuousFunctions.sh"

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
	mount ${FTPSERVER_IP}:/ftp/aacpl /mnt/ftp >> ${OUTPUT_FILE}
	find /mnt/ftp/symphony-updates -maxdepth 1 -mmin -60 -name *.mrc| while FILES= read FILE; do
		#Above find is for test only. Copy any partial exports from the last 30 minutes because of the moving out the partials is only done in production

		#find /mnt/ftp/symphony-updates -maxdepth 1 -name *.mrc| while FILES= read FILE; do
		#Above find is for production only. Copy any partial exports from the last 30 minutes
		# Note: the space after the equals is important in  "while FILES= read FILE;"
		if test "`find $FILE -mmin -1`"; then
#			echo "$FILE was modified less than 1 minute ago, waiting to copy " >> ${OUTPUT_FILE}
			continue
		else
			cp --update --preserve=timestamps $FILE /data/pika/${PIKASERVER}/marc_updates/ >> ${OUTPUT_FILE}
		fi
	done

	#Get orders file from the FTP server
	cp --update --preserve=timestamps /mnt/ftp/PIKA-onorderfile.txt /data/pika/${PIKASERVER}/marc/ >> ${OUTPUT_FILE}

	umount /mnt/ftp >> ${OUTPUT_FILE}

	#Get holds files from Google Drive
	cd /data/pika/${PIKASERVER}/marc
#	wget -q "https://drive.google.com/uc?export=download&id=0B_xqNQMfUrAzanJUZkNXekgtU2s" -O "Pika_Hold_Periodicals.csv"
#	wget -q "https://drive.google.com/uc?export=download&id=0B_xqNQMfUrAzNGJrajJzQWs3ZGs" -O "Pika_Holds.csv"
	wget -q "https://drive.google.com/uc?export=download&id=1OOS8p8cZcWoHPVnt1jQlpcR9NwRnjlam" -O "Pika_Hold_Periodicals.csv"
	wget -q "https://drive.google.com/uc?export=download&id=1aT8jXgDd3jG0K3xTYYcFi0hgBRWW6fj2" -O "Pika_Holds.csv"

	cd /usr/local/pika/vufind/symphony_export/
	java -server -jar symphony_export.jar  ${PIKASERVER} >> ${OUTPUT_FILE}


	# Pause if another reindexer is running; check in 10 second intervals
	paused=$(checkConflictingProcesses "reindexer.jar" 10)
	# push output into a variable to avoid so it doesn't echo out of the script

	#run reindex
	cd /usr/local/pika/vufind/reindexer
	nice -n -5 java -server -XX:+UseG1GC -jar reindexer.jar ${PIKASERVER} >> ${OUTPUT_FILE}
	checkForDBCrash $?

	# send notice of any issues
	sendEmail "Symphony Continuous Extract and Reindexing - ${PIKASERVER}"

		#end block
done
