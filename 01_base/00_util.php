<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

/**
 * Checks if a version matches an expected version string.
 *
 * @param expectedVersionString can be '>=0.53', '>0.53', '=0.53', etc.
 * @param currentVersion the current version, e.g. '0.53'
 *
 * @return true, if the version matches the expected version string, otherwise false
 */
function checkVersion($expectedVersionString, $currentVersion)
{
    $expVerMatches = array();
    $matchResult = preg_match("/^([><]?[=]?)([0-9]+\.?[0-9]*)$/s", $expectedVersionString, $expVerMatches);
    if (!$matchResult) {
        throw new RuntimeException("Illegal expected-version-string: '".$expectedVersionString."'");
    }
    $comp = $expVerMatches[1];
    $expVersion = floatval($expVerMatches[2]);
    $isVersion = floatval($currentVersion);
    return (($comp == ">=" && $isVersion >= $expVersion) ||
      ($comp == ">" && $isVersion > $expVersion) ||
      ($comp == "=" && $isVersion == $expVersion) ||
      ($comp == "" && $isVersion == $expVersion) ||
      ($comp == "<=" && $isVersion <= $expVersion) ||
      ($comp == "<" && $isVersion < $expVersion));
}

/**
 * Checks if a string starts with a another string (query)
 *
 * @param str string to check if it starts with query
 * @param query the string to check against
 *
 * @return result true if $str starts with $query.
 */
function startsWith($str, $query)
{
    return substr($str, 0, strlen($query)) === $query;
}

/**
 * Removes a start and/or ending slash (e.g. for urls or paths).
 *
 * @param   url the url to trim the slash
 * @param   pos can be SLASH_STARTING, SLASH_ENDING, SLASH_BOTH
 */
function removeSlash($url, $pos=SLASH_ENDING)
{
    if (!$url || $url == "") {
        return $url;
    }
    $url = str_replace("\\", "/", $url);
    if (($pos & SLASH_STARTING) && substr($url, 0, 1) == "/") {
        $url = substr($url, 1);
    }
    if (($pos & SLASH_ENDING) && $url != "" && substr($url, -1) == "/") {
        $url = substr($url, 0, -1);
    }
    return $url;
}


/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

function bigintval($value)
{
    $value = trim($value);
    $negative = substr($value, 0, 1)=="-";
    $multiply = $negative ? -1 : 1;
    $value = $negative ? substr($value, 1) : $value;
    if (ctype_digit($value)) {
        return $value * $multiply;
    }
    $value = preg_replace("/[^0-9](.*)$/", '', $value);
    if (ctype_digit($value)) {
        return $value * $multiply;
    }
    return 0;
}

/**
 * Encodes an object as utf_8
 */
function encode_utf8($object)
{
    if (is_string($object)) {
        return utf8_encode($object);
    } elseif (is_array($object)) {
        foreach ($object as $key => $value) {
            $object[$key] = encode_utf8($value);
        }
    }
    return $object;
}

// Origin: https://base64.guru/developers/php/examples/base64url
function base64url_encode($data)
{
    $b64 = base64_encode($data);
    // Convert Base64 to Base64URL by replacing “+” with “-” and “/” with “_”
    // Remove padding character from the end of line and return the Base64URL result
    return $b64 === false ? false : rtrim(strtr($b64, '+/', '-_'), '=');
}

function base64url_decode($data)
{
    // Convert Base64URL to Base64 by replacing “-” with “+” and “_” with “/”
    return base64_decode(strtr($data, '-_', '+/'), true);
}

// returns a random string with a length max 128. 128 is the default length
function randomHashString($length=128)
{
    $tmpSalt = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $salt = "";
    for ($n = 0; $n < 128; $n++) {
        $rnd = rand(0, strlen($tmpSalt)-1);
        $salt .= substr($tmpSalt, $rnd, 1);
    }
    $hash = hash('sha512', $salt);
    return $length === 128 ? $hash : substr($hash, 0, $length);
}

/**
 * Creates a unique file name in the $outputDir based on $baseFilename with the extension $extension.
 * If $extension is null, then the extension of baseFilename is being used.
 * If the filename already exists a counting variable is added, separated with $sep.
 */
function getUniqueFileName($outputDir, $baseFilename, $extension=null, $sep="-")
{
    if (is_null($extension)) {
        $info =pathinfo($outputDir.$baseFilename);
        $extension =  ".".$info['extension'];
        $baseFilename = $info['filename'];
    }
    $counter = 0;
    $result = $baseFilename.$extension;
    while (file_exists($outputDir.$result)) {
        $counter++;
        $result = $baseFilename.$sep.$counter.$extension;
    }
    return $result;
}

/**
 * Formats a seconds in the form of hh:mm:ss.sss (depending on the given parameters).
 */
