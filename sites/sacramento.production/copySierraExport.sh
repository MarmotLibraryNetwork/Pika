#!/bin/bash
#Retrieve marc records from the FTP server
mount 10.1.2.7:/ftp/sacramento/sierra /mnt/ftp
# sftp.marmot.org server

#copy production extract
FILE1=$(find /mnt/ftp/ -name *.mrc -mtime -1 | sort -n | tail -1)
cp --preserve=timestamps --update /mnt/ftp/${FILE1} /data/vufind-plus/sacramento.production/marc/fullexport.mrc
umount /mnt/ftp

