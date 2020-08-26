<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

class Auth
{
    const EXPR_MICROSERVICE = "/^".AUTH_ROLE_MICROSERVICE."(-([0-9a-zA-Z:]+)$|$)/s";

    /**
     * Checks, whether the user has the corresponding role
     *
     * @param role the role to check
     * @param user the user's id
     *
     * @return trueFalse true if the user has the corresponding role
     */
    public static function hasRole($role, $user=null)
    {
        if ($role == AUTH_ROLE_USER) {
            return self::isUserLoggedIn();
        }
        if ($role == AUTH_ROLE_SELF) {
            return $user && self::isUserLoggedIn() && $user == Request::sessionField("user");
        }
        $rights = Request::sessionField("scope");
        return in_array($role, $rights);
    }

    /**
     * Checks whether the user is logged in
     */
    public static function isUserLoggedIn()
    {
        $user = Request::sessionField("user");
        return !is_null($user) && $user > 0;
    }

    public static function isAuthenticated($call, &$params)
    {
        // if no authentication is required or the method is public accessible
        if (!AUTH_ENABLED || $call["auth"] == null) {
            return true;
        }
    
        // if rights are present, a user must be present or microservice (user ID 9999)
        if (!self::isUserLoggedIn()) {
            throw new RestException(401, "No user logged in.");
        }
        
        $callRights = $call["auth"];
        $currentUser = Request::sessionField("user");
        $currentRights = Request::sessionField("scope");

        if (!Request::sessionField("isMicroservice")) {
            $currentRights[] = AUTH_ROLE_USER; // user is logged in at this point so add it as right
            $paramUser = array_key_exists('$user', $params) ? $params['$user'] : null;
            if (!is_null($paramUser) && !preg_match("[0-9]+", $paramUser)) { // if the username is provided instead of the user id
                $userInfo = DB::fetch("SELECT ID FROM users WHERE username=:username", array("username"=>$paramUser));
                if (!$userInfo) {
                    throw new RuntimeException("Could not retrieve user ID from user '$paramUser'.");
                }
                $paramUser = intval($userInfo["ID"]);
            }
            // if the user equals a given user, the right 'self' will be added
            if (!is_null($paramUser) && $paramUser == $currentUser) {
                $currentRights[] = AUTH_ROLE_SELF;
            }
            // if the user variable has not been set yet, it will be set to the current user
            if (is_null($paramUser) && in_array(AUTH_ROLE_SELF, $callRights)) {
                $params['$user'] = $currentUser;
                $currentRights[] = AUTH_ROLE_SELF;
            }
        } elseif (substr(Request::$url."/", 0, 3)!="ms/") {
            throw new RestException(403, "Microservices are only allowed to access microservice-urls starting with 'ms'!");
        }

        // check whether, the current rights and the rights for the call have an intersection
        $intersect = array_intersect($currentRights, $callRights);
        if (count($intersect) == 0) { // if there is no intersection -> rights are missing
            throw new RestException(403, "You don't have the necessary rights.");
        }
        return true;
    }
}
