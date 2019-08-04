<?php

if (($QUEUED_JOBS_DIR = basename(dirname(__FILE__))) != 'queuedjobs') {
    die("The queued jobs module must be installed in /queuedjobs, not $QUEUED_JOBS_DIR");
}
// SilverStripe 3.7 and PHP 7.2 compatibility
if (!class_exists('SS_Object')) {
    class_alias('Object', 'SS_Object');
}
