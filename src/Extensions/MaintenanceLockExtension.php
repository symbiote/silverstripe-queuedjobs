<?php

namespace Symbiote\QueuedJobs\Extensions;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\SiteConfig\SiteConfig;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Class MaintenanceLockExtension
 * Adds a maintenance lock UI to SiteConfig
 *
 * @package Symbiote\QueuedJobs\Extensions
 *
 * @extends DataExtension<SiteConfig&static>
 */
class MaintenanceLockExtension extends DataExtension
{
    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        if (!QueuedJobService::config()->get('lock_file_enabled')) {
            return;
        }

        $fields->addFieldsToTab('Root.QueueSettings', [
            $lockField = CheckboxField::create(
                'MaintenanceLockEnabled',
                _t(__CLASS__ . '.LOCK_ENABLED', 'Maintenance Lock Enabled'),
                QueuedJobService::singleton()->isMaintenanceLockActive()
            ),
        ]);

        $lockField->setDescription(
            _t(
                __CLASS__ . '.LOCK_DESCRIPTION',
                'Enable maintenance lock to prevent new queued jobs from being started'
            )
        );
    }

    /**
     * @param bool $value
     */
    public function saveMaintenanceLockEnabled($value)
    {
        if (!QueuedJobService::config()->get('lock_file_enabled')) {
            return;
        }

        if ($value && !QueuedJobService::singleton()->isMaintenanceLockActive()) {
            QueuedJobService::singleton()->enableMaintenanceLock();
        }

        if (!$value && QueuedJobService::singleton()->isMaintenanceLockActive()) {
            QueuedJobService::singleton()->disableMaintenanceLock();
        }
    }
}
