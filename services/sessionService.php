<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

require_once("baseService.php");
require_once("userService.php");
require_once("accessTokenService.php");

/**
 * Provides login functionality
 *
 * @auth user
 */
class SessionService
{

    /**
     * Creates session for a corresponding user password has to be checked allready
     *
     * @see AuthService
     *
     * @param user the user ID or the complete user info retrieved using UserService::get()
     * @param session_name name of the session (optional)
     *
     * @return result true, if the creation of the session was successful
     */
    public static function create($user, $session_name="Unnamed Session")
    {
        if (AUTH_MODE != AUTH_SERVER) {
            throw new RuntimeException("Illegal session configuration. This backend has no session login functionality.");
        }
        $userInfo = is_array($user) ? $user : UserService::get($user);

        // Check for existing sessions and delete those if present
        $currentLocalSession = self::getLocalSession();
        if ($currentLocalSession) {
            self::delete($currentLocalSession["guid"]);
        }
        
        // check if audience is valid
        $guid = self::createGuid();

        $data = array("user"=>$userInfo["ID"],"guid"=>$guid,"name"=>$session_name);
        $session_id = BaseService::create("INSERT INTO `sessions` (user, `guid`, `session_name`,`expires_at`) VALUES (:user, :guid, :name, DATE_ADD(NOW(), INTERVAL 14 DAY) )", $data);
        if (!$session_id) {
            Log::error("Could not create session: ".print_r($data, true));
            return false;
        }

        // Store guid in cookie
        self::setLocalSession($guid, $userInfo["ID"], $userInfo["username"]);
        return true;
    }

    /**
     * Returns the guid of the current local session (if one exists)
     *
     * @return guid of the current session or null if no session exists
     */
    public static function getCurrentGuid()
    {
        $localSession = self::getLocalSession();
        return is_null($localSession) ? null : $localSession["guid"];
    }

    /**
     * Creates a new access code in case the current session is valid
     *
     * @param request_url the url that requested the access code
     * @param audience the api url the access token will be for
     *
     * @return access_code the code to access the access token
     */
    public static function createNewAccessCode($request_url, $audience)
    {
        $session_info = self::getSessionSafe();
        return AccessTokenService::createAccessCode($session_info["ID"], $request_url, $audience);
    }

    /**
     * Returns a session safely.
     * Checks if the session has been expired already.
     * In any error case, this method will throw an error.
     *
     * @param session_id the ID or guid of the session
     *
     * @return session the information of the session
     */
    public static function getSessionSafe($session_id=null)
    {
        // 0. check if the authentication mode is set to server
        if (AUTH_MODE != AUTH_SERVER) {
            throw new RuntimeException("Illegal session configuration. This backend has no session login functionality.");
        }
    
        // 1. retrieve local session if no session_id is given
        if (is_null($session_id)) {
            $localSession = self::getLocalSession();
            if (is_null($localSession)) {
                throw new RestException(404, "No current session available.");
            }
            $session_id = $localSession["guid"];
        }

        // 2. retrieve session from database
        $session_info = self::get($session_id); // will throw an error in case the session cannot be found

        // 3. check if session is still valid
        if (strtotime($session_info["expires_at"]) - time() < 0) {
            self::delete($session_info["guid"]); // delete invalid session
            throw new RestException(400, "Session has expired. Please log in again.");
        }

        return $session_info;
    }

    /**
     * Returns an access token in exchange for a access code
     *
     * @param access_code the access code that should be exchanged for an access token
     * @param nonce the nonce (a random number) that will be integrated into the token
     *
     * @return access_token
     */
    public static function getAccessToken($access_code, $nonce)
    {
        // 1. check if session has been expired
        $session_id = AccessTokenService::getSessionId($access_code);
        $session_info = self::getSessionSafe($session_id);

        // 2. Update session expire date
        if (!BaseService::execute("UPDATE `sessions` SET expires_at = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE `ID` = :id", array("id"=>$session_id))) {
            throw new RuntimeException("Could not update session!");
        }

        // 3. retrieve necessary information
        $userId = intval($session_info["user"]);
        $userInfo = UserService::get($userId);
        $scope = UserService::getRoles($userId);

        // 4. generate and return access token
        return AccessTokenService::useAccessCode($access_code, $nonce, $session_info["guid"], $userInfo, $scope);
    }

