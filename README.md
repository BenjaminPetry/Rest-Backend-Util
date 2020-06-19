# Backend Util

This project is a PHP framework for my (Benjamin Petry) personal projects' backend. It implements a REST API Server with basic authorization.

**IMPORTANT: This software is provided on an "AS IS" BASIS, without warranties or conditions of any kind, either express or implied.**

## Setup

<sup>v0.53</sup>

1. Checkout this framework as a submodule of your project, preferably in the subfolder `util`.
2. Include the `init.php` file in your main php-file (e.g. `index.php`). A recommended backend data structure (also see the project Create-BPetry-App) is the following:

```txt
backend           # the current working directory (also as \$cwd)
|-> cache         # the directory for caches
|-> log           # the log directory (must be writable by the program)
|-> services      # for all the services to implement
|-> util          # this project as a submodule
|   |-> services  # default services of this framework
|   |-> init.php  # the main file of this framework
|-> .htaccess     # requires for rerouting the requests to index.php
|-> config.development.php    # the configuration files for development
|-> config.production.php     # the configuration files for production
|-> index.php     # the main script for the REST API including init.php
```

### Configuration

<sup>v0.53</sup>

The `init.php` can be configured by a configuration file. Use `config.php` for general settings and `config.development.php` and `config.production.php` for development and production mode, respectively. The file should look like the following:

```PHP
<?php
// Use $cwd if you want to refer to the current working directory (parent directory of this directory)

//define('DEBUG',true); // uncomment to print debug text
//define('TEST',true); // uncomment to use TEST-settings, such as the test database
$config["run_as_local"] = true; // whether the PHP script should be executed at localhost (e.g. not sending e-mail when exceptions occur)
$config["name"] = "create-bpetry-app"; // description of the project for exception e-mails
$config["cors-accept-origins"] = array("http://localhost:3000"); // the url from which the backend accepts cross origin requests

// EMAIL SETTINGS
$config["email-default-sender"] = "admin@bpetry.de";
$config["email-log-receiver"] = "exceptions@bepeproductions.de";

// PATH SETTINGS
$config["path-cache"] = "$cwd/cache";
$config["path-tmp"] = "$cwd/tmp";
$config["path-log"] = "$cwd/log";

// DATABASE SETTINGS
$config["db-host"] = "127.0.0.1:3306";
$config["db-user"] = "util";
$config["db-password"] = "password";
$config["db-name"] = "util";

// TEST DATABASE SETTINGS
$config["db-host-test"] = "127.0.0.1:3306";
$config["db-user-test"] = "util";
$config["db-password-test"] = "password";
$config["db-name-test"] = "util-test";

// BACKEND SECRET
$config["secret"] = "abc..."; // 128-byte secret, used for token signatures

// TOOLS
$config["tools-enabled"] = false;

// AUTHORIZATION
$config["auth"] = AUTH_SERVER; // AUTH_NONE, AUTH_SERVER, AUTH_CLIENT
$config["auth-superadmin"] = 1; // the user ID of the user that is superadmin
$config["auth-iss"] = "http://localhost/example/v1"; // issuer of the tokens
$config["auth-clients"] = array(
  "http://localhost/example/v1" => "secret...abc..."
); // the keys that the authentication clients use (if this is the server). Every client must be present in the microservices array, too!
$config["auth-microservices"] = array(
  "http://localhost/example/v1" => "secret...abc..."
); // the keys of microservices that can use methods of these classes
$config["auth-microservices-permissions"] = array(
  "http://localhost/example/v1" => array("permission1","permission2")
}; // this array describes the specific permissions of a microservice (use @auth microservice-[permission] in your methods)
```
<sup>v0.61</sup>
**Note**: Setting the HTTP headers `"HTTP_TEST" = true` and `"HTTP_VERBOSE" = true` will override the setting of `CF_TEST` and `CF_VERBOSE`, respectively.

### Directories and Files

<sup>v0.53</sup>

In the configuration, the following directories can be defined:

- the `path-cache` is used to store information that improves the performance of the REST API.
- the `path-log` is used to store log information, e.g. when unexpected Exceptions have been thrown.
- the `path-tmp` is used to store temporary information, e.g. when a user uploads a picture.

**IMPORTANT**: All of this directories must be readable and writeable by the server! Under MacOS use `sudo chmod 777 [path]` to set a directory readable, writeable and executable.

