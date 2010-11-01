<?php

if (($QUEUED_JOBS_DIR = basename(dirname(__FILE__))) != 'queuedjobs') {
	die("The queued jobs module must be installed in /queuedjobs, not $QUEUED_JOBS_DIR");
}