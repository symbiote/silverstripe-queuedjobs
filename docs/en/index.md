# Overview

## Installation

Install the cronjob needed to manage all the jobs within the system. It is best to have this execute as the
same user as your webserver - this prevents any problems with file permissions.

```
*/1 * * * * /path/to/silverstripe/vendor/bin/sake dev/tasks/ProcessJobQueueTask
```

To test things are working, run the following command to create a dummy task

```
vendor/bin/sake dev/tasks/CreateQueuedJobTask 
```

Every job is tracked as a database record, through `QueuedJobDescriptor` objects.
This means jobs can also be managed via the CMS.
Open up `/admin/queuedjobs` in a browser.
You should see the new job with `Status=New`.
Now either wait for your cron to execute, or trigger execution manually.

```
vendor/bin/sake dev/tasks/ProcessJobQueueTask 
```

The job should now be marked with `Status=Completed`.

## Triggering jobs

```php
$publish = new PublishItemsJob(21);
singleton('QueuedJobService')->queueJob($publish);
```

To schedule a job to be executed at some point in the future, pass a date through with the call to queueJob

```php
// Runs a day from now
$publish = new PublishItemsJob(21);
singleton('QueuedJobService')->queueJob($publish, date('Y-m-d H:i:s', time() + 86400));
```

## Choosing a runner

The default runner (`Symbiote\QueuedJobs\Tasks\Engines\QueueRunner`)
for queued (rather than immediate) jobs
will pick up one job every time it executes. Since that usually happens through
a cron task, and crons can only run once per minute,
this is quite limiting.

The modules comes with more advanced approaches to speed up queues:

 * [Doorman](configure-runners.md): Spawns child PHP processes. Does not have any system dependencies.
 * [Gearman](configure-runners.md): Triggered through a `gearmand` system process
 * [lsyncd](immediate-run-through-lsyncd.md): Works based on watching files.

Note: If you're running a hosting-specific recipe such as
[cwp/cwp-core](https://github.com/silverstripe/cwp-core),
a runner might already be preconfigured for you - in this case Doorman. 

## Cleaning up job entries {#cleanup}

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

## Long-Running Jobs

If your code is to make use of the 'long' jobs, ie that could take days to process, also install another task
that processes this queue. Its time of execution can be left a little longer.

```
*/15 * * * * /path/to/silverstripe/vendor/bin/sake dev/tasks/ProcessJobQueueTask queue=large
```

From your code, add a new job for execution.

```php
use Symbiote\QueuedJobs\Services\QueuedJobService;

$publish = new PublishItemsJob(21);
QueuedJobService::singleton()->queueJob($publish);
```

To schedule a job to be executed at some point in the future, pass a date through with the call to queueJob
The following will run the publish job in 1 day's time from now.

```php
use SilverStripe\ORM\FieldType\DBDatetime;
use Symbiote\QueuedJobs\Services\QueuedJobService;

$publish = new PublishItemsJob(21);
QueuedJobService::singleton()
    ->queueJob($publish, DBDatetime::create()->setValue(DBDatetime::now()->getTimestamp() + 86400)->Rfc2822());
```

## Immediate Jobs

Queued jobs can be executed immediately (instead of being limited by cron's 1 minute interval) by using
a file based notification system. This relies on something like inotifywait to monitor a folder (by
default this is SILVERSTRIPE_CACHE_DIR/queuedjobs) and triggering the ProcessJobQueueTask as above
but passing job=$filename as the argument. An example script is in queuedjobs/scripts that will run
inotifywait and then call the ProcessJobQueueTask when a new job is ready to run.

Note - if you do NOT have this running, make sure to set `QueuedJobService::$use_shutdown_function = true;`
so that immediate mode jobs don't stall. By setting this to true, immediate jobs will be executed after
the request finishes as the php script ends.


## Jobs queue pause setting

It's possible to enable a setting which allows the pausing of the queued jobs processing. To enable it, add following code to your config YAML file:

```yaml
Symbiote\QueuedJobs\Services\QueuedJobService:
  lock_file_enabled: true
  lock_file_path: '/shared-folder-path'
```
## Default Jobs {#default-jobs}

Some jobs should always be either running or queued to run, things like data refreshes or periodic clean up jobs, we call these Default Jobs.
See [Default Jobs](default-jobs.md) for information on how to
disable or pause these jobs.

### Understanding job states

It's really useful to understand how job state changes during the job lifespan as it makes troubleshooting easier.
Following chart shows the whole job lifecycle:

![JobStatus](docs/job_status.jpg)

* every job starts in `New` state
* every job should eventually reach either `Complete`, `Broken` or `Paused`
* `Cancelled` state is not listed here as the queue runner will never transition job to that state as it is reserved for user triggered actions
* progress indication is either state change or step increment
* every job can be restarted however there is a limit on how many times (see `stall_threshold` config)

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
