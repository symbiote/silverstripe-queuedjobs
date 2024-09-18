<?php

namespace Symbiote\QueuedJobs\Tests;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\SapphireTest;

/**
 * @package Symbiote\QueuedJobs\Tests
 */
class MaintenanceLockTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // The shutdown handler doesn't play nicely with SapphireTest's database handling
        QueuedJobService::config()->set('use_shutdown_function', false);
    }

    /**
     * @param $lockFileEnabled
     * @param $fileExists
     * @param $lockActive
     */
    #[DataProvider('maintenanceCaseProvider')]
    public function testEnableMaintenanceIfActive($lockFileEnabled, $fileExists, $lockActive)
    {
        $fileName = 'test-lock.txt';
        $filePath = Director::baseFolder() . DIRECTORY_SEPARATOR . $fileName;

        Config::modify()->set(QueuedJobService::class, 'lock_file_enabled', $lockFileEnabled);
        Config::modify()->set(QueuedJobService::class, 'lock_file_path', '');
        Config::modify()->set(QueuedJobService::class, 'lock_file_name', $fileName);

        QueuedJobService::singleton()->enableMaintenanceLock();

        $this->assertEquals($fileExists, file_exists($filePath ?? ''));
        $this->assertEquals($lockActive, QueuedJobService::singleton()->isMaintenanceLockActive());

        QueuedJobService::singleton()->disableMaintenanceLock();
        $this->assertFalse(file_exists($filePath ?? ''));
        $this->assertFalse(QueuedJobService::singleton()->isMaintenanceLockActive());
    }

    /**
     * @return array
     */
    public static function maintenanceCaseProvider()
    {
        return [
            [
                false,
                false,
                false,
            ],
            [
                true,
                true,
                true,
            ],
        ];
    }
}
