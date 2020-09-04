# Troubleshooting

## Jobs aren't running

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

## Jobs are broken but I can't see errors {#cant-see-errors}

Make sure that you've got the right loggers configured.

Check for messages on the job database record (`SavedJobMessages`).

When using the Doorman runner, messages are only recorded on the job,
and not visible on the command line (see [bug report](https://github.com/asyncphp/doorman/issues/23)). 

## Jobs are executed more than once

A long running job _may_ fool the system into thinking it has gone away (ie the job health check fails because
`currentStep` hasn't been incremented). To avoid this scenario, you can set `$this->currentStep = -1` in your job's
constructor, to prevent any health checks detecting the job.****

## Jobs are marked as broken when they aren't

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

## HTTP_HOST not set errors

```
Director::protocolAndHost() lacks sufficient information - HTTP_HOST not set.
```

The CLI execution environment doesn't know about your domains by default.
If anything in your jobs relies on this, you'll need to add it
an `SS_BASE_URL` to your `.env` file:

```
SS_BASE_URL="http://localhost/"
```

## php command not found

If you are setting up the crons under Plesk 10, you might receive an email:

_-: php: command not found

This restriction is a security feature coming with Plesk 10.
On round about page 150 of the plesk Administrator Guide you will find a solution to enable scheduled tasks which use the command line. (But the latest Guide for 10.3.1 mentions /usr/local/psa/admin/bin/server_pref -u -crontab-secure-shell "/bin/sh" although "server_pref" doesnt exit.
Since we are using a dedicated server for only one customer, we defined the crons under "Server Management"->"Tools & Utilities"->"Scheduled Tasks"->"root". The security restrictions of plesk are not involved then. 

