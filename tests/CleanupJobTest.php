<?php

namespace Symbiote\QueuedJobs\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Jobs\CleanupJob;

/**
 * @author Andrew Aitken-Fincham <andrew@silverstripe.com>
 */
class CleanupJobTest extends AbstractTest
{
    /**
     * {@inheritDoc}
     * @var string
     */
    protected static $fixture_file = 'CleanupJobFixture.yml';

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        // Have to set a fake time here to work with
        // the LastEdited dates in the fixture
        DBDatetime::set_mock_now("2002-02-03 02:02:02");
        parent::setUp();
        Config::modify()->set(\Symbiote\QueuedJobs\Services\QueuedJobService::class, 'use_shutdown_function', false);
    }

    /**
     * {@inheritDoc}
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        DBDatetime::clear_mock_now();
    }

    public function testByDays()
    {
        $job = new CleanupJob();
        Config::modify()->set(CleanupJob::class, 'cleanup_method', 'age');
        Config::modify()->set(CleanupJob::class, 'cleanup_value', 30);
        Config::inst()->remove(CleanupJob::class, 'cleanup_statuses');
        Config::modify()->set(
            CleanupJob::class,
            'cleanup_statuses',
            array('Broken', 'Complete')
        );
        $job->process();
        $data = $job->getJobData();
        $this->assertStringContainsString("2 jobs cleaned up.", $data->messages[0]);
    }

    public function testByNumber()
    {
        $job = new CleanupJob();
        Config::modify()->set(CleanupJob::class, 'cleanup_method', 'number');
        Config::modify()->set(CleanupJob::class, 'cleanup_value', 3);
        Config::inst()->remove(CleanupJob::class, 'cleanup_statuses');
        Config::modify()->set(
            CleanupJob::class,
            'cleanup_statuses',
            array('Broken', 'Complete')
        );
        $job->process();
        $data = $job->getJobData();
        $this->assertStringContainsString("2 jobs cleaned up.", $data->messages[0]);
    }

    public function testByStatus()
    {
        $job = new CleanupJob();
        Config::modify()->set(CleanupJob::class, 'cleanup_method', 'number');
        Config::modify()->set(CleanupJob::class, 'cleanup_value', 3);
        Config::inst()->remove(CleanupJob::class, 'cleanup_statuses');
        Config::modify()->set(
            CleanupJob::class,
            'cleanup_statuses',
            array('Broken', 'Complete', 'New')
        );
        $job->process();
        $data = $job->getJobData();
        $this->assertStringContainsString("3 jobs cleaned up.", $data->messages[0]);
    }

    public function testNoCleanup()
    {
        $job = new CleanupJob();
        Config::modify()->set(CleanupJob::class, 'cleanup_method', 'number');
        Config::modify()->set(CleanupJob::class, 'cleanup_value', 6);
        Config::inst()->remove(CleanupJob::class, 'cleanup_statuses');
        Config::modify()->set(
            CleanupJob::class,
            'cleanup_statuses',
            array('Broken', 'Complete', 'New')
        );
        $job->process();
        $data = $job->getJobData();
        $this->assertStringContainsString("No jobs to clean up.", $data->messages[0]);
    }
}
