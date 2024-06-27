<?php

namespace Symbiote\QueuedJobs\Services;

use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataList;

/**
 * Class EmailService
 *
 * Email reporting for queued jobs
 *
 * @package Symbiote\QueuedJobs\Services
 */
class EmailService
{
    use Injectable;

    public function __construct()
    {
        $queuedEmail = Config::inst()->get(Email::class, 'queued_job_admin_email');

        if ($queuedEmail || $queuedEmail === false) {
            return;
        }

        // if not set (and not explictly set to false), fallback to the admin email.
        Config::modify()->set(
            Email::class,
            'queued_job_admin_email',
            Config::inst()->get(Email::class, 'admin_email')
        );
    }

    public function createMissingDefaultJobReport(array $jobConfig, string $title): ?Email
    {
        $subject = sprintf('Default Job "%s" missing', $title);
        $from = Config::inst()->get(Email::class, 'queued_job_admin_email');
        $to = array_key_exists('email', $jobConfig ?? []) &&  $jobConfig['email']
            ? $jobConfig['email']
            : $from;

        if (!$to) {
            return null;
        }

        return Email::create($from, $to, $subject)
            ->setData($jobConfig)
            ->addData('Title', $title)
            ->addData('Site', Director::absoluteBaseURL())
            ->setHTMLTemplate('QueuedJobsDefaultJob');
    }

    public function createStalledJobReport(string $subject, string $message, int $jobID): ?Email
    {
        $email = $this->createReport($subject);
        if ($email === null) {
            return null;
        }

        return $email
            ->setData([
                'JobID' => $jobID,
                'Message' => $message,
                'Site' => Director::absoluteBaseURL(),
            ])
            ->setHTMLTemplate('QueuedJobsStalledJob');
    }

    public function createBrokenJobsReport(string $subject, string $message, DataList $jobs): ?Email
    {
        $email = $this->createReport($subject);
        if ($email === null) {
            return null;
        }
        return $email
            ->setData([
                'Message' => $message,
                'Jobs' => $jobs,
                'Site' => Director::absoluteBaseURL(),
            ])
            ->setHTMLTemplate('QueuedJobsBrokenJobs');
    }

    /**
     * Create a generic email report
     * useful for reporting queue service issues
     */
    public function createReport(string $subject): ?Email
    {
        $from = Config::inst()->get(Email::class, 'admin_email');
        $to = Config::inst()->get(Email::class, 'queued_job_admin_email');

        if (!$to) {
            return null;
        }

        return Email::create($from, $to, $subject);
    }
}