Furthermore, you need the `.htaccess` file to reroute all requests to the index.php script:

```xml
# Benjamin Petry (www.bpetry.de)
# Copyright 2020 by Benjamin Petry.
# This software is provided on an "AS IS" BASIS,
# without warranties or conditions of any kind, either express or implied.
# Disclaimer: Derived from Drupal htaccess

# Protect .htaccess
<Files .htaccess>
Order allow,deny
Deny from all
</Files>

# Don't show directory listings for URLs which map to a directory.
Options -Indexes

# Hide all the contents of directories
IndexIgnore *

# Set the default handler.
DirectoryIndex index.php

# Various rewrite rules.
<IfModule mod_rewrite.c>
  RewriteEngine on

  # Set "protossl" to "s" if we were accessed via https://.  This is used later
  # if you enable "www." stripping or enforcement, in order to ensure that
  # you don't bounce between http and https.
  RewriteRule ^ - [E=protossl]
  RewriteCond %{HTTPS} on
  RewriteRule ^ - [E=protossl:s]

  # Make sure Authorization HTTP header is available to PHP
  # even when running as CGI or FastCGI.
  RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

  # Block access to "hidden" directories whose names begin with a period.
  RewriteRule "/\.|^\.(?!well-known/)" - [F]

  # Pass all requests to index.php
  RewriteRule ^ index.php [L]

</IfModule>
```

### Add Path to XAMPP for Testing

<sup>v0.53</sup>

Add to `etc/extra/httpd-xampp.conf` the following

```xml
Alias /yourdomain/api/v1 "pathToYourDirectory"

<Directory "pathToYourDirectory">
    MultiviewsMatch Any
    AllowOverride all
    Order Deny,Allow
    Allow from all
    Require all granted
    Options MultiViews Indexes SymLinksIfOwnerMatch IncludesNoExec
    Require method GET POST OPTIONS PUT PATCH HEAD
</Directory>
```

## Get started

<sup>v0.53</sup>

When you created the project folder as described in the `Setup` section, open index.php and replace the content with the following:

```PHP
<?php
  define("EXPECTED_BACKEND_UTIL_VERSION", ">=0.53");
  define("META_VERSION", "0.1");
  define("META_COPYRIGHT", "2020 by YOUR NAME");

  require_once("util/init.php"); // adding the framework
  // add a controller that could potentially react to a REST request
  Rest::controller("UserService","util/services/userService.php");
  // handle the current request
  Rest::handle();
```

### Implement a controller

<sup>v0.50</sup>

Implementing a controller is simple. Imagine you want to implement a test service that returns the current time on the server.
Create a new file under `backend/services`, e.g. `testService.php`. In this file define a new class, e.g. `TestService`.

```php
class TestService
{
  /**
   * returns the current time
   *
   * @auth none
   * @url GET /currentTime
   */
  public static function currentTime()
  {
    return date();
  }
}
```

> TODO

#### Use of Exceptions

<sup>v0.53</sup>

If something goes wrong, you should throw exceptions. Those are directly handled by the Rest class and a proper response with the error will be sent back. Most Exception allow you to enter an own `internal code` so that the frontend can handle the error in a more specific way. The following exceptions are implemented by default.

```php
// the PUT/POST/UPDATE request did not contain all required fields.
// This will generate a response with a 400 status code.
throw new MissingFieldsException($message = null, $missingFields = array(), $internalCode = null);

// some of the fields of the PUT/POST/UPDATE request are invalid.
// This will generate a response with a 422 status code.
throw new FieldValidationException($message = null, $invalidFields = array(), $internalCode = null);

// something that the user tries to do is not correct.
// This exceptions can be used for any user interaction errors that require an internal code.
throw new UserInputException($code, $message = null, $internalCode = null);

// this exception can be used for any other errors that require
// a specific status code, but no internal code.
throw new RestException($code, $message = null);

// this exception should be used for all exception that are caused by a server problem (or wrong programming).
// It will generate a response with a 500 status code and log the exception.
throw new RuntimeException($message);
```

### Use the Rest Class

<sup>v0.1</sup>

The `Rest` class is the main class of the Rest Server. It provides the following methods:

```PHP
Rest::init();     // initializes the Rest Server which is done automatically by the init.php script.
Rest::request();  // returns the current request as Request instance (see later section about Request)
Rest::response(); // returns the current prepared Response instance (see later section about Response)
Rest::controller($urlId, $file, $controllerClass); // if $urlId == Rest::request()->controller, then the $file will be included and $controllerClass->handle() will be executed to handle the request
Rest::finalize(); // Should be called at the end of your main script and returns a 405 (invalid action) response, if no controller handled the request
```

