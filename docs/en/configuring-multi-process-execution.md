# Configuring Multi-process Execution

You can enable multi-process execution by selecting `doorman` as the engine:

	:::yaml
	---
	Name: myqueuedjobsconfig
	After: '#queuedjobsettings'
	---
	Injector:
	  ProcessJobQueueTask:
	    properties:
	      TaskRunner: %$DoormanRunner


By default, this will allow a single child process to complete queued jobs. You can increase the number of processes allowed by changing the default rule:


:::yaml
	---
	Name: myqueuedjobsconfig
	---
	Injector:
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


As with all parallel processing architectures, you should be aware of the race conditions that can occur. You cannot depend on a predictable order of execution, or that every process has a predictable state. Use this with caution!
