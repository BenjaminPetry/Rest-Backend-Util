<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */
require_once("baseService.php");
require_once("userService.php");

/**
 * The access token service can generate access codes and corresponding access tokens.
 * It does not exploit REST functionality, except for microservice revoking tokens.
 */
class AccessTokenService
{
    private static $ACCESS_CODE_EXPIRY_INTERVAL = 300; // in seconds

    /**
     * Creates a new access code that can be retrieved
     */
    public static function createAccessCode($session_id, $request_url, $audience)
    {
        $checkAccessCode = DB::prepare("SELECT COUNT(*) as `count` FROM access_codes WHERE access_code = :value");
        $checkTokenId = DB::prepare("SELECT COUNT(*) as `count` FROM access_codes WHERE token_id = :value");
        $access_code = uniqueEntry($checkAccessCode, 16);
        $token_id = uniqueEntry($checkTokenId, 64);
        $expire_date = strtotime(date('Y-m-d H:i:s')." + ".self::$ACCESS_CODE_EXPIRY_INTERVAL." seconds");

        $id = BaseService::create(
            "INSERT INTO access_codes (access_code, `session_id`, request_url, audience, token_id, expire_date) VALUES (:access_code, :session_id, :request_url, :audience, :token_id, :expire_date)",
            array(
                "access_code" => $access_code,
                "session_id" => $session_id,
                "request_url" => $request_url,
                "audience" => $audience,
                "token_id" => $token_id,
                "expire_date" => DB::date($expire_date)
              )
        );
        if (!$id) {
            throw new RuntimeException("Could not create a new access code.");
        }
        return $access_code;
    }

    public static function getSessionId($access_code)
    {
        $result = BaseService::get("SELECT `session_id` FROM access_codes WHERE access_code = :access_code", array("access_code"=>$access_code));
        return $result["session_id"];
    }
    
    public static function useAccessCode($access_code, $nonce, $guid, $userInfo, $scope)
    {
        // 1. find the access token
        $info = BaseService::get("SELECT * FROM access_codes WHERE access_code = :access_code", array("access_code"=>$access_code));
        if (!$info) {
            throw new RestException(404, "No access_code '$access_code' available.");
        }

        // 2. check if access code has been used already
        if (intval($info["used"]) == 1) {
            self::revokeToken($info["token_id"], $info["audience"]);
            self::invalidateAccessCode($access_code);
            throw new RestException(401, "Access code has already been used. Previous delivered access token will be revoked");
        }

        // 3. check if access code is still valid
        if (intval($info["valid"]) != 1) {
            throw new RestException(401, "Access code is not valid anymore.");
        }

        // 4. check if request_url starts with the formerly request_url
        if (!startsWith(Request::$originUrl, $info["request_url"])) {
            //self::invalidateAccessCode($access_code);
            //throw new RestException(401, "The request for the access token came from a different url than the request for the access code!");
        }

        // 5. check expire date
        $current_date = strtotime(date('Y-m-d H:i:s'));
        
        if (strtotime($info["expire_date"]) - time() < 0) {
            throw new RestException(401, "Access code has been expired.");
        }

        // 6. Set access token to be used
        BaseService::execute("UPDATE access_codes SET `used` = 1 WHERE access_code = :access_code", array("access_code"=>$access_code));
        
        // 7. Deliver access token
        $audience = $info["audience"];
        $token_id = $info["token_id"];

        return TokenManager::createAccessToken($scope, $audience, $userInfo, $guid, $nonce, $token_id);
    }

    public static function invalidateAccessCode($access_code)
    {
        BaseService::execute("UPDATE access_codes SET valid = 0 WHERE access_code = :access_code", array("access_code"=>$access_code));
    }

    public static function invalidateSessionData($session_id)
    {
        // 1. Set not used access codes of the session to invalid
        BaseService::execute("UPDATE access_codes SET valid = 0 WHERE `used` = 0 AND `session_id` = :session_id", array("session_id"=>$session_id));

        // Get the other tokens
        $codes = BaseService::getList("SELECT * FROM access_codes WHERE `used` = 1 AND `session_id` = :session_id", array("session_id"=>$session_id));

        // 2. revoke (only) the tokes that are still valid
        foreach ($codes as $code) {
            // only revoke the ones that are still valid
            if (time() - strtotime($code["expire_date"]) < TokenManager::TOKEN_EXPIRE_DURATION()) {
                self::revokeToken($code["token_id"], $code["audience"]);
            }
        }
    }

    /**
     * Revokes a token from an audience.
     * This method will sent a query via a microservice if necessary.
     *
     * @param token_id the id of the token
     * @param audience the api the token was created for
     */
    public static function revokeToken($token_id, $audience)
    {
        if ($audience === Request::$apiUrl) {
            self::revokeTokenByMS($token_id);
        } else {
            MicroService::exec("POST", $audience, "ms/authorize/access_tokens/$token_id/revoke", array());
        }
    }

    public static function revokeTokenByMS($token_id)
    {
        return BaseService::create("INSERT INTO access_tokens_revoked (token_id) VALUES (:token_id)", array("token_id"=>$token_id));
    }
};
