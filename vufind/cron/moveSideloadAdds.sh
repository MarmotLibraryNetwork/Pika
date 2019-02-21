#!/bin/bash

# This script is for moving marc files that are adds or deletes from on the ftp server to data directory on the pika server

if [[ $# -ne 3 ]]; then
	echo "To use, add the ftp source directory for the first parameter, the data directory destination as the second parameter, and the pika server sitename as the third parameter."
	echo "$0 source destination pika_server"
	echo "eg: $0 hoopla hoopla pika.test"
else

	# Source & Destination set by command line options
	SOURCE=$1
	DESTINATION=/data/vufind-plus/$2/marc
	PIKASERVER=$3

	LOG="logger -t $0"
	# tag logging with script name and command line options

	REMOTE="10.1.2.7:/ftp"
	LOCAL="/mnt/ftp"

	$LOG "~~ mount $REMOTE $LOCAL"
	mount $REMOTE $LOCAL

	if [ -d "$LOCAL/$SOURCE/" ]; then
		if [ -d "$DESTINATION/" ]; then

			# Process regular MARC files
			if [ $(ls -1A "$LOCAL/$SOURCE/" | grep .mrc$ | wc -l) -gt 0 ] ; then
			# only do copy command if there are files present to move

				$LOG "~~ Copy sideload adds/deletes marc file(s)."
				$LOG "~~ cp -v $LOCAL/$SOURCE/*.mrc $DESTINATION/"
				cp -v $LOCAL/$SOURCE/*.mrc $DESTINATION/

				if [ $? -ne 0 ]; then
					$LOG "~~ Copying $SOURCE marc files failed."
					echo "Copying $SOURCE marc files failed."
				else
					$LOG "~~ $SOURCE marc files were copied."
					echo "$SOURCE marc files were copied."

					if [[ ! $PIKASERVER =~ ".test" ]]; then
						# Only move marc files to processed folder for production servers
						# The test server MUST run before production or the file won't exist
						if [ ! -d "$LOCAL/$SOURCE/processed/" ]; then
							mkdir $LOCAL/$SOURCE/processed/
						fi
						echo "Moving files on ftp server to processed directory."
						mv -v $LOCAL/$SOURCE/*.mrc $LOCAL/$SOURCE/processed/
					fi
				fi

			fi

			# Process compressed MARC files
			if [ $(ls -1A "$LOCAL/$SOURCE/" | grep .mrc.gz$ | wc -l) -gt 0 ] ; then
				# if they are gzipped files copy and unzip
				$LOG "~~ Gzip files found."
				echo "~~ Gzip files found."

				$LOG "~~ cp $LOCAL/$SOURCE/*.mrc.gz $DESTINATION/"
				cp -v $LOCAL/$SOURCE/*.mrc.gz $DESTINATION/

				if [ $? -ne 0 ]; then
					$LOG "~~ Copying $SOURCE marc files failed."
					echo "Copying $SOURCE marc files failed."
				else
					$LOG "~~ $SOURCE gzipped marc files were copied. Decompressing."
					echo "$SOURCE gzipped marc files were copied. Decompressing."

					$LOG "~~ gunzip -v $DESTINATION/*.mrc.gz"
					gunzip -v $DESTINATION/*.mrc.gz
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


		else
			echo "Path $DESTINATION/ doesn't exist."
		fi

	else
		echo "Path $LOCAL/$SOURCE/ doesn't exist."
	fi

	# Make sure we undo the mount every time it is mounted in the first place.
	$LOG "~~ umount $LOCAL"
	umount $LOCAL

	$LOG "Finished $0 $*"

fi