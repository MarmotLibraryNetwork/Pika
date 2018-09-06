#!/usr/bin/env bash
#Retrieve marc records from the FTP server
mount 10.1.2.7:/ftp/sacramento/sierra /mnt/ftp
# sftp.marmot.org server

#copy production extract
cp --preserve=timestamps --update /mnt/ftp/fullexport.mrc /data/vufind-plus/sacramento.production/marc/fullexport.mrc
umount /mnt/ftp

