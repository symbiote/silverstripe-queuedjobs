<?php

namespace Symbiote\QueuedJobs\Jobs;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * A convenience wrapper for running BuildTask implementations.
 * These are usually executed via synchronous web request
 * or synchronous CLI execution (under dev/tasks/*).
 *
 * Caution: This job can't increment steps. This is a signal
 * for job health checks that a job should be considered stale
 * after a (short) timeout. If you expect a build task to run
 * for more than a few minutes, create it as a job with steps,
 * increase timeouts, or disable health checks.
 * See "Defining Jobs" in the docs for details.
 *
 * @author marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class RunBuildTaskJob extends AbstractQueuedJob
{
    /**
     * @param DataObject $node
     */
    public function __construct($taskClass = null, $queryString = null)
    {
        if ($taskClass) {
            $this->TaskClass = $taskClass;
        }

        if ($queryString) {
            $this->QueryString = $queryString;
        }

        $this->currentStep = 0;
        $this->totalSteps = 1;
    }

    /**
     * @param string (default: Object)
     *
     * @return DataObject
     */
    protected function getObject($name = 'SilverStripe\\Core\\Object')
    {
        return DataObject::get_by_id($this->TargetClass, $this->TargetID);
    }

    /**
     * @return string
     */
    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        $taskName = $this->QueryString ? ($this->TaskClass . '?' . $this->QueryString) : $this->TaskClass;
        return _t('RunBuildTaskJob.JOB_TITLE', 'Run BuildTask {task}', ['task' => $taskName]);
    }

    public function process()
    {
        if (!is_subclass_of($this->TaskClass, BuildTask::class)) {
            throw new \LogicException($this->TaskClass . ' is not a build task');
        }

        $task = Injector::inst()->create($this->TaskClass);
        if (!$task->isEnabled()) {
            throw new \LogicException($this->TaskClass . ' is not enabled');
        }

        $getVars = [];
        parse_str($this->QueryString, $getVars);
        $request = new HTTPRequest('GET', '/', $getVars);
        $task->run($request);

        $this->currentStep = 1;
        $this->isComplete = true;
    }
}
