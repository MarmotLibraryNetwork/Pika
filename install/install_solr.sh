#!/bin/bash
#
# Copyright (C) 2021  Marmot Library Network
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <https://www.gnu.org/licenses/>.
#

#  Download and install SOLR for Pika
#
# author Pascal Brammeier
#

PIKASERVER=$1
SOLR_HEAP_SIZE=$2
SOLR_VERSION="8.8.2"
SOLR_INDEXER_NAME=solr_master
SOLR_SEARCHER_NAME=solr_searcher

CURRENT_DIR="$(cd "$(dirname "$0")" && pwd -P)"

# NOTE:
# /opt/solr is the SOLR installation directory
# /var/solr is the SOLR data directory
# /etc/default SOLR environment variable file


if [[ $# = 2 ]] || [[ $# = 3 ]];then
	if [[ $# = 3 ]];then
		SOLR_VERSION=$3
	fi
	echo "Please turn off any existing Solr installation before proceeding.  Current indexes will be moved."
	read -p "Proceed with SOLR installation?" -n 1 -r
	echo    # (optional) move to a new line
	if [[ $REPLY =~ ^[Yy]$ ]]; then

		# Create installation directories for Pika
		mkdir /var/${SOLR_INDEXER_NAME} /var/log/vufind-plus/${PIKASERVER}/${SOLR_INDEXER_NAME}/
		mkdir /var/${SOLR_SEARCHER_NAME} /var/log/vufind-plus/${PIKASERVER}/${SOLR_SEARCHER_NAME}/

		#Add links for where we want data and logs to actually live
		ln -s /var/log/vufind-plus/${PIKASERVER}/${SOLR_INDEXER_NAME}/ /var/${SOLR_INDEXER_NAME}/logs
		ln -s /var/log/vufind-plus/${PIKASERVER}/${SOLR_SEARCHER_NAME}/ /var/${SOLR_SEARCHER_NAME}/logs

		# Move old data directories
		mv /data/vufind-plus/${PIKASERVER}/${SOLR_INDEXER_NAME} /data/vufind-plus/${PIKASERVER}/${SOLR_INDEXER_NAME}_old_delete_me
		mv /data/vufind-plus/${PIKASERVER}/${SOLR_SEARCHER_NAME} /data/vufind-plus/${PIKASERVER}/${SOLR_SEARCHER_NAME}_old_delete_me

		# Install data directories
		cp -r "${CURRENT_DIR}/../data_dir_setup/${SOLR_INDEXER_NAME}" "/data/vufind-plus/${PIKASERVER}"
		cp -r "${CURRENT_DIR}/../data_dir_setup/${SOLR_SEARCHER_NAME}" "/data/vufind-plus/${PIKASERVER}"

		# Add links to solr standard data directory to ours
		ln -s /data/vufind-plus/${PIKASERVER}/${SOLR_INDEXER_NAME}/ /var/${SOLR_INDEXER_NAME}/data
		ln -s /data/vufind-plus/${PIKASERVER}/${SOLR_SEARCHER_NAME}/ /var/${SOLR_SEARCHER_NAME}/data

		read -p "Directories set up complete. Proceed with SOLR installation?" -n 1 -r
		echo    # (optional) move to a new line
		if [[ $REPLY =~ ^[Yy]$ ]]; then

			#Download SOLR library
			cd ~
			wget https://mirrors.sonic.net/apache/lucene/solr/${SOLR_VERSION}/solr-${SOLR_VERSION}.tgz
			# this is a mirror site

			#TODO: confirm hash
			#wget https://downloads.apache.org/lucene/solr/${SOLR_VERSION}/solr-${SOLR_VERSION}-src.tgz.asc

			read -p "Download Solr package. Proceed with SOLR installation?" -n 1 -r
			echo    # (optional) move to a new line
			if [[ $REPLY =~ ^[Yy]$ ]]; then

				#Extract installation script
				tar xzf solr-${SOLR_VERSION}.tgz solr-${SOLR_VERSION}/bin/install_solr_service.sh --strip-components=2

				# Install indexing solr core
				./install_solr_service.sh solr-${SOLR_VERSION}.tgz -u solr -s ${SOLR_INDEXER_NAME} -p 8180 -n

				# move environment file back to original name (undoes a change made by the install_solr_service script
				SOLR_INSTALL_DIR="/opt/solr-${SOLR_VERSION}/"
				mv "$SOLR_INSTALL_DIR/bin/solr.in.sh.orig" "$SOLR_INSTALL_DIR/bin/solr.in.sh"

				# Install searching solr core
				./install_solr_service.sh solr-${SOLR_VERSION}.tgz -u solr -s ${SOLR_SEARCHER_NAME} -p 8080 -n

				chown solr /var/${SOLR_INDEXER_NAME}/logs
				chown solr /var/${SOLR_SEARCHER_NAME}/logs
				chown solr --recursive /data/vufind-plus/${PIKASERVER}/${SOLR_INDEXER_NAME}
				chown solr --recursive /data/vufind-plus/${PIKASERVER}/${SOLR_SEARCHER_NAME}

				# Set the replicator port for searcher
				echo 'SOLR_OPTS="$SOLR_OPTS -Dsolr.masterport=8180"' >> /etc/default/${SOLR_SEARCHER_NAME}.in.sh

				# Set solr heap size
					echo "SOLR_HEAP=\"${SOLR_HEAP_SIZE}\"" >> /etc/default/${SOLR_SEARCHER_NAME}.in.sh
					echo "SOLR_HEAP=\"${SOLR_HEAP_SIZE}\"" >> /etc/default/${SOLR_INDEXER_NAME}.in.sh
			fi
		fi
	fi

else
  echo ""
  echo "Usage:  $0 {PikaServer} {Solr Heap Size (include g or m)} {Solr Version (optional) default:${SOLR_VERSION}}"
  echo "eg: $0 marmot.test 2g"
  echo ""
  exit 1
fi
#
#--eof--