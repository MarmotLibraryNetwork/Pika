#!/bin/bash
################################################################################
# Tests to see if Tomcat is running, if not it starts it and sends an email
#
#
EMAIL="root@marmot.org,brandon@marmot.org,pascal@marmot.org"

NUMBEROFTOMCATSRUNNING=$( ps -ef|grep tomcat|grep -v grep|wc -l)
if [ ${NUMBEROFTOMCATSRUNNING} -eq 1 ];then

	PID="$(ps x | grep /usr/local/fedora/tomcat/bin |grep -v grep | cut -d ' ' -f1)"
	if [ ${PID} -gt 0 ]; then
	##################EMAIL root@marmot.org that an error occured
	#                SUBJECT="**HEARTBEAT** Tomcat on Islandora Alive"
	#                EMAIL="root@marmot.org,mark@marmot.org,pascal@marmot.org"
	#                EMAILMESSAGE="Tomcat on Islandora is still alive."
	#                mailx -s "$SUBJECT" "$EMAIL" <<< $EMAILMESSAGE
		exit 0
	else
	  cd /usr/local/fedora/tomcat/bin; ./startup.sh

		##################EMAIL root@marmot.org that an error occured
	  SUBJECT="**RECOVERY** Tomcat on Islandora Stopped"
	  EMAILMESSAGE="Tomcat on Islandora stopped but was restarted."
	  mailx -s "$SUBJECT" "$EMAIL" <<< $EMAILMESSAGE
	fi
else
	SUBJECT="**WARNING** Too many Tomcats on Islandora"
	EMAILMESSAGE="There are ${NUMBEROFTOMCATSRUNNING} running on Islandora"
	mailx -s "$SUBJECT" "$EMAIL" <<< $EMAILMESSAGE
fi