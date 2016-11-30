#HttpRequest - HTTP Requests the easy way#
_By Allan Rehhoff_

```
<?php
	require "autoload.php";
	$request = new HttpRequest("http://httpbin.org/post");
	$response = $request->post(["foo" => "bar", "john" => "doe"]);
?>
```
The library wraps around PHP's built in curl library, eliminating all the hassle and the need for $ch variables. (god I hate $ch)  
Please inspect the library source and test cases for further documentation  

This tool is licensed under [ WTFPL ](http://www.wtfpl.net/)  