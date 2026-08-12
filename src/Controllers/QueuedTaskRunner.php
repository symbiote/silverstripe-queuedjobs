<?php

namespace Symbiote\QueuedJobs\Controllers;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\DebugView;
use SilverStripe\Dev\TaskRunner;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\ModelData;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Jobs\RunBuildTaskJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Tasks\CreateQueuedJobTask;
use Symbiote\QueuedJobs\Tasks\DeleteAllJobsTask;
use Symbiote\QueuedJobs\Tasks\ProcessJobQueueTask;

/**
 * Class QueuedTaskRunner
 *
 * @package Symbiote\QueuedJobs\Controllers
 */
class QueuedTaskRunner extends TaskRunner
{
    private static array $url_handlers = [
        'queue/$TaskName' => 'queueTask',
    ];

    private static array $allowed_actions = [
        'queueTask',
    ];

    private static array $css = [
        'symbiote/silverstripe-queuedjobs:client/styles/task-runner.css',
    ];

    /**
     * Tasks on this list will not be available to run via the jobs queue
     */
    private static array $task_blacklist = [
        ProcessJobQueueTask::class,
        CreateQueuedJobTask::class,
        DeleteAllJobsTask::class,
    ];

    /**
     * Tasks on this list will be available to be run only via jobs queue
     */
    private static array $queued_only_tasks = [];

    public function index()
    {
        $baseUrl = Director::absoluteBaseURL();
        $tasks = $this->getTasks();

        $blacklist = (array) $this->config()->get('task_blacklist');
        $queuedOnlyList = (array) $this->config()->get('queued_only_tasks');
        $backlistedTasks = [];
        $queuedOnlyTasks = [];

        $taskList = ArrayList::create();

        // universal tasks
        foreach ($tasks as $task) {
            if (in_array($task['class'], $blacklist ?? [])) {
                $backlistedTasks[] = $task;

                continue;
            }

            if (in_array($task['class'], $queuedOnlyList ?? [])) {
                $queuedOnlyTasks[] = $task;

                continue;
            }

            $taskList->push(ArrayData::create([
                'QueueLink' => Controller::join_links($baseUrl, 'dev/tasks/queue', $task['segment']),
                'TaskLink' => Controller::join_links($baseUrl, 'dev/tasks', $task['segment']),
                'Title' => $task['title'],
                'Description' => $task['description'],
                'Type' => 'universal',
                'Parameters' => $task['parameters'],
                'Help' => $task['help'],
            ]));
        }

        // Non-queueable tasks
        foreach ($backlistedTasks as $task) {
            $taskList->push(ArrayData::create([
                'TaskLink' => Controller::join_links($baseUrl, 'dev/tasks', $task['segment']),
                'Title' => $task['title'],
                'Description' => $task['description'],
                'Type' => 'immediate',
                'Parameters' => $task['parameters'],
                'Help' => $task['help'],
            ]));
        }

        // Queue only tasks
        foreach ($queuedOnlyTasks as $task) {
            $taskList->push(ArrayData::create([
                'QueueLink' => Controller::join_links($baseUrl, 'dev/tasks/queue', $task['segment']),
                'Title' => $task['title'],
                'Description' => $task['description'],
                'Type' => 'queue-only',
                'Parameters' => $task['parameters'],
                'Help' => $task['help'],
            ]));
        }

        $renderer = DebugView::create();
        $header = $renderer->renderHeader();
        $header = $this->addCssToHeader($header);

        $data = [
            'Tasks' => $taskList,
            'Header' => $header,
            'Footer' => $renderer->renderFooter(),
            'Info' => $renderer->renderInfo('SilverStripe Development Tools: Tasks (QueuedJobs version)', $baseUrl),
        ];

        return ModelData::create()->renderWith(static::class, $data);
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
        $querystring = http_build_query($variables ?? []);

        $title = function ($content) {
            printf(Environment::isCli() ? "%s\n\n" : '<h1>%s</h1>', $content);
        };

        $message = function ($content) {
            printf(Environment::isCli() ? "%s\n" : '<p>%s</p>', $content);
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
                $adminLink = Controller::join_links(
                    Director::baseURL(),
                    AdminRootController::config()->get('url_base'),
                    'queuedjobs',
                    str_replace('\\', '-', QueuedJobDescriptor::class)
                );
                $message("Visit <a href=\"$adminLink\">queued jobs admin</a> to see job status");
                return;
            }
        }

        $message(sprintf('The build task "%s" could not be found', Convert::raw2xml($name)));
    }
}
