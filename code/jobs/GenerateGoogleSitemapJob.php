<?php

/**
 * A job for generating a site's google sitemap
 *
 * If the sitemap module is installed, uses information from that to populate things
 *
 * @author marcus@silverstripe.com.au
 * @license http://silverstripe.org/bsd-license/
 */
class GenerateGoogleSitemapJob extends AbstractQueuedJob {

	public static $regenerate_time = 43200;

	public function __construct() {
		$this->pagesToProcess = DB::query('SELECT ID FROM "SiteTree_Live" WHERE "ShowInSearch"=1')->column();
		$this->currentStep = 0;
		$this->totalSteps = count($this->pagesToProcess);
	}

	/**
	 * Sitemap job is going to run for a while...
	 */
	public function getJobType() {
		return QueuedJob::QUEUED;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return _t('GenerateSitemapJob.REGENERATE', 'Regenerate Google sitemap .xml file');
	}

	/**
	 * Return a signature for this queued job
	 * 
	 * For the generate sitemap job, we only ever want one instance running, so just use the class name
	 * 
	 * @return String
	 */
	public function getSignature() {
		return md5(get_class($this));
	}

	/**
	 * Note that this is duplicated for backwards compatibility purposes...
	 */
	public function  setup() {
		parent::setup();
		increase_time_limit_to();

		$restart = $this->currentStep == 0;
		if (!$this->tempFile || !file_exists($this->tempFile)) {
			$tmpfile = tempnam(getTempFolder(), 'sitemap');
			if (file_exists($tmpfile)) {
				$this->tempFile = $tmpfile;
			}
			$restart = true;
		}

		if ($restart) {
			$this->pagesToProcess = DB::query('SELECT ID FROM SiteTree_Live WHERE ShowInSearch=1')->column();
		}
	}

	/**
	 * On any restart, make sure to check that our temporary file is being created still. 
	 */
	public function prepareForRestart() {
		parent::prepareForRestart();
		// if the file we've been building is missing, lets fix it up
		if (!$this->tempFile || !file_exists($this->tempFile)) {
			$tmpfile = tempnam(getTempFolder(), 'sitemap');
			if (file_exists($tmpfile)) {
				$this->tempFile = $tmpfile;
			}
			$this->currentStep = 0;
			$this->pagesToProcess = DB::query('SELECT ID FROM SiteTree_Live WHERE ShowInSearch=1')->column();
		}
	}

	public function process() {
		if (!$this->tempFile) {
			throw new Exception("Temporary sitemap file has not been set");
		}
		
		if (!file_exists($this->tempFile)) {
			throw new Exception("Temporary file $this->tempFile has been deleted!");
		}

		$remainingChildren = $this->pagesToProcess;

		// if there's no more, we're done!
		if (!count($remainingChildren)) {
			$this->completeJob();
			$this->isComplete = true;
			return;
		}

		
		// lets process our first item - note that we take it off the list of things left to do
		$ID = array_shift($remainingChildren);

		// get the page
		$page = Versioned::get_by_stage('Page', 'Live', '"SiteTree_Live"."ID" = '.$ID);

		if (!$page || !$page->Count()) {
			$this->addMessage("Page ID #$ID could not be found, skipping");
		} else {
			$page = $page->First();
		}

		if ($page && $page instanceof Page && !($page instanceof ErrorPage)) {
			if($page->canView() && (!isset($page->Priority) || $page->Priority > 0)) {
				$created = $page->dbObject('Created');
				$now = new SS_Datetime();
				$now->value = date('Y-m-d H:i:s');
				$versions = $page->Version;
				$timediff = $now->format('U') - $created->format('U');

				// Check how many revisions have been made over the lifetime of the
				// Page for a rough estimate of it's changing frequency.
				$period = $timediff / ($versions + 1);

				if($period > 60*60*24*365) { // > 1 year
					$page->ChangeFreq='yearly';
				} elseif($period > 60*60*24*30) { // > ~1 month
					$page->ChangeFreq='monthly';
				} elseif($period > 60*60*24*7) { // > 1 week
					$page->ChangeFreq='weekly';
				} elseif($period > 60*60*24) { // > 1 day
					$page->ChangeFreq='daily';
				} elseif($period > 60*60) { // > 1 hour
					$page->ChangeFreq='hourly';
				} else { // < 1 hour
					$page->ChangeFreq='always';
				}

				// do the generation of the file in a temporary location
				$content = $page->renderWith('SitemapEntry');

				$fp = fopen($this->tempFile, "a");
				if (!$fp) {
					throw new Exception("Could not open $this->tempFile for writing");
				}
				fputs($fp, $content, strlen($content));
				fclose($fp);
			}
		}

		// and now we store the new list of remaining children
		$this->pagesToProcess = $remainingChildren;
		$this->currentStep++;

		if (!count($remainingChildren)) {
			$this->completeJob();
			$this->isComplete = true;
			return;
		}
	}

	/**
	 * Outputs the completed file to the site's webroot
	 */
	protected function completeJob() {

		$content = '<?xml version="1.0" encoding="UTF-8"?>'.
					'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		$content .= file_get_contents($this->tempFile);
		$content .= '</urlset>';

		$sitemap = Director::baseFolder() .'/sitemap.xml';
		
		file_put_contents($sitemap, $content);

		if (file_exists($this->tempFile)) {
			unlink($this->tempFile);
		}

		$nextgeneration = new GenerateGoogleSitemapJob();
		singleton('QueuedJobService')->queueJob($nextgeneration, date('Y-m-d H:i:s', time() + self::$regenerate_time));
	}
}
