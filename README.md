#HttpRequest - HTTP Requests the easy way#
_By [ Allan Rehhoff ](http://rehhoff.me/)_

```
<?php
	// Assuming you have an autoloader in place.
	$request = new Http\Request("http://httpbin.org/post");
	$response = $request->post(["foo" => "bar", "john" => "doe"]);

	print_r($response->asObject()); // Assumes a valid JSON response

	// Alternatively you can include the bundled autoloader
	require "autoload.php";
?>
```

You can tell cURL to fail upon error using Http\Request::failOnError($bool);

```
<?php
	// Alternatively you can include the bundled autoloader
	require "autoload.php";

	try {
		$req = new Http\Request("https://httpbin.org/status/418");
		$response = $req->failOnError(true)->get();
	} catch(Exception $e) {
		// $response will be NULL
		print $e->getCode();
		print $e->getMessage();
	}
?>
```

The library wraps around PHP's built in curl library, eliminating all the hassle and the need for $ch variables. (god I hate $ch)  
Please inspect the library source and test cases for further documentation and usage examples.  

This tool is licensed under [ WTFPL ](http://www.wtfpl.net/)  