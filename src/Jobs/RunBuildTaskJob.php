<?php

namespace Symbiote\QueuedJobs\Jobs;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * A job used to delete a data object. Typically used for deletes that need to happen on
 * a schedule, or where the delete may have some onflow affect that takes a while to
 * finish the deletion.
 *
 * @author marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class RunBuildTaskJob extends AbstractQueuedJob {
    /**
     * @param DataObject $node
     */
    public function __construct($taskClass = null, $queryString = null) {
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
     * @return DataObject
     */
    protected function getObject($name = 'SilverStripe\\Core\\Object') {
        return DataObject::get_by_id($this->TargetClass, $this->TargetID);
    }

    /**
     * @return string
     */
    public function getJobType() {
        return QueuedJob::QUEUED;
    }

    /**
     * @return string
     */
    public function getTitle() {
        $taskName = $this->QueryString ? ($this->TaskClass . '?' . $this->QueryString) : $this->TaskClass;
        return _t('RunBuildTaskJob.JOB_TITLE', 'Run BuildTask {task}', array('task' => $taskName));
    }

    public function process() {
        if (!is_subclass_of($this->TaskClass, BuildTask::class)) {
            throw new \LogicException($this->TaskClass . ' is not a build task');
        }

        $task = Injector::inst()->create($this->TaskClass);
        if (!$task->isEnabled()) {
            throw new \LogicException($this->TaskClass . ' is not enabled');
        }

        $getVars = array();
        parse_str($this->QueryString, $getVars);
        $request = new HTTPRequest('GET', '/', $getVars);
        $task->run($request);

        $this->currentStep = 1;
        $this->isComplete = true;
    }
}
