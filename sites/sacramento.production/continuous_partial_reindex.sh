#!/bin/bash

set -a
EMAIL=root@odysseus
PIKASERVER=sacramento.production
USE_SIERRA_API_EXTRACT=1
set +a

# Kick-off Sierra Extract/Reindex loop
/usr/local/vufind-plus/vufind/bash/sierra_continuous_reindex.sh &

# Kick-off Overdrive Extract/Reindex loop
/usr/local/vufind-plus/vufind/bash/overdrive_continuous_reindex.sh &
