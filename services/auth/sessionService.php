<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

require_once(FRAMEWORK_CWD."/services/baseService.php");

/**
 * The session service provides methods to manage sessions.
 * Furthermore, it ensures that a logged in session is valid for a longer period of time using cookies.
 * 
 * @see https://phppot.com/php/secure-remember-me-for-login-using-php-session-and-cookies/
 */
class SessionService
{
    private static $EXPIRE_INTERVAL = 14; // in days

    private static $FIELD_USER = "session_user"; // session + cookie
    private static $FIELD_GUID = "session_guid"; // session + cookie
    private static $FIELD_PASSWORD = "random_password"; // cookie
    private static $FIELD_SELECTOR = "random_selector"; // cookie

    /**
     * Creates a new session for a user. The password will NOT be checked!
     *
     * @see AuthService
     *
     * @param user the user ID
     * @param session_name name of the session (optional)
     *
     * @return result true, if the creation of the session was successful
     */
    public static function create($user, $session_name="Unnamed Session")
    {
        // 1. generate new guid
        $guid = uniqueEntry("SELECT COUNT(*) as count FROM `sessions` WHERE `guid` = :value", function($counter) {
          return SessionService::createGuid();
        });

        // 2. create database entry
        $data = array("user"=>$user,"guid"=>$guid,"name"=>$session_name, "days"=>self::$EXPIRE_INTERVAL);
        $session_id = BaseService::create("INSERT INTO `sessions` (user, `guid`, `session_name`,`expires_at`) VALUES (:user, :guid, :name, DATE_ADD(NOW(), INTERVAL :days DAY) )", $data);
        if (!$session_id) {
            Log::error("Could not create session: ".print_r($data, true));
            return null;
        }

        return $guid;
    }


    /**
     * Retrieves a session
     *
     * @param guid the guid or ID of the session
     * @param remove_sensitive_information set it to false to preserve cookie secret hashed information
     * @param throw404 whether a 404 exception should be thrown or NULL be returned
     *
     * @return session_info or NULL if no session has been found and $throw404==false.
     */
    public static function get($guid, $remove_sensitive_information=true, $throw404=true)
    {
        $whereClause = preg_match("/^[0-9]+?$/", $guid) ? "`ID`=:selector" : "`guid`=:selector";
        $result = BaseService::get("SELECT * FROM `sessions` WHERE $whereClause AND valid = 1", array("selector"=>$guid), $throw404, "Session", $guid);
        if ($result && $remove_sensitive_information)
        {
            unset($result["random_password_hash"]);
            unset($result["random_selector_hash"]);
        }
        return $result;
    }

    /**
     * Invalidates a session and, if it is the current session, clears the session variable and cookie information.
     * Will also invalidate tokens
     * 
     * @param guid the GUID of the session to invalidate
     * 
     * @return bool true if everything could be cleared and invalidated
     */
    public static function invalidate($guid) {
        $current_guid = self::getCurrentGuid();
        
        // 1. Check if this session is the current session
        if ($guid == $current_guid)
        {
            self::removeCurrent();
        }

        // 2. Remove database entry
        return BaseService::execute("UPDATE `sessions` SET valid = 0 WHERE `guid` = :guid", array("guid"=>$guid), false);
    }


    /**
     * Returns the current session by using the session variable and cookies.
     * 
     * @return array|null array(guid => ..., user => ...) filled with data of the current session. If no session is present NULL will be returned.
     */
    public static function getCurrent() {

        // 1. if the session has still a valid GUID -> return the session; user is logged in
        if (array_key_exists(self::$FIELD_GUID, $_SESSION)) {
            return array("guid"=>$_SESSION[self::$FIELD_GUID],"user"=>$_SESSION[self::$FIELD_USER]);
        }

        // 2. if no session is present, try to recover from cookie
        if (!empty($_COOKIE[self::$FIELD_GUID]) &&
            !empty($_COOKIE[self::$FIELD_USER]) &&
            !empty($_COOKIE[self::$FIELD_PASSWORD]) &&
            !empty($_COOKIE[self::$FIELD_SELECTOR]))
        {
            // Retrieve cookie verification information from the database
            $guid = $_COOKIE[self::$FIELD_GUID];
            $user = $_COOKIE[self::$FIELD_USER];
            $session = self::get($guid, false, false);

            // if no such information is present, the cookie information is not valid anymore
            if (is_null($session))
            {
                self::setCurrent(null);
                return null;
            }

            // if information is present, verify cookie information
            $current_date = date("Y-m-d H:i:s", time());
            $isPasswordVerified = password_verify($_COOKIE[self::$FIELD_PASSWORD], $session["random_password_hash"]);
            $isSelectorVerified = password_verify($_COOKIE[self::$FIELD_SELECTOR], $session["random_selector_hash"]);
            $isExpiryDateVerified = $session["expires_at"] >= $current_date;

            if ($session["user"] === $user && $isPasswordVerified && $isSelectorVerified && $isExpiryDateVerified)
            {
                return self::setCurrent($guid);
            }
        }

        return null;
    }

