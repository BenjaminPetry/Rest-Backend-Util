<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

class TokenManager
{
    public static function TOKEN_EXPIRE_DURATION()
    { // returns the duration (in min) after a token expires
        return 120;
    }

    /**
     * Checks whether the given audience exists and returns the secret of the audience
     */
    public static function checkAudience($audience, $isMicroservice=false)
    {
        global $config;
        if (($isMicroservice && !array_key_exists($audience, $config[CF_AUTH_MICROSERVICES])) || (!$isMicroservice && !array_key_exists($audience, $config[CF_AUTH_AUDIENCES]))) {
            throw new RestException(401, "Illegal authentication audience.");
        }
        return true;
    }

    /**
     * Creates an access token for a microservice
     */
    public static function createMicroserviceToken($microserviceBaseUrl)
    {
        $expireAt = strtotime(date('Y-m-d H:i:s')." + 5 minutes");
        $secret = self::getSecret($microserviceBaseUrl, true);
        return self::createToken($secret, array(AUTH_ROLE_MICROSERVICE), $microserviceBaseUrl, $expireAt, Request::$apiUrl);
    }

    /**
     * Creates an access token
     */
    public static function createAccessToken($scope, $audience, $userInfo, $guid, $nonce, $tokenId)
    {
        if ($audience == null) {
            $audience = Request::$apiUrl;
        }
        $expireAt = strtotime(date('Y-m-d H:i:s')." + ".self::TOKEN_EXPIRE_DURATION()." minutes");
        $secret = self::getSecret($audience, false);
        return self::createToken($secret, $scope, $audience, $expireAt, $userInfo, $guid, $nonce, $tokenId);
    }

    public static function checkAccessTokenRevokeState($session)
    {
        if (!AUTH_ENABLED || $session["isMicroservice"]) {
            return true;
        }
        $result = DB::count("SELECT COUNT(*) FROM `access_tokens_revoked` WHERE `token_id`=:token_id", array("token_id"=>$session["tokenId"]));
        if ($result > 0) {
            throw new RestException(401, "Token has been revoked.");
        }
        return true;
    }

    public static function verifyAccessToken($token)
    {
        global $config;
        $audience = Request::$apiUrl;

        $matches = array();

        // remove white spaces
        $token = preg_replace('/\s+/', '', $token);

        if (!preg_match("/^(.*)\.(.*)\.(.*)$/", $token, $matches)) {
            throw new RestException(401, "Invalid Access Token");
        }

        // decode and check if object is json
        $header = self::decodeParseAndCheck64($matches[1]);
        $payload = self::decodeParseAndCheck64($matches[2]);
        $signature = $matches[3];
        if ($header == null || $payload == null || $signature == null) {
            throw new RestException(401, "Invalid Encoded Token");
        }

        if ($header->alg != "HS256" || $header->typ != "JWT") {
            throw new RestException(401, "Unsupported token algorithm or type.");
        }

        if (!property_exists($payload, "exp") || !property_exists($payload, "scope") || !property_exists($payload, "aud") || !property_exists($payload, "jti")) {
            throw new RestException(401, "Token is missing important properties for this application.");
        }

        $expire_date = $payload->exp;
        $current_date = strtotime(date('Y-m-d H:i:s'));
        if ($current_date > $expire_date) {
            throw new RestException(401, "Token has been expired.");
        }

        $userInfo = $payload->azp;

        if ($payload->aud != $audience) {
            throw new RestException(401, "Token is not supposed to be used with this audience.");
        }
        $isMicroservice = in_array(AUTH_ROLE_MICROSERVICE, $payload->scope);

        $secret = self::getSecret($audience, $isMicroservice);

        $hashed_signature = self::createSignature($matches[1], $matches[2], $secret);
        if ($hashed_signature != $signature) {
            throw new RestException(401, "Invalid Token Signature.");
        }

        $result = array("scope"=>$payload->scope, "audience"=>$payload->aud, "tokenId"=>$payload->jti, "isMicroservice"=>$isMicroservice);
        if ($isMicroservice) {
            $result["guid"]="";
            $result["user"]=9999;
            $result["username"]=$userInfo;// sending url
            if (array_key_exists($userInfo, $config[CF_AUTH_MICROSERVICES_PERMISSIONS])) { // checking if there are additional permissions for the microservice
                foreach ($config[CF_AUTH_MICROSERVICES_PERMISSIONS][$userInfo] as $permission) {
                    $result["scope"][] = AUTH_ROLE_MICROSERVICE."-".$permission;
                }
            }
        } else {
            $result["guid"]=$payload->sub;
            $result["user"]=$userInfo->user;
            $result["username"]=$userInfo->username;
        }

        return $result;
    }

    /**
     * Creates a Token
     *
     * @param secret the secret to sign the token
     * @param scope the roles the user has
     * @param audience the url of the backend this token is for
     * @param expireAt the date the token will expire
     * @param userInfo information about the user: array("user"=> ID, "username"=>name). Except for microservices: backend-url that is requesting
     * @param guid id of the session (only access token)
     * @param nonce a number that the requesting frontend provides to check if the token comes from the corresponding request (only access token)
     * @param tokenId a string the makes binds the token to a session state (only access token)
     */
    private static function createToken($secret, $scope, $audience, $expireAt, $userInfo, $guid=null, $nonce=null, $tokenId=null)
    {
        if (is_null($tokenId)) {
            $tokenId = randomHashString();
        }
        $header = array("typ" => "JWT", "alg" => "HS256");
        $body = array(
          "iss"=>AUTH_ISSUER,
          "aud" => $audience,
          "exp" => $expireAt,
          "iat" => strtotime(date('Y-m-d H:i:s')),
          "jti" => $tokenId,
          "scope" => $scope,
          "azp" => $userInfo
        );
        if (!is_null($guid)) {
            $body["sub"]=$guid;
        }
        if (!is_null($guid)) {
            $body["nonce"]=$nonce;
        }
        $header64 = base64url_encode(json_encode($header));
        $body64 = base64url_encode(json_encode($body));
        return $header64.".".$body64.".".self::createSignature($header64, $body64, $secret);
    }
    
    private static function getSecret($audience, $isMicroservice)
    {
        global $config;
        if ($audience == Request::$apiUrl) {
            return SECRET;
        }
        self::checkAudience($audience, $isMicroservice);
        return $isMicroservice ? $config[CF_AUTH_MICROSERVICES][$audience] : $config[CF_AUTH_AUDIENCES][$audience];
    }

    private static function createSignature($header64, $body64, $secret)
    {
        return base64url_encode(hash_hmac("sha256", $header64.".".$body64, $secret, true));
    }

    private static function decodeParseAndCheck64($string)
    {
        $decode = base64url_decode($string);
        if ($decode == null || base64url_encode($decode) != $string) {
            return null;
        }
        $json = json_decode($decode);
        return (json_last_error() === 0) ? $json : null;
    }
}
