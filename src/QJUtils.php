<?php

namespace SilverStripe\QueuedJobs;

use SilverStripe\Core\Convert;
use SilverStirpe\Core\Injector\Injector;

/**
 * A set of utility functions used by the queued jobs module
 *
 * @license http://silverstripe.org/bsd-license
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class QJUtils
{
    /**
     * Quote up a filter of the form
     *
     * array ("ParentID =" => 1)
     *
     * @param array $filter
     * @param string $join
     * @return string
     */
    public function dbQuote($filter = array(), $join = " AND ")
    {
        $quoteChar = defined('DB::USE_ANSI_SQL') ? '"' : '';

        $string = '';
        $sep = '';

        foreach ($filter as $field => $value) {
            // first break the field up into its two components
            $operator = '';
            if (is_string($field)) {
                list($field, $operator) = explode(' ', trim($field));
            }

            $value = $this->recursiveQuote($value);

            if (strpos($field, '.')) {
                list($tb, $fl) = explode('.', $field);
                $string .= $sep . $quoteChar . $tb . $quoteChar . '.' . $quoteChar . $fl . $quoteChar . " $operator " . $value;
            } else {
                if (is_numeric($field)) {
                    $string .= $sep . $value;
                } else {
                    $string .= $sep . $quoteChar . $field . $quoteChar . " $operator " . $value;
                }
            }

            $sep = $join;
        }

        return $string;
    }

    /**
     * @param mixed $val
     * @return string
     */
    protected function recursiveQuote($val)
    {
        if (is_array($val)) {
            $return = array();
            foreach ($val as $v) {
                $return[] = $this->recursiveQuote($v);
            }

            return '('.implode(',', $return).')';
        } elseif (is_null($val)) {
            $val = 'NULL';
        } elseif (is_int($val)) {
            $val = (int) $val;
        } elseif (is_double($val)) {
            $val = (double) $val;
        } elseif (is_float($val)) {
            $val = (float) $val;
        } else {
            $val = "'" . Convert::raw2sql($val) . "'";
        }

        return $val;
    }

    /**
     * @deprecated 3.0 Use Injector::inst()->get('Logger') instead
     *
     * @param string $message
     * @param int $level
     */
    public function log($message, $level = null)
    {
        Injector::inst()
            ->get('Logger')
            ->debug(
                print_r(
                    array(
                        'errno' => '',
                        'errstr' => $message,
                        'errfile' => dirname(__FILE__),
                        'errline' => '',
                        'errcontext' => array()
                    ),
                    true
                )
            );
    }

    /**
     * @param string $message
     * @param string $status
     * @return string
     */
    public function ajaxResponse($message, $status)
    {
        return Convert::raw2json(array(
            'message' => $message,
            'status' => $status,
        ));
    }
}
