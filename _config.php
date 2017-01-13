<?php

if (($queuedJobsDir = basename(dirname(__FILE__))) != 'queuedjobs') {
    die("The queued jobs module must be installed in /queuedjobs, not {$queuedJobsDir}");
}
