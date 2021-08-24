#!/bin/sh
# Copies needed solr files to the server specified as a command line argument
if [ -z "$1" ]; then
  echo ""
  echo "Usage:  $0 {Pika Sites Directory Name for this instance}"
  echo "eg: $0 pika.test"
  echo ""
  exit 1
else
	echo "Please make sure existing Solr engines are off."
	read -p "Proceed with SOLR configuration updates?" -n 1 -r
	echo    # (optional) move to a new line
	if [[ $REPLY =~ ^[Yy]$ ]]; then
		PIKASERVER=$1
		CURRENT_DIR="$(cd "$(dirname "$0")" && pwd -P)"
		cp -vr ${CURRENT_DIR}/solr_master /data/vufind-plus/${PIKASERVER}
		cp -vr ${CURRENT_DIR}/solr_searcher /data/vufind-plus/${PIKASERVER}
		exit 0
	fi
fi
