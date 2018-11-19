<?php
/**
* Provides a (relatively?) easy way of performing RESTful requests via HTTP.
* There are usage examples available in the attached HttpRequestTest cases
* (you should be able to locate that in the repository using the link in this docblock)
* Or read the individual method documentation for more information.
*
* Currently supports GET, POST, HEAD, PUT, DELETE, PATCH requests.
* Other requests types is possible by using the ->call(); method.
*
* This class implements the magic method __call(); in a way that allows you to call any curl_* function
* That has not already been implemented by this class, while omitting the curl handle.
*
* Some limitations may apply because this library wraps around cURL
*
* @author Allan Thue Rehhoff <http://rehhoff.me>
* @version 2.1
* @package HttpRequest
* @license WTFPL
* {@link https://bitbucket.org/allanrehhoff/httprequest/src HttpRequest at bitbucket}
*/

namespace Http {
	class Request {
		public $curl, $response, $verbose, $cookiejar, $headerHandle;
		public $curlInfo = [];
		private $cookies = [];
		private $headers = [];
		private $options = [];

		const GET = "GET";
		const POST = "POST";
		const HEAD = "HEAD";
		const PUT = "PUT";
		const DELETE = "DELETE";
		const PATCH = "PATCH";

		/**
		* The constructor takes a single argument, the url of the host to request.
		* @param (string) $url A fully qualified url, on which the service can be reached.
		*/
		public function __construct($url = null) {
			$this->curl = curl_init();
			$this->cookiejar = tempnam(sys_get_temp_dir(), "/HttpRequestCookiejar");

			$this->options = [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS => 5,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_FAILONERROR => false,
				CURLOPT_URL => $url // defaults to null, by assigning a potentially unmodified argument we ensure cURL behaves as it normally would
			];
		}

		/**
		* Do not bother about this method, you should not be calling this.
		* @return void
		*/
		public function __destruct() {
			curl_close($this->curl);
			if(is_resource($this->verbose)) {
				fclose($this->verbose);
			}
		}

		/**
		* Allows usage for any curl_* functions in PHP not implemented by this class.
		* @param (string) $function - cURL function to call without, curl_ part must be ommited.
		* @param (array) $params - Array of arguments to pass to $function.
		* @return object
		* @link http://php.net/manual/en/ref.curl.php
		*/
		public function __call($function, $params) {
			if(function_exists("curl_".$function)) {
				array_unshift($params, $this->curl);
				call_user_func_array("curl_".$function, $params);
			} else {
				throw new BadRequestException($function." is not a valid cURL function. Invoked by Http\Request::__call()");
			}
			return $this;
		}

		/**
		* The primary function of this class, performs the actual call to a specified service. Parsing the headers afterwards.
		* @param (string) $method HTTP method to use for this request.
		* @param (mixed) $data The full data body to transfer with this request.
		* @param (int) $timeout Seconds this request shall last before it times out.
		* @return object
		*/
		public function call($method = false, $data = false, $timeout = 60) {
			// Make sure data are sent in a correct format.
			if($method === self::GET) {
				$getRequestParams = ($data !== false) ? '?'.http_build_query($data, '', '&') : '';
				$this->setUrl($this->getUrl().$getRequestParams);
				$this->setOption(CURLOPT_HTTPGET, true);
			} elseif($method !== false) {
				$this->setOption(CURLOPT_CUSTOMREQUEST, $method);
				$this->setOption(CURLOPT_POSTFIELDS, $data);
			}

			$this->headerHandle = fopen("php://temp", "rw+");

			$this->setOption(CURLOPT_HTTPHEADER, $this->headers);
			$this->setOption(CURLOPT_TIMEOUT, $timeout);
			$this->setOption(CURLOPT_WRITEHEADER, $this->headerHandle);

			// If there is any stored cookies, use the assigned cookiejar
			if((bool) $this->cookiejar !== false) {
				if(fopen($this->cookiejar, "a+") === false) {
					throw new Exception("The cookiejar we were given could not not be opened.");
				}

				$this->setOption(CURLOPT_COOKIEJAR, $this->cookiejar);
				$this->setOption(CURLOPT_COOKIEFILE, $this->cookiejar);
			}

			// Send cookies manually associated with this request
			// Most likely not going to happen if a cookiejar was utilized.
			// But we're going to allow it anyway. at least as for now.
			if(!empty($this->cookies)) {
				$cookieString = '';
				$iterations = 0;
				$numCookiesSet = count($this->cookies);

				foreach($this->cookies as $cookie) {
					$iterations++;
					$cookieString .= $cookie->name.'='.$cookie->value;
					if($iterations < $numCookiesSet) $cookieString .= "; ";
				}

				$this->setOption(CURLOPT_COOKIE, $cookieString); 
			}		

			// Finally perform the request
			curl_setopt_array($this->curl, $this->options);
			$this->response = curl_exec($this->curl);
			$this->curlInfo = curl_getinfo($this->curl);

			if($this->response === false) {
				throw new BadRequestException(curl_errno($this->curl).": ".curl_error($this->curl), curl_errno($this->curl));
			}

			return new Response($this);
		}

		/**
		* Perform the request through HTTP GET
		* @param (mixed) $data
		* 	Parameters to send with this request, see the call method for more information on this parameter.
		*	Naturally you should not find a need for this parameter, but it is implemented just in case the server masquarades.
		*
		* @param (int) $timeout - Seconds this request shall last before it times out.
		* @return object
		*/
		public function get($data = false, $timeout = 60) {
			return $this->call(self::GET, $data, $timeout);
		}

