#!/bin/bash
#Retrieve marc records from the FTP server
mount 10.1.2.7:/ftp/sacramento/sierra /mnt/ftp
# sftp.marmot.org server

#copy production extract
FILE1=$(find /mnt/ftp/ -name *.mrc -mtime -1 | sort -n | tail -1)
cp --preserve=timestamps --update /${FILE1} /data/vufind-plus/sacramento.production/marc/fullexport.mrc
#Delete full exports older than 3 days
find /mnt/ftp/ -name *.mrc -mtime +3 -delete

umount /mnt/ftp

