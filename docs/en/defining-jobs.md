# Defining Jobs

The best way to learn about defining your own jobs is by checking the examples

* `PublishItemsJob` - A job used to publish all the children of a particular node. To create this job, run the PublishItemsTask passing in the parent as a request var (eg ?parent=1)
* `GenerateGoogleSitemapJob` - A job used to create a google sitemap. If the googlesitemaps module is installed it will include priority settings as defined there, otherwise just produces a generic structure. To create an initial instance of this job, call dev/tasks/CreateDummyJob?name=GenerateGoogleSitemapJob. This will create the initial job and queue it; once the job has been run once, it will automatically schedule itself to be run again 24 hours later. 
* `CreateDummyJob` - A very simple skeleton job. 

## API Overview

The module comes with an `AbstractQueuedJob` class that defines many of the boilerplate functionality for the
job to execute within the framework. An example job can be found in queuedjobs/code/jobs/PublishItemsJob.php.

The key points to be aware of are

* When defining the constructor for your job, be aware that the QueuedJobService will, when
loading the job for execution, create an object of your job type without passing any parameters. Therefore, if you want to pass parameters when initially creating the job, make sure to provide defaults
(eg *__construct($param=null)*), and that their presence is detected before being used. So the base rules for `__construct`ors are
  * they must have default parameters, as the JobService will re-create the job class passing through no constructor params
  * you must have logic in your constructor that can determine if it's been constructed by the job service, or by user-land code, and whether the constructor params are to be used.

The kind of logic to use in your constructor could be something like

```php 

public function __construct($to = null) {
    if ($to) {
        // we know that we've been called by user code, so
        // do the real initialisation work
    } 
}
```

Of course, the other alternative is to set properties on the job directly after constructing it from your own code.

* **_Job Properties_** QueuedJobs inherited from the AbstractQueuedJob have a default mechanism for persisting values via the __set and __get mechanism that stores items in the *jobData* map, which is serialize()d between executions of the job processing. All you need to do from within your job is call `$this->myProperty = 'somevalue';`, and it will be automatically persisted for you; HOWEVER, on subsequent creation of the job object (ie, in the `__constructor()`) these properties _have not_ been loaded, so you _cannot_ rely on them at that point. 
* **_Special Properties_** The queuedjobs framework itself expects the following properties to be set by a job to ensure that jobs execute smoothly and can be paused/stopped/restarted. **YOU MUST DEFINE THESE for this to be effectively hooked **
  * **totalSteps** - the total number of steps in the job
  * **currentStep** - the current step in the job
  * **isComplete** - whether the job is complete yet. This MUST be set to true for the job to be removed from the queue
  * **messages** - an array that contains a list of messages about the job that can be displayed to the user via the CMS
* **_Titles_** Make sure to return a title via *getTitle()* - this is so that users can be shown what's running in the CMS admin.
* **_Job Signatures_** When a job is added to the job queue, it is assigned a unique key based on the parameters of the job
(see AbstractQueuedJob->getSignature()). If a job is already in the queue with the same signature, the new job
is NOT queued up; this prevents duplicate jobs being added to a queue, but in some cases that may be the
intention. If so, you'll need to override the getSignature() method in your custom job class and make sure
to return a unique signature for each instantiation of the job.
* **_Job Type_** You can use either QueuedJob::QUEUED, which will mean the job will run within a minute (due to the cronjob), or QueuedJob::IMMEDIATE, which will execute the job as soon as possible. This forces execution of the job at the end of the currently
processing request, OR if you have set QueuedJobService::$use_shutdown_function = false, a monitoring job to trigger the execution of the job queue (see the lsyncd config section). This job type is useful for doing small things (such as deleting a few items at a time, indexing content in a separate search indexer, etc)
* **_queueJob()_** To actually add a job to a queue, you call QueuedJobService->queueJob(Job $job, $startAfter=null).
This will add the job to whichever queue is relevant, with whatever 'startAfter' (a date in Y-m-d H:i:s format)
to start execution after particular datetime.
* **_Switching Users_** Jobs can be specified to run as a particular user. By default this is the user that created
the job, however it can be changed by setting the value returned by setting a user via the RunAs
relationship of the job.
* **_Job Execution_** The following sequence occurs at job execution
  * The cronjob looks for jobs that need execution.
  * The job is passed to QueuedJobService->runJob()
  * The user to run as is set into the session
  * The job is initialised. This calls *QueuedJob->setup()*. Generally, the *setup()* method should be used to provide
some initialisation of the job, in particular figuring out how many total steps will be required to execute (if it's
actually possible to determine this). Typically, the *setup()* method is used to generate a list of IDs of data 
objects that are going to have some processing done to them, then each call to *process()* processes just one of
these objects. This method makes pausing and resuming jobs later quite a lot easier.
It is very important to be aware that this method is called every time a job is 'started' by a cron execution,
meaning that any time a job is paused and restarted, this code is executed. Your Job class MUST handle this in its
*setup()* method. In some cases, it won't change what happens because a restarted job should re-perform everything,
but in others it might only need to process the remainder of what is left.
  * The QueuedJobService enters a loop that executes until either the job indicates it is finished
(the  *QueuedJob->jobFinished()* method returns true), the job is in some way broken, or a user has paused the job
via the CMS. This loop repeatedly calls *QueuedJob->process()* - each time this is called, the job should execute
code equivalent of 1 step in the overall process, updating its currentStep value each call, and finally updating
the isComplete value if it is actually finished. After each return of *process()*, the job state is saved so that
broken or paused jobs can be picked up again later.

## Terminology

The following are some key parts of the system that you should be familiar with

### AbstractQueuedJob

A subclass to define your own queued jobs based upon. You don't neeeed to use it, but it takes care of a lot of stuff for you. 

### QueuedJobService

A service for registering instances of queued jobs

### QueuedJobProcessorTask 

The task you run to have queued jobs processed. This must be set up to run via cron. 

### QueuedJobDescriptor

A QueuedJobDescriptor is the stored representation of a piece of work that could take a while to execute,
because of which it is desireable to not have it executing in parallel to other jobs.

A queued job should always attempt to report how many potential dataobjects will be affected by being executed;
this will determine which queue it is placed within so that some shorter jobs can execute immediately without needing
to wait for a potentially long running job.

Note that in future this may/will be adapted to work with the messagequeue module to provide a more distributed
approach to solving a very similar problem. The messagequeue module is a lot more generalised than this approach,
and while this module solves a specific problem, it may in fact be better working in with the messagequeue module

## Multiple Steps {#multiple-steps}

It is highly recommended to use the job steps feature in your jobs.
Job steps are required to avoid long-running jobs from being falsely detected as stale
(see [Troubleshooting: Jobs are marked as broken when they aren't](troubleshooting#broken)).

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

## Advanced Job Setup

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
