<?php

namespace Symbiote\QueuedJobs\Services;

use Exception;
use SilverStripe\Core\Injector\Injectable;

/**
 * Class used to handle errors for a single job
 */
class JobErrorHandler
{
    use Injectable;

    public function __construct()
    {
        set_error_handler(array($this, 'handleError'));
    }

    public function clear()
    {
        restore_error_handler();
    }

    public function handleError($errno, $errstr, $errfile, $errline)
    {
        if (error_reporting()) {
            // Don't throw E_DEPRECATED in PHP 5.3+
            if (defined('E_DEPRECATED')) {
                if ($errno == E_DEPRECATED || $errno = E_USER_DEPRECATED) {
                    return;
                }
            }

            switch ($errno) {
                case E_NOTICE:
                case E_USER_NOTICE:
                case E_STRICT:
                    break;
                default:
                    throw new Exception($errstr . " in $errfile at line $errline", $errno);
                    break;
            }
        }
    }
}
