#!/bin/bash
#
#  newuploads
#
#  Author: Steve Lindemann
#    Date: 15 Nov 2012
#
#-------------------------------------------------------------------------
# 15 Nov 12 - v0.0.1 - sml - create initial script
# 28 Nov 12 - v0.0.2 - sml - added $CC to mutt
#-------------------------------------------------------------------------

#-------------------------------------------------------------------------
# declare variables
#-------------------------------------------------------------------------

TO="root,lloyd@marmot.org,brandon@marmot.org"
SUBJECT='New uploads on sftp server'
TMPFILE='/tmp/newuploads'

#-------------------------------------------------------------------------
# main loop
#-------------------------------------------------------------------------

find /ftp -mtime -1 -type f -exec /bin/ls -Flh {} \; > $TMPFILE
mail -s "$SUBJECT" $TO < $TMPFILE
/bin/rm -f $TMPFILE

#-------------------------------------------------------------------------
#--- eof ---
