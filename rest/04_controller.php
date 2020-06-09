<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

class ControllerParser
{
    // adds the methods according their urls into the UrlManager
    public static function parseClass($class, $requiredFile)
    {
        if (class_exists($class)) {
            $reflection = new ReflectionClass($class);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            $defaultAuth = self::parseRights($reflection->getDocComment());
            foreach ($methods as $method) {
                $doc = $method->getDocComment();
                $auth = self::parseRights($doc, $defaultAuth);
                if (preg_match_all('/@url[ \t]+(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)[ \t]+\/?([^\s\?]*)(\?\S*)?([ \t]+[^\n]*)?/s', $doc, $matches, PREG_SET_ORDER)) {
                    $params = $method->getParameters();
                    $args = array();
                    $userPresent = false;
                    foreach ($params as $param) {
                        $defaultValue = $param->isOptional() ? $param->getDefaultValue() : null;
                        $args[$param->getName()] = array("pos" => $param->getPosition(), "optional"=>$param->isOptional(), "defaultValue" => $defaultValue);
                        $userPresent |= ($param->getName() == "user");
                    }
                    if ($auth != null && in_array(AUTH_ROLE_SELF, $auth) && !$userPresent) {
                        throw new RuntimeException("A method that wants to check the 'self'-right requires to have a parameter named '\$user'.");
                    }

                    foreach ($matches as $match) {
                        $urlInfo = self::parseUrlMatch($match);
                        $call = array("file"=>$requiredFile, "class"=>$class, "method"=> $method->getName(), "args"=>$args, "auth"=>$auth);
            
                        UrlManager::add($urlInfo, $call);
                    }
                }
            }
        }
    }

    private static function parseRights($doc, $parentRights = array(AUTH_ROLE_USER))
    {
        if (preg_match_all('/@auth ([0-9a-zA-Z,: -]+)/s', $doc, $matches)) {
            $rightString = trim($matches[1][0]);
            $tmp = explode(",", $rightString);
            $tmp = array_map(function ($element) {
                return trim($element);
            }, $tmp);
            if (in_array(AUTH_ROLE_NONE, $tmp)) {
                return null;
            }
            return $tmp;
        }
        return $parentRights;
    }

    private static function parseUrlMatch($match)
    {
        $urlInfo = array();

        $urlInfo["method"] = $match[1];
        $urlInfo["url"] = substr($match[2], -1) == "/" ? substr($match[2], 0, -1) : $match[2];
        $urlInfo["tree"] = $urlInfo["url"] == "" ? array() : explode("/", $urlInfo["url"]);

        $query = count($match) >= 4 ? $match[3] : "";
        $query = substr($query, 0, 1) == "?" ? substr($query, 1) : $query;
        $queryFields = $query == "" ? array() : explode("&", $query);
        $urlInfo["query-fields"] = array_map(function ($element) {
            return self::parseFields($element, true);
        }, $queryFields);

        $body = count($match) >= 5 ? preg_replace('/\s/', '', $match[4]) : "";
        $bodyFields = $body == "" ? array() : explode(",", $body);
        $urlInfo["body-fields"] = array_map(function ($element) {
            return self::parseFields($element, false);
        }, $bodyFields);
        return $urlInfo;
    }

    // parses a fieldstring, such as field=$variable
    private static function parseFields($fieldEqualString, $isQuery)
    {
        $matches = array();
        if (!preg_match('/([^\s\=]+)=(\$[^\s\=\!\?]+?)(\?|\!|$)/s', $fieldEqualString, $matches)) {
            throw new RuntimeException('Invalid url. The fields in the REST url\'s '.($isQuery ? "query" : "body").' '.$fieldEqualString.' must be in the format: field=$variable');
        }
        $field = $matches[1];
        $variable = $matches[2];
        $required = $matches[3] == "?" ? false : ($matches[3] == "!" ? true : !$isQuery);
        return array("field" => $field, "variable"=>$variable, "required"=>$required);
    }
}
