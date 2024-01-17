<?php
/**
* Parses and contains all content written to the HTTP stream
* @package Http\Request
* @license MIT
*/
namespace Http {
	class Response {
		private $request, $rawHeaders;
		private $responseHeaders = [];
		public $xmlErrors = [];

		public function __construct(Request $request) {
			$this->request = $request;

			// And parse the headers for a client to use.
			rewind($this->request->headerHandle); 
			$this->rawHeaders = rtrim(stream_get_contents($this->request->headerHandle), "\r\n");
			fclose($this->request->headerHandle);

			$this->responseHeaders = $this->parseHeaders($this->rawHeaders);

			if(is_resource($this->request->verbose)) {
				rewind($this->request->verbose); //@todo: Why do I need this, I'm still wondering...
				$verboseContent = stream_get_contents($this->request->verbose);

				$this->rawHeaders .= $verboseContent;
				$this->responseHeaders["Verbosity"] = explode("\n", $verboseContent);
				unset($this->request->verbose);
			}
		}

		/**
		* Gives the raw response returned by remote server.
		* @since 1.2
		* @return string
		*/
		public function __toString() {
			return $this->getBody();
		}

		/**
		* Get cURL information regarding this request.
		* If index is given, returns its value, NULL if index is undefined.
		* Otherwise, returns an associative array of all available values.
		*
		* @param string $opt An index from curl_getinfo() returned array.
		* @see http://php.net/manual/en/function.curl-getinfo.php
		* @throws CurlError
		* @return mixed
		*/
		public function getInfo($opt = false) {
			if(empty($this->request->curlInfo)) {
				throw new CurlError("A cURL session has yet to be performed.");
			}

			if($opt !== false) {
				return isset($this->request->curlInfo[$opt]) ? $this->request->curlInfo[$opt] : null;
			}

			return $this->request->curlInfo;
		}

		/**
		* Returns the HTTP code represented by this reponse
		* @return int
		*/
		public function getCode() : int {
			return (int) $this->getInfo("http_code");
		}

		/**
		* Finds out whether a request was successful or not.
		* @return bool
		*/
		public function isSuccess() : bool {
			return $this->getCode() < 400;
		}

		/**
		 * Attempt to parse HTTP headers from raw respnose.
		 * - headers spanning multiple lines will be returning as a single index with line breaks
		 * - headers sent multiple times e.g Set-Cookie will be returned as an array
		 * - regular key-value headers will be returned as-is indexed by its key.
		 * Herein lies deep and dark magic.
		 * @param string $rawHeaders the raw header reponse
		 * @return array The parsed headers.
		 */
		function parseHeaders(string $rawHeaders): array {
			$headers = [];
			$currentKey = '';
		
			foreach (explode("\n", $rawHeaders) as $headerLine) {
				$headerParts = explode(':', $headerLine, 2);
		
				if (isset($headerParts[1])) {
					// Regular headers with a key and value
					// While RFC 7230 dictates HTTP headers are allowed to be all lowercase the first letter of each word
					// will be capitalized in order to maintain a uniform response across all requests.
					// \b: Word boundary anchor, asserts the position between a word character and a non-word character.
					// \w: Shorthand for any word character (alphanumeric + underscore).
					$headerKey = preg_replace_callback('/\b\w/', function($matches) {
						return strtoupper($matches[0]);
					}, trim($headerParts[0]));
		
					$headerValue = trim($headerParts[1]);
		
					if (!isset($headers[$headerKey])) {
						// If the header key is not set, assign the value
						$headers[$headerKey] = $headerValue;
						$currentKey = $headerKey;
					} elseif (is_array($headers[$headerKey])) {
						// If the header key already exists as an array
						// add the header value to the array
						$headers[$headerKey][] = $headerValue;
					} else {
						// If the header key already exists as a single value, convert it to an array,
						// fx. if Set-Cookie has been sent more than once.
						$headers[$headerKey] = [$headers[$headerKey], $headerValue];
					}
				} elseif (isset($headerParts[0]) && substr($headerParts[0], 0, 1) === "\t" && $currentKey) {
					// Multi-line headers, e.g., Set-Cookie with multiple values
					$headers[$currentKey] .= "\r\n\t" . trim($headerParts[0]);
				} elseif (!$currentKey) {
					// No header key (e.g., the status line)
					$headers[0] = trim($headerParts[0]);
				}
			}
			return $headers;
		}

		/**
		* Returns parsed header values.
		* If header is given returns that headers value.
		* Otherwise all response headers is returned.
		* 
		* @param string $header Name of the header for which to get the value
		* @return mixed
		*/
		public function getHeaders($header = false) {
			if($header !== false) {
				return isset($this->responseHeaders[$header]) ? $this->responseHeaders[$header] : null;
			}

			return $this->responseHeaders;
		}

		/**
		* Get cookies set by the remote server for the performed request, in case a cookiejar wasn't utilized.
		* @since 1.2
		* @param string $cookie Name of the cookie for which to retrieve details, null if it doesn't exist, ommit to get all cookies.
		* @return array
		*/
		public function getCookie($cookie = false) : array {
			if($cookie !== false) {
				return isset($this->responseHeaders["Set-Cookie"][$cookie]) ? $this->responseHeaders["Set-Cookie"][$cookie] : null;
			}

			return $this->responseHeaders["Set-Cookie"];
		}

		public function getCookies() {
			return $this->responseHeaders["Set-Cookie"];
		}

		/**
		* Get the request response text without the headers.
		* @return string
		*/
		public function getBody() : string {
			if($this->request->returndata === null) {
				throw new \RuntimeException("Perform a request before accessing response data.");
			}

			return $this->request->returndata;
		}

		/**
		* Decodes and returns an object, assumes HTTP Response is JSON
		* @return \stdClass
		*/
		public function asObject() : \stdClass {
			return json_decode($this->getBody());
		}

		/**
		* Decodes and returns an associative array, assumes the HTTP Response is JSON
		* @return array
		*/
		public function asArray() : array {
			return json_decode($this->getBody(), true);
		}

		/**
		* Returns a SimpleXML object with containing the response content.
		* After calling any potential xml error will be available for inspection in the $xmlErrors property.
		* @param bool $useErrors Toggle xml errors supression. Please be advised that setting this to true will also clear any previous XML errors in the buffer.
		* @return \SimpleXMLElement
		*/
		public function asXml($useErrors = false) : \SimpleXMLElement {
			libxml_use_internal_errors($useErrors);
			$xml = simplexml_load_string($this->getBody());
			if($useErrors == false) $this->xmlErrors = libxml_get_errors();
			return $xml;
		}
	}
}