<?php

/**
 * A set of utility functions used by the queued jobs module
 *
 * @license http://silverstripe.org/bsd-license
 * @author Marcus Nyeholt <marcus@silverstripe.com.au> 
 */
class QJUtils {
	public function __construct() {}


	/**
	 * Quote up a filter of the form
	 *
	 * array ("ParentID =" => 1)
	 *
	 *
	 *
	 * @param unknown_type $filter
	 * @return unknown_type
	 */
	function dbQuote($filter = array(), $join = " AND ") {
		$QUOTE_CHAR = defined('DB::USE_ANSI_SQL') ? '"' : '';

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
				$string .= $sep . $QUOTE_CHAR . $tb . $QUOTE_CHAR . '.' . $QUOTE_CHAR . $fl . $QUOTE_CHAR . " $operator " . $value;
			} else {
				if (is_numeric($field)) {
					$string .= $sep . $value;
				} else {
					$string .= $sep . $QUOTE_CHAR . $field . $QUOTE_CHAR . " $operator " . $value;
				}
			}

			$sep = $join;
		}

		return $string;
	}

	protected function recursiveQuote($val) {
		if (is_array($val)) {
			$return = array();
			foreach ($val as $v) {
				$return[] = $this->recursiveQuote($v);
			}

			return '('.implode(',', $return).')';
		} else if (is_null($val)) {
			$val = 'NULL';
		} else if (is_int($val)) {
			$val = (int) $val;
		} else if (is_double($val)) {
			$val = (double) $val;
		} else if (is_float($val)) {
			$val = (float) $val;
		} else {
			$val = "'" . Convert::raw2sql($val) . "'";
		}

		return $val;
	}

	function log($message, $level=null) {
		if (!$level) {
			$level = SS_Log::NOTICE;
		}
		$message = array(
			'errno' => '',
			'errstr' => $message,
			'errfile' => dirname(__FILE__),
			'errline' => '',
			'errcontext' => ''
		);

		SS_Log::log($message, $level);
	}

	public function ajaxResponse($message, $status) {
		return Convert::raw2json(array(
			'message' => $message,
			'status' => $status,
		));
	}
}
?>