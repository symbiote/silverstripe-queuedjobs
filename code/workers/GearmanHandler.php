<?php

/**
 * Add to the worker classes 
 * 
 * @author marcus
 */
interface GearmanHandler {
	public function getName();
	public function handle();
}
