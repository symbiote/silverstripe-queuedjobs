<?php

namespace SilverStripe\QueuedJobs\Jobs;

use AsyncPHP\Doorman\Manager\ProcessManager;

class DoormanProcessManager
{
    /**
     * @var ProcessManager
     */
    protected $manager;

    /**
     * Get the Doorman ProcessManager with a customised worker path
     *
     * @return ProcessManager
     */
    public function getManager()
    {
        if ($this->manager === null) {
            $this->manager = new ProcessManager;
            $this->manager->setWorker(BASE_PATH . '/framework/cli-script.php dev/tasks/ProcessJobQueueChildTask');
        }
        return $this->manager;
    }
}
