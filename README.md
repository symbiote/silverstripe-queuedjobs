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

<?php

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

API
-----------------------------------------------

Troubleshooting
-----------------------------------------------

