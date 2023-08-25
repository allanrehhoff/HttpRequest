<?php
	/**
	* Exception class used by the library to distinct any thrown exception from a standard PHP Exception
	* @extends Exception
	* @package Http\Request
	*/
	namespace Http {
		class CurlError extends \Exception {
			public function __construct($message, $code = 0, \Exception $previous = null) {
				parent::__construct($message, $code, $previous);
			}
		}
	}