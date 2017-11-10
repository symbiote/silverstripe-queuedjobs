<?php

class QueuedTaskRunner extends TaskRunner
{

    private static $url_handlers = array(
        'queue/$TaskName' => 'queueTask'
    );

    private static $allowed_actions = array(
        'queueTask'
    );

    private static $task_blacklist = array(
        'ProcessJobQueueTask',
        'ProcessJobQueueChildTask',
        'CreateQueuedJobTask',
    );

    public function index()
    {
        $tasks = $this->getTasks();

        $blacklist = (array)$this->config()->task_blacklist;
        $backlistedTasks = array();

        // Web mode
        if(!Director::is_cli()) {
            $renderer = new DebugView();
            $renderer->writeHeader();
            $renderer->writeInfo("SilverStripe Development Tools: Tasks (QueuedJobs version)", Director::absoluteBaseURL());
            $base = Director::absoluteBaseURL();

            echo "<div class=\"options\">";
            echo "<h2>Queueable jobs</h2>\n";
            echo "<p>By default these jobs will be added the job queue, rather than run immediately</p>\n";
            echo "<ul>";
            foreach ($tasks as $task) {
                if (in_array($task['class'], $blacklist)) {
                    $backlistedTasks[] = $task;
                    continue;
                }

                $queueLink = $base . "dev/tasks/queue/" . $task['segment'];
                $immediateLink = $base . "dev/tasks/" . $task['segment'];

                echo "<li><p>";
                echo "<a href=\"$queueLink\">" . $task['title'] . "</a> <a style=\"font-size: 80%; padding-left: 20px\" href=\"$immediateLink\">[run immediately]</a><br />";
                echo "<span class=\"description\">" . $task['description'] . "</span>";
                echo "</p></li>\n";
            }
            echo "</ul></div>";

            echo "<div class=\"options\">";
            echo "<h2>Non-queueable tasks</h2>\n";
            echo "<p>These tasks shouldn't be added the queuejobs queue, but you can run them immediately.</p>\n";
            echo "<ul>";
            foreach ($backlistedTasks as $task) {
                $immediateLink = $base . "dev/tasks/" . $task['segment'];

                echo "<li><p>";
                echo "<a href=\"$immediateLink\">" . $task['title'] . "</a><br />";
                echo "<span class=\"description\">" . $task['description'] . "</span>";
                echo "</p></li>\n";
            }
            echo "</ul></div>";

            $renderer->writeFooter();

        // CLI mode - revert to default behaviour
        } else {
            return parent::index();
        }
    }


    /**
     * Adds a RunBuildTaskJob to the job queue for a given task
     * @param SS_HTTPRequest $request
     */
    public function queueTask($request)
    {
        $name = $request->param('TaskName');
        $tasks = $this->getTasks();

        $variables = $request->getVars();
        unset($variables['url']);
        unset($variables['flush']);
        unset($variables['flushtoken']);
        unset($variables['isDev']);
        $querystring = http_build_query($variables);

        $title = function ($content) {
            printf(Director::is_cli() ? "%s\n\n" : '<h1>%s</h1>', $content);
        };

        $message = function ($content) {
            printf(Director::is_cli() ? "%s\n" : '<p>%s</p>', $content);
        };

        foreach ($tasks as $task) {
            if ($task['segment'] == $name) {
                $inst = Injector::inst()->create($task['class']); /** @var BuildTask $inst */
                if (!$inst->isEnabled()) {
                    $message('The task is disabled');
                    return;
                }

                $title(sprintf('Queuing Task %s', $inst->getTitle()));

                $job = new RunBuildTaskJob($task['class'], $querystring);
                $jobID = Injector::inst()->get('QueuedJobService')->queueJob($job);

                $message('Done: queued with job ID ' . $jobID);
                $adminLink = Director::baseURL() . "admin/queuedjobs/QueuedJobDescriptor";
                $message("Visit <a href=\"$adminLink\">queued jobs admin</a> to see job status");
                return;
            }
        }

        $message(sprintf('The build task "%s" could not be found', Convert::raw2xml($name)));
    }
}