    /**
     * Retrieves a session
     *
     * @param guid the guid or ID of the session
     * @param user user ID or NULL
     * @param throw404 whether a 404 exception should be thrown or NULL be returned
     *
     * @return session_info or NULL if no session has been found and $throw404==false.
     *
     * @auth admin,self
     * @url GET /users/$user/sessions/$guid
     */
    public static function get($guid, $user=null, $throw404=true)
    {
        $whereClause = preg_match("/^[0-9]+?$/", $guid) ? "`ID`=:selector" : "`guid`=:selector";
        $data = array("selector"=>$guid,"user"=>$user);
        return BaseService::get("SELECT ID, `guid`, session_name, user, created_at, expires_at FROM `sessions` WHERE $whereClause AND (:user is NULL OR :user = `user`)", $data, $throw404, "session", $guid);
    }

    /**
     * Deletes a user's session.
     *
     * @param guid guid or ID of the session
     *
     * @auth admin,self
     * @url DELETE /me/sessions/$sessionId
     * @url DELETE /users/$user/sessions/$sessionId
     */
    public static function delete($guid, $user=null)
    {
        if (AUTH_MODE != AUTH_SERVER) {
            throw new RuntimeException("Illegal session configuration. This backend has no session login functionality.");
        }

        // 1. check if session exists
        $session_info = self::get($guid);


        // 2. invalidate not used access codes and revoke still valid tokens
        AccessTokenService::invalidateSessionData($session_info["ID"]);
        
        // 3. remove local session
        self::setLocalSession(null);

        // 4. remove database session
        if (!BaseService::execute("DELETE FROM `sessions` WHERE `guid` = :guid", array("guid"=>$guid))) {
            throw new RuntimeException("Could not delete the session '$guid'.");
        }
        Response::setStatus(204);
        return true;
    }

    /**
     * Deletes a session
     *
     * @auth admin
     * @url DELETE /sessions/$sessionId
     */
    public static function deleteByAdmin($sessionId)
    {
        return self::delete($sessionId, null);
    }


    /**
     * Retrieves a specific session
     *
     * @auth admin
     * @url GET /sessions/$guid
     */
    public static function getByAdmin($guid, $throw404=true)
    {
        return self::get($guid, null, $throw404);
    }

    /**
     * Retrieves a specific session
     *
     * @auth user
     * @url GET /sessions/current
     */
    public static function getCurrent($throw404=true)
    {
        $guid = Request::sessionField("guid");
        if (is_null($guid)) {
            $currentLocalSession = self::getLocalSession();
            $guid = is_null($currentLocalSession) ? null : $currentLocalSession["guid"];
        }
        if (is_null($guid) && $throw404) {
            throw new RestException(404, "No session present");
        }
        return $guid ? self::get($guid, null, false) : null;
    }


    /**
     * Writes session information into the session cookie
     *
     * @param guid the guid to set. If set to null, the cookie information will be removed
     * @param user the current user's id
     * @param username the name of the current user
     */
    private static function setLocalSession($guid, $user=null, $username=null)
    {
        $_SESSION["guid"] = $guid;
        $_SESSION["user"] = $user;
        $_SESSION["username"] = $username;
        if (is_null($guid)) {
            unset($_SESSION["guid"]);
            unset($_SESSION["user"]);
            unset($_SESSION["username"]);
        }
    }

    /**
     * Returns the session information stored in the cookie
     *
     * @return result or null if no session is available. The result will be an array with the keys: guid, user, username.
     */
    private static function getLocalSession()
    {
        if (array_key_exists("guid", $_SESSION)) {
            return array("guid"=>$_SESSION["guid"],"user"=>$_SESSION["user"],"username"=>$_SESSION["username"]);
        }
        return null;
    }


    private static function createGuid($data=null)
    {
        $data = $data ?? random_bytes(16);
  
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
  
      return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private static function checkGuid($guid)
    {
        if (!preg_match("/^[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}$/s", $guid)) {
            throw new RestException(500, "Illegal Session ID");
        }
        return $guid;
    }
}
