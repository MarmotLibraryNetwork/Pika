#!/bin/bash
#
#  purge_ftp_dir
#
#  clear out old files from /ftp directory structure
#  keep last 90 days worth of files
#
#  Author: Steve Lindemann
#    Date: 24 Jun 15
#
#  add the following to roots crontab:
#     # purge /ftp directory at 1.30am daily
#     30 1 * * * /root/cron/purge_ftp_dir
#
#-------------------------------------------------------------------------
# 24 Jun 15 - sml - create initial script
#-------------------------------------------------------------------------
#-------------------------------------------------------------------------
# declare variables
#-------------------------------------------------------------------------
LOG="logger -t purge_ftp_dir "

#-------------------------------------------------------------------------
# main loop
#-------------------------------------------------------------------------
$LOG ">> Purge ftp directory starting <<"
$LOG "~> Keeping last 90 days worth of files"

#find /ftp -maxdepth 6 -mtime +90 -type f ! -iname '.*' -exec /bin/rm {} \;
find /ftp -maxdepth 6 -mtime +90 -type f ! -iname '.*' ! -name authorized_keys ! -name empty.txt -exec /bin/rm {} \;
# added to exclude ssh keys for scp. pascal 4-21-2017
# adjust the maxdepth from 4 to 5. pascal 7-26-2018
# adjust the maxdepth from 5 to 6. pascal 10-08-2018

EXITCODE=$?
$LOG "~> Exit Code $EXITCODE"

$LOG ">> Purge ftp directory complete <<"
exit 0
#-------------------------------------------------------------------------
#--- eof ---
