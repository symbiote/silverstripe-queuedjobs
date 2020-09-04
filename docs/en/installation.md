## Quick Usage Overview

* Install the cronjob needed to manage all the jobs within the system. It is best to have this execute as the
same user as your webserver - this prevents any problems with file permissions.

```
*/1 * * * * /path/to/silverstripe/vendor/bin/sake dev/tasks/ProcessJobQueueTask
```

* If your code is to make use of the 'long' jobs, ie that could take days to process, also install another task
## Cleaning up job entries

Depending 

By default the CleanupJob is disabled. To enable it, set the following in your YML:

```yaml
Symbiote\QueuedJobs\Jobs\CleanupJob:
  is_enabled: true
```

You will need to trigger the first run manually in the UI. After that the CleanupJob is run once a day.

You can configure this job to clean up based on the number of jobs, or the age of the jobs. This is
configured with the `cleanup_method` setting - current valid values are "age" (default)  and "number".
Each of these methods will have a value associated with it - this is an integer, set with `cleanup_value`.
For "age", this will be converted into days; for "number", it is the minimum number of records to keep, sorted by LastEdited.
The default value is 30, as we are expecting days.

You can determine which JobStatuses are allowed to be cleaned up. The default setting is to clean up "Broken" and "Complete" jobs. All other statuses can be configured with `cleanup_statuses`. You can also define `query_limit` to limit the number of rows queried/deleted by the cleanup job (defaults to 100k).

The default configuration looks like this:

```yaml
Symbiote\QueuedJobs\Jobs\CleanupJob:
  is_enabled: false
  query_limit: 100000
  cleanup_method: "age"
  cleanup_value: 30
  cleanup_statuses:
    - Broken
    - Complete
```
that processes this queue. Its time of execution can be left a little longer.

```
*/15 * * * * /path/to/silverstripe/vendor/bin/sake dev/tasks/ProcessJobQueueTask queue=large
```

* From your code, add a new job for execution.

```php
use Symbiote\QueuedJobs\Services\QueuedJobService;

$publish = new PublishItemsJob(21);
QueuedJobService::singleton()->queueJob($publish);
```

* To schedule a job to be executed at some point in the future, pass a date through with the call to queueJob
The following will run the publish job in 1 day's time from now.

```php
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Services\QueuedJobService;

$publish = new PublishItemsJob(21);
QueuedJobService::singleton()
    ->queueJob($publish, DBDatetime::create()->setValue(DBDatetime::now()->getTimestamp() + 86400)->Rfc2822());
```

## Using Doorman for running jobs

Doorman is included by default, and allows for asynchronous task processing.

This requires that you are running an a unix based system, or within some kind of environment
emulator such as cygwin.

In order to enable this, configure the ProcessJobQueueTask to use this backend.

In your YML set the below:


```yaml
---
Name: localproject
After: '#queuedjobsettings'
---
SilverStripe\Core\Injector\Injector:
  Symbiote\QueuedJobs\Services\QueuedJobService:
    properties:
      queueRunner: %$DoormanRunner
```

## Using Gearman for running jobs

* Make sure gearmand is installed
* Get the gearman module from https://github.com/nyeholt/silverstripe-gearman
* Create a `_config/queuedjobs.yml` file in your project with the following declaration

```yaml
---
Name: localproject
After: '#queuedjobsettings'
---
SilverStripe\Core\Injector\Injector:
  QueueHandler:
    class: Symbiote\QueuedJobs\Services\GearmanQueueHandler
```

* Run the gearman worker using `php gearman/gearman_runner.php` in your SS root dir

This will cause all queuedjobs to trigger immediate via a gearman worker (`src/workers/JobWorker.php`)
EXCEPT those with a StartAfter date set, for which you will STILL need the cron settings from above

## Using `QueuedJob::IMMEDIATE` jobs

