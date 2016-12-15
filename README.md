#HttpRequest - HTTP Requests the easy way#
_By Allan Rehhoff_

```
<?php
	// Assuming you have an autoloader in place.
	$request = new Http\Request("http://httpbin.org/post");
	$response = $request->post(["foo" => "bar", "john" => "doe"]);

	print_r($response->asObject()); // Assumes a valid JSON response
?>
```
The library wraps around PHP's built in curl library, eliminating all the hassle and the need for $ch variables. (god I hate $ch)  
Please inspect the library source and test cases for further documentation and usage examples.  

This tool is licensed under [ WTFPL ](http://www.wtfpl.net/)  