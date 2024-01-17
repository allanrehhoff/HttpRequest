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
* @package Http\Request
* @license MIT
* {@link https://github.com/allanrehhoff/httprequest HttpRequest at GitHub}
*/

namespace Http {
	class Request {
		/**
		 * @var null|false|\CurlHandle $curl Primary curl handle
		 */
		public null|false|\CurlHandle $curl;

		/**
		 * @var Response $response The response object, once request has been invoked
		 */
		public Response $response;

		/**
		 * @var null|resource|false $verbosityHandle Verbosity/Debug is is enabled when using a temporary file stream
		 */
		public mixed $verbosityHandle = null;

		/**
		 * @var null|string $cookiejar Path to the cookiejar in use, if enabled, default null
		 */
		public null|string $cookiejar = null;

		/**
		 * @var resource|false $headerHandle Header handle resource
		 */
		public mixed $headerHandle;

		/**
		 * @var string|bool $returndata Response returned by curl_exec
		 */
		public string|bool $returndata;

		/**
		 * @var array $curlInfo Curl info response
		 */
		public array $curlInfo = [];

		/**
		 * @var array $cookies Cookies to be sent with requests
		 */
		public array $cookies = [];

		/**
		 * @var array $headers Headers to be sent with requests
		 */
		public array $headers = [];

		/**
		 * @var array $options Options to be set on curl handle, e.g CURLOPT_*
		 */
		public array $options = [];

		const GET = "GET";
		const POST = "POST";
		const HEAD = "HEAD";
		const PUT = "PUT";
		const DELETE = "DELETE";
		const PATCH = "PATCH";

		/**
		* The constructor takes a single argument, the url of the host to request.
		* @param string $url A fully qualified url, on which the service can be reached.
		*/
		public function __construct($url = null) {
			$this->curl = curl_init();

			$this->headerHandle = fopen("php://temp", "rw+");

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
			if(is_resource($this->verbosityHandle)) {
				fclose($this->verbosityHandle);
			}

			if(is_resource($this->headerHandle)) {
				fclose($this->headerHandle);
			}
		}

		/**
		* Allows usage for any curl_* functions in PHP not implemented by this class.
		* @param string $function - cURL function to call without, curl_ part must be ommited.
		* @param array $params - Array of arguments to pass to $function.
		* @return mixed
		* @link http://php.net/manual/en/ref.curl.php
		*/
		public function __call(string $function, array $params): mixed {
			$function = "curl_".strtolower($function);

			if(function_exists($function)) {
				return $function($this->curl, ...$params);
			} else {
				throw new \InvalidArgumentException($function." is not a valid cURL function. Invoked by Http\Request::__call()");
			}
			
			return $this;
		}

		/**
		* The primary function of this class, performs the actual call to a specified service.
		* Doing GET requests will append a query build from $data to the URL specified
		* @param string $method HTTP method to use for this request, default GET.
		* @param null|string|array $data The full data body to transfer with this request.
		* @param int $timeout Seconds this request shall last before it times out.
		* @return Request
		*/
		public function call(?string $method = self::GET, null|string|array $data = null, int $timeout = 60): Request {
			if($method === self::GET) {
				$url =  $this->getUrl();

				if($data !== null) {
					$sign = strpos($url, '?') ? '&' : '?';
					$url .= $sign . http_build_query($data, '', '&');
				}

				$this->setUrl($url);
				$this->setOption(CURLOPT_HTTPGET, true);
			} else {
				$this->setOption(CURLOPT_CUSTOMREQUEST, $method);
				$this->setOption(CURLOPT_POSTFIELDS, $data);
			}

			$this->setOption(CURLOPT_HTTPHEADER, $this->headers);
			$this->setOption(CURLOPT_TIMEOUT, $timeout);
			$this->setOption(CURLOPT_WRITEHEADER, $this->headerHandle);

			// If there is any stored cookies, use the assigned cookiejar
			if($this->cookiejar !== null) {
				if(fopen($this->cookiejar, "a+") === false) {
					throw new \RuntimeException("The cookiejar we were given could not not be opened.");
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

			$this->returndata = curl_exec($this->curl);
			$this->curlInfo = curl_getinfo($this->curl);

			$this->response = new Response($this);

			curl_close($this->curl);
 
			// PHP 8.0.0 > compat
			// as of PHP8 curl_close has no effect
			// causing cookiejars to not be flushed
			// forcing a new connection circumvents
			$this->curl = null;
			$this->curl = curl_init();

			if($this->curlInfo["redirect_count"] >= $this->getOption(CURLOPT_MAXREDIRS)) {
				throw new ClientError(sprintf(
					"Reached maximum number of redirects (%d)",
					$this->getOption(CURLOPT_MAXREDIRS)),
				CURLE_TOO_MANY_REDIRECTS);
			} else if(curl_errno($this->curl) != CURLE_OK) {
				throw new ClientError(
					curl_error($this->curl),
					curl_errno($this->curl)
				);
			} else if($this->response->isSuccess() !== true) {
				throw new HttpError(
					$this->response->getBody(),
					$this->response->getCode()
				);
			}

			return $this;
		}

		/**
		* Perform the request through HTTP GET
		* @param null|string|array $data
		* 	Parameters to send with this request, see the call method for more information on this parameter.
		*	Naturally you should not find a need for this parameter, but it is implemented just in case the server masquarades.
		*
		* @param int $timeout - Seconds this request shall last before it times out.
		* @return \Http\Request
		*/
		public function get(null|string|array $data = null, int $timeout = 60): Request {
			return $this->call(self::GET, $data, $timeout);
		}

		/**
		* Perform the request through HTTP POST
		* @param null|string|array $data Postfields to send with this request, see the call method for more information on this parameter
		* @param int $timeout Seconds this request shall last before it times out.
		* @return Request
		*/
		public function post(null|string|array $data = null, int $timeout = 60): Request {
			return $this->call(self::POST, $data, $timeout);
		}

		/**
		* Obtain metainformation about the request without transferring the entire message-body
		*A HEAD request does not accept post data, so the $data parameter is not available here.
		* 
		* @param int $timeout Seconds this request shall last before it times out.
		* @return Request
		*/
		public function head(int $timeout = 60): Request {
			return $this->call(self::HEAD, null, $timeout);
		}

		/**
		* Put data through HTTP PUT.
		* @param null|string|array $data Data to send through this request, see the call method for more information on this parameter.
		* @param int $timeout Seconds this request shall last before it times out.
		* @return Request
		*/
		public function put(null|string|array $data = null, int $timeout = 60): Request {
			return $this->call(self::PUT, $data, $timeout);
		}

		/**
		* Requests that the origin server delete the resource identified by the Request-URI.
		* @param null|string|array $data 
		*	When using this parameter you should consider signaling the pressence of a message body
		*	By providing a Content-Length or Transfer-Encoding header.
		*
		* @param int $timeout - Seconds this request shall last before it times out.
		*/
		public function delete(null|string|array $data = null, int $timeout = 60): Request {
			return $this->call(self::DELETE, $data, $timeout);
		}

		/**
		* Patch those data to the service.
		* @param null|string|array $data - Data to send with this requst.
		* @param int $timeout Seconds this request shall last before it times out.
		* @return object
		*/
		public function patch(null|string|array $data = null, int $timeout = 60): Request {
			return $this->call(self::PATCH, $data, $timeout);
		}

		/**
		* Provide an additional header for this request.
		* @param string $header The header to send with this request.
		* @return Request
		*/
		public function setHeader(string $header): Request {
			$this->headers[] = $header;
			return $this;
		}

		/**
		* Specifies the port to be requested upon
		* @param int a port number.
		* @return Request
		*/
		public function port(int $port): Request {
			$this->setOption(CURLOPT_PORT, $port);
			return $this;
		}

		/**
		* Send a cookie with this request.
		* @param string $name name of the cookie
		* @param string $value value of the cookie
		* @return Request
		*/
		public function setCookie(string $name, string $value): Request {
			$this->cookies[$name] = (object) [
				"name" => $name,
				"value" => $value
			];

			return $this;
		}

		/**
		* The name of a file in which to store all recieved cookies when the handle is closed, e.g. after a call to curl_close.
		* This is automatically done by this class is destructed.
		* @param string $filepath
		* @return object
		* @since 1.4
		*/
		public function cookiejar(string $filepath): Request {
			$this->cookiejar = $filepath;
			return $this;
		}

		/**
		* Manually set a cURL option for this request.
		* @param int $option The CURLOPT_XXX option to set.
		* @param mixed Value for the option
		* @return Request
		* @see http://php.net/curl_setopt
		*/
		public function setOption(int $option, mixed $value): Request {
			$this->options[$option] = $value;
			return $this;
		}

		/**
		* Retrieve the current value of a given cURL option
		* @param int $option CURLOPT_* value to retrieve
		* @return mixed
		* @since 1.3
		*/
		public function getOption(int $option): mixed {
			return $this->options[$option];
		}

		/**
		* A string to use as authorization for this request.
		* @param string $username The username to use
		* @param string $password The password that accompanies the username
		* @param int $authType The HTTP authentication method(s) to use
		* @return Request
		*/
		public function authorize(string $username, string $password, int $authType = CURLAUTH_ANY): Request {
			$this->setOption(CURLOPT_HTTPAUTH, $authType);
			$this->setOption(CURLOPT_USERPWD, $username.":".$password);
			return $this;
		}

		/**
		* Alias/Helper method for the above.
		* @param string $username The username to use
		* @param string $password The password that accompanies the username
		* @param int $authType The HTTP authentication method(s) to use
		* @return Request
		*/
		public function authenticate(string $username, string $password, int $authType = CURLAUTH_ANY): Request {
			return $this->authorize($username, $password, $authType);
		}

		/**
		* Enable CURL verbosity, captures and pushes the output to the response headers.
		* @return Request
		*/
		public function verbose(): Request {
			$this->verbosityHandle = fopen('php://temp', 'rw+');
			$this->setOption(CURLOPT_VERBOSE, true);
			$this->setOption(CURLOPT_STDERR, $this->verbosityHandle);

			return $this;
		}

		/**
		* Sets destination url, to which this request will be sent.
		* @param string $value a fully qualified url
		* @return Request
		*/
		public function setUrl(string $value): Request {
			$this->setOption(CURLOPT_URL, $value);
			return $this;
		}

		/**
		* Get the URL to be requested.
		* @return string
		* @since 1.1
		*/
		public function getUrl(): string {
			return $this->getOption(CURLOPT_URL);
		}

		/**
		 * Get the response object
		 * @since 3.0
		 * @return Response
		 */
		public function getResponse(): Response {
			return $this->response;
		}
	}
}