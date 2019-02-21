#!/bin/sh

# This script is for moving a marc full export file from on the ftp server to data directory on the pika server

if [[ $# -ne 3 ]]; then
	echo "To use, add the ftp source directory for the first parameter, the data directory destination as the second parameter, and the pika server sitename as the third parameter."
	echo "$0 source destination pika_server"
	echo "eg: $0 hoopla hoopla pika.test"
else

	# Source & Destination set by command line options
	SOURCE=$1
	DESTINATION=$2
	PIKASERVER=$3

	LOG="logger -t $0"
	# tag logging with script name and command line options

	REMOTE="10.1.2.7:/ftp"
	LOCAL="/mnt/ftp"

	$LOG "~~ mount $REMOTE $LOCAL"
	mount $REMOTE $LOCAL

	if [ -d "$LOCAL/$SOURCE/" ]; then
		if [ -d "/data/vufind-plus/$DESTINATION/marc/" ]; then
			if [ $(ls -1A "$LOCAL/$SOURCE/" | grep -i .mrc | wc -l) -gt 0 ]; then
				# only do copy command if there are files present to move

				FILE1=$(ls -rt1 $LOCAL/$SOURCE/*|grep -i .mrc$|tail -1)
				# Get only the latest file, and must end with .mrc
				if [ -n "$FILE1" ]; then
					if [ $(ls -1A "$LOCAL/$SOURCE/" | grep -i .mrc | wc -l) -gt 1 ]; then
						echo "There is more that 1 MARC file present in $LOCAL/$SOURCE/ during $0 process."
					fi

					$LOG "~~ Copy fullexport marc file."
					$LOG "~~ cp --update $FILE1 /data/vufind-plus/$DESTINATION/marc/fullexport.mrc"
					cp --update -v "$FILE1" /data/vufind-plus/$DESTINATION/marc/fullexport.mrc

					if [ $? -ne 0 ]; then
						$LOG "~~ Copying $FILE1 file failed."
						echo "Copying $FILE1 file failed."
					else
						$LOG "~~ $FILE1 file was copied."
						echo "$FILE1 file was copied."
						if [[ ! $PIKASERVER =~ ".test" ]]; then
							# Only move marc files to processed folder for production servers
							# The test server MUST run before production or the file won't exist
							if [ ! -d "$LOCAL/$SOURCE/processed/" ]; then
								mkdir $LOCAL/$SOURCE/processed/
							fi
							echo "Moving $FILE1 on ftp server to processed directory."
							mv "$FILE1" $LOCAL/$SOURCE/processed/
						fi
					fi

				else

			# Process compressed MARC files
			if [ $(ls -1A "$LOCAL/$SOURCE/" | grep .mrc.gz$ | wc -l) -gt 0 ] ; then
					if [ $(ls -1A "$LOCAL/$SOURCE/" | grep .mrc.gz$ | wc -l) -gt 1 ]; then
						echo "There is more that 1 MARC file present in $LOCAL/$SOURCE/ during $0 process."
					fi
				# if they are gzipped files copy and unzip
				$LOG "~~ Gzip files found."
				echo "~~ Gzip files found."

				$LOG "~~ cp $LOCAL/$SOURCE/*.mrc.gz $DESTINATION/"
				cp -v $LOCAL/$SOURCE/*.mrc.gz /data/vufind-plus/$DESTINATION/marc/

				if [ $? -ne 0 ]; then
					$LOG "~~ Copying $SOURCE marc files failed."
					echo "Copying $SOURCE marc files failed."
				else
					$LOG "~~ $SOURCE gzipped marc files were copied. Decompressing."
					echo "$SOURCE gzipped marc files were copied. Decompressing."

					$LOG "~~ gunzip -v $DESTINATION/*.mrc.gz"
					gunzip -vf /data/vufind-plus/$DESTINATION/marc/*.mrc.gz > /data/vufind-plus/$DESTINATION/marc/fullexport.mrc
					if [ $? -eq 1 ];then
						$LOG "~~ Decompression failed."
						echo "Decompression failed."
					else

						if [[ ! $PIKASERVER =~ ".test" ]]; then
							# Only move marc files to processed folder for production servers
							# The test server MUST run before production or the file won't exist
							if [ ! -d "$LOCAL/$SOURCE/processed/" ]; then
								mkdir $LOCAL/$SOURCE/processed/
							fi
							echo "Moving files on ftp server to processed directory."
							mv -v $LOCAL/$SOURCE/*.mrc.gz $LOCAL/$SOURCE/processed/
						fi
					fi
				fi
			fi

				fi
			fi
		else
			echo "Path /data/vufind-plus/$DESTINATION/marc/ doesn't exist."
		fi
	else
		echo "Path $LOCAL/$SOURCE/ doesn't exist."
	fi

	# Make sure we undo the mount every time it is mounted in the first place.
	$LOG "~~ umount $LOCAL"
	umount $LOCAL

	$LOG "Finished $0 $*"

fi