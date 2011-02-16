<?php

/**
 * Description of TestScheduledJob
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class TestScheduledExecution extends SapphireTest {
	
	protected $extraDataObjects = array(
		'TestScheduledDataObject',
	);
	
	public function testScheduledExecution() {
		
		$this->resetDBSchema(true);
		
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