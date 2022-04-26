<?php

namespace Symbiote\QueuedJobs\Tasks;

use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Task used to process the job queue
 *
 * @author Marcus Nyeholt <marcus@symbiote.com.au>
 * @license BSD http://silverstripe.org/bsd-license/
 */
class ProcessJobQueueTask extends BuildTask
{
    /**
     * {@inheritDoc}
     * @var string
     */
    private static $segment = 'ProcessJobQueueTask';

    /**
     * @return string
     */
    public function getDescription()
    {
        return _t(
            __CLASS__ . '.Description',
            'Used via a cron job to execute queued jobs that need to be run.'
        );
    }

    /**
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        if (QueuedJobService::singleton()->isMaintenanceLockActive()) {
            return;
        }

        $service = $this->getService();

        // Ensure that log messages are visible when executing this task on CLI.
        // Could be replaced with BuildTask logger: https://github.com/silverstripe/silverstripe-framework/issues/9183
        if (Environment::isCli()) {
            $logger = $service->getLogger();

            // Assumes that general purpose logger usually doesn't already contain a stream handler.
            $errorHandler = new StreamHandler('php://stderr', Logger::ERROR);
            $standardHandler = new StreamHandler('php://stdout');

            // Avoid double logging of errors
            $standardFilterHandler = new FilterHandler(
                $standardHandler,
                Logger::DEBUG,
                Logger::WARNING
            );

            $logger->pushHandler($standardFilterHandler);
            $logger->pushHandler($errorHandler);
        }

        if ($request->getVar('list')) {
            // List helper
            $service->queueRunner->listJobs();
            return;
        }

        // Check if there is a job to run
        if (($job = $request->getVar('job')) && strpos($job ?? '', '-')) {
            // Run from a isngle job
            $parts = explode('-', $job ?? '');
            $id = $parts[1];
            $service->runJob($id);
            return;
        }

        // Run the queue
        $queue = $this->getQueue($request);
        $service->runQueue($queue);
    }

    /**
     * Resolves the queue name to one of a few aliases.
     *
     * @todo Solve the "Queued"/"queued" mystery!
     *
     * @param HTTPRequest $request
     * @return string
     */
    protected function getQueue($request)
    {
        $queue = $request->getVar('queue');

        if (!$queue) {
            $queue = 'Queued';
        }

        switch (strtolower($queue ?? '')) {
            case 'immediate':
                $queue = QueuedJob::IMMEDIATE;
                break;
            case 'queued':
                $queue = QueuedJob::QUEUED;
                break;
            case 'large':
                $queue = QueuedJob::LARGE;
                break;
            default:
                break;
        }

        return $queue;
    }

    /**
     * Returns an instance of the QueuedJobService.
     *
     * @return QueuedJobService
     */
    public function getService()
    {
        return QueuedJobService::singleton();
    }
}
