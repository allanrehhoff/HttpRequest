<?php
	if(!class_exists("HttpRequest")) {
		// Todo: figure out a better way than a bunch of requires
		require __DIR__."/src/HttpException.class.php";
		require __DIR__."/src/HttpRequest.class.php";
		require __DIR__."/src/HttpResponse.class.php";
	}