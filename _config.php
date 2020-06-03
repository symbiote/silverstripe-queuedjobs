<?php

// Ensure compatibility with PHP 7.2 ("object" is a reserved word),
// with SilverStripe 3.6 (using Object) and SilverStripe 3.7 (using SS_Object)
if (!class_exists('SS_Object')) class_alias('Object', 'SS_Object');

if (($QUEUED_JOBS_DIR = basename(dirname(__FILE__))) != 'queuedjobs') {
	die("The queued jobs module must be installed in /queuedjobs, not $QUEUED_JOBS_DIR");
}