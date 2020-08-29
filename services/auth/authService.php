<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

require_once(FRAMEWORK_CWD."/services/userService.php");
require_once("sessionService.php");
require_once("accessTokenService.php");

/**
 * Provides authorization services.
 *
 * @auth user
 */
class AuthService
{

    /**
     * Creates session for a corresponding user if the password is correct
     *
     * @see AuthService
     *
     * @param email the email of the user
     * @param password the password of the user
     * @param session_name a name for the session (optional)
     *
     * @return result false if the login failed
     */
    public static function login($email, $password, $session_name="Unnamed Session")
    {
        if (AUTH_MODE != AUTH_SERVER) {
            throw new RuntimeException("Illegal session configuration. This backend has no session login functionality.");
        }

        // 1. remove existing sessions
        self::logout();

        // 2. check the password
        if (!UserService::checkPassword($email, $password, true)) {
          return false;
        }

        // 3. retrieve the user's ID
        $userInfo = UserService::get($email, true, true);
        
        // 4. create session entry
        $guid = SessionService::create($userInfo["ID"], true, $session_name);
        if (is_null($guid))
        {
          throw new RuntimeException("Could not create session!");
        }

        // 5. set new session as current session
        SessionService::setCurrent($guid);
        return true;
    }

    /**
     * Logs a user out of the current session
     */
    public static function logout()
    {
      $currentSession = SessionService::getCurrent();
      if (!is_null($currentSession))
      {
        SessionService::setCurrent(null);
        return self::invalidateSession($currentSession["guid"]);
      }
      return true;
    }

    /**
     * Checks if there is currently a session present
     * 
     * @return bool true if a session is present, false otherwise
     */
    public static function isLoggedIn()
    {
      return !is_null(SessionService::getCurrent());
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
        // 1. get current session
        $session_info = self::getSessionSafe();

        // 2. update session expire date
        SessionService::updateCurrent();

        // 3. create access code
        return AccessTokenService::createAccessCode($session_info["ID"], $request_url, $audience);
    }

    /**
     * Revokes an access token
     * 
     * @param token_id ID of the token
     */
    public static function revokeAccessToken($token_id)
    {
        return AccessTokenService::revokeTokenByMS($token_id);
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
            $session = SessionService::getCurrent();
            if (is_null($session)) {
                throw new RestException(404, "No current session available.");
            }
            $session_id = $session["guid"];
        }

        // 2. retrieve session from database
        $session_info = SessionService::get($session_id); // will throw an error in case the session cannot be found

        // 3. check if session is still valid
        if (strtotime($session_info["expires_at"]) - time() < 0) {
            self::invalidateSession($session_info["guid"]); // delete invalid session
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

        // 2. retrieve necessary information
        $userId = intval($session_info["user"]);
        $userInfo = UserService::get($userId);
        $scope = UserService::getRoles($userId);

        // 3. generate and return access token
        return AccessTokenService::useAccessCode($access_code, $nonce, $session_info["guid"], $userInfo, $scope);
    }


    /**
     * Deletes a user's session.
     *
     * @param guid guid or ID of the session
     *
     */
    public static function invalidateSession($guid)
    {
        if (AUTH_MODE != AUTH_SERVER) {
            throw new RuntimeException("Illegal session configuration. This backend has no auth functionality.");
        }

        // 1. check if session exists
        $session_info = SessionService::get($guid);

        // 2. invalidate not used access codes and revoke still valid tokens
        AccessTokenService::invalidateSessionData($session_info["ID"]);
        
        // 3. remove session information
        SessionService::invalidate($guid);
        return true;
    }
}
