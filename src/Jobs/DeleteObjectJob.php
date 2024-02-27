<?php

namespace Symbiote\QueuedJobs\Jobs;

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
class DeleteObjectJob extends AbstractQueuedJob
{
    /**
     * @param DataObject $node
     */
    public function __construct($node = null)
    {
        if ($node) {
            $this->TargetClass = get_class($node);
            $this->TargetID = $node->ID;
            $this->currentStep = 0;
            $this->totalSteps = 1;
        }
    }

    /**
     * @param  string $name
     * @return DataObject
     */
    protected function getObject($name = 'Object')
    {
        return DataObject::get_by_id($this->TargetClass, $this->TargetID);
    }

    /**
     * @return string
     */
    public function getJobType()
    {
        return QueuedJob::IMMEDIATE;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        $obj = $this->getObject();
        if ($obj) {
            return _t(__CLASS__ . '.DELETE_OBJ2', 'Delete {title}', array('title' => $obj->Title));
        } else {
            return _t(__CLASS__ . '.DELETE_JOB', 'Delete node');
        }
    }

    public function process()
    {
        $obj = $this->getObject();
        $obj->delete();
        $this->currentStep = 1;
        $this->isComplete = true;
    }
}
