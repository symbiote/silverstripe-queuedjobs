<?php

namespace Symbiote\QueuedJobs\Services;

use AsyncPHP\Doorman\Manager\ProcessManager as BaseProcessManager;

/**
 * Class ProcessManager
 *
 * customise shell command to allow child tasks to persist even after manager process is terminated
 * this lets the started jobs to finish properly in case the management process terminates
 * fore example there are no more jobs to start or queue is paused
 *
 * @package Symbiote\QueuedJobs\Services
 */
class ProcessManager extends BaseProcessManager
{
    /**
     * @param string $binary
     * @param string $worker
     * @param string $stdout
     * @param string $stderr
     * @return string
     */
    protected function getCommand($binary, $worker, $stdout, $stderr) // phpcs:ignore SlevomatCodingStandard.TypeHints
    {
        // Nohup is short for “No Hangups.” It’s not a command that you run by itself.
        // Nohup is a supplemental command that tells the Linux system not to stop another command once it has started.
        // That means it’ll keep running until it’s done, even if the user that started it logs out
        // Nohup is used here because the queue management process runs in a loop and it's starting new child processes
        // which are running jobs
        // It's possible to have the situation when queue management process terminates as it's no longer needed
        // to create any new child processes but we still want the existing child processes to continue their work
        // and finish the jbos which are already running
        return sprintf('nohup %s %s %%s %s %s & echo $!', $binary, $worker, $stdout, $stderr);
    }

    public function __destruct()
    {
        // Prevent background tasks from being killed when this script finishes
        // this is an override for the default behaviour of killing background tasks
    }
}
