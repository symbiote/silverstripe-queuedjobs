<?php

namespace Symbiote\QueuedJobs\Tasks;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Deprecation;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * @deprecated 5.3.0 Will be replaced with Symbiote\QueuedJobs\Cli\ProcessJobQueueChildCommand
 */
class ProcessJobQueueChildTask extends BuildTask
{
    /**
     * {@inheritDoc}
     * @var string
     */
    private static $segment = 'ProcessJobQueueChildTask';

    public function __construct()
    {
        parent::__construct();
        Deprecation::withNoReplacement(function () {
            Deprecation::notice(
                '5.3.0',
                'Will be replaced with Symbiote\QueuedJobs\Cli\ProcessJobQueueChildCommand',
                Deprecation::SCOPE_CLASS
            );
        });
    }

    /**
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        if (!isset($_SERVER['argv'][2])) {
            print "No task data provided.\n";
            return;
        }

        $task = @unserialize(@base64_decode($_SERVER['argv'][2] ?? ''));

        if ($task) {
            $this->getService()->runJob($task->getDescriptor()->ID);
        }
    }

    /**
     * Returns an instance of the QueuedJobService.
     *
     * @return QueuedJobService
     */
    protected function getService()
    {
        return QueuedJobService::singleton();
    }
}
