###############################################
Module
###############################################

Maintainer Contact
-----------------------------------------------
Marcus Nyeholt

<marcus (at) silverstripe (dot) com (dot) au>

Requirements
-----------------------------------------------
SilverStripe 2.4.x

Documentation
-----------------------------------------------

A QueuedJobDescriptor is the stored representation of a piece of work that could take a while to execute,
because of which it is desireable to not have it executing in parallel to other jobs.

A queued job should always attempt to report how many potential dataobjects will be affected by being executed;
this will determine which queue it is placed within so that some shorter jobs can execute immediately without needing
to wait for a potentially long running job.

Note that in future this may/will be adapted to work with the messagequeue module to provide a more distributed
approach to solving a very similar problem. The messagequeue module is a lot more generalised than this approach,
and while this module solves a specific problem, it may in fact be better working in with the messagequeue module


Quick Usage Overview
-----------------------------------------------

* Install the cronjob needed to manage all the jobs within the system. It is best to have this execute as the
same user as your webserver - this prevents any problems with file permissions.

> */1 * * * * php /path/to/silverstripe/sapphire/cli-script.php dev/tasks/ProcessJobQueueTask

* If your code is to make use of the 'long' jobs, ie that could take days to process, also install another task
that processes this queue. Its time of execution can be left a little longer.

> */15 * * * * php /path/to/silverstripe/sapphire/cli-script.php dev/tasks/ProcessJobQueueTask queue=2

* From your code, add a new job for execution.

	$publish = new PublishItemsJob(21);
	singleton('QueuedJobService')->queueJob($publish);

* To schedule a job to be executed at some point in the future, pass a date through with the call to queueJob
The following will run the publish job in 1 day's time from now. 

	$publish = new PublishItemsJob(21);
	singleton('QueuedJobService')->queueJob($publish, date('Y-m-d H:i:s', time() + 86400));

API
-----------------------------------------------

The module comes with an AbstractQueuedJob class that defines many of the boilerplate functionality for the
job to execute within the framework. An example job can be found in queuedjobs/code/jobs/PublishItemsJob.php.
The key points to be aware of are

* _Creating a new job_ When defining the constructor for your job, be aware that the QueuedJobService will, when
loading the job for execution, create an object of your job type without passing any parameters. Therefore,
if you want to pass parameters when initially creating the job, make sure to provide defaults
(eg *__construct($param=null)*), and that their presence is detected before being used. Additionally, make sure
to return a title via *getTitle()* - this is so that users can be shown what's running in the CMS admin.
* _Job Signatures_ When a job is added to the job queue, it is assigned a unique key based on the parameters of the job
(see AbstractQueuedJob->getSignature()). If a job is already in the queue with the same signature, the new job
is NOT queued up; this prevents duplicate jobs being added to a queue, but in some cases that may be the
intention. If so, you'll need to override the getSignature() method in your custom job class and make sure
to return a unique signature for each instantiation of the job.
* _Job Type_ Typically, you'll want to use the default QueuedJob::QUEUED type for the job. In some cases you may
want to use the QueuedJob::IMMEDIATE type - this forces execution of the job at the end of the currently
processing request. Carefully consider what you're trying to do in this case - it may just be better to
execute the code directly.
* _queueJob()_ To actually add a job to a queue, you call QueuedJobService->queueJob(Job $job, $startAfter=null).
This will add the job to whichever queue is relevant, with whatever 'startAfter' (a date in Y-m-d H:i:s format)
to start execution after particular datetime.
* _Job Properties_ QueuedJobs inherited from the AbstractQueuedJob have a default mechanism for persisting values via 
the __set and __get mechanism that stores items in the *jobData* map, which is serialize()d between executions of
the job processing. The framework itself expects the following properties to be set by a job to ensure that jobs
execute smoothly and can be paused/stopped/restarted.
  * *totalSteps* - the total number of steps in the job
  * *currentStep* - the current step in the job
  * *isComplete* - whether the job is complete yet. This MUST be set to true for the job to be removed from the queue
  * *messages* - an array that contains a list of messages about the job that can be displayed to the user via the CMS
* _Switching Users_ Jobs can be specified to run as a particular user. By default this is the user that created
the job, however it can be changed by setting the value returned by setting a user via the RunAs
relationship of the job.
* _Job Execution_ The following sequence occurs at job execution
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

Troubleshooting
-----------------------------------------------

To make sure your job works, you can first try to execute the job directly outside the framework of the
queues - this can be done by manually calling the *setup()* and *process()* methods. If it works fine
under these circumstances, try having *getJobType()* return *QueuedJob::IMMEDIATE* to have execution
work immediately, without being persisted or executed via cron. If this works, next make sure your
cronjob is configured and executing correctly. 