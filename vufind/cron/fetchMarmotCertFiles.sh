#!/bin/bash
#
# author Pascal Brammeier
#
#-------------------------------------------------------------------------
#  Fetch certificate files from central location and place in standard place
#  on a web server
#-------------------------------------------------------------------------


CERTIFICATEDIRECTORY="data/wildcard.marmot.org"
REMOTE="10.1.2.7:/$CERTIFICATEDIRECTORY"
LOCAL="/mnt/ftp"

LOG="logger -t $0"
# tag logging with script name and command line options

$LOG "~~ mount $REMOTE $LOCAL"
mount $REMOTE $LOCAL

if [ $? -eq 0 ]; then
	if [ -d "$LOCAL/" ]; then
		if [ -d "/etc/pki/tls/certs/" ]; then
			if [ -d "/etc/pki/tls/private/" ]; then
				cp --update --preserve=timestamps $LOCAL/wildcard.marmot.org.crt /etc/pki/tls/certs/wildcard.marmot.org.crt
				cp --update --preserve=timestamps $LOCAL/rapidssl.crt /etc/pki/tls/certs/rapidssl.crt
				cp --update --preserve=timestamps $LOCAL/wildcard.marmot.org.key /etc/pki/tls/private/wildcard.marmot.org.key
			else
				echo "Path /etc/pki/tls/private/ doesn't exist."
			fi
		else
			echo "Path /etc/pki/tls/certs doesn't exist."
		fi
	else
		echo "Path $LOCAL/$CERTIFICATEDIRECTORY/ doesn't exist."
	fi
else
	echo "File Mounting failed"
fi
# Make sure we undo the mount every time it is mounted in the first place.
$LOG "~~ umount $LOCAL"
umount $LOCAL

$LOG "Finished $0 $*"
