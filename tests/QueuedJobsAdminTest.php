<?php

namespace Symbiote\QueuedJobs\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use Symbiote\QueuedJobs\Controllers\QueuedJobsAdmin;
use Symbiote\QueuedJobs\Jobs\PublishItemsJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

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

        // The shutdown handler doesn't play nicely with SapphireTest's database handling
        QueuedJobService::config()->set('use_shutdown_function', false);

        $this->admin = new QueuedJobsAdmin();
        $request = new HTTPRequest('GET', '/');
        $request->setSession($this->session());
        Injector::inst()->registerService($request, HTTPRequest::class);
        $this->admin->setRequest($request);

        $mockQueue = $this->createMock(QueuedJobService::class);
        $this->admin->jobQueue = $mockQueue;

        $this->logInWithPermission('ADMIN');
        $this->admin->doInit();
    }

    /**
     * Ensure that the JobParams field is added as a Textarea
     */
    public function testConstructorParamsShouldBeATextarea()
    {
        $fields = $this->admin->getEditForm('foo', new FieldList())->Fields();
        $this->assertInstanceOf(TextareaField::class, $fields->fieldByName('JobParams'));
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

        $form = $this->admin->getEditForm('foo', new FieldList());
        $form->Fields()->fieldByName('JobParams')->setValue(implode(PHP_EOL, ['foo123', 'bar']));
        $form->Fields()->fieldByName('JobType')->setValue(PublishItemsJob::class);

        $this->admin->createjob($form->getData(), $form);
    }
}
