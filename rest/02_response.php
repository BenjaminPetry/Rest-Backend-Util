<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */
// Inspired by https://github.com/jacwright/RestServer/blob/master/source/Jacwright/RestServer/RestServer.php
class Response // Methods to generate a normalized JSON outcome
{
    private static $statusCode = null;
    private static $redirectLocation = "";

    private static $codes = array(
      '100' => 'Continue',
      '200' => 'OK',
      '201' => 'Created',
      '202' => 'Accepted',
      '203' => 'Non-Authoritative Information',
      '204' => 'No Content',
      '205' => 'Reset Content',
      '206' => 'Partial Content',
      '300' => 'Multiple Choices',
      '301' => 'Moved Permanently',
      '302' => 'Found',
      '303' => 'See Other',
      '304' => 'Not Modified',
      '305' => 'Use Proxy',
      '307' => 'Temporary Redirect',
      '400' => 'Bad Request',
      '401' => 'Unauthorized',
      '402' => 'Payment Required',
      '403' => 'Forbidden',
      '404' => 'Not Found',
      '405' => 'Method Not Allowed',
      '406' => 'Not Acceptable',
      '409' => 'Conflict',
      '410' => 'Gone',
      '411' => 'Length Required',
      '412' => 'Precondition Failed',
      '413' => 'Request Entity Too Large',
      '414' => 'Request-URI Too Long',
      '415' => 'Unsupported Media Type',
      '416' => 'Requested Range Not Satisfiable',
      '417' => 'Expectation Failed',
      '422' => 'One or more fields raised validation errors.',
      '500' => 'Internal Server Error',
      '501' => 'Not Implemented',
      '503' => 'Service Unavailable'
    );

    public static function init()
    {
        self::$statusCode = null;
        self::$redirectLocation = "";
    }
  
    public static function getStatus()
    {
        return self::$statusCode;
    }

    // If $code == 201, $relativeLocationRedirect should be entered, too
    // $relativeLocationRedirect is the relative url of the source that has been created
    public static function setStatus($statusCode, $relativeLocationRedirect="")
    {
        self::$statusCode = $statusCode;
        self::$redirectLocation = $relativeLocationRedirect == "" ? "" : Request::$rootUrl."/".removeSlash($relativeLocationRedirect, 1);
    }
  
    public static function error($statusCode, $errorMessage, $data=null)
    {
        self::send($data, $statusCode, $errorMessage);
    }

    // $relativeLocationRedirect - see self::setStatus()
    public static function send($data, $statusCode=null, $message="", $relativeLocationRedirect="")
    {
        if (is_null(self::$statusCode) || !is_null($statusCode)) {
            self::setStatus(is_null($statusCode) ? 200 : $statusCode, $relativeLocationRedirect);
        }
        self::header();
        $message = $message ? $message : self::$codes[self::$statusCode];
        $result = array("message"=> $message, "status"=>self::$statusCode, "data"=>$data);
        $body = json_encode($result);
        echo($body);
        exit(0);
    }

    private static function header()
    {
        $currentOrigin = !empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
        $allowedOrigins = in_array($currentOrigin, CONFIG_CORS_ACCEPT_ORIGINS) ? array($currentOrigin) : CONFIG_CORS_ACCEPT_ORIGINS;
        foreach ($allowedOrigins as $origin) { // to support multiple origins
            header("Access-Control-Allow-Origin: $origin");
        }
        header('Access-Control-Allow-Methods: GET,POST,PUT,PATCH,DELETE,OPTIONS');
        header('Access-Control-Allow-Credential: true');
        header("Access-Control-Max-Age: 1000");
        header('Access-Control-Allow-Headers: X-Requested-With, content-type, access-control-allow-origin, access-control-allow-methods, access-control-allow-headers, Authorization');
        header('Content-Type: application/json;charset=utf-8');

        $protocol = $_SERVER['SERVER_PROTOCOL'] ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
        // microservices require a 200 HTTP status code.
        $tmpCode = Request::sessionField("isMicroservice") ? 200 : self::$statusCode;
        $code = $tmpCode . ' ' . self::$codes[$tmpCode];
        header("$protocol $code");
        if (self::$redirectLocation != "") {
            header("Location: ".self::$redirectLocation);
        }
    }
}
