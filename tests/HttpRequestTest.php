<?php
// Simple example on how to authorize against MailChimps API
// $req = new Http\Request("https://<dc>.api.mailchimp.com/3.0/lists");
// $req->authorize("apikey:<your-api-key>");
// $req->call(Http\Request::GET);

// Simple way to get the content of a webpage
// print (new Http\Request("http://rehhoff.me"))->get()->getResponse();
// use "->setHeader($headernane, $headervalue);" before ->get() to provide additional headers

// Or check the headers return by a server
// $req = (new Http\Request("http://rehhoff.me"))->head()->getResponseHeaders();
// Why not set a timeout now that we are at it.
// $req = (new Http\Request("http://rehhoff.me"))->head(10)->getResponseHeaders();

// You should also be able to use an unsupported HTTP Request.
// $req = (new Http\Request("http://rehhoff.me/help-for-example"))->call("OPTIONS")->getResponse();

// Example on how to add a new subscriber to your mailchimp list.
// $subscriber = json_encode( array(
// 	"email_address" => $email,
// 	"status" => "subscribed",
// 	"merge_fields" => array(
// 		"FNAME" => $firstname,
// 		"LNAME" => $lastname
// 	) ) );
// 
// $req = new Http\Request("https://<dc>.api.mailchimp.com/3.0/lists/<list-id>/members");
// $req->authorize("apikey:<your-api-key>");
// $req->post($subscriber, 15); // Second paramter set's timeout.

// At some point you might also want to debug the request
// print (new Http\Request("http://rehhoff.me"))->verbose()->head()->getResponseHeaders();

// $request->getInfo("http_code"); Will return an integer representing the HTTP status of the request performed.
// $request->getResponseHeader("http_code"); Will return the header string containing the status code.
//
// You can also print the object to see response formatted.
// print (new Http\Request())->setOpt(CURLOPT_URL, "http://rehhoff.me")->verbose()->head();

class HttpRequestTest extends \PHPUnit\Framework\TestCase {
	public function setUp() {

	}

	/**
	* Tests GET request works as expected
	* @author Allan Rehhoff
	*/	
	public function testGetRequest() {
		$req = (new Http\Request("https://httpbin.org/get"))->get();
		$this->assertEquals(200, $req->getInfo("http_code"));
	}

	/**
	* Tests POST request works as expected
	* @author Allan Rehhoff
	*/
	public function testPostRequest() {
		$req = (new Http\Request("https://httpbin.org/post"))->post();
		$this->assertEquals(200, $req->getInfo("http_code"));
	}

	/**
	* Tests PATCH request works as expected
	* @author Allan Rehhoff
	*/
	public function testPatchRequest() {
		$req = (new Http\Request("https://httpbin.org/patch"))->patch();
		$this->assertEquals(200, $req->getInfo("http_code"));
	}

	/**
	* Tests PUT request works as expected
	* @author Allan Rehhoff
	*/
	public function testPutRequest() {
		$req = (new Http\Request("https://httpbin.org/put"))->put();
		$this->assertEquals(200, $req->getInfo("http_code"));
	}

	/**
	* Now test that we can actually put stuff.
	* @author Allan Thue Rehhoff
	*/
	public function testPutData() {
		$req = new Http\Request("https://httpbin.org/put");
		$response = $req->put(http_build_query(["foo" => "bar"]))->asObject();

		$this->assertTrue(isset($response->form->foo));
	}

	/**
	* Tests DELETE request works as expected
	* @author Allan Rehhoff
	*/
	public function testDeleteRequest() {
		$req = (new Http\Request("https://httpbin.org/delete"))->delete();
		$this->assertEquals(200, $req->getInfo("http_code"));
	}

	/**
	* Tests cookies can be set and parsed accordingly.
	* @author Allan Rehhoff
	*/
	public function testCanRecieveCookies() {
		$cookiejar = dirname(__FILE__)."/cookiejar";

		$req = new \Http\Request("https://httpbin.org/cookies/set?name1=value1&value2=value2&value3=value3");
		$req->cookiejar($cookiejar);
		$response = $req->get();

		$this->assertNotEmpty($response->getHeaders("Set-Cookie"));
	}

