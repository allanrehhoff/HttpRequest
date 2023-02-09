#Changelog#
v3.0
- Adding return types to methods.  
- Fixing docblock syntaxes.  
- \Http\Request now returns self
- Added public method \Http\Request::getResponse();
- Added public method \Http\Response::asRaw();

v2.4
- Added method to allow http error suppression  

v2.4
- Added an additional test.  
- Now throwing exception on response codes above 300  

v2.3
- Added a new Digest authentication test  
- Added (hopefully) proper cookie test cases  
- Added a new parameter $password to Http\Request::authorize();  
- Removed an unneccessary if statement  
- Add a new helper/alias method Http\Request::authenticate(); does the same as authorize()  
- Using a cookiejar can now be disabled.

v2.2.0  
- Seperated classes in Http\ namespace  
- Removed incomplete tests  
- Rewritten tests to be compatible with the latest structure  
- Added composer.json file, soon to be commited to packagist  

v2.1.1  
- Changed the way HTTP error codes are handled to a more specific way  
- Fixed a bug where HttpResponse::isSuccess(); would return false, when a redirect was recieved  
  
v2.1  
- Implemented classes autoloading functionality  
- Introduced HttpResponse::asXml(); methoded  
- Updated documentation  
  
v2.0 - **Backwards incompatible**  
- Renamed from WebRequest to a more generic HttpRequest
- Divided Request and Response logic into two seperate classes
- Added autoloader file
- Removed deprecated getCookies(); method