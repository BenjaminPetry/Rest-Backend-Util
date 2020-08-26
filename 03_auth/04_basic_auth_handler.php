<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

class BasicAuthHandler
{
    /**
     * Verifies an access token and retrieves the current session.
     * If the token or session is invalid an exception will be thrown
     */
    public function getCurrentSession($accessToken)
    {
        $currentSession = TokenManager::verifyAccessToken($accessToken);
        TokenManager::checkAccessTokenRevokeState($currentSession);
        return $currentSession;
    }

    /**
     * Checks if the user has access to this call with the given parameters.
     * The params may change, if e.g., no user is provided, but @auth is self
     */
    public static function hasAccess($call, &$params)
    {
        return Auth::isAuthenticated($call, $params);
    }
}
