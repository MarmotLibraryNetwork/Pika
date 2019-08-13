#!/bin/bash

set -a
EMAIL=root@rhea
PIKASERVER=aspencat.test
set +a

# Kick-off Sierra Extract/Reindex loop
/usr/local/vufind-plus/vufind/bash/koha_continuous_reindex.sh &

# Kick-off Overdrive Extract/Reindex loop
/usr/local/vufind-plus/vufind/bash/overdrive_continuous_reindex.sh &