    /**
     * Updates the expiration dates of the current session and refreshes the cookie secrets
     */
    public static function updateCurrent()
    {
        $current = self::getCurrent();
        if (!is_null($current))
        {
            self::setCurrent($current["guid"]);
        }
    }

    /**
     * Sets the guid and user as the current session.
     * The database entry will be updated and the information is written into the session variable and cookies.
     *
     * @param guid the guid to set to the current session. Set it to null to remove the current session. NOTE: it won't be invalidated!
     */
    public static function setCurrent($guid)
    {
        // Check if the current session should be removed
        if (is_null($guid))
        {
            $guid = self::getCurrentGuid();
            self::removeCurrent();
            return true;
        }

        $session = self::get($guid, false, true);

        $current_time = time();
        $current_date = date("Y-m-d H:i:s", $current_time);
        $cookie_expiration_time = $current_time + (self::$EXPIRE_INTERVAL * 24 * 60 * 60);
        $cookie_options = array (
            'expires' => $cookie_expiration_time,
            //'secure' => true,     // or false
            'httponly' => true,    // or false
            'samesite' => 'Strict' // None || Lax  || Strict
            );
        
        // calculate confirmation data
        $random_password = randomHashString(16);
        $random_selector = randomHashString(32);
        $random_password_hash = password_hash($random_password, PASSWORD_DEFAULT);
        $random_selector_hash = password_hash($random_selector, PASSWORD_DEFAULT);
        
        // Update the database entry
        $data = array("guid"=>$guid,"days"=>self::$EXPIRE_INTERVAL, "pw_hash" => $random_password_hash, "sel_hash" => $random_selector_hash);
        if (!BaseService::execute("UPDATE `sessions` SET random_password_hash = :pw_hash, random_selector_hash = :sel_hash, expires_at = DATE_ADD(NOW(), INTERVAL :days DAY) WHERE `guid` = :guid AND valid = 1", $data)) {
            throw new RuntimeException("Could not update the session!");
        }

        // set session and cookie information
        $user = $session["user"];
        $_SESSION[self::$FIELD_GUID] = $guid;
        $_SESSION[self::$FIELD_USER] = $user;

        setcookie(self::$FIELD_GUID, $guid, $cookie_options);
        setcookie(self::$FIELD_USER, $user, $cookie_options);
        setcookie(self::$FIELD_PASSWORD, $random_password, $cookie_options);
        setcookie(self::$FIELD_SELECTOR, $random_selector, $cookie_options);

        // return the current session
        return array("guid"=>$_SESSION[self::$FIELD_GUID],"user"=>$_SESSION[self::$FIELD_USER]);
   }

   /**
    * Removes all session and cookie data
    */
   private static function removeCurrent()
   {
       // 1. Reset Session Variable
       $_SESSION[self::$FIELD_GUID] = "";
       $_SESSION[self::$FIELD_USER] = "";
       unset($_SESSION[self::$FIELD_GUID]);
       unset($_SESSION[self::$FIELD_USER]);

       // 2. Reset Cookie
       if (isset($_COOKIE[self::$FIELD_GUID])) {
           setcookie(self::$FIELD_GUID, "");
       }
       if (isset($_COOKIE[self::$FIELD_USER])) {
           setcookie(self::$FIELD_USER, "");
       }
       if (isset($_COOKIE[self::$FIELD_PASSWORD])) {
           setcookie(self::$FIELD_PASSWORD, "");
       }
       if (isset($_COOKIE[self::$FIELD_SELECTOR])) {
           setcookie(self::$FIELD_SELECTOR, "");
       }
   }

   private static function getCurrentGuid()
   {
       return (isset($_SESSION[self::$FIELD_GUID])) ? $_SESSION[self::$FIELD_GUID] : (empty($_COOKIE[self::$FIELD_GUID]) ? null : $_COOKIE[self::$FIELD_GUID]);
   }


   private static function createGuid($data=null)
   {
       $data = $data ?? random_bytes(16);
 
       $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
       $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
 
     return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
   }
}
