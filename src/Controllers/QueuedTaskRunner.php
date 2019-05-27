<?php

namespace Symbiote\QueuedJobs\Controllers;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\DebugView;
use SilverStripe\Dev\TaskRunner;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Jobs\RunBuildTaskJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Tasks\CreateQueuedJobTask;
use Symbiote\QueuedJobs\Tasks\DeleteAllJobsTask;
use Symbiote\QueuedJobs\Tasks\ProcessJobQueueChildTask;
use Symbiote\QueuedJobs\Tasks\ProcessJobQueueTask;

/**
 * Class QueuedTaskRunner
 *
 * @package Symbiote\QueuedJobs\Controllers
 */
class QueuedTaskRunner extends TaskRunner
{
    /**
     * @var array
     */
    private static $url_handlers = [
        'queue/$TaskName' => 'queueTask',
    ];

    /**
     * @var array
     */
    private static $allowed_actions = [
        'queueTask',
    ];

    /**
     * Tasks on this list will be available to be run only via browser
     *
     * @config
     * @var array
     */
    private static $task_blacklist = [
        ProcessJobQueueTask::class,
        ProcessJobQueueChildTask::class,
        CreateQueuedJobTask::class,
        DeleteAllJobsTask::class,
    ];

    /**
     * Tasks on this list will be available to be run only via jobs queue
     *
     * @config
     * @var array
     */
    private static $queued_only_tasks = [];

    public function index()
    {
        $tasks = $this->getTasks();

        $blacklist = (array) $this->config()->get('task_blacklist');
        $queuedOnlyList = (array) $this->config()->get('queued_only_tasks');
        $backlistedTasks = [];
        $queuedOnlyTasks = [];

        // Web mode
        if (!Director::is_cli()) {
            $renderer = new DebugView();
            echo $renderer->renderHeader();
            echo $renderer->renderInfo(
                "SilverStripe Development Tools: Tasks (QueuedJobs version)",
                Director::absoluteBaseURL()
            );
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

                if (in_array($task['class'], $queuedOnlyList)) {
                    $queuedOnlyTasks[] = $task;

                    continue;
                }

                $queueLink = $base . "dev/tasks/queue/" . $task['segment'];
                $immediateLink = $base . "dev/tasks/" . $task['segment'];

                echo "<li><p>";
                echo "<a href=\"$queueLink\">" . $task['title'] . "</a> <a style=\"font-size: 80%; padding-left: 20px\""
                    . " href=\"$immediateLink\">[run immediately]</a><br />";
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

            echo "<div class=\"options\">";
            echo "<h2>Queueable only tasks</h2>\n";
            echo "<p>These tasks must be be added the queuejobs queue, running it immediately is not allowed.</p>\n";
            echo "<ul>";
            foreach ($queuedOnlyTasks as $task) {
                $queueLink = $base . "dev/tasks/queue/" . $task['segment'];

                echo "<li><p>";
                echo "<a href=\"$queueLink\">" . $task['title'] . "</a><br />";
                echo "<span class=\"description\">" . $task['description'] . "</span>";
                echo "</p></li>\n";
            }
            echo "</ul></div>";

            echo $renderer->renderFooter();

            // CLI mode - revert to default behaviour
        } else {
            return parent::index();
        }
    }


    /**
     * Adds a RunBuildTaskJob to the job queue for a given task
     *
     * @param HTTPRequest $request
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
                /** @var BuildTask $inst */
                $inst = Injector::inst()->create($task['class']);
                if (!$inst->isEnabled()) {
                    $message('The task is disabled');
                    return;
                }

                $title(sprintf('Queuing Task %s', $inst->getTitle()));

                $job = new RunBuildTaskJob($task['class'], $querystring);
                $jobID = Injector::inst()->get(QueuedJobService::class)->queueJob($job);

                $message('Done: queued with job ID ' . $jobID);
                $adminUrl = Director::baseURL() . AdminRootController::config()->get('url_base');
                $adminLink = $adminUrl . "/queuedjobs/" . str_replace('\\', '-', QueuedJobDescriptor::class);
                $message("Visit <a href=\"$adminLink\">queued jobs admin</a> to see job status");
                return;
            }
        }

        $message(sprintf('The build task "%s" could not be found', Convert::raw2xml($name)));
    }
}