		/**
		* Perform the request through HTTP POST
		* @param (mixed) $data Postfields to send with this request, see the call method for more information on this parameter
		* @param (int) $timeout Seconds this request shall last before it times out.
		* @return (object)
		*/
		public function post($data = false, $timeout = 60) {
			return $this->call(self::POST, $data, $timeout);
		}

		/**
		* Obtain metainformation about the request without transferring the entire message-body
		*A HEAD request does not accept post data, so the $data parameter is not available here.
		* 
		* @param (int) $timeout Seconds this request shall last before it times out.
		* @return (object)
		*/
		public function head($timeout = 60) {
			return $this->call(self::HEAD, false, $timeout);
		}

		/**
		* Put data through HTTP PUT.
		* @param (mixed) $data Data to send through this request, see the call method for more information on this parameter.
		* @param (int) $timeout Seconds this request shall last before it times out.
		* @return (object)
		*/
		public function put($data = false, $timeout = 60) {
			return $this->call(self::PUT, $data, $timeout);
		}

		/**
		* Requests that the origin server delete the resource identified by the Request-URI.
		* @param (mixed) $data 
		*	When using this parameter you should consider signaling the pressence of a message body
		*	By providing a Content-Length or Transfer-Encoding header.
		*
		* @param (int) $timeout - Seconds this request shall last before it times out.
		*/
		public function delete($data = false, $timeout = 60) {
			return $this->call(self::DELETE, $data, $timeout);
		}

		/**
		* Patch those data to the service.
		* @param (mixed) $data - Data to send with this requst.
		* @param (int) $timeout Seconds this request shall last before it times out.
		* @return (object)
		*/
		public function patch($data = false, $timeout = 60) {
			return $this->call(self::PATCH, $data, $timeout);
		}

		/**
		* Provide an additional header for this request.
		* @param (string) $header The header to send with this request.
		* @return (object)
		*/
		public function setHeader($header) {
			$this->headers[] = $header;
			return $this;
		}

		/**
		* Specifies the port to be requested upon
		* @param (int) a port number.
		* @return (object)
		*/
		public function port($port) {
			$this->setOption(CURLOPT_PORT, $port);
			return $this;
		}

		/**
		* Send a cookie with this request.
		* @param (string) $name name of the cookie
		* @param (string) $value value of the cookie
		* @return (object)
		*/
		public function setCookie($name, $value) {
			$this->cookies[$name] = (object) [
				"name" => $name,
				"value" => $value
			];

			return $this;
		}

		/**
		* The name of a file in which to store all recieved cookies when the handle is closed, e.g. after a call to curl_close.
		* This is automatically done by this class is destructed.
		* @param (string) $filepath
		* @return (object)
		* @throws Http\BadRequestException
		* @since 1.4
		*/
		public function cookiejar($filepath) {
			/* if(is_file($filepath) === false && is_string($filepath) === false) {
				throw new BadRequestException("Http\Request::cookiejar Expects filepath to be of type file. ".gettype($filepath)." was given.");
			} else if(fopen($filepath, "w+") === false) {
				throw new BadRequestException("Unable to create new cookiejar '".$filepath."'");
			} */

			$this->cookiejar = $filepath;
			return $this;
		}

		/**
		* Tells cURL if it should fail upon error, resulting in an exception being thrown
		* Returns current setting value.
		* @return (mixed)
		*/
		public function failOnError($fail = null) {
			if(is_bool($fail) === true) {
				$this->setOption(CURLOPT_FAILONERROR, $fail);
				return $this;
			}

			return $this->getOption(CURLOPT_FAILONERROR);
		}

		/**
		* Manually set a cURL option for this request.
		* @param (int) $option The CURLOPT_XXX option to set.
		* @param (mixed) Value for the option
		* @return (object)
		* @see http://php.net/curl_setopt
		*/
		public function setOption($option, $value) {
			$this->options[$option] = $value;
			//curl_setopt($this->curl, $option, $value);
			return $this;
		}

		/**
		* Retrieve the current value of a given cURL option
		* @param (int) $option CURLOPT_* value to retrieve
		* @return (mixed)
		* @since 1.3
		*/
		public function getOption($option) {
			return $this->options[$option];
		}

		/**
		* A string to use as authorization for this request.
		* @param (string) $username The username to use
		* @param (string) $password The password that accompanies the username
		* @param (int) $authType The HTTP authentication method(s) to use
		* @return (object)
		*/
		public function authorize($username, $password, $authType = CURLAUTH_ANY) {
			$this->setOption(CURLOPT_HTTPAUTH, $authType);
			$this->setOption(CURLOPT_USERPWD, $username.":".$password);
			return $this;
		}

		/**
		* Alias/Helper method for the above.
		* @param (string) $username The username to use
		* @param (string) $password The password that accompanies the username
		* @param (int) $authType The HTTP authentication method(s) to use
		* @return (object)
		*/
		public function authenticate($username, $password, $authType = CURLAUTH_ANY) {
			return $this->authorize($username, $password, $authType);
		}

		/**
		* Enable CURL verbosity, captures and pushes the output to the response headers.
		* @return (object)
		*/
		public function verbose() {
			$this->verbose = fopen('php://temp', 'rw+');
			$this->setOption(CURLOPT_VERBOSE, true);
			$this->setOption(CURLOPT_STDERR, $this->verbose);

			return $this;
		}

		/**
		* Sets destination url, to which this request will be sent.
		* @param $value a fully qualified url
		* @return (object)
		*/
		public function setUrl($value) {
			//$this->url = $value;
			$this->setOption(CURLOPT_URL, $value);
			return $this;
		}

		/**
		* Get the URL to be requested.
		* @return (string)
		* @since 1.1
		*/
		public function getUrl() {
			return $this->getOption(CURLOPT_URL);
		}
	}
}