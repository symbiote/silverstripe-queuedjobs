<?php

namespace App\Queue\Admin;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\ORM\DataExtension as BaseDataExtension;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * Class DataExtension
 *
 * @property QueuedJobDescriptor|$this $owner
 * @package App\Queue\Admin
 */
class DataExtension extends BaseDataExtension
{

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields): void
    {
        $owner = $this->owner;

        $fields->addFieldsToTab('Root.JobData', [
            $jobDataPreview = TextareaField::create('SavedJobDataPreview', 'Job Data'),
        ]);

        if (strlen($owner->getMessagesRaw()) > 0) {
            $fields->addFieldToTab(
                'Root.MessagesRaw',
                $messagesRaw = LiteralField::create('MessagesRaw', $owner->getMessagesRaw())
            );
        }

        $jobDataPreview->setReadonly(true);
    }

    /**
     * @return string|null
     */
    public function getSavedJobDataPreview(): ?string
    {
        return $this->owner->SavedJobData;
    }

    /**
     * @return string|null
     */
    public function getMessagesRaw(): ?string
    {
        return $this->owner->SavedJobMessages;
    }

    /**
     * @return string
     */
    public function getImplementationSummary(): string
    {
        $segments = explode('\\', $this->owner->Implementation);

        while (count($segments) > 2) {
            array_shift($segments);
        }

        return implode('\\', $segments);
    }
}
