#Changelog#
v2.1.1  
- Changed the way HTTP error codes are handled to a more specific way  

v2.1  
- Implemented classes autoloading functionality  
- Introduced HttpResponse::asXml(); methoded  
- Updated documentation  
  
v2.0 - **Backwards incompatible**  
- Renamed from WebRequest to a more generic HttpRequest
- Divided Request and Response logic into two seperate classes
- Added autoloader file
- Removed deprecated getCookies(); method