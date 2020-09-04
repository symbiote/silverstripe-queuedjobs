# Performance

## Increase concurrent execution through runners

The default runner only executes one job per minute
if it's set up via `cron`. That's not great.
See [alternative runners](configure-runners.md)
to speed this up, as well as
[Multi Process Execution in Doorman](#multi-doorman).

## Clean up jobs database

Every job is recorded in the database via the `QueuedJobDescriptor` table.
If you're running a lot of them, this table can quickly grow!
This can affect job execution due to slow lookups.
The easiest way around this is to 
[clear out old job entries](index.md#cleanup)
regularly.

## Time and Memory Limits

By default task swill run until either 256mb or the limit specified by php\_ini('memory\_limit') is reached.
For some jobs you might need to increase this value:


```yaml
Symbiote\QueuedJobs\Services\QueuedJobService\QueuedJobsService:
  # Accepts b, k, m, or b suffixes
  memory_limit: 512m
```


You can also enforce a time limit for each queue, after which the task will attempt a restart to release all
resources. By default this is disabled, so you must specify this in your project as below:


```yml
# Force limit to 10 minutes
Symbiote\QueuedJobs\Services\QueuedJobService\QueuedJobsService:
  time_limit: 600
```

## Indexes

```sql
ALTER TABLE `QueuedJobDescriptor` ADD INDEX ( `JobStatus` , `JobType` )
```

## Multi Process Execution in Doorman {#multi-doorman}

The Doorman runner (`Symbiote\QueuedJobs\Tasks\Engines\DoormanRunner`)
supports multi process execution through the
[asyncphp/doorman](https://github.com/asyncphp/doorman/) library.
It works by spawning child processes within the main PHP execution
triggered through a cron job.

The default configuration is limited to a single process.
If you want to allocate more server capacity to running queues,
you can increase the number of processes allowed by changing the default rule:

```yaml
---
Name: myqueuedjobsconfig
---
SilverStripe\Core\Injector\Injector:
  LowUsageRule:
    class: 'AsyncPHP\Doorman\Rule\InMemoryRule'
    properties:
      Processes: 2
      MinimumProcessorUsage: 0
      MaximumProcessorUsage: 50
      MinimumMemoryUsage: 0
      MaximumMemoryUsage: 50
  MediumUsageRule:
    class: 'AsyncPHP\Doorman\Rule\InMemoryRule'
    properties:
      Processes: 1
      MinimumProcessorUsage: 50
      MaximumProcessorUsage: 75
      MinimumMemoryUsage: 50
      MaximumMemoryUsage: 75
  HighUsageRule:
    class: 'AsyncPHP\Doorman\Rule\InMemoryRule'
    properties:
      Processes: 0
      MinimumProcessorUsage: 75
      MaximumProcessorUsage: 100
      MinimumMemoryUsage: 75
      MaximumMemoryUsage: 100
  DoormanRunner:
    properties:
      DefaultRules:
        - '%LowUsageRule'
        - '%MediumUsageRule'
        - '%HighUsageRule'
```

As with all parallel processing architectures, you should be aware of the race conditions that can occur. You cannot depend on a predictable order of execution, or that every process has a predictable state. Use this with caution!


## Ideal job size

How much work should be done by a single job? This is the question you should ask yourself when implementing a new job type.
There is no precise answer. This really depends on your project setup but there are some good practices that should be considered:

* similar size — it's easier to optimise the queue settings and stack size of your project when your jobs are about the same size
* split the job work into steps — this prevents your job running for too long without an update to the job manager and it lowers the risk of the job getting labelled as crashed
* avoid jobs that are too small — jobs that are too small produce a large amount of job management overhead and are thus inefficient
* avoid jobs that are too large — jobs that are too large are difficult to execute as they may cause timeout issues during execution.

As a general rule of thumb, one run of your job's `process()` method should not exceed 30 seconds.

If your job is too large and takes way too long to execute, the job manager may label the job as crashed even though it's still executing.
If this happens you can:

* Add job steps which help the job manager to determine if job is still being processed.
* If you're job is already divided in steps, try dividing the larger steps into smaller ones.
* If your job performs actions that can be completed independently from the others, you can split the job into several smaller dependant jobs (e.g.: there is value even if only one part is completed).

The dependant job approach also allows you to run these jobs concurrently on some project setups.
Job steps, on the other hand, always run in sequence.

Read [Defining Jobs](defining-jobs.md) for different ways to create jobs.
