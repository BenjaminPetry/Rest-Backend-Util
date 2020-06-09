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
     * Log in and get access token
     *
     * @auth none
     * @url POST /sessions username=$username,password=$password,audience=$audience?,nonce=$nonce,session_name=$session_name?
     */
    public static function create($username, $password, $nonce, $audience=null, $session_name="Unnamed Session")
    {
        if (AUTH_MODE != AUTH_SERVER) {
            throw new RuntimeException("Illegal session configuration. This backend has no session login functionality.");
        }
        if (!UserService::checkPassword($username, $password)) {
            throw new RestException(401, "Invalid password");
        }
        $audience = (is_null($audience)) ? Request::$apiUrl : $audience;
        $userInfo = UserService::get($username);

        // Check for existing sessions and delete those if present
        $currentSession = self::getCurrent(false);
        if ($currentSession) {
            self::delete($currentSession["guid"]);
        }
        $currentLocalSession = self::getLocalSession();
        if ($currentLocalSession) {
            self::setLocalSession(null);
            self::delete($currentSession["guid"]);
        }
        
        // check if audience is valid
        $guid = self::guidv4();
        $tokenId = substr(randomHashString(), 0, 64);
        self::createMS($guid, $userInfo["ID"], $session_name, $tokenId);
        $result = self::informClients(MicroService::POST, "ms/sessions/", array("guid"=>$guid,"user_id"=>$userInfo["ID"],"session_name"=>"Test","token_id"=>$tokenId));

        // Store guid in cookie
        self::setLocalSession($guid, $userInfo["ID"], $userInfo["username"]);
        Response::setStatus(200);
        return self::getToken($nonce, $audience);
    }
    
    /**
     * Creates a session (can be used by microservices)
     *
     * @auth microservice
     * @url POST /ms/sessions/ guid=$guid,user_id=$userId,session_name=$sessionName,token_id=$tokenId
     */
    public static function createMS($guid, $userId, $sessionName, $tokenId)
    {
        if (!self::checkGuid($guid)) {
            throw new FieldValidationException("Not a valid guid", array("guid"));
        }
        $session_id = BaseService::create("INSERT INTO `sessions` (user, `guid`, `session_name`,`token_id`) VALUES (:user, :guid, :name, :tokenId)", array("user"=>$userId,"guid"=>$guid,"name"=>$sessionName, "tokenId"=>$tokenId));
        if (!$session_id) {
            throw new RuntimeException("Could not create a new session.");
        }
        return true;
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
     * If the user is already logged in, this method will provide a token
     */
    public static function getToken($nonce, $audience=null)
    {
        $localSession = self::getLocalSession();
        if (is_null($localSession)) {
            throw new RestException(401, "No session present");
        }
        return self::createToken(array("user"=>$localSession["user"],"username"=>$localSession["username"]), $localSession["guid"], $nonce, $audience);
    }

    /**
     * Delete a user's session
     *
     * @auth admin,self
     * @url DELETE /me/sessions/$sessionId
     * @url DELETE /users/$user/sessions/$sessionId
     */
    public static function delete($sessionId, $user=null)
    {
        if (AUTH_MODE != AUTH_SERVER) {
            throw new RuntimeException("Illegal session configuration. This backend has no session login functionality.");
        }
        if (!self::get($sessionId, $user)) {
            throw new RestException(400, "Could not find session '$sessionId' for user $user");
        }
        self::deleteByMS($sessionId);
        self::setLocalSession(null);
        $result = self::informClients(MicroService::DELETE, "ms/sessions/".$sessionId);
        return $result->status < 300;
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
     * Deletes a session (also for microservices)
     *
     * @auth microservice
     * @url DELETE ms/sessions/$sessionId
     */
    public static function deleteByMS($sessionId)
    {
        if (!BaseService::execute("DELETE FROM `sessions` WHERE `guid` = :guid", array("guid"=>$sessionId))) {
            throw new RuntimeException("Could not delete the session '$sessionId'.");
        }
        Response::setStatus(204);
        return true;
    }

    /**
     * Sends a message to all clients.
     *
     * @param method GET, POST, PATCH, DELETE
     * @param relativeUrl the relative url of the request. The apiUrl will be pre-pended automatically.
     * @param data the data to send
     *
     * @return the result of the last error or, if no error occurred, the result of the last request.
     */
    private static function informClients($method, $relativeUrl, $data=array())
    {
        global $config;
        $lastResult = new class {
            public $status = 200;
        };
        $lastUrl = "";
        foreach ($config[CF_AUTH_CLIENTS] as $api => $secret) {
            if (!array_key_exists($api, $config[CF_AUTH_MICROSERVICES])) {
                throw new RuntimeException("Every client must have a shared secret for microservice functionality, too!");
            }
            $result = MicroService::exec($method, $api, $relativeUrl, $data);
            if ($lastResult->status < 300 || $result->status >= 300) {
                $lastResult = $result;
                $lastUrl = $api."/".$relativeUrl;
            }
        }
        if ($lastResult->status >=300) {
            throw new RuntimeException("There has been a problem with informing at least one client! Last error-request was '$lastUrl'.");
        }
        return $lastResult;
    }

    /**
    * Retrieves a specific session
    *
    * @auth admin,self
    * @url GET /users/$user/sessions/$guid
    */
    public static function get($guid, $user=null, $throw404=true)
    {
        if (is_null($user)) {
            return BaseService::get("SELECT `guid`, session_name, user, created_at, expires_at FROM `sessions` WHERE `guid`=:guid", array("guid"=>$guid), $throw404, "session", $guid);
        }
        return BaseService::get("SELECT `guid`, session_name, user, created_at, expires_at FROM `sessions` WHERE `guid`=:guid AND `user`=:user", array("guid"=>$guid,"user"=>$user), $throw404, "session", $guid." of user $user");
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

    private static function getTokenId($guid)
    {
        $result = BaseService::get("SELECT token_id FROM `sessions` WHERE `guid`=:guid", array("guid"=>$guid), false, "session", $guid);
        return $result ? $result["token_id"] : null;
    }

    private static function createToken($userInfo, $guid, $nonce, $audience)
    {
        $scope = UserService::getRoles($userInfo["user"]);

        if (!BaseService::execute("UPDATE `sessions` SET expires_at = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE `guid` = :guid", array("guid"=>$guid))) {
            throw new RuntimeException("Could not update session!");
        }

        $_SESSION["guid"] = $guid;

        // create new token
        $tokenId = self::getTokenId($guid);

        $accessToken = TokenManager::createAccessToken($scope, $audience, $userInfo, $guid, $nonce, $tokenId);
        
        return array("access_token"=>$accessToken);
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


    private static function guidv4($data=null)
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
