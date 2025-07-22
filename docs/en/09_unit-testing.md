---
title: Unit Testing
summary: Writing units tests for queued jobs
icon: code
---

# Unit testing

Writing units tests for queued jobs can be tricky as it's quite a complex system. Still, it can be done.

## Overview

Note that you don't actually need to run your queued job via the [`QueuedJobService`](api:Symbiote\QueuedJobs\Services\QueuedJobService) in your unit test in most cases. Instead, you can run it directly, like this:

```php
use App\Jobs\YourQueuedJob;

$job = new YourQueuedJob($someArguments);
$job->setup();
$job->process();

$this->assertTrue($job->jobFinished());
// other assertions can be placed here (job side effects, job data assertions...)
```

[`AbstractQueuedJob::setup()`](api:Symbiote\QueuedJobs\Services\AbstractQueuedJob::setup()) needs to be run only once and [`AbstractQueuedJob::process()`](api:Symbiote\QueuedJobs\Services\AbstractQueuedJob::process()) needs to be run as many times as needed to complete the job. This depends on your job and the job data.
Usually, `process()` needs to be run once for every `step` your job completes, but this may vary per job implementation. Please avoid using `do while {jobFinished}`, you should always be clear on how many times the `process()` runs in your test job.
If you are unsure, do a test run in your application with some logging to indicate how many times it is run.

This should cover most cases, but sometimes you need to run a job via the service. For example you may need to test if your job related extension hooks are working.

## Advanced usage

Please be sure to disable the shutdown function and the queued job handler as these two will cause you some major pain in your unit tests.
You can do this in multiple ways:

- `setUp()` at the start of your unit test

This is pretty easy, but it may be tedious to add this to your every unit test.

- create a parent class for your unit tests and add `setUp()` function to it

You can now have the code in just one place, but inheritance has some limitations.

- add a test state and add `setUp()` function to it, see `SilverStripe\Dev\State\TestState`

Create your test state like this:

```php
namespace App\Dev\State;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\State\TestState;
use Symbiote\QueuedJobs\Services\QueuedJobHandler;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Tests\QueuedJobsTest\QueuedJobsTest_Handler;

class QueuedJobTestState implements TestState
{
    public function setUp(SapphireTest $test)
    {
        Injector::inst()->registerService(new QueuedJobsTest_Handler(), QueuedJobHandler::class);
        Config::modify()->set(QueuedJobService::class, 'use_shutdown_function', false);
    }

    public function tearDown(SapphireTest $test)
    {
        // noop
    }

    public function setUpOnce($class)
    {
        // noop
    }

    public function tearDownOnce($class)
    {
        // noop
    }
}
```

Register your test state with `Injector` like this:

```yml
SilverStripe\Core\Injector\Injector:
  SilverStripe\Dev\State\SapphireTestState:
    properties:
      States:
        queuedjobs: '%$App\Dev\State\QueuedJobTestState'
```

This solution is great if you want to apply this change to all of your unit tests.

Regardless of which approach you choose, the two changes that need to be inside the `setUp()` function are as follows:

This will replace the standard logger with a dummy one.

```php
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\QueuedJobHandler;
use Symbiote\QueuedJobs\Tests\QueuedJobsTest\QueuedJobsTest_Handler;

Injector::inst()->registerService(new QueuedJobsTest_Handler(), QueuedJobHandler::class);
```

This will disable the shutdown function completely as `QueuedJobService` doesn't work well with `SapphireTest`.

```php
use Symbiote\QueuedJobs\Services\QueuedJobService;

Config::modify()->set(QueuedJobService::class, 'use_shutdown_function', false);
```

This is how your run a job via service in your unit tests.

```php
use App\Jobs\YourQueuedJob;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

$job = new YourQueuedJob($someArguments);

/** @var QueuedJobService $service */
$service = Injector::inst()->get(QueuedJobService::class);

$descriptorID = $service->queueJob($job);
$service->runJob($descriptorID);

/** @var QueuedJobDescriptor $descriptor */
$descriptor = QueuedJobDescriptor::get()->byID($descriptorID);
$this->assertNotEquals(QueuedJob::STATUS_BROKEN, $descriptor->JobStatus);
```

For example, this code snippet runs the job and checks if the job ended up in a non-broken state.
