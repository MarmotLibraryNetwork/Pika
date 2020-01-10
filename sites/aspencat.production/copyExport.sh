#!/bin/bash
# Copy Aspencat Extracts from ftp server
# runs after files are received on the ftp server
#-------------------------------------------------------------------------
# declare variables and constants
#-------------------------------------------------------------------------
REMOTE="10.1.2.7:/ftp"
LOCAL="/mnt/ftp"
DEST="/data/vufind-plus/aspencat.production/marc"
LOG="logger -t copyExport "

#-------------------------------------------------------------------------

$LOG "~> starting copyExport.sh"

#$LOG "~~ remove old deleted and updated marc record files"
#rm -f $DEST/ascc-catalog-deleted.* $DEST/ascc-catalog-updated.*
#$LOG "~~ exit code " $?
# Merging Process will move these to ../marc_backup pascal 5-9-2017

$LOG "~~ mount $REMOTE $LOCAL"
mount $REMOTE $LOCAL
$LOG "~~ exit code " $?

# Only grab the full export file if it is less that a day old.
FILE=$(find $LOCAL/aspencat/bywaterkoha/completeCollection/ -name "*.mrc.gz" -mtime -1|more -1)
# final slash helps limit to directory only; quotes around the name pattern is needed in some cases;
# piping through more -1 ensure only 1 file is used.
if [ -n "$FILE" ]; then
	$LOG "~~ unzip $FILE file to fullexport.mrc"
	gunzip -cv $FILE > $DEST/fullexport.mrc
	$LOG "~~ exit code " $?
fi


$LOG "~~ umount $LOCAL"
umount $LOCAL
$LOG "~~ exit code " $?

$LOG "~> finished copyExport.sh"

#-------------------------------------------------------------------------
#-- eof --
