#!/bin/bash

set -a
EMAIL=root@tethys.marmot.org
PIKASERVER=wcpl.test
set +a

# Kick-off Sierra Extract/Reindex loop
/usr/local/vufind-plus/vufind/bash/wcpl_horizon_continuous_reindex.sh &

# Kick-off Overdrive Extract/Reindex loop
/usr/local/vufind-plus/vufind/bash/overdrive_continuous_reindex.sh &
