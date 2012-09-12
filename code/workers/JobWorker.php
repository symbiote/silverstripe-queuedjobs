<?php

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class JobWorker implements GearmanHandler {
	
	public function getName() {
		return 'jobqueueExecute';
	}
	
	public function handle() {
		echo "Executed";
	}
}
