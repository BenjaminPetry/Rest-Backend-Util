<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */
// The following HTTP methods should be used to work with resources:
// - POST (create)
// - GET (retrieve)
// - PUT (update everything)
// - PATCH (update subpart)
// - DELETE (delete)
// The URL of such a request should be like the following form:
// - e.g. GET http://localhost/example/users/1
// - e.g. GET http://localhost/example/users/

// Inspired by https://github.com/jacwright/RestServer/blob/master/source/Jacwright/RestServer/RestServer.php
class Request
{
    public static $rootDir; // the servers directory in which the API is located on the disc (no ending slash)
    public static $rootUrl; // the root url under which this API can be accessed (without the server and no ending slash)
    public static $apiUrl; // the api url (like $rootUrl, but including the server. No ending slash)
    public static $originUrl; // the origin, which sent this request
    private static $queryParams; // the query (only GET requests, e.g. api?=query) of the url as array
    private static $bodyParams; // the body parameters (not for GET requests)
    public static $method;
    public static $url; // the current url without the root url (no starting and ending slash)
    public static $accessToken;
    public static $currentSession; // contains information about the current session. Use sessionField() to retrieve specific information.

    public static function init()
    {
        self::$rootDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']));
        self::$rootUrl = dirname($_SERVER['SCRIPT_NAME']);
        self::$apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")."://".$_SERVER['HTTP_HOST'].self::$rootUrl;
        self::$originUrl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER["HTTP_ORIGIN"] : "");
        self::$method = $_SERVER['REQUEST_METHOD'];
        self::$queryParams = filter_input_array(INPUT_GET);
        self::$bodyParams = self::$method != "GET" ? json_decode(file_get_contents('php://input'), true) : array();
        self::$url = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']); // remove the query
        self::$url = preg_replace('/^' . preg_quote(self::$rootUrl, '/') . '/', '', self::$url); // remove the root url
        self::$url = removeSlash(self::$url, 3); // remove starting and ending slash
        $formats = isset($_GET['format']) ? $_GET['format'] : (isset($_SERVER['HTTP_FORMAT']) ? $_SERVER['HTTP_FORMAT'] : (isset($_SERVER["HTTP_ACCEPT"]) ? preg_replace('/\s+/i', '', $_SERVER['HTTP_ACCEPT']) : null));
        $authorization_header = isset($_SERVER["HTTP_AUTHORIZATION"]) ? $_SERVER["HTTP_AUTHORIZATION"] : "";
        self::$accessToken = ($authorization_header != "" && strtoLower(substr($authorization_header, 0, 7)) == "bearer ") ? substr($authorization_header, 7) : "";
        self::$currentSession = null;
    }

    public static function sessionField($field)
    {
        return (!is_null(self::$currentSession) && array_key_exists($field, self::$currentSession)) ? self::$currentSession[$field] : null;
    }

    public static function queryFieldExists($field)
    {
        return self::$queryParams && array_key_exists($field, self::$queryParams);
    }
  
    public static function query($field)
    {
        return self:: queryFieldExists($field) ? filter_var(self::$queryParams[$field]) : null;
    }

    public static function bodyFieldExists($field)
    {
        return self::$bodyParams && array_key_exists($field, self::$bodyParams);
    }
    public static function body($field)
    {
        return self::bodyFieldExists($field) ? filter_var(self::$bodyParams[$field]) : null;
    }
}
