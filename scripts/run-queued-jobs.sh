#!/bin/sh

# This script is an EXAMPLE ONLY. You must copy this into your system's script
# execution framework (init.d, service etc) and run it as a daemon AFTER
# editing the relevant paths for your system roots. 

SILVERSTRIPE_ROOT=/path/to/silverstripe
SILVERSTRIPE_CACHE=/path/to/silverstripe-cache

inotifywait --monitor --event attrib --format "php $SILVERSTRIPE_ROOT/sapphire/cli-script.php dev/tasks/ProcessJobQueueTask job=%f" $SILVERSTRIPE_CACHE/queuedjobs | sh