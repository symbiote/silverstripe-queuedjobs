---
title: Configuring Runners
summary: Configuring alternative job runners to improve throughput
icon: cogs
---

# Configuring runners

## Overview

The default runner [`QueueRunner`](api:Symbiote\QueuedJobs\Tasks\Engines\QueueRunner)
isn't great for any serious queue throughput,
and causes delays before a job gets picked up. Here's some alternatives.

You might also be interested in ways to run [immediate jobs](./04_immediate-jobs.md)
through watchers such as `inotifyd`.

## Using `Doorman` for running jobs {#doorman}

Doorman is included by default, and allows for asynchronous task processing.

This requires that you are running an a unix based system, or within some kind of environment
emulator such as cygwin.

In order to enable this, configure the [`ProcessJobQueueTask`](api:Symbiote\QueuedJobs\Tasks\ProcessJobQueueTask) to use this backend.

```yml
---
Name: localproject
After: '#queuedjobsettings'
---
SilverStripe\Core\Injector\Injector:
  Symbiote\QueuedJobs\Services\QueuedJobService:
    properties:
      queueRunner: %$DoormanRunner
```

See [Multi process execution in Doorman](./07_performance.md#multi-doorman)
for more ways to increase concurrency in Doorman.

## Using `Gearman` for running jobs {#gearman}

- Make sure gearmand is installed
- Get the gearman module from <https://github.com/nyeholt/silverstripe-gearman>
- Create a `_config/queuedjobs.yml` file in your project with the following declaration

```yml
---
Name: localproject
After: '#queuedjobsettings'
---
SilverStripe\Core\Injector\Injector:
  QueueHandler:
    class: Symbiote\QueuedJobs\Services\GearmanQueueHandler
```

- Run the gearman worker using `php gearman/gearman_runner.php` in your SS root dir

This will cause all queuedjobs to trigger immediate via a gearman worker (`src/workers/JobWorker.php`)
EXCEPT those with a StartAfter date set, for which you will STILL need the cron settings from above
