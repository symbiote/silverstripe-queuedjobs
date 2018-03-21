<?php

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Forms\FieldList;
use Symbiote\QueuedJobs\Controllers\QueuedJobsAdmin;
use Symbiote\QueuedJobs\Jobs\PublishItemsJob;

/**
 * Tests for the QueuedJobsAdmin ModelAdmin clas
 *
 * @coversDefaultClass \Symbiote\QueuedJobs\Controllers\QueuedJobsAdmin
 * @package queuedjobs
 * @author  Robbie Averill <robbie@silverstripe.com>
 */
class QueuedJobsAdminTest extends FunctionalTest
{
    /**
     * {@inheritDoc}
     * @var string
     */
    // protected static $fixture_file = 'QueuedJobsAdminTest.yml';

    protected $usesDatabase = true;

    /**
     * @var QueuedJobsAdmin
     */
    protected $admin;

    /**
     * Get a test class, and mock the job queue for it
     *
     * {@inheritDoc}
     */
    protected function setUp()
    {
        parent::setUp();
        Config::modify()->set(\Symbiote\QueuedJobs\Services\QueuedJobService::class, 'use_shutdown_function', false);

        $this->admin = new QueuedJobsAdmin;

        // Compatible with both PHPUnit 3 and PHPUnit 5+
        $mockQueue = method_exists($this, 'createMock') ? $this->createMock('Symbiote\\QueuedJobs\\Services\\QueuedJobService') : $this->getMock('Symbiote\\QueuedJobs\\Services\\QueuedJobService');
        $this->admin->jobQueue = $mockQueue;

        $this->logInWithPermission('ADMIN');
        $this->admin->doInit();
    }

    /**
     * Ensure that the JobParams field is added as a Textarea
     */
    public function testConstructorParamsShouldBeATextarea()
    {
        $fields = $this->admin->getEditForm('foo', new FieldList)->Fields();
        $this->assertInstanceOf('SilverStripe\\Forms\\TextareaField', $fields->fieldByName('JobParams'));
    }

    /**
     * Ensure that when a multi-line value is entered for JobParams, it is split by new line and each value
     * passed to the constructor of the JobType that is created by the reflection in createjob()
     *
     * @covers ::createjob
     */
    public function testCreateJobWithConstructorParams()
    {
        $this->admin->jobQueue
            ->expects($this->once())
            ->method('queueJob')
            ->with($this->callback(function ($job) {
                return $job instanceof PublishItemsJob && $job->rootID === 'foo123';
            }));

        $form = $this->admin->getEditForm('foo', new FieldList);
        $form->Fields()->fieldByName('JobParams')->setValue(implode(PHP_EOL, ['foo123', 'bar']));
        $form->Fields()->fieldByName('JobType')->setValue(PublishItemsJob::class);

        $this->admin->createjob($form->getData(), $form);
    }
}
