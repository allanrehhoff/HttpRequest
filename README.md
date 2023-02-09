# HttpRequest - HTTP Requests the easy way.

```php
<?php
	// Using the bundled autoloader is optional
	require "autoload.php";

	// Assuming you have an autoloader in place.
	$request = new Http\Request("http://httpbin.org/post");
	$response = $request->post(["foo" => "bar", "john" => "doe"]);

	// Assumes a valid JSON response
	print_r($response->getResponse()->asObject());

	// Likewise assumes a valid JSON response
	print_r($response->getResponse()->asArray());

	// Assumes a valid XML response
	print_r($response->getResponse()->asXML());

	// ... Or if you prefer to do it all by yourself
	print_r($response->getResponse()->asRaw());
```

Other request methods include 'get', 'put', 'delete' and 'patch'.  

You can tell cURL to fail upon error using Http\Request::failOnError($bool);

```php
<?php
	// Using the bundled autoloader is optional
	require "autoload.php";

	try {
		$iRequest = new Http\Request("https://httpbin.org/status/418");
		$response = $iRequest->failOnError(true)->get();
	} catch(Exception $e) {
		// $response will be NULL
		print $e->getCode();
		print $e->getMessage();
	}
```

The library wraps around PHP's built in curl library, eliminating all the hassle and the need for $ch variables. (god I hate $ch)  
Please inspect the library source and test cases for further documentation and usage examples.  

This tool is licensed under [ WTFPL ](http://www.wtfpl.net/)  