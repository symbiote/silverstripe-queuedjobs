# SilverStripe Queued Jobs Module

[![Build Status](https://travis-ci.org/symbiote/silverstripe-queuedjobs.svg?branch=master)](https://travis-ci.org/symbiote/silverstripe-queuedjobs)
[![Scrutinizer](https://scrutinizer-ci.com/g/symbiote/silverstripe-queuedjobs/badges/quality-score.png)](https://scrutinizer-ci.com/g/symbiote/silverstripe-queuedjobs/)
[![SilverStripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

## Overview

The Queued Jobs module provides a framework for SilverStripe developers to
define long running processes that should be run as background tasks.
This asynchronous processing allows users to continue using the system
while long running tasks proceed when time permits. It also lets
developers set these processes to be executed in the future.

The module comes with

* A section in the CMS for viewing a list of currently running jobs or scheduled jobs.
* An abstract skeleton class for defining your own jobs.
* A task that is executed as a cronjob for collecting and executing jobs.
* A pre-configured job to cleanup the QueuedJobDescriptor database table.

## Installation

```
composer require symbiote/silverstripe-queuedjobs
```

Now setup a cron job:

```
*/1 * * * * /path/to/silverstripe/vendor/bin/sake dev/tasks/ProcessJobQueueTask
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
          email: 'admin@email.com'
        # Minimal implementation will send alerts but not recreate
        AnotherTitle:
          type: 'AJob'
          filter:
            JobTitle: 'A job'
```

## Configuring the CleanupJob

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

## Health Checking

Jobs track their execution in steps - as the job runs it increments the "steps" that have been run. Periodically jobs
are checked to ensure they are healthy. This asserts the count of steps on a job is always increasing between health
checks. By default health checks are performed when a worker picks starts running a queue.

In a multi-worker environment this can cause issues when health checks are performed too frequently. You can disable the
automatic health check with the following configuration:

```yaml
Symbiote\QueuedJobs\Services\QueuedJobService:
  disable_health_check: true
```

In addition to the config setting there is a task that can be used with a cron to ensure that unhealthy jobs are
detected:

```
*/5 * * * * /path/to/silverstripe/vendor/bin/sake dev/tasks/CheckJobHealthTask
```
## Special job variables

It's good to be aware of special variables which should be used in your job implementation.

* `totalSteps` (integer) - defaults to `0`, maps to `TotalSteps` DB column, information only
* `currentStep` (integer) - defaults to `0`, maps to `StepsProcessed` DB column, Queue runner uses this to determine if job is stalled or not
* `isComplete` (boolean) - defaults to `false`, related to `JobStatus` DB column, Queue runner uses this to determine if job is completed or not

See `copyJobToDescriptor` for more details on the mapping between `Job` and `JobDescriptor`.

### Total steps

Represents total number of steps needed to complete the job.

* this variable should be set to the number of steps you expect your job to go though during its execution
* this needs to be done in the `setup()` function of your job, the value should not be changed after that
* this variable is not used by the Queue runner and is only meant to indicate how many steps are needed (information only)
* it is recommended to avoid using this variable inside the `process()` function of your job instead, determine if your job is complete based on the job data (if there are any more items left to process)

### Current step

Represents number of steps processed.

* your job should increment this variable each time a job step was successfully completed
* Queue runner will read this variable to determine if your job is stalled or not
* it is recommended to return out of the `process()` function each time you increment this variable
* this allows the queue runner to create a checkpoint by saving your job progress into the job descriptor which is stored in the DB

### Is complete

Represents the job state (complete or not).

* setting this variable to `true` will give a signal to the queue runner to mark the job as successfully completed
* your job should set this variable to `true` only once

### Example

This example illustrates how each special variable should be used in your job implementation.

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

        // Set the total steps to the number of items we want to process
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
        $this->doSomethingWithTheItem($item);

        $this->remaining = $remaining;

        // Updating current step tells the Queue runner that the job is progressing
        $this->currentStep += 1;

        // check for job completion
        if (count($remaining) > 0) {
            // Note that we do not process more than one item at a time
            // this makes the Queue runner save the job progress into DB
            // in case something goes wrong the job will be resumed from the last checkpoint
            return;
        }

        // Queue runner will mark this job as finished
        $this->isComplete = true;
    }
}
```

## Troubleshooting

To make sure your job works, you can first try to execute the job directly outside the framework of the
queues - this can be done by manually calling the *setup()* and *process()* methods. If it works fine
under these circumstances, try having *getJobType()* return *QueuedJob::IMMEDIATE* to have execution
work immediately, without being persisted or executed via cron. If this works, next make sure your
cronjob is configured and executing correctly.

If defining your own job classes, be aware that when the job is started on the queue, the job class
is constructed _without_ parameters being passed; this means if you accept constructor args, you
_must_ detect whether they're present or not before using them. See [this issue](https://github.com/symbiote/silverstripe-queuedjobs/issues/35)
and [this wiki page](https://github.com/symbiote/silverstripe-queuedjobs/wiki/Defining-queued-jobs) for
more information.

If defining your own jobs, please ensure you follow PSR conventions, i.e. use "YourVendor" rather than "SilverStripe".

Ensure that notifications are configured so that you can get updates or stalled or broken jobs. You can
set the notification email address in your config as below:


```yaml
SilverStripe\Control\Email\Email:
  queued_job_admin_email: support@mycompany.com
```

## Documentation

 * [Overview](docs/en/index.md): Running and triggering jobs. Different queue types and job lifecycles.
 * [Defining Jobs](docs/en/defining-jobs.md): Jobs are just PHP classes. Learn how to write your own.
 * [Performance](docs/en/performance.md): Advice on job performance in large or highly concurrent setups
 * [Troubleshooing](docs/en/troubleshooting.md)
 * [Dependant Jobs](docs/en/dependant-jobs.md) 
 * [Immediate jobs](docs/en/immediate-run.md) 
 * [Unit Testing](docs/en/unit-testing.md) 

## Show job data

In case you need an easy access to additonal job data via CMS for debug purposes enable the `show_job_data` option by including the configuration below.

```yaml
Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor:
  show_job_data: true
```

This will add Job data and Messages raw tabs to the job descriptor edit form. Displayed information is read only.

## Contributing

### Translations

Translations of the natural language strings are managed through a third party translation interface, transifex.com. Newly added strings will be periodically uploaded there for translation, and any new translations will be merged back to the project source code.

Please use [https://www.transifex.com/projects/p/silverstripe-queuedjobs](https://www.transifex.com/projects/p/silverstripe-queuedjobs) to contribute translations, rather than sending pull requests with YAML files.
