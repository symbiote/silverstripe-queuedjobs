<?php

/**
 * A set of utility functions used by the queued jobs module
 * @license BSD http://silverstripe.org/bsd-license/
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
	function quote($filter = array(), $join = " AND ") {
		$QUOTE_CHAR = defined('DB::USE_ANSI_SQL') ? '"' : '';

		$string = '';
		$sep = '';

		foreach ($filter as $field => $value) {
			// first break the field up into its two components
			$operator = '';
			if (is_string($field)) {
				list($field, $operator) = explode(' ', trim($field));
			}

			if (is_array($value)) {
				// quote each individual one into a string
				$ins = '';
				$insep = '';
				foreach ($value as $v) {
					$ins .= $insep . Convert::raw2sql($v);
					$insep = ',';
				}
				$value = '('.$ins.')';
			} else if (is_null($value)) {
				$value = 'NULL';
			} else if (is_string($field)) {
				$value = "'" . Convert::raw2sql($value) . "'";
			}

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

}
?>