Queued jobs can be executed immediately (instead of being limited by cron's 1 minute interval) by using
a file based notification system. This relies on something like inotifywait to monitor a folder (by
default this is SILVERSTRIPE_CACHE_DIR/queuedjobs) and triggering the ProcessJobQueueTask as above
but passing job=$filename as the argument. An example script is in queuedjobs/scripts that will run
inotifywait and then call the ProcessJobQueueTask when a new job is ready to run.

Note - if you do NOT have this running, make sure to set `QueuedJobService::$use_shutdown_function = true;`
so that immediate mode jobs don't stall. By setting this to true, immediate jobs will be executed after
the request finishes as the php script ends.

# Default Jobs

Some jobs should always be either running or queued to run, things like data refreshes or periodic clean up jobs, we call these Default Jobs.
Default jobs are checked for at the end of each job queue process, using the job type and any fields in the filter to create an SQL query e.g.

```yml
ArbitraryName:
  type: 'ScheduledExternalImportJob'
  filter:
    JobTitle: 'Scheduled import from Services'
```

Will become:

```php
QueuedJobDescriptor::get()->filter([
  'type' => 'ScheduledExternalImportJob',
  'JobTitle' => 'Scheduled import from Services'
]);
```

This query is checked to see if there's at least 1 healthly (new, run, wait or paused) job matching the filter. If there's not and recreate is true in the yml config we use the construct array as params to pass to a new job object e.g:

```yml
ArbitraryName:
  type: 'ScheduledExternalImportJob'
  filter:
    JobTitle: 'Scheduled import from Services'
  recreate: 1
  construct:
    repeat: 300
    contentItem: 100
      target: 157
```
If the above job is missing it will be recreated as:
```php
Injector::inst()->createWithArgs(ScheduledExternalImportJob::class, $construct[])
```

### Pausing Default Jobs

If you need to stop a default job from raising alerts and being recreated, set an existing copy of the job to Paused in the CMS.

### YML config

Default jobs are defined in yml config the sample below covers the options and expected values

```yaml
SilverStripe\Core\Injector\Injector:
  Symbiote\QueuedJobs\Services\QueuedJobService:
    properties:
      defaultJobs:
        # This key is used as the title for error logs and alert emails
        ArbitraryName:
          # The job type should be the class name of a job REQUIRED
          type: 'ScheduledExternalImportJob'
          # This plus the job type is used to create the SQL query REQUIRED
          filter:
            # 1 or more Fieldname: 'value' sets that will be queried on REQUIRED
            #  These can be valid ORM filter
            JobTitle: 'Scheduled import from Services'
          # Parameters set on the recreated object OPTIONAL
          construct:
            # 1 or more Fieldname: 'value' sets be passed to the constructor REQUIRED
            # If your constructor needs none, put something arbitrary
            repeat: 300
            title: 'Scheduled import from Services'
          # A date/time format string for the job's StartAfter field REQUIRED
          # The shown example generates strings like "2020-02-27 01:00:00"
          startDateFormat: 'Y-m-d H:i:s'
          # A string acceptable to PHP's date() function for the job's StartAfter field REQUIRED
          startTimeString: 'tomorrow 01:00'
          # Sets whether the job will be recreated or not OPTIONAL
          recreate: 1
          # Set the email address to send the alert to if not set site admin email is used OPTIONAL
          email: 'admin@example.com'
        # Minimal implementation will send alerts but not recreate
        AnotherTitle:
          type: 'AJob'
          filter:
            JobTitle: 'A job'
```

## Jobs queue pause setting

It's possible to enable a setting which allows the pausing of the queued jobs processing. To enable it, add following code to your config YAML file:

```yaml
Symbiote\QueuedJobs\Services\QueuedJobService:
  lock_file_enabled: true
  lock_file_path: '/shared-folder-path'
```

`Queue settings` tab will appear in the CMS settings and there will be an option to pause the queued jobs processing. If enabled, no new jobs will start running however, the jobs already running will be left to finish.
 This is really useful in case of planned downtime like queue jobs related third party service maintenance or DB restore / backup operation.

Note that this maintenance lock state is stored in a file. This is intentionally not using DB as a storage as it may not be available during some maintenance operations.
Please make sure that the `lock_file_path` is pointing to a folder on a shared drive in case you are running a server with multiple instances.

One benefit of file locking is that in case of critical failure (e.g.: the site crashes and CMS is not available), you may still be able to get access to the filesystem and change the file lock manually.
This gives you some additional disaster recovery options in case running jobs are causing the issue.

### Understanding job states

It's really useful to understand how job state changes during the job lifespan as it makes troubleshooting easier.
Following chart shows the whole job lifecycle:

![JobStatus](docs/job_status.jpg)

* every job starts in `New` state
* every job should eventually reach either `Complete`, `Broken` or `Paused`
* `Cancelled` state is not listed here as the queue runner will never transition job to that state as it is reserved for user triggered actions
* progress indication is either state change or step increment
* every job can be restarted however there is a limit on how many times (see `stall_threshold` config)


## Unit tests

Writing units tests for queued jobs can be tricky as it's quite a complex system. Still, it can be done.

### Basic unit tests

Note that you don't actually need to run your queued job via the `QueuedJobService` in your unit test in most cases. Instead, you can run it directly, like this:

```
$job = new YourQueuedJob($someArguments);
$job->setup();
$job->process();

$this->assertTrue($job->jobFinished());
other assertions can be placed here (job side effects, job data assertions...)
```

`setup()` needs to be run only once and `process()` needs to be run as many times as needed to complete the job. This depends on your job and the job data.
Usually, `process()` needs to be run once for every `step` your job completes, but this may vary per job implementation. Please avoid using `do while {jobFinished}`, you should always be clear on how many times the `process()` runs in your test job.
If you are unsure, do a test run in your application with some logging to indicate how many times it is run.

This should cover most cases, but sometimes you need to run a job via the service. For example you may need to test if your job related extension hooks are working.

### Advanced unit tests

Please be sure to disable the shutdown function and the queued job handler as these two will cause you some major pain in your unit tests.
You can do this in multiple ways:

* `setUp()` at the start of your unit test

This is pretty easy, but it may be tedious to add this to your every unit test.

* create a parent class for your unit tests and add `setUp()` function to it

You can now have the code in just one place, but inheritance has some limitations.

* add a test state and add `setUp()` function to it, see `SilverStripe\Dev\State\TestState`

Create your test state like this:

```
<?php

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
    }

    public function setUpOnce($class)
    {
    }

    public function tearDownOnce($class)
    {
    }
}

```

Register your test state with `Injector` like this:

```
SilverStripe\Core\Injector\Injector:
  SilverStripe\Dev\State\SapphireTestState:
    properties:
      States:
        queuedjobs: '%$App\Dev\State\QueuedJobTestState'
```

This solution is great if you want to apply this change to all of your unit tests.

Regardless of which approach you choose, the two changes that need to be inside the `setUp()` function are as follows:

This will replace the standard logger with a dummy one. 
```
Injector::inst()->registerService(new QueuedJobsTest_Handler(), QueuedJobHandler::class);
```

This will disable the shutdown function completely as `QueuedJobService` doesn't work well with `SapphireTest`.

```
Config::modify()->set(QueuedJobService::class, 'use_shutdown_function', false);
```

This is how your run a job via service in your unit tests.

```
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

## Advanced job setup

This section is recommended for developers who are already familiar with basic concepts and want to take full advantage of the features in this module.

### Job creation

First, let's quickly summarise the lifecycle of a queued job:

1. job is created as an object in your code
2. job is queued, the matching job descriptor is saved into the database
3. job is picked and processed up by the queue runner.

Important thing to note is that **step 3** will create an empty job instance and populate it with data from the matching job descriptor.
Any defined params in the job constructor will not be populated in this step.
If you want to define your own job constructor and not use the inherited one, you will need to take this into account when implementing your job.
Incorrect implementation may result in the job processing losing some or all of the job data before processing starts.
To avoid this issue consider using one of the options below to properly implement your job creation.

Suppose we have a job which needs a `string`, an `integer` and an `array` as the input.

#### Option 1: Job data is set directly

It's possible to completely avoid defining constructor on your job and set the job data directly to the job object.
This is a good approach for simple jobs, but more complex jobs with a lot of properties may end up using several lines of code.

##### Job class constructor

```php
// no constructor
```

##### Job creation

```php
$job = new MyJob();
// set job data
$job->string = $string;
$job->integer = $integer;
$job->array = $array;
```

##### Advantages

* No need to define constructor.
* Nullable values don't need to be handled.

##### Disadvantages

* No strict parameter types.
* Code may not be as DRY in case you create the job in many different places.

#### Option 2: Job constructor with specific params

Defining your own constructor is the most intuitive approach.
We need to take into consideration that the job constructor will be called without any params by the queue runner.
The implementation needs to provide default values for all parameters and handle this special case.

##### Job class constructor

```php
public function __construct(?string $string = null, ?int $integer = null, ?array $array = null)
{
    if ($string === null || $integer === null || $array === null) {
        // job constructor called by the queue runner - exit early
        return;
    }

    // job constructor called in project code - populate job data
    $this->string = $string;
    $this->integer = $integer;
    $this->array = $array;
}
```

##### Job creation

```php
$job = new MyJob($string, $integer, $array);
```

##### Advantages

* Typed parameters.

##### Disadvantages

* Nullable values need to be provided and code handling of the nullable values has to be present. That is necessary because the queue runner calls the constructor without parameters as data will come in later from job descriptor.
* Strict type is not completely strict because nullable values can be passed when they shouldn't be (e.g.: at job creation in your code).

This approach is especially problematic on PHP 7.3 or higher as the static syntax checker may have an issue with nullable values and force you to implement additional check like `is_countable` on the job properties.

#### Option 3: Job constructor without specific params

The inherited constructor has a generic parameter array as the only input and we can use it to pass arbitrary parameters to our job.
This makes the job constructor match the parent constructor signature but there is no type checking.

##### Job class constructor

```php
public function __construct(array $params = [])
{
    if (!array_key_exists('string', $params) || !array_key_exists('integer', $params) || !array_key_exists('array', $params)) {
        // job constructor called by the queue runner - exit early
        return;
    }

    // job constructor called in project code - populate job data
    $this->string = $params['string'];
    $this->integer = $params['integer'];
    $this->array = $params['array'];
}
```

##### Job creation

```php
$job = new MyJob(['string' => $string, 'integer' => $integer, 'array' => $array]);
```

##### Advantages

* Nullable values don't need to be handled.

##### Disadvantages

* No strict parameter types.

This approach is probably the simplest one but with the least parameter validation.

#### Option 4: Separate hydrate method

This approach is the strictest when it comes to validating parameters but requires the `hydrate` method to be called after each job creation.
Don't forget to call the `hydrate` method in your unit test as well.
This option is recommended for projects which have many job types with complex processing. Strict checking reduces the risk of input error.

##### Job class constructor

```php
// no constructor

public function hydrate(string $string, int $integer, array $array): void
{
    $this->string = $string;
    $this->integer = $integer;
    $this->array = $array;
}
```

##### Job creation

```php
$job = new MyJob();
$job->hydrate($string, $integer, $array);
```

##### Unit tests

```php
$job = new MyJob();
$job->hydrate($string, $integer, $array);
$job->setup();
$job->process();

$this->assertTrue($job->jobFinished());
// other assertions can be placed here (job side effects, job data assertions...)
```

##### Advantages

* Strict parameter type checking.
* No nullable values.
* No issues with PHP 7.3 or higher.

##### Disadvantages

* Separate method has to be implemented and called after job creation in your code.

### Job steps

It is highly recommended to use the job steps feature in your jobs.
Correct implementation of jobs steps makes your jobs more robust.

The job step feature has two main purposes.

* Communicating progress to the job manager so it knows if the job execution is still underway.
* Providing a checkpoint in case job execution is interrupted for some reason. This allows the job to resume from the last completed step instead of restarting from the beginning.

The currently executing job step can also be observed in the CMS via the _Jobs admin_ UI. This is useful mostly for debugging purposes when monitoring job execution.

Job steps *should not* be used to determine if a job is completed or not. You should rely on the job data or the database state instead.

For example, consider a job which accept a list of items to process and each item represents a separate step.

```php
<?php

namespace App\Jobs;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * Class MyJob
 *
 * @property array $items
 * @property array $remaining
 */
class MyJob extends AbstractQueuedJob
{
    public function hydrate(array $items): void
    {
        $this->items = $items;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return 'My awesome job';
    }

    public function setup(): void
    {
        $this->remaining = $this->items;
        $this->totalSteps = count($this->items);
    }

    public function process(): void
    {
        $remaining = $this->remaining;

        // check for trivial case
        if (count($remaining) === 0) {
            $this->isComplete = true;

            return;
        }

        $item = array_shift($remaining);

        // code that will process your item goes here

        // update job progress
        $this->remaining = $remaining;
        $this->currentStep += 1;

        // check for job completion
        if (count($remaining) > 0) {
            return;
        }

        $this->isComplete = true;
    }
}

```

This job setup has following features:

* one item is processed in each step
* each step will produce a checkpoint so job can be safely resumed
* job manager will be notified about job progress and is unlikely to label the job as crashed by mistake
* job uses data to determine job completion rather than the steps
* original list of items is preserved in the job data so it can be used for other purposes (dependant jobs, debug).

Don't forget that in your unit test you must call `process()` as many times as you have items in your test data as one `process()` call handles only one item.

### Dependant jobs

Sometimes it makes sense to split the work to be done between several jobs.
For example, consider the following flow:

* page gets published (generates URLs for static cache)
* page gets statically cached (generates static HTML for provided URLs)
* page flushes cache on CDN for provided URLs.

One way to implement this flow using queued jobs is to split the work between several jobs.
Note that these actions have to be done in sequence, so we may not be able to queue all needed jobs right away.

This may be because of:

* queue processing is run on multiple threads and we can't guarantee that jobs will be run in sequence
* later actions have data dependencies on earlier actions.

In this situation, it's recommended to use the _Dependant job_ approach.

Use the `updateJobDescriptorAndJobOnCompletion` extension point in `QueuedJobService::runJob()` like this:

```php
public function updateJobDescriptorAndJobOnCompletion(
    QueuedJobDescriptor $descriptor, 
    QueuedJob $job
): void
{
    // your code goes here
}
```

This extension point is invoked each time a job completes successfully.
This allows you to create a new job right after the current job completes.
You have access to the job object and to the job descriptor in the extension point. If you need any data from the previous job, simply use these two variables to access the needed data.

Going back to our example, we would use the extension point to look for the static cache job, i.e. if the completed job is not the static cache job, just exit early.
Then we would extract the URLs we need form the `$job` variable and queue a new CDN flush job with those URLs.

This approach has a downside though. The newly created job will be placed at the end of the queue.
As a consequence, the work might end up being very fragmented and each chunk may be processed at a different time.

Some projects do not mind this however, so this solution may still be quite suitable.
