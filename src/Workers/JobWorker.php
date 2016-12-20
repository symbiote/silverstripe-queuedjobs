<?php

namespace SilverStripe\QueuedJobs\Workers;

use SilverStripe\ORM\DataList;

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */

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
            $job = DataList::create('SilverStripe\\QueuedJobs\\DataObjects\\QueuedJobDescriptor')->byID($jobId);
            if ($job) {
                // check that we're not trying to execute something tooo soon
                if (strtotime($job->StartAfter) > time()) {
                    return;
                }

                $this->queuedJobService->runJob($jobId);
            }
        }
    }
}
