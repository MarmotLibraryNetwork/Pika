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
		cp -r solr_master /data/vufind-plus/$1
		cp -r solr_searcher /data/vufind-plus/$1
		exit 0
	f1
fi
