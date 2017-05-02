<?php

namespace SilverStripe\QueuedJobs\Tests\ScheduledExecutionTest;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use SilverStripe\QueuedJobs\Extensions\ScheduledExecutionExtension;

class TestScheduledDataObject extends DataObject implements TestOnly
{
    private static $table_name = 'TestScheduledDataObject';

    private static $db = array(
        'Title' => 'Varchar',
        'Message' => 'Varchar',
    );

    private static $extensions = array(
        ScheduledExecutionExtension::class
    );

    public function onScheduledExecution()
    {
        $this->Message = 'EXECUTED';
        $this->write();
    }
}
