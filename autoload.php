<?php
	/**
	* Load any required own library files as-needed.
	* @author Allan Thue Rehhoff
	* @version 2.0
	* @package HttpRequest
	* @license WTFPL
	*/
	spl_autoload_register(function($class) {
		$filename = __DIR__.DIRECTORY_SEPARATOR.str_replace("\\", DIRECTORY_SEPARATOR, $class).".php";
		if(is_readable($filename) && !class_exists($class) && substr($class, 0, 4) == "Http") {
			require $filename;
		}
	}, true, true);