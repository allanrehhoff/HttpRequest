# Introduction

PHP cURL/HTTP requests made easy.  

The library wraps around PHP's built in curl library, eliminating all the hassle and the need for $ch variables.    
Please inspect the library source and test cases for further documentation and usage examples.  

## Making requests
```php
<?php
	// Using the bundled autoloader is optional
	require "autoload.php";

	// Assuming you have an autoloader in place.
	$iRequest = new \Http\Request("http://httpbin.org/post");
	$iResponse = $iRequest->post(["foo" => "bar", "john" => "doe"]);

	// Assumes a valid JSON response body
	print_r($iResponse->getResponse()->asObject());

	// Likewise assumes a valid JSON response body
	print_r($iResponse->getResponse()->asArray());

	// Assumes a valid XML response body
	print_r($iResponse->getResponse()->asXML());

	// ... Or if you prefer to do it all by yourself
	print_r($iResponse->getResponse()->getBody());
```

You may use the factory method 'with' if all you need is a simple request, with an expected JSON response

```php
<?php
	\Http\Request::with("https://httpbin.org/post")
	->post(["data" => "foo"])
	->getResponse()
	->asObject();
```

the above is equivelant to:

```php
<?php
	json_decode(
		\Http\Request::with("https://httpbin.org/post")
		->setMethod(Method::POST)
		->send(["data" => "foo"])
		->getResponse()
		->getBody()
	);
```

Check the response code:  
```php
<?php
	\Http\Request::with("https://httpbin.org/status/301")
	->setOption(CURLOPT_FOLLOWLOCATION, false)
	->head()
	->getHttpCode();
```

Available request methods include `get`, `post`, `post`, `patch`, `delete`, `head`, `options`, `connect`, `trace`

## Error handling
```php
<?php
	// Using the bundled autoloader is optional
	require "autoload.php";

	try {
		$iRequest = new \Http\Request("https://httpbin.org/status/418");
		$iResponse = $iRequest->post(["lorem", "ipsum"]);
	} catch(\Http\HttpError $iHttpError) {
		// There was an error that caused the remote to return a HTTP code >= 400
		// This is likely due to an errornous integration

		// Error code will match the HTTP code returned by remote
		$iHttpError->getCode();

		// Error message will be the raw response body, likely in JSON
		$iHttpError->getMessage();
	} catch(\Http\ConnectionError $iConnectionError) {
		// There was a unexpected error that made cURL unable to properly connect to remote

		// Error code will be the curl generated error number
		// as provided by curl_errno();
		$iConnectionError->getCode();

		// Error message will be the curl generated error message
		// as provided by curl_error();
		$iConnectionError->getMessage();
	} catch(\JsonException $iJsonException) {
		// The remote resource returned successfully.
		// but the response body failed parsing as JSON
		// This exception is native to PHP
		print $iJsonException->getCode();
		print $iJsonException->getMessage();
	}
```