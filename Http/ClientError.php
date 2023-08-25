<?php
	/**
	* Exception used when remote answers with a non-successful HTTP code < 400
	* @extends Exception
	* @package Http\Request
	*/
	namespace Http {
		class ClientError extends \Exception {
			public function __construct($message, $code = 0, \Exception $previous = null) {
				parent::__construct($message, $code, $previous);
			}
		}
	}