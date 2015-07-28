<?php

use AsyncPHP\Doorman\Manager\ProcessManager;

class DoormanProcessManager extends ProcessManager
{
	/**
	 * @inheritdoc
	 *
	 * @return string
	 */
	public function getWorker() {
		return BASE_PATH . "/framework/cli-script.php dev/tasks/ProcessJobQueueChildTask";
	}
}
