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
SOLR_VERSION="8.7.0"
SOLR_INDEXER_NAME=solr_master
SOLR_SEARCHER_NAME=solr_searcher

CURRENT_DIR="$(cd "$(dirname "$0")" && pwd -P)"


if [[ $# = 1 ]];then
#	mkdir /var/${SOLR_INDEXER_NAME} /var/${SOLR_INDEXER_NAME}/logs /data/vufind-plus/${PIKASERVER}/${SOLR_INDEXER_NAME}
#	mkdir /var/${SOLR_SEARCHER_NAME} /var/${SOLR_SEARCHER_NAME}/logs /data/vufind-plus/${PIKASERVER}/${SOLR_SEARCHER_NAME}
#	mkdir /var/${SOLR_INDEXER_NAME} /var/${SOLR_INDEXER_NAME}/logs
#	mkdir /var/${SOLR_SEARCHER_NAME} /var/${SOLR_SEARCHER_NAME}/logs
	mkdir /var/${SOLR_INDEXER_NAME} /var/log/vufind-plus/${PIKASERVER}/${SOLR_INDEXER_NAME}/
	mkdir /var/${SOLR_SEARCHER_NAME} /var/log/vufind-plus/${PIKASERVER}/${SOLR_SEARCHER_NAME}/

	# /opt/solr is the SOLR installation directory
	# /var/solr is the SOLR data directory
	# /etc/default SOLR environment variable file

	#Add links for where we want data and logs to actually live
	ln -s /var/log/vufind-plus/${PIKASERVER}/${SOLR_INDEXER_NAME}/ /var/${SOLR_INDEXER_NAME}/logs
	ln -s /var/log/vufind-plus/${PIKASERVER}/${SOLR_SEARCHER_NAME}/ /var/${SOLR_SEARCHER_NAME}/logs

	# Install data directories
	cp -r "${CURRENT_DIR}/../data_dir_setup/${SOLR_INDEXER_NAME}" "/data/vufind-plus/${PIKASERVER}"
	cp -r "${CURRENT_DIR}/../data_dir_setup/${SOLR_SEARCHER_NAME}" "/data/vufind-plus/${PIKASERVER}"

	# Add links to solr standard data directory to ours
	ln -s /data/vufind-plus/${PIKASERVER}/${SOLR_INDEXER_NAME}/ /var/${SOLR_INDEXER_NAME}/data
	ln -s /data/vufind-plus/${PIKASERVER}/${SOLR_SEARCHER_NAME}/ /var/${SOLR_SEARCHER_NAME}/data

	chown solr /var/${SOLR_INDEXER_NAME}/logs
	chown solr /var/${SOLR_SEARCHER_NAME}/logs

 	read -p "Proceed with SOLR installation?" -n 1 -r
	echo    # (optional) move to a new line
	if [[ $REPLY =~ ^[Yy]$ ]]; then

		#Download SOLR library
		cd ~
		wget https://mirrors.sonic.net/apache/lucene/solr/${SOLR_VERSION}/solr-${SOLR_VERSION}.tgz
		# this is a mirror site


		#TODO: confirm hash
		#wget https://downloads.apache.org/lucene/solr/${SOLR_VERSION}/solr-${SOLR_VERSION}-src.tgz.asc

		#Extract installation script
		tar xzf solr-${SOLR_VERSION}.tgz solr-${SOLR_VERSION}/bin/install_solr_service.sh --strip-components=2

		# Install indexing solr core
		./install_solr_service.sh solr-${SOLR_VERSION}.tgz -u solr -s ${SOLR_INDEXER_NAME} -p 8180

		# Install searching solr core
		./install_solr_service.sh solr-${SOLR_VERSION}.tgz -u solr -s ${SOLR_SEARCHER_NAME} -p 8080

		#TODO: modify bin/solr.in.sh to set the SOLR_HEAP variable
	fi

else
  echo ""
  echo "Usage:  $0 {PikaServer}"
  echo "eg: $0 marmot.test "
  echo ""
  exit 1
fi
#
#--eof--