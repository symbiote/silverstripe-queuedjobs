<?php

namespace Symbiote\QueuedJobs\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class CheckJobHealthTask extends BuildTask
{
    /**
     * {@inheritDoc}
     * @var string
     */
    private static $segment = 'CheckJobHealthTask';

    /**
     * {@inheritDoc}
     * @return string
     */
    public function getDescription()
    {
        return _t(
            __CLASS__ . '.Description',
            'A task used to check the health of jobs that are "running". Pass a specific queue as the "queue" ' .
            'parameter or otherwise the "Queued" queue will be checked'
        );
    }

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return
     */
    public function run($request)
    {
        $queue = $request->requestVar('queue') ?: QueuedJob::QUEUED;
        $jobHealth = $this->getService()->checkJobHealth($queue);

        $unhealthyJobCount = 0;

        foreach ( $jobHealth as $type => $IDs ) {
            $count = count($IDs);
            echo 'Detected and attempted restart on ' . $count . ' ' . $type . ' jobs';
            $unhealthyJobCount = $unhealthyJobCount + $count;
        }

        if( $unhealthyJobCount === 0 ) {
            echo 'All jobs are healthy';
        } else {
            die(1);
        }
    }

    protected function getService()
    {
        return QueuedJobService::singleton();
    }
}
