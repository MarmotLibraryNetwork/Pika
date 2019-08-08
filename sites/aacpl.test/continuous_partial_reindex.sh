#!/bin/bash

set -a
EMAIL=root@dione
PIKASERVER=aacpl.test
set +a

# Kick-off Sierra Extract/Reindex loop
/usr/local/vufind-plus/sites/${PIKASERVER}/aacpl_symphony_continuous_reindex.sh &

# Kick-off Overdrive Extract/Reindex loop
/usr/local/vufind-plus/vufind/bash/overdrive_continuous_reindex.sh &
