#!/bin/bash
# Mark Noble, Marmot Library Network
# James Staub, Nashville Public Library
# Script executes continuous re-indexing.

# CONFIGURATION
# PLEASE SET CONFLICTING PROCESSES AND PROHIBITED TIMES IN FUNCTION CALLS IN SCRIPT MAIN DO LOOP
# this version emails script output as a round finishes
EMAIL=root@titan
PIKASERVER=marmot.test
#OUTPUT_FILE="/var/log/vufind-plus/${PIKASERVER}/continuous_partial_reindex_output.log"
USE_SIERRA_API_EXTRACT=1
# set to USE_SIERRA_API_EXTRACT to 1 enable

source "/usr/local/vufind-plus/vufind/bash/checkConflicts.sh"

# Kick-off Sierra Extract/Reindex loop
/usr/local/vufind-plus/sites/${PIKASERVER}/sierra_continuous_reindex.sh &

# Kick-off Overdrive Extract/Reindex loop
/usr/local/vufind-plus/sites/${PIKASERVER}/overdrive_continuous_reindex.sh &