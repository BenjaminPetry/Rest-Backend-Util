<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

require_once("baseService.php");
require_once("userService.php");

/**
 * Provides login functionality
 *
 * @auth user
 */
class SessionService
{
    /**
     * Provides Meta Information to the client about the login options
     *
     * @auth none
     * @url GET /authorize/meta
     */
    public static function meta()
    {
        global $config;
        $init = array(
          "auth-method" => $config["auth"],
        );
        if ($config["auth"] == Auth::METHOD_SSO) {
            $init["sso-id"] = $config["auth-sso-backend-id"];
            $init["sso-url"] = $config["auth-sso-service-url"];
        }
        return $init;
    }



    // /**
    //  * Retrieves a specific session
    //  *
    //  * @auth user
    //  * @url GET /sessions/current
    //  */
    // public static function getCurrent($throw404=true)
    // {
    //     $guid = Request::sessionField("guid");
    //     if (is_null($guid) && $throw404) {
    //         throw new RestException(404, "No session present");
    //     }
    //     return $guid ? self::get($guid) : null;
    // }

    // /**
    //  * Retrieves the list of sessions
    //  *
    //  * @auth self,admin
    //  * @url GET /sessions
    //  * @url GET /me/sessions
    //  * @url GET /users/$username/sessions
    //  */
    // public static function list($username)
    // {
    //     return BaseService::getList("SELECT `guid`, session_name, username, created_at, expires_at FROM `sessions` WHERE `username`=:username", array("username"=>$username));
    // }

    private static function isValid($session)
    {
        $sessionExpire = strtotime($session["expires_at"]);
        if ($sessionExpire < time()) {
            self::delete($session["guid"]);
            return false;
        }
        return true;
    }

    /**
     * Deletes a session
     * @auth none
     * @url DELETE /sessions/current
     */
    public static function deleteCurrent()
    {
        // if ($username != Request::sessionField("username")) {
        //     throw new RestException(403, "You are not allowed to delete this session!");
        // }
        $session = self::getCurrent();
        return self::delete($session["guid"]);
    }
}
