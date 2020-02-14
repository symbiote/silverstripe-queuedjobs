<?php

/**
 * @author marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */

 namespace Symbiote\QueuedJobs\Workers;

use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

// GearmanHandler is an extension that could be not available.
/**
 * @todo Test and implement against it for SilverStripe 4.x compatibility
 */
if (interface_exists('GearmanHandler')) {
    class JobWorker implements \GearmanHandler
    {
        /**
         * @var QueuedJobService
         */
        public $queuedJobService;

        /**
         * @return string
         */
        public function getName()
        {
            return 'jobqueueExecute';
        }

        /**
         * @param int $jobId
         * @return void
         */
        public function jobqueueExecute($jobId)
        {
            $this->queuedJobService->checkJobHealth();
            $job = QueuedJobDescriptor::get()->byID($jobId);
            if ($job) {
                // check that we're not trying to execute something tooo soon
                if (strtotime($job->StartAfter) > DBDatetime::now()->getTimestamp()) {
                    return;
                }

                $this->queuedJobService->runJob($jobId);
            }
        }
    }
}
