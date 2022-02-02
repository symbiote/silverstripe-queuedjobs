# Default Jobs

## Overview

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

## Pausing Default Jobs

If you need to stop a default job from raising alerts and being recreated, set an existing copy of the job to Paused in the CMS.

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
          # Make this job specific to only certain queue type (see QueuedJob interface)
          queue: 1 # This would make this job be picked up only by Immediate queue
        # Minimal implementation will send alerts but not recreate
        AnotherTitle:
          type: 'AJob'
          filter:
            JobTitle: 'A job'
```

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
