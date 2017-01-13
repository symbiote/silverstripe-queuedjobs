<?php

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\QueuedJobs\Controllers\QueuedJobsAdmin;
use SilverStripe\QueuedJobs\Jobs\PublishItemsJob;

/**
 * Tests for the QueuedJobsAdmin ModelAdmin clas
 *
 * @coversDefaultClass \SilverStripe\QueuedJobs\Controllers\QueuedJobsAdmin
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

    /**
     * @var QueuedJobsAdmin
     */
    protected $admin;

    /**
     * Get a test class, and mock the job queue for it
     *
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();
        $this->admin = new QueuedJobsAdmin;

        $mockQueue = $this->getMock('SilverStripe\\QueuedJobs\\Services\\QueuedJobService');
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
