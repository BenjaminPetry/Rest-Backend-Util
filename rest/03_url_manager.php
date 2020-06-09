<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

// the UrlManager is used to create a map of the methods and their corresponding urls
class UrlManager
{
    private static $files = array();
    private static $urls = array();
    private static $cacheFile = "";

    public static function init($cacheFile)
    {
        self::$cacheFile = $cacheFile;
        if (file_exists(self::$cacheFile)) {
            list(self::$files, self::$urls) = unserialize(file_get_contents(self::$cacheFile));
        }
        foreach (self::$files as $file => $fileModDate) {
            if (!file_exists($file) || $fileModDate != filemtime($file)) {
                self::clear();
                break;
            }
        }
    }

    public static function clear()
    {
        self::$files = array();
        self::$urls = array();
        self::save();
    }

    protected static function save()
    {
        file_put_contents(self::$cacheFile, serialize(array(self::$files, self::$urls)));
    }

    public static function fileExists($file)
    {
        return array_key_exists($file, self::$files);
    }

    public static function add($urlInfo, $call)
    {
        if (!array_key_exists($call["file"], self::$files)) {
            self::$files[$call["file"]] = filemtime($call["file"]);
        }

        if (!array_key_exists($urlInfo["method"], self::$urls)) {
            self::$urls[$urlInfo["method"]] = array("call"=>null,"urls"=> array());
        }
    
        $call["query-fields"] = $urlInfo["query-fields"];
        $call["body-fields"] = $urlInfo["body-fields"];
        self::$urls[$urlInfo["method"]] = self::addToArray(self::$urls[$urlInfo["method"]], $urlInfo["tree"], $call);

        self::save();
    }

    protected static function addToArray($array, $urlTree, $urlAndcallInfo)
    {
        if (count($urlTree) == 0) {
            $array["call"] = $urlAndcallInfo;
            return  $array;
        }
        $url = array_shift($urlTree);
        if (!array_key_exists($url, $array["urls"])) {
            $array["urls"][$url] = self::addToArray(array("call"=>null,"urls"=>array()), $urlTree, $urlAndcallInfo);
        } else {
            $array["urls"][$url] = self::addToArray($array["urls"][$url], $urlTree, $urlAndcallInfo);
        }
        return $array;
    }

    public static function findInTree($urlTree, $cUrlArray, &$params)
    {
        if (count($cUrlArray) == 0) { // complete url is matched
            return $urlTree["call"];
        }
        if (count($urlTree["urls"])==0) { // no more paths to match
            return null;
        }
        $urlPart = array_shift($cUrlArray);
        if (array_key_exists($urlPart, $urlTree["urls"])) {
            $call = self::findInTree($urlTree["urls"][$urlPart], $cUrlArray, $params);
            if ($call != null) {
                return $call;
            }
        }
        foreach ($urlTree["urls"] as $path => $pathTree) {
            if (substr($path, 0, 1) == "$") {
                $call = self::findInTree($pathTree, $cUrlArray, $params);
                if ($call != null) {
                    $params[$path] = $urlPart;
                    return $call;
                }
            }
        }
        return null;
    }

    public static function findUrlCall($method, $cUrl, &$params)
    {
        if (!array_key_exists($method, self::$urls)) {
            return null;
        }
        $urlTree = self::$urls[$method];
        $cUrl =(substr($cUrl, -1) == "/") ? substr($cUrl, 0, -1) : $cUrl;
        $cUrlArray = $cUrl == "" ? array() : explode("/", $cUrl);
        return self::findInTree($urlTree, $cUrlArray, $params);
    }

    public static function getUrls($httpMethod)
    {
        return array_key_exists($httpMethod, self::urls) ? self::urls[$httpMethod] : array();
    }
}