## REST Principles

<sup>v0.53</sup>

### URL Schema

<sup>v0.53</sup>

```
[backend url]/[version]/[REST url]
```

1. The API endpoint must be versioned, e.g. `.../api/v1/...`
2. The API uses plural for entity paths, e.g. `...v1/users/Benjamin` (`users` instead of `user`)
3. The API might have a query for `GET` requests for e.g. filters: `...?user=testuser`
4. The API might have a body for all `non-GET` requests (e.g. `{'username': "testuser"}`)

Example requests:

```shell
GET localhost/example/api/v1/users/testuser
GET localhost/example/api/v1/users/?online=true
```

### Request

<sup>v0.53</sup>

- GET is used for fetching one (single) or multiple (collection) resources.
- POST is used for creating a new resource.
- PUT is used for updating a whole resource.
- PATCH is used to update a part (e.g. on field) of a resource.
- DELETE is used to delete a source.

#### Request Class

<sup>v0.53</sup>

The request class already prepares the sent request. It is implemented as a static-class and is initiated after definition, but extended with session info by the [Rest class](#Use-the-Rest-Class) later. The request class provides the following public accessible fields:

```PHP
Request::$rootDir; // the servers directory in which the API is located on the disc (no ending slash)
Request::$rootUrl; // the root url under which this API can be accessed (without the server and no ending slash)
Request::$apiUrl; // the api url (like $rootUrl, but including the server. No ending slash)
Request::$method; // the HTTP method, e.g., GET, POST, ...
Request::$url; // the current url without the root url (no starting and ending slash, no query)
Request::$accessToken; // the access token (see Authentication)
```

Furthermore, the request class provides the following methods to assess further data:

```PHP
Request::sessionField($field); // returns information from the current session, derived from the access token (e.g., current user).
Request::queryFieldExists($field); // true if the given field was part of the url's query
Request::query($field); // returns the value of a field in the url's query or null if the field was not provided
Request::bodyFieldExists($field); // true if the given field was part of the request's body
Request::body($field); // returns the value of a field in the request's body or null if the field was not provided
```

### Response

<sup>v0.53</sup>

A response always looks like this:

```json
{
  "status" : 200,
  "message" : "OK",
  "data" : ...
}
```

**Note**: The status code is always sent as well to make tempering with the HTTP Status code more difficult.

#### Response Class

<sup>v0.53</sup>

The response class prepares and sends the response (including header information). It is implemented as a static-class and contains the following public methods:

```PHP
Request::getStatus(); // returns the current status code, that has been set
Request::setStatus($statusCode, $relativeLocationRedirect=""); // sets the current status code. If the status code is 201, it expects a relative path to the created resource (Request::rootUrl will be added)
Request::error($statusCode, $errorMessage, $data=null); // will directly send an error response. Data can be anything the front-end could use for better error handling.
Request::send($data, $statusCode=null, $message="", $relativeLocationRedirect=""); // sends a response directly. If status code is NULL, 200 will be sent as status code.
```

### Status Codes

<sup>v0.53</sup>

#### Data Codes

| Status Code | When                                                   | HTTP Methods            | Single/Collection Request |
| ----------- | ------------------------------------------------------ | ----------------------- | ------------------------- |
| 405         | the requested information does not support collections | PUT, PATCH, DELETE      | Collection                |
| 400         | the requested information is incomplete or malformed   | POST, PUT               | Single/Collection         |
| 422         | the requested information is okay, but invalid         | POST, PUT, PATCH        | Single                    |
| 404         | everything is okay, but the resource doesn’t exist     | GET, PUT, PATCH, DELETE | Single                    |
| 409         | a conflict of data exists, even with valid information | POST                    | Single/Collection         |

#### Successful Codes

| Status Code | When                                             | HTTP Methods       | Single/Collection Request |
| ----------- | ------------------------------------------------ | ------------------ | ------------------------- |
| 201         | resource has been created successfully           | POST               | Single/Collection         |
| 200         | resource request has been conducted successfully | GET, PUT, PATCH    | Single                    |
| 204         | successful request with no returned data         | PUT, PATCH, DELETE | Single                    |

#### Authentication Codes

| Status Code | When                                                |
| ----------- | --------------------------------------------------- |
| 401         | access token isn’t provided, or is invalid          |
| 403         | access token is valid, but requires more privileges |

#### Server Failure Codes

| Status Code | When                                                          |
| ----------- | ------------------------------------------------------------- |
| 500         | something in the backend went wrong (e.g., RuntimeExceptions) |

## Log

<sup>v0.53</sup>

The following methods can be used to log information:

```php
Log::exception($ex); // logs exceptions exceptions (will be done automatically and an e-mail will be sent to $config["email-log-receiver"] if not running on localhost)
Log::debug($content); // logs information only if DEBUG is defined (see Configuration)
Log::log($content); // logs information into the log file
```

## Authentication

<sup>v0.53</sup>

There are three authentication modes:

- AUTH_NONE: In this mode the backend does not require any authentication
- AUTH_SERVER: In this mode, the backend contains the user list and checks passwords (for a single application this will be used too)
- AUTH_CLIENT: In this mode, the authentication happens on another server that provides this backend with information about the session and provides the front-end with an access and id token.

> TODO document the BASIC AUTHENTICATION part

## Database

<sup>v0.53</sup>

The database connection has to be configured in the [configuration files](#Configuration). Then, you can use the following methods to query the database:

```php
DB::prepare($query); // turns a query into a prepared statement
DB::execute($preparedStatement, $param=array()) // executes a prepared statement or query directly with the given parameters
DB::lastInsertId(); // if the last executed query has been an INSERT-query, this method returns the last inserted ID
DB::fetch($preparedStatement, $param=array(), $fetchAll=false); // fetches a prepared statement or query directly with given parameters.
DB::date($seconds); // Converts a UTC date into the sql format
```

## Microservices

<sup>v0.53</sup>

Microservices can be used for backends to interact with each other in a secure way. For each request a special access token is provided. Hence, both backends need to share the same secret defined in `\$config["auth-microservices"]`.

### Using Microservices (receiving backend)

The methods a microservice can use are defined as usual by roles. Use the methods `@auth` comment section with the role `microservice` to make it accessible by all microservices that share a key your backend.

A further restriction is, that the path for microservices must start with `ms/`:

```php
/**
 * Sending info
 *
 * @auth microservice
 * @url GET /ms/info
 */
public static function sendInfo()
{
  // DO STUFF
}
```

Sometimes you want that only specific backends can access certain methods. If you want to have more specific permissions for a backend, you can define those in `$config["auth-microservices-permissions"]`.

```php
$config["auth-microservices-permissions"] = array(
  "http://localhost/example/v1" => array("test")
);
```

This config part gives the audience `http://localhost/example/v1` the additional role `microservice-test`. You can use this role in your methods as usual. The general `microservice` role won't match with it, only the backend, that have the additional permission of `test`.

### Using Microservices (sending backend)

To send a request to another backend, you can use the `MicroService`-class. It provides the following methods:

```php
MicroService::get($baseUrl, $relativeUrl, $data); // Sends a GET request. Don't add a query to the relativeUrl!
MicroService::post($baseUrl, $relativeUrl, $data); // Sends a POST request
MicroService::patch($baseUrl, $relativeUrl, $data); // Sends a PATCH request
MicroService::delete($baseUrl, $relativeUrl, $data); // Sends a DELETE request
```

- The `$baseUrl` must be the base url of the backend you want to send the request to. The `$config["auth-microservices"]` array must contain a key with `$baseUrl` whose value is the secret that will be used to sign the access token.
- The `$relativeUrl` is the url you want to access, e.g., `ms/sessions/`.
- `$data` contains the data, you want to send.

### Microservice Response

The receiving backend's response will be the same, as the response a front-end would expect. However, you are not able to use the HTTP status code directly. But since every response contains a field 'status' you can retrieve the status code from the JSON-decoded response the MicroService's methods return.

## Mail

<sup>v0.53</sup>

For sending a simple e-mail just use this method:

```php
Mail::send($to, $subject, $message, $from=null)
```

## Tools

<sup>v0.53</sup>

> TODO

## Util Methods

### removeSlash($url, $pos=2)

<sup>v0.53</sup>

Removes a start and/or ending slash (e.g. for urls or paths).

- @param url the url to trim the slash
- @param pos can be SLASH_STARTING, SLASH_ENDING, SLASH_BOTH
