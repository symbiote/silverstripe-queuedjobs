<?php

/**
 * @author Andrew Aitken-Fincham <andrew@silverstripe.com>
 */
class CleanupJobTest extends SapphireTest {

    protected static $fixture_file = 'CleanupJobFixture.yml';

    public function setUp() {
        parent::setUp();
        // Have to set a fake time here to work with 
        // the LastEdited dates in the fixture
        SS_Datetime::set_mock_now("02-02-03 02:02:02");
    }

    public function tearDown() {
        parent::tearDown();
        SS_Datetime::clear_mock_now();
    }

    public function testByDays() {
        $job = new CleanupJob();
        Config::inst()->update('CleanupJob', 'cleanup_method', 'age');
        Config::inst()->update('CleanupJob', 'cleanup_value', 30);
        Config::inst()->remove('CleanupJob', 'cleanup_statuses');
        Config::inst()->update('CleanupJob', 'cleanup_statuses',
            array('Broken', 'Complete'));
        $job->process();
        $data = $job->getJobData();
        $this->assertContains("2 jobs cleaned up.", $data->messages[0]);
    }

    public function testByNumber() {
        $job = new CleanupJob();
        Config::inst()->update('CleanupJob', 'cleanup_method', 'number');
        Config::inst()->update('CleanupJob', 'cleanup_value', 3);
        Config::inst()->remove('CleanupJob', 'cleanup_statuses');
        Config::inst()->update('CleanupJob', 'cleanup_statuses',
            array('Broken', 'Complete'));
        $job->process();
        $data = $job->getJobData();
        $this->assertContains("2 jobs cleaned up.", $data->messages[0]);
    }

    public function testByStatus() {
        $job = new CleanupJob();
        Config::inst()->update('CleanupJob', 'cleanup_method', 'number');
        Config::inst()->update('CleanupJob', 'cleanup_value', 3);
        Config::inst()->remove('CleanupJob', 'cleanup_statuses');
        Config::inst()->update('CleanupJob', 'cleanup_statuses',
            array('Broken', 'Complete', 'New'));
        $job->process();
        $data = $job->getJobData();
        $this->assertContains("3 jobs cleaned up.", $data->messages[0]);
    }

    public function testNoCleanup() {
        $job = new CleanupJob();
        Config::inst()->update('CleanupJob', 'cleanup_method', 'number');
        Config::inst()->update('CleanupJob', 'cleanup_value', 6);
        Config::inst()->remove('CleanupJob', 'cleanup_statuses');
        Config::inst()->update('CleanupJob', 'cleanup_statuses',
            array('Broken', 'Complete', 'New'));
        $job->process();
        $data = $job->getJobData();
        $this->assertContains("No jobs to clean up.", $data->messages[0]);
    }

}
