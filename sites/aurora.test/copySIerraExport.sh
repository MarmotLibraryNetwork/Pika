#!/bin/bash

#Extract from ILS
#Copy extracts from FTP Server
mount 10.1.2.7:/ftp/aurora/sierra /mnt/ftp
FILE1=$(find /mnt/ftp/ -name fullexport*.mrc -mtime -1 | sort -n | tail -1)

if [ -n "$FILE1" ]; then
		FILE1SIZE=$(wc -c <"$FILE1")
		if [ $FILE1SIZE -ge $MINFILE1SIZE ]; then

			echo "Latest file is " $FILE1 >> ${OUTPUT_FILE}
			DIFF=$(($FILE1SIZE - $MINFILE1SIZE))
			PERCENTABOVE=$((100 * $DIFF / $MINFILE1SIZE))
			echo "The export file is $PERCENTABOVE (%) larger than the minimum size check." >> ${OUTPUT_FILE}
			NEWLEVEL=$(($FILE1SIZE * 97 / 100))
			echo "Based on today's export file, a new minimum filesize check level should be set to $NEWLEVEL" >> ${OUTPUT_FILE}
			echo "" >> ${OUTPUT_FILE}

			cp $FILE1 /data/vufind-plus/${PIKASERVER}/marc/fullexport.mrc

			#Delete full exports older than 3 days (production pika only)
#			find /mnt/ftp/ -name fullexport*.mrc -mtime +3 -delete

		#Validate the export
		cd /usr/local/vufind-plus/vufind/cron; java -server -XX:+UseG1GC -jar cron.jar ${PIKASERVER} ValidateMarcExport >> ${OUTPUT_FILE}

		else
			echo $FILE1 " size " $FILE1SIZE "is less than minimum size :" $MINFILE1SIZE "; Export was not moved to data directory." >> ${OUTPUT_FILE}
		fi
fi
umount /mnt/ftp