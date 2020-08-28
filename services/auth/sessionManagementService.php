<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

require_once("sessionService.php");

/**
 * Provides REST interfaces to manage sessions
 *
 * @auth self
 */
class SessionManagementService
{

    /**
     * Retrieves a session
     *
     * @param guid the guid or ID of the session
     * @param user user ID or NULL
     *
     * @return session_info or NULL if no session has been found and $throw404==false.
     *
     * @auth admin,self
     * @url GET /users/$user/sessions/$guid
     */
    public static function getSession($guid,$user=null)
    {
        $session = SessionService::get($guid);
        if (!is_null($user) && $user !== $session["user"])
        {
          throw new RestException(403, "You don't have the permission to access this information");
        }
        return $session;
    }

    /**
     * Retrieves a session
     *
     * @auth admin
     * @url GET /sessions/$guid
     */
    public static function getSessionByAdmin($guid)
    {
        self::getSession($guid);
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

}
