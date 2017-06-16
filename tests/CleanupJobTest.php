<?php

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Jobs\CleanupJob;

/**
 * @author Andrew Aitken-Fincham <andrew@silverstripe.com>
 */
class CleanupJobTest extends SapphireTest
{
    /**
     * {@inheritDoc}
     * @var string
     */
    protected static $fixture_file = 'CleanupJobFixture.yml';

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        // Have to set a fake time here to work with
        // the LastEdited dates in the fixture
        DBDatetime::set_mock_now("2002-02-03 02:02:02");
        parent::setUp();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        parent::tearDown();
        DBDatetime::clear_mock_now();
    }

    public function testByDays()
    {
        $job = new CleanupJob();
        Config::modify()->set('Symbiote\\QueuedJobs\\Jobs\\CleanupJob', 'cleanup_method', 'age');
        Config::modify()->set('Symbiote\\QueuedJobs\\Jobs\\CleanupJob', 'cleanup_value', 30);
        Config::inst()->remove('Symbiote\\QueuedJobs\\Jobs\\CleanupJob', 'cleanup_statuses');
        Config::modify()->set(
            'Symbiote\\QueuedJobs\\Jobs\\CleanupJob',
            'cleanup_statuses',
            array('Broken', 'Complete')
        );
        $job->process();
        $data = $job->getJobData();
        $this->assertContains("2 jobs cleaned up.", $data->messages[0]);
    }

    public function testByNumber()
    {
        $job = new CleanupJob();
        Config::modify()->set('Symbiote\\QueuedJobs\\Jobs\\CleanupJob', 'cleanup_method', 'number');
        Config::modify()->set('Symbiote\\QueuedJobs\\Jobs\\CleanupJob', 'cleanup_value', 3);
        Config::inst()->remove('Symbiote\\QueuedJobs\\Jobs\\CleanupJob', 'cleanup_statuses');
        Config::modify()->set(
            'Symbiote\\QueuedJobs\\Jobs\\CleanupJob',
            'cleanup_statuses',
            array('Broken', 'Complete')
        );
        $job->process();
        $data = $job->getJobData();
        $this->assertContains("2 jobs cleaned up.", $data->messages[0]);
    }

    public function testByStatus()
    {
        $job = new CleanupJob();
        Config::modify()->set('Symbiote\\QueuedJobs\\Jobs\\CleanupJob', 'cleanup_method', 'number');
        Config::modify()->set('Symbiote\\QueuedJobs\\Jobs\\CleanupJob', 'cleanup_value', 3);
        Config::inst()->remove('Symbiote\\QueuedJobs\\Jobs\\CleanupJob', 'cleanup_statuses');
        Config::modify()->set(
            'Symbiote\\QueuedJobs\\Jobs\\CleanupJob',
            'cleanup_statuses',
            array('Broken', 'Complete', 'New')
        );
        $job->process();
        $data = $job->getJobData();
        $this->assertContains("3 jobs cleaned up.", $data->messages[0]);
    }

    public function testNoCleanup()
    {
        $job = new CleanupJob();
        Config::modify()->set('Symbiote\\QueuedJobs\\Jobs\\CleanupJob', 'cleanup_method', 'number');
        Config::modify()->set('Symbiote\\QueuedJobs\\Jobs\\CleanupJob', 'cleanup_value', 6);
        Config::inst()->remove('Symbiote\\QueuedJobs\\Jobs\\CleanupJob', 'cleanup_statuses');
        Config::modify()->set(
            'Symbiote\\QueuedJobs\\Jobs\\CleanupJob',
            'cleanup_statuses',
            array('Broken', 'Complete', 'New')
        );
        $job->process();
        $data = $job->getJobData();
        $this->assertContains("No jobs to clean up.", $data->messages[0]);
    }
}
