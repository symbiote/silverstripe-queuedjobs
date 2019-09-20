<?php

namespace Symbiote\QueuedJobs\Workers;

use Exception;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

if (interface_exists('GearmanHandler')) {
    /**
     * Class JobWorker
     *
     * GearmanHandler is an extension that could be not available.
     * @todo Test and implement against it for SilverStripe 4.x compatibility
     *
     * @author marcus@symbiote.com.au
     * @license BSD License http://silverstripe.org/bsd-license/
     * @package Symbiote\QueuedJobs\Workers
     */
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
         * @throws Exception
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
