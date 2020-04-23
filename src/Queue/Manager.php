<?php

namespace App\Queue;

use AsyncPHP\Doorman\Manager\ProcessManager;

/**
 * Class Manager
 *
 * customise shell command to allow child tasks to persist even after manager process is terminated
 * this lets the started jobs to finish properly in case the management process terminates
 * fore example there are no more jobs to start or queue is paused
 *
 * @package App\Queue
 */
class Manager extends ProcessManager
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
        return sprintf('nohup %s %s %%s %s %s & echo $!', $binary, $worker, $stdout, $stderr);
    }

    public function __destruct()
    {
        // Prevent background tasks from being killed when this script finishes
        // this is an override for the default behaviour of killing background tasks
    }
}
