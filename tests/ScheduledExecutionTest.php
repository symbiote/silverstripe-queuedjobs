<?php

namespace Symbiote\QueuedJobs\Tests;

use DateTime;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Tests\ScheduledExecutionTest\TestScheduledDataObject;

/**
 * @author marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ScheduledExecutionTest extends AbstractTest
{
    /**
     * We need the DB for this test
     *
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * {@inheritDoc}
     * @var array
     */
    protected static $extra_dataobjects = array(
        TestScheduledDataObject::class
    );

    protected function setUp()
    {
        parent::setUp();

        DBDatetime::set_mock_now('2018-05-28 13:15:00');
    }

    public function testScheduledExecutionTimes()
    {
        $test = new TestScheduledDataObject();

        $test->Title = 'Test execute of stuff';
        $test->write();

        $test->FirstExecution = '1980-09-22 09:15:00';
        $test->ExecuteEvery = 'Hour';

        $test->write();

        // should now have a job
        $this->assertTrue($test->ScheduledJobID > 0, 'Scheduled job has not been created');

        $jobId = $test->ScheduledJobID;

        // execute said job
        $job = $test->ScheduledJob();

        $job->execute();

        // reload the test object and make sure its job has now changed
        $test = DataObject::get_by_id(TestScheduledDataObject::class, $test->ID);

        $this->assertNotEquals($test->ScheduledJobID, $jobId);
        $this->assertEquals('EXECUTED', $test->Message);
    }

    public function testScheduledExecutionInterval()
    {
        $test = new TestScheduledDataObject();

        $test->Title = 'Test execute at custom interval sizes';
        $test->write();

        $test->FirstExecution = '1980-09-22 09:15:00';
        $test->ExecuteEvery = 'Minute';

        $test->write();

        // should now have a job
        $this->assertTrue($test->ScheduledJobID > 0, 'Scheduled job has not been created');
        // should default the ExecuteInterval
        $this->assertEquals(1, $test->ExecuteInterval, 'ExecuteInterval did not default to 1');

        // should check the interval in code also
        $test->ExecuteInterval = 0;
        $test->write();

        $jobId = $test->ScheduledJobID;

        // execute said job
        $job = $test->ScheduledJob();
        $job->execute();

        // reload the test object and make sure its job has now changed
        $test = DataObject::get_by_id(TestScheduledDataObject::class, $test->ID);

        $this->assertNotEquals($test->ScheduledJobID, $jobId);
        $this->assertEquals('EXECUTED', $test->Message);

        $job = $test->ScheduledJob();

        $expected = new DateTime('+1 minute');
        $actual = new DateTime($job->StartAfter);

        // Allow within 1 second.
        $this->assertLessThanOrEqual(1, abs($actual->diff($expected)->s), 'Did not reschedule 1 minute later');

        // test a custom interval of 3 minutes

        $test->ExecuteInterval = 3;
        $test->write();

        $job = $test->ScheduledJob();
        $job->execute();

        $test = DataObject::get_by_id(TestScheduledDataObject::class, $test->ID);

        $job = $test->ScheduledJob();

        $expected = new DateTime('+3 minutes');
        $actual = new DateTime($job->StartAfter);

        $this->assertLessThanOrEqual(1, abs($actual->diff($expected)->s), 'Did not reschedule 3 minutes later');
    }
}
