<?php

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ScheduledExecutionTest extends SapphireTest {
	
	protected $extraDataObjects = array(
		'TestScheduledDataObject',
	);
	
	public function testScheduledExecutionTimes() {
		
		$test = new TestScheduledDataObject;
		
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
		$test = DataObject::get_by_id('TestScheduledDataObject', $test->ID);
		
		$this->assertNotEquals($test->ScheduledJobID, $jobId);
		$this->assertEquals('EXECUTED', $test->Message);
	}
	
	public function testScheduledExecutionInterval() {

		$test = new TestScheduledDataObject;

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
		$test = DataObject::get_by_id('TestScheduledDataObject', $test->ID);

		$this->assertNotEquals($test->ScheduledJobID, $jobId);
		$this->assertEquals('EXECUTED', $test->Message);

		$job = $test->ScheduledJob();

		// should reschedule in 1 minute time
		$expectedMinutes = date('i', time());
		$expectedMinutes = intval($expectedMinutes, 10);
		if ($expectedMinutes + 1 > 59) { // Wrap around the hour
			$expectedMinutes = $expectedMinutes - 59;
		}
		$scheduledMinutes = substr($job->StartAfter, 14, 2);
		$scheduledMinutes = intval($scheduledMinutes, 10);

		$this->assertEquals($expectedMinutes + 1, $scheduledMinutes, 'Did not reschedule 1 minute later');

		// test a custom interval of 3 minutes

		$test->ExecuteInterval = 3;
		$test->write();

		$job = $test->ScheduledJob();
		$job->execute();

		$test = DataObject::get_by_id('TestScheduledDataObject', $test->ID);

		$job = $test->ScheduledJob();

		// should reschedule in 3 minutes time
		$expectedMinutes = date('i', time());
		$expectedMinutes = intval($expectedMinutes, 10);
		if ($expectedMinutes + 3 > 59) {
			$expectedMinutes = $expectedMinutes - 59;
		}
		$scheduledMinutes = substr($job->StartAfter, 14, 2);
		$scheduledMinutes = intval($scheduledMinutes, 10);

		$this->assertEquals($expectedMinutes + 3, $scheduledMinutes, 'Did not reschedule 3 minutes later');
	}
}


class TestScheduledDataObject extends DataObject implements TestOnly {
	public static $db = array(
		'Title' => 'Varchar',
		'Message' => 'Varchar',
	);
	
	public static $extensions = array(
		'ScheduledExecutionExtension'
	);
	
	public function onScheduledExecution() {
		$this->Message = 'EXECUTED';
		$this->write();
	}
}