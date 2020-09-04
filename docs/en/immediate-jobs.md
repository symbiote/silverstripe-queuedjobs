# Immediate 

## Overview

Queued jobs can be executed "immediately", which happens through
a PHP shutdown function by default ([details](index.md#immediate-jobs)).
Below are more robust ways to achieve immediate execution,
in addition to the standard queueing behaviour.

When using these approaches, remember to disable the PHP shutdown behaviour:

```yml
Symbiote\QueuedJobs\Services\QueuedJobService:
  use_shutdown_function: false
```

## inotifywait

The `inotifywait` system utility monitors a folder for changes (by
default this is SILVERSTRIPE_CACHE_DIR/queuedjobs) and triggers the `ProcessJobQueueTask`
with `job=$filename` as the argument. An example script is in `queuedjobs/scripts` that will run
inotifywait and then call the task when a new job is ready to run.

```sh
#!/bin/sh

# This script is an EXAMPLE ONLY. You must copy this into your system's script
# execution framework (init.d, service etc) and run it as a daemon AFTER
# editing the relevant paths for your system roots. 

SILVERSTRIPE_ROOT=/path/to/silverstripe
SILVERSTRIPE_CACHE=/path/to/silverstripe-cache

inotifywait --monitor --event attrib --format "php $SILVERSTRIPE_ROOT/vendor/bin/sake dev/tasks/ProcessJobQueueTask job=%f" $SILVERSTRIPE_CACHE/queuedjobs | sh
```

You can also turn this into an `init.d` service:

```
#!/bin/bash
#
#	/etc/init.d/queue_processor
#
#	Service that watches for changes in queued_jobs targets.  Defined targets will spawn an instance
#	of inotitywait.
#
#	Currently only tested on Centos5.6 (x86_64)
#
# 	Depends: inotify-tools (tested with Centos Package inotify-tools-3.14-1.el5)
#
#	Usage:  - Ensure that inotify-tools is installed.
#		- Silverstripe cache paths are expected to be in $webroot/silverstripe-cache rather than /tmp
#		- SILVERSTRIPE_ROOT is a space separated Array of Silvestripe installations
#
#		- Copy this script to /etc/init.d/queue_processor
#		- Update the SILVERSTRIPE_ROOT to reflect your installations
#		- execute /etc/init.d/queue_processor start

PATH=/bin:/usr/bin:/sbin:/usr/sbin
export PATH

# Source function library.
. /etc/init.d/functions

# Define a couple of base variables

# list all the silverstripe root directories that you want monitored here
SILVERSTRIPE_ROOT=(/var/www/deployer/ /home/other/public-html/deployer)


start() {
	echo -n "Starting queue_processor: "
	for PATH in ${SILVERSTRIPE_ROOT[@]};
	do
	INOTIFY_OPTS="--monitor --event attrib -q"
	INOTIFY_ARGS="--format 'php ${PATH}/vendor/bin/sake dev/tasks/ProcessJobQueueTask job=%f' ${PATH}/silverstripe-cache/queuedjobs | /bin/sh"
			daemon --user apache /usr/bin/inotifywait ${INOTIFY_OPTS} ${INOTIFY_ARGS} &
			/bin/touch /var/lock/subsys/queue_processor
		done

			return 0
}

stop() {
	echo -n "Shutting down queue_processor: "
	killproc inotifywait
	rm -f /var/lock/subsys/queue_processor
	return 0
}

case "$1" in
    start)
	start
	;;
    stop)
	stop
	;;
    restart)
    	stop
	start
	;;
    *)
	echo "Usage: queue_processor {start|stop|reload|}"
	exit 1
	;;
esac
exit $?
```

## lsyncd

Similar concept to `inotifywait`, but with the `lsyncd` system utility. 

The following is an example config `/etc/lsyncd.conf` 

```
-- Queue Processor configuration, typically placed in /etc/lsyncd.conf 

settings = {
    logfile    = "/var/log/lsyncd/lsyncd.log",
    statusFile = "/var/run/lsyncd.status",
    nodaemon   = true,
}

-- Define the command and path for the each system being monitored here, where webuser is the user your webserver
-- runs as
runcmd = "/sbin/runuser webuser -c \"/usr/bin/php /var/www/sitepath/framework/cli-script.php dev/tasks/ProcessJobQueueTask job=\"$1\" /var/www/sitepath/framework/silverstripe-cache/queuedjobs\""

site_processor = {
    onCreate = function(event)
    log("Normal", "got an onCreate Event")
       spawnShell(event, runcmd, event.basename)
    end,
}

sync{site_processor, source="/var/www/sitepath/silverstripe-cache/queuedjobs"}
```