	/**
	* Because it would appear that cookies are only written to the cookiejar once the object is destroyed.
	* We'll check in a seperate test, that this is not empty yet..
	* @author Allan Rehhoff
	*/
	public function testCookiejarIsNotEmpty() {
		$cookiejar = dirname(__FILE__)."/cookiejar";
		$this->assertGreaterThan(0, filesize($cookiejar));

		unlink($cookiejar);
	}

	/**
	* Quick test that we get a useful object from an XML response
	* @author Allan Rehhoff
	*/
	public function testParseXmlPositive() {
		$req = new Http\Request("https://httpbin.org/xml");
		$xml = $req->get()->asXml();
		$this->assertInstanceOf("SimpleXMLElement", $xml);
	}

	/**
	* Test GET request parameters are send as expected
	* @author Allan Rehhoff
	*/
	public function testGetRequestParams() {
		$req = (new Http\Request("https://httpbin.org/get?john=doe"))->get();
		$response = json_decode($req->getResponse());
		$this->assertNotEmpty($response->args);
		$this->assertEquals("doe", $response->args->john);

		$params = ["meal" => "pizza", "toppings" => ["cheese", "ham", "pineapple", "bacon"]];
		$req2 = (new Http\Request("https://httpbin.org/get"))->get($params);
		$response2 = json_decode($req2->getResponse(), true);
		$this->assertNotEmpty($response2["args"]);
		
		/*
		* I check the keys this way because the returned keys are in this format:
		* Array (
     	* 	'meal' => 'pizza'
		* 	'toppings[0]' => 'cheese'
		* 	'toppings[1]' => 'ham'
		* 	'toppings[2]' => 'pineapple'
		* 	'toppings[3]' => 'bacon'
		* 	'toppings' => Array (...)
 		* )
 		* 
		* When what I really wanted was this:
		* Array (
		*	    [meal] => pizza
		*	    [toppings] => Array (
		*	    	[0] => cheese
		*	    	[1] => ham
		*	    	[2] => pineapple
		*	    	[3] => bacon
		*	    )
		*	)
		*/
		$args = $response2["args"];
		foreach($params["toppings"] as $key => $value) {
			$key2check = "toppings[".$key.']';
			if(!isset($args[$key2check]) || $args[$key2check] != $value) {
				$this->fail("A response key/value was not properly returned.");
			}
		}
	}

	/**
	* Test we are able to send a header
	* @author Allan Rehhoff
	*/
	public function testHeaders() {
		$ourHeaders = [
			"X-Firstname" => "John",
			"X-Lastname" => "Doe",
		];

		$req = new Http\Request("https://httpbin.org/headers");
		foreach($ourHeaders as $key => $value) {
			$req->setHeader($key.": ".$value);
		}

		$response = json_decode($req->get()->getResponse(), true);

		$this->assertNotEmpty($response["headers"]);

		$responseHeadersInCommonWtihOurHeaders = array_intersect($response["headers"], $ourHeaders);

		// This should assert that we got all our headers back.
		$this->assertEquals($ourHeaders, $responseHeadersInCommonWtihOurHeaders);
	}

	/**
	* Test user agent spoofing
	* @author Allan Rehhoff
	*/
	public function testUserAgentSpoofing() {
		$agent = "Mozilla/5.0 (Windows; U; Windows NT 6.1; rv:2.2) Gecko/20110201";

		$req = new Http\Request("https://httpbin.org/user-agent");
		$req->setOption(CURLOPT_USERAGENT, $agent);
		$response = $req->get()->getResponse();
		$response = json_decode($response, true);

		$this->assertEquals($agent, $response["user-agent"]);
	}

