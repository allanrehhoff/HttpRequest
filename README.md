#HttpRequest - Request made easy#
_By Allan Rehhoff_

```
<?php
	require "autoload.php";
	$request = new WebRequest("https://google.com");
	$response = $request->get();
?>
```

Please inspect the library source and test cases for further documentation