

<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

/**
 * This class provides tools that can be used while development. Info() can be used always.
 *
 * @auth none
 */
class ToolService
{
    /**
     * Provides information about the backend
     *
     * @url GET /
     *
     * @return infoArray an array with information about version, status, and copyright.
     */
    public static function info()
    {
        return array(
          "status"=>"running",
          "mode"=>(TEST) ? "test" : "normal",
          "version"=>defined("META_VERSION") ? META_VERSION : "NA",
          "copyright"=>defined("META_COPYRIGHT") ? META_COPYRIGHT : "NA",
          "util-version"=>defined("META_UTIL_VERSION") ? META_UTIL_VERSION : "NA",
          "util-copyright"=>defined("META_UTIL_COPYRIGHT") ? META_UTIL_COPYRIGHT : "NA",
          "util-tools-enabled"=>CONFIG_ENABLE_TOOLS);
    }

    /**
     * Generates a random 128-byte string
     *
     * @url GET /tools/generate/randomstring
     */
    public static function generateSecret()
    {
        if (CONFIG_ENABLE_TOOLS) {
            return randomHashString();
        }
        throw new RestException(405, "The URL is not valid.");
    }

    /**
     * Hashes a password
     *
     * @url POST /tools/hash-password password=$password
     */
    public static function hashPassword($password)
    {
        if (CONFIG_ENABLE_TOOLS) {
            $password_pepper = hash_hmac("sha256", $password, PWD_PEPPER);
            Log::log($password);
            Log::log($password_pepper);
            return password_hash($password_pepper, PASSWORD_DEFAULT);
        }
        throw new RestException(405, "The URL is not valid.");
    }
}
