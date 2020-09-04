# Immediate runs through lsyncd

By default the system will execute 'immediate' jobs when the current web request ends, which to an extent removes any of the benefits of using a background processor. However, to make sure these immediate jobs execute, well, immediately, there needs to be a mechanism for detecting their creation, and launching the background processing script. To aid this, whenever a job is created an empty file will be created in $TEMP_DIR/queuedjobs (typically project-name/silverstripe-cache/queuedjobs or /tmp/silverstripe-instance/queuedjobs). Using lsyncd (or something similar for windows), you can then trigger the processor script to actually execute the job. 

The following is an example config /etc/lsyncd.conf 


	-- Queue Processor configuration, typically placed in /etc/lsyncd.conf 
	-- Remember to set QueuedJobService::$use_shutdown_function = false; in local.conf.php

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