function formatSeconds($seconds, $hh = true, $mm = true, $ss = true, $sss = true)
{
    $tmp = $seconds;
    $hhValue = floor($tmp / 3600);
    $tmp = $tmp - $hhValue * 3600;
    $mmValue = floor($tmp / 60);
    $tmp = $tmp - $mmValue * 60;
    $ssValue = floor($tmp);
    $sssValue = round(($tmp - $ssValue) * 1000);

    $result = $hh ? str_pad($hhValue, 2, "0", STR_PAD_LEFT) : "";
    $result .= $hh && ($mm || $ss) ? ":" : "";
    $result .= $mm ? str_pad($mmValue, 2, "0", STR_PAD_LEFT) : "";
    $result .= $mm && $ss ? ":" : "";
    $result .= $ss ? str_pad($ssValue, 2, "0", STR_PAD_LEFT) : "";
    $result .= ($hh || $mm || $ss) && $sss ? "." : "";
    $result .= $sss ? str_pad($sssValue, 3, "0", STR_PAD_RIGHT) : "";
    return $result;
}

/**
 * Parses time of hh:mm:ss.sss.
 * Result will be provided in seconds.
 */
function parseTime($time)
{
    $regex = "/((([0-9]?[0-9]):)?([0-9]?[0-9]):)?([0-9]?[0-9])(\.([0-9][0-9]?[0-9]?))?/s";
    if (!preg_match($regex, $time, $matches)) {
        return null;
    }
    $hh = intval($matches[3] ? $matches[3] : "0");
    $mm = intval($matches[4] ? $matches[4] :  "0");
    $ss = intval($matches[5] ? $matches[5] : "0");
    $sss = intval($matches[7] ? str_pad($matches[7], 3, "0", STR_PAD_RIGHT) : "000");
    $timeSeconds = $hh * 3600 + $mm * 60 + $ss + $sss / 1000.0;
    return $timeSeconds;
}

/**
 * Parses a directory with a regular expression and return the absolute paths
 */
function parseDir($folder, $matching_expr)
{
    $tmp = array();
    if ($handle = opendir($folder)) {
        while (false !== ($entry = readdir($handle))) {
            if (substr($entry, 0, 1) != ".") {
                $matches = array();
                if (preg_match_all($matching_expr, $entry, $matches)) {
                    $tmp[] = array("file"=> $entry,
                                  "path"=> $folder."/".$entry,
                                  "folder"=>$folder);
                }
            }
        }
        closedir($handle);
    }
    return $tmp;
}


/**
 * Iterates recursively through an array and applies a map function on each item.
 *
 * @param arr The array to iterate through
 * @param map_fun the map function to apply on each array item
 * @param params additional parameters for the map function
 *
 * @return arr the modified array
 *
 * @see https://gist.github.com/vdvm/4665450
 */
function array_map_rec($arr, $map_fun, $params)
{
    if (!is_array($arr)) {
        return $map_fun($arr, $params);
    }

    $newArray = array();

    foreach ($arr as $key => $value) {
        $newArray[$key] = array_map_rec($value, $map_fun, $params);
    }

    return $newArray;
}

/**
 * Applies a function to generate a new unique entry for a table
 *
 * @param prepared_query the query to check for duplicates. The query's result needs to have a field `count` and an argument ':value' to set the current value.
 * @param fun the function that generates a unique entry. It will receive the number of trials (counter) as parameter.
 *
 * @return mixed the unique entry value
 */
function uniqueEntry($prepared_query, $fun)
{
    $entry_value = null;
    $counter = 0;
    do {
        $entry_value = $fun($counter);
        $result = DB::execute($prepared_query, array("value"=>$entry_value));
        $counter++;
    } while (intval($result["count"]) > 0);
    return $entry_value;
}


/**
 * Generates a random string that does not occur in a database table's field.
 *
 * @param prepared_query the query to check for duplicates. The query's result needs to have a field `count` and an argument ':value' to set the current value.
 * @param random_string_length the length of the unique string (must be smaller than 128)
 *
 * @return random_string checked random string
 */
function uniqueRandomString($prepared_query, $random_string_length)
{
    return uniqueEntry($prepared_query, function($counter) use ($random_string_length) {
      return substr(randomHashString(), 0, $random_string_length);
    });
}


/**
 * Combines values of an associative array into a URL-query (including ?)
 *
 * @param params the associative array, e.g. array("email" => "test@here.de","value"=> "abc")
 *
 * @return query combined URL-query, e.g. '?email=test@here.de&value=abc'
 */
function createUrlQuery($params)
{
    $query = "";
    foreach ($params as $key => $value) {
        $query .= ($query == "") ? "?" : "&";
        $query .= $key."=".strval($value);
    }
    return $query;
}
