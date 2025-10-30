---
title: Troubleshooting
summary: Common issues and resolutions
icon: wrench
---

# Troubleshooting

## Jobs are not running

To make sure your job works, you can first try to execute the job directly outside the framework of the
queues - this can be done by manually calling the *setup()* and *process()* methods. If it works fine
under these circumstances, try having *getJobType()* return *QueuedJob::IMMEDIATE* to have execution
work immediately, without being persisted or executed via cron. If this works, next make sure your
cronjob is configured and executing correctly.

If defining your own job classes, be aware that when the job is started on the queue, the job class
is constructed *without* parameters being passed; this means if you accept constructor args, you
*must* detect whether they're present or not before using them. See [this issue](https://github.com/symbiote/silverstripe-queuedjobs/issues/35).

If defining your own jobs, please ensure you follow PSR conventions, i.e. use `YourVendor` rather than `SilverStripe`.

Ensure that notifications are configured so that you can get updates or stalled or broken jobs. You can
set the notification email address in your config as below:

```yml
SilverStripe\Control\Email\Email:
  queued_job_admin_email: support@mycompany.com
```

## Jobs are broken but I cannot see errors

Make sure that you've got the right loggers configured.

Check for messages on the job database record in  the `SavedJobMessages` column.

When using the Doorman runner, messages are only recorded on the job,
and not visible on the command line (see [bug report](https://github.com/asyncphp/doorman/issues/23)).

## Jobs are executed more than once

A long running job *mSymbiote\QueuedJobs\Services\QueuedJobay* fool the system into thinking it has gone away (ie the job health check fails because
`currentStep` hasn't been incremented). To avoid this scenario, you can set `$this->currentStep = -1` in your job's
constructor, to prevent any health checks detecting the job.

## Jobs are marked as broken when they are mot

Jobs track their execution in steps - as the job runs it increments the "steps" that have been run. Periodically jobs
are checked to ensure they are healthy. This asserts the count of steps on a job is always increasing between health
checks. By default health checks are performed when a worker picks starts running a queue.

In a multi-worker environment this can cause issues when health checks are performed too frequently. You can disable the
automatic health check with the following configuration:

```yml
Symbiote\QueuedJobs\Services\QueuedJobService:
  disable_health_check: true
```

Job health is checked automatically in queue processing.
You might also need to disable the [`CheckJobHealthTask`](api:Symbiote\QueuedJobs\Tasks\CheckJobHealthTask) if it's set up as a cron job.

Alternatively, you can increase the TTL before jobs are considered stalled:

```yml
Symbiote\QueuedJobs\Services\QueuedJobService:
  worker_ttl: 'PT120M'
```

The [`RunBuildTaskJob`](api:Symbiote\QueuedJobs\Jobs\RunBuildTaskJob) is excluded from these health checks because it can't use steps,
so you'll need to find other ways to ensure this type of job stays healthy when using it.

## `HTTP_HOST` not set errors

```text
Director::protocolAndHost() lacks sufficient information - HTTP_HOST not set.
```

The CLI execution environment doesn't know about your domains by default.
If anything in your jobs relies on this, you'll need to add it
an `SS_BASE_URL` to your `.env` file:

```text
SS_BASE_URL="http://localhost/"
```

## PHP command not found

If you are setting up the crons under Plesk 10, you might receive an email:

```text
_-: php: command not found
```

This restriction is a security feature coming with Plesk 10.
On round about page 150 of the plesk Administrator Guide you will find a solution to enable scheduled tasks which use the command line. (But the latest Guide for 10.3.1 mentions `/usr/local/psa/admin/bin/server_pref -u -crontab-secure-shell "/bin/sh"` although "server_pref" doesnt exit.
Since we are using a dedicated server for only one customer, we defined the crons under "Server Management"->"Tools & Utilities"->"Scheduled Tasks"->"root". The security restrictions of plesk are not involved then.

## Broken jobs

Sometimes, jobs break without having any issues with their implementation but rather an external factor is the root cause, for example database table lock may prevent a DB write.
Most common scenario is "publish" action related jobs such as scheduled publish feature which may experience DB deadlocks on the versioned table as this can be frequently accessed.

For this scenario it's recommended to configure automatic job retries.
Configuration has some flexibility on number of retries and the timing of the retry attempts.

### Basic configuration

This configuration is recommended as a good starting point when trying to set up automatic retries.

* `max_retry_attempts` - number of retry attempts, this allows to control how many times a broken job is retried
* `initial_retry_delay` - minimal waiting time in seconds before the retry attempt is executed, this helps spread retry attempts apart from each other

This configuration is applied to your job class.

### Advanced configuration

In case you have specific scenarios that can't be quite covered by basic configuration you can use the advanced configuration which provides more control over the automated job retries.

Use the sample configuration below as a starting point and adjust as needed.
This code snippet need to be placed into your job class.

* `retry_falloff_multiplier` provides the capability to increase the retry period with each retry attempt, defaults to `1`
* `retry_falloff_multiplier_variance` acts as a modifier for `retry_falloff_multiplier`, needs to be always lower value compared to `retry_falloff_multiplier`, this allows you to break up clusters of broken jobs which can prevent load spikes and DB deadlocks from forming, defaults to `0`

**Examples**

```php
namespace App\Jobs;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

class MyJob extends AbstractQueuedJob
{
    // Linear retry pattern
    private static int $max_retry_attempts = 4;
    private static int $initial_retry_delay = 600;
    private static float $retry_falloff_multiplier = 1;
    private static float $retry_falloff_multiplier_variance = 0;

    // First retry attempt - Retry after 10 minutes
    // Second retry attempt - Retry after 10 minutes
    // Third retry attempt - Retry after 10 minutes
    // Fourth retry attempt - Retry after 10 minutes

    // Exponential retry pattern
    private static int $max_retry_attempts = 4;
    private static int $initial_retry_delay = 600;
    private static float $retry_falloff_multiplier = 2;
    private static float $retry_falloff_multiplier_variance = 0;

    // First retry attempt - Retry after 10 minutes
    // Second retry attempt - Retry after 20 minutes
    // Third retry attempt - Retry after 40 minutes
    // Fourth retry attempt - Retry after 80 minutes

    // Retry pattern with spread
    private static int $max_retry_attempts = 4;
    private static int $initial_retry_delay = 600;
    private static float $retry_falloff_multiplier = 1;
    private static float $retry_falloff_multiplier_variance = 0.2;

    // First retry attempt - Retry after 8 to 12 minutes
    // Second retry attempt - Retry after 6.4 to 14.4 minutes
    // Third retry attempt - Retry after 5.1 to 27.4 minutes
    // Fourth retry attempt - Retry after 4 to 38.4 minutes
}
```

#### Cluster breaking configuration

This configuration is recommended for dealing with clusters of broken jobs.
A fixed retry delay typically doesn't help as all jobs will likely be retried in roughly the same time which will repeat the situation that caused the initial cluster to form.
This scenario is best handled by introducing random delay which spreads the jobs and thus eliminates the cluster.
It's recommended to refine this configuration in case you have multiple types of jobs that have significantly different priority.
Higher priority jobs should have lower offset and spread compared to lower priority jobs to minimise waiting times to process high priority jobs.

PHP config

```php
namespace App\Jobs;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

class MyJob extends AbstractQueuedJob
{
    private static int $max_retry_attempts = 5;
    private static int $initial_retry_delay = 600;
    private static float $retry_falloff_multiplier = 1.2;
    private static float $retry_falloff_multiplier_variance = 0.2;

    // First retry attempt - Retry after 10 to 19.6 minutes
    // Second retry attempt - Retry after 10 to 27.4 minutes
    // Third retry attempt - Retry after 10 to 38.4 minutes
    // Fourth retry attempt - Retry after 10 to 53.7 minutes
    // Fifth retry attempt - Retry after 10 to 75.2 minutes
}
```

Global configuration is available on the `QueuedJobService` class:

* `job_retry_sentinel` - this represents separation of job retry and job processing mechanism to avoid potential edge cases, defaults to 1 minute
* `job_retry_limit` - how many broken jobs can be retries per a single execution of `runQueue()`, set to `0` to disable job retries, defaults to `0` (globally disabled), recommended safe value is `10`
* `job_retry_status` - defines the job status transformation map, this allows to customise how the job statuses change during a job retry, defaults to `New` for `Broken` jobs and `Waiting` for `Paused` jobs

Overall, it's recommended to keep the broken job retries configuration applied to only those jobs that needed it.
Incorrectly configured broken jobs retry may cause queue job processing delays.

**Example scenario**

We have a "Scheduled publish job" which is high priority, and we want to get it executed as close to the scheduled time as possible.
This job must not be executed after certain period of time, let's say four hours, as it could lead to unintentionally publishing draft content which was produced while job was waiting for a retry.

We have a "CDN flush job" which is low priority, and we want to get it executed ideally as soon as possible but having it delayed even for days is not a big deal.
It's still worthwhile executing let's say even after two days of waiting as the CDN cache expiry is six days.

Both of these job types are aiming to avoid clustering.
For "Scheduled publish job" we want to avoid clustering around DB deadlocks.
For "CDN flush job" we want to avoid clustering around CDN API downtimes.

These two jobs need "Cluster breaking configuration" but they need to use different time periods to reflect the priority of processing of these jobs.
