# Dependant jobs

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