	/**
	* Test some poor developer wont end up in a black hole somewhere.
	* @author Allan Rehhoff
	*/
	public function testMaxRedirs() {
		try {
			$numRedirs = 10;
			$req = new Http\Request("https://httpbin.org/redirect/".$numRedirs);
			$req->get()->getResponse();
		} catch(\Http\BadRequestException $e) {
			$this->assertEquals(CURLE_TOO_MANY_REDIRECTS, $e->getCode());
			return;
		}
		$this->fail("Expected BadRequestException exception [Maximum (5) redirects followed] was not thrown.");
	}

	/**
	* Test invalid http codes are handled
	* @expectedException Http\BadRequestException
	* @author Allan Rehhoff
	*/
	public function testIsInvalidHttpCode() {
		$req = new Http\Request("https://httpbin.org/status/418");
		$req->failOnError(true)->get();
	}

	/**
	* Oh god, i'm not done yet, let's find out if we're able to do a basic HTTP authentication
	* @author Allan Rehhoff
	*/
	public function testBasicAuth() {
		$u = "john";
		$p = "doe";

		$req = new Http\Request("https://httpbin.org/basic-auth/".$u.'/'.$p);
		$req->authorize($u, $p);
		$response = $req->get();

		$this->assertEquals(200, $response->getCode());

		$response = $response->asObject();
		$this->assertTrue($response->authenticated);
	}

	/**
	* I cannot guarantee that this will ever work...
	* @author Allan Rehhoff
	*/
	public function testDigestAuth() {
		$qop = "auth";
		$u = "john";
		$p = "wayne";

		$url = "https://httpbin.org/digest-auth/".$qop."/".$u."/".$p."/MD5/never";

		try {
			$request = new \Http\Request($url);
			$request->verbose();
			$request->authorize("john", "wayne");
			$response = $request->get();

			$this->assertTrue($response->isSuccess());
		} catch(Exception $e) {
			$this->fail($e->getMessage());
		}
	}

	/**
	* Test posting a file.
	* @author Allan Rehhoff
	*/
	public function testPostFileRequest() {
		// Create a temporary file, for the purpose of this test.
		// Could be any file path
		$time = time();
		$tmpfile =  tempnam("/tmp", $time);
		$tmpfileHandle = fopen($tmpfile, "r+");
		fwrite($tmpfileHandle, $time);

		// As of PHP 5.5 CURLFile objects should be used instead for POSTing files.
		$cfile = new CURLFile($tmpfile, mime_content_type($tmpfile),'tmpfile.txt');
		$data = array('tmpfile' => $cfile);

		// Let's now do the more fancy part
		$req = new Http\Request("https://httpbin.org/post");
		$req->setHeader("Content-Type", "multipart/form-data");
		$res = $req->post($data)->getResponse();
		
		// Time to validate the data we got.
		$res = json_decode($res);
		$this->assertNotEmpty($res->files);
		$this->assertEquals($time, $res->files->tmpfile);
	}

	/**
	* Test putting a file.
	* In a real world scenario one should consider POSTing a file instead.
	* I'm only testing this because i'm corious about if this approach actually works.
	* @see testPostFileRequest
	* @todo Finish this test.
	* @author Allan Rehhoff
	*/
	public function testPutFileRequest() {
		// I hate having to do it this way, but appearently tempnam(); and tmpfile(); hangs the cURL request.
		$filepath = __FILE__;
		$tmpfileHandle = fopen($filepath, 'r');

		$req = new Http\Request("https://httpbin.org/put");
		$req->setOption(CURLOPT_INFILE, $tmpfileHandle);
		$req->setOption(CURLOPT_INFILESIZE, filesize($filepath));
		$req->setOption(CURLOPT_PUT, true);
		$res = $req->call(false, false, 60)->getResponse();
		$res = json_decode($res);

		$this->assertNotEmpty($res->data);
	}

	/**
	* Test we can do requests on a non-standard port
	* @expectedException Http\BadRequestException
	* @author Allan Rehhoff
	*/
	public function testRequestOnDifferentPort() {
		$request = new \Http\Request("localhost");
		$response = $request->failOnError(true)->port(8080)->head();
	}
}
