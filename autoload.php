<?php
	/**
	* Load any required library files as-needed
	* @author Allan Thue Rehhoff
	* @version 2.0
	* @package HttpRequest
	* @license WTFPL
	*/
	if(!class_exists("HttpRequest")) {
		spl_autoload_register(function($class) {
			//$filename = __DIR__.DIRECTORY_SEPARATOR."src".DIRECTORY_SEPARATOR.$class.".class.php";
			$filename = __DIR__.DIRECTORY_SEPARATOR.str_replace("\\", DIRECTORY_SEPARATOR, $class).".class.php";
			if(is_readable($filename) && !class_exists($class)) {
				require $filename;
			}
		}, true, true);
	}