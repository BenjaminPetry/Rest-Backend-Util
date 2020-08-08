<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

class RestServer
{
    private static $authHandler = null;
  
    public static function init()
    {
        set_exception_handler("RestServer::onError");
        if (PHP_MAJOR_VERSION >= 7) {
            set_error_handler(function ($errno, $errstr) {
                return strpos($errstr, 'Declaration of') === 0;
            }, E_WARNING);
        }

        session_start();

        // Check for OPTIONS request
        if (Request::$method == "OPTIONS") {
            Response::send(null);
            exit(0);
        }
    
        // Check if request is valid
        if (!in_array(Request::$method, array("GET","POST","PUT","PATCH","DELETE","OPTIONS","HEAD"))) {
            throw new RestException(405);
        }
    }

    public static function onError($exception)
    {
        if ($exception instanceof RestException) {
            Response::error($exception->getCode(), $exception->getMessage(), $exception->getData());
        } else {
            Log::exception($exception);
            Response::error(500, "Internal Server Failure. Try again later.");
        }
    }

    public static function setAuthHandler($handler)
    {
        self::$authHandler = $handler;
    }

    public static function controller($controllerClass, $file)
    {
        if (UrlManager::fileExists($file)) {
            return;
        }
        require_once($file);
        ControllerParser::parseClass($controllerClass, $file);
    }

    protected static function callMethod($call, $params)
    {
        require_once($call["file"]);
        $controller = new $call["class"];
        if (method_exists($controller, $call["method"])) {
            $result = call_user_func_array(array($controller, $call["method"]), $params);
            Response::send($result);
        } else {
            throw new RuntimeException("Method ".$call["class"].".".$call["method"]." is not implemented.");
        }
    }

    public static function handle()
    {
        if (AUTH_ENABLED) {
            if (is_null(self::$authHandler)) {
                throw new RuntimeException("You must set a authorization handler if you enable authorization! Use BasicAuthHandler() if you don't want to implement your own.");
            }
            // If an access token is present get the current session
            if (Request::$accessToken) {
                Request::$currentSession = self::$authHandler->getCurrentSession(Request::$accessToken);
            }
        }

        $params = array();
        $call = UrlManager::findUrlCall(Request::$method, Request::$url, $params);
        if ($call) {
            // Check Query Fields
            $missingFields = array();
            foreach ($call["query-fields"] as $field) {
                if (Request::queryFieldExists($field["field"])) {
                    $params[$field["variable"]] = Request::query($field["field"]);
                } elseif ($field["required"]) {
                    $missingFields[] = $field["field"];
                }
            }
            if (count($missingFields) > 0) {
                throw new MissingFieldsException("The field(s) '". join(", ", $missingFields)."' must be provided as part of the query of a GET request.");
            }

            // Check Body Fields
            foreach ($call["body-fields"] as $field) {
                if (Request::bodyFieldExists($field["field"])) {
                    $params[$field["variable"]] = Request::body($field["field"]);
                } elseif ($field["required"]) {
                    $missingFields[] = $field["field"];
                }
            }
            if (count($missingFields) > 0) {
                throw new MissingFieldsException("The field/s '". join(", ", $missingFields)."' must be provided as part of the body of a non-GET request.");
            }


            // Check Authorization
            if (is_null(self::$authHandler) || self::$authHandler->hasAccess($call, $params)) {
                $indexedParams = self::sortParams($call, $params);
                self::callMethod($call, $indexedParams);
            }
        }
        throw new RestException(405, "The URL is not valid.");
    }

    private static function sortParams($call, $params)
    {
        $result = array();
        foreach ($call["args"] as $field => $fieldInfo) {
            $paramKeyExists = array_key_exists("$".$field, $params);
            if (!$paramKeyExists && !$fieldInfo["optional"]) {
                throw new RuntimeException("The method's ('".$call["method"]."') field '$field' must be part of the request documentation and, if optional, have a default value.");
            }
            $paramValue = $paramKeyExists ? $params["$$field"] : $fieldInfo["defaultValue"];
            $result[$fieldInfo["pos"]] = $paramValue;
        }
        return $result;
    }
}
