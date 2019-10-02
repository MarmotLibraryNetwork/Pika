#!/bin/sh
# Copies needed solr files to the server specified as a command line argument
if [ -z "$1" ]; then
  echo ""
  echo "Usage:  $0 {Pika Sites Directory Name for this instance}"
  echo "eg: $0 pika.test"
  echo ""
  exit 1
else
	rm -f /data/vufind-plus/$1/solr_master/lib/*
	cp -r solr_master /data/vufind-plus/$1
	rm -f /data/vufind-plus/$1/solr_searcher/lib/*
	cp -r solr_searcher /data/vufind-plus/$1
	exit 0
fi
