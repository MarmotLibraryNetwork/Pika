#!/bin/bash

PIKASERVER=marmot.test

#source "/usr/local/vufind-plus/vufind/bash/checkConflicts.sh"

# Kick-off Sierra Extract/Reindex loop
/usr/local/vufind-plus/sites/${PIKASERVER}/sierra_continuous_reindex.sh &

# Kick-off Overdrive Extract/Reindex loop
/usr/local/vufind-plus/sites/${PIKASERVER}/overdrive_continuous_reindex.sh &