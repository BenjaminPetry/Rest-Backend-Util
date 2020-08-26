<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

// Sends requests to other backends
class MicroService
{
    const POST = "POST";
    const PATCH = "PATCH";
    const DELETE = "DELETE";
    const GET = "GET";


    public static function delete($baseUrl, $relativeUrl, $data)
    {
        return self::exec(self::DELETE, $baseUrl, $relativeUrl, $data);
    }

    public static function patch($baseUrl, $relativeUrl, $data)
    {
        return self::exec(self::PATCH, $baseUrl, $relativeUrl, $data);
    }

    // do not add the query parameter to the relative URL!
    public static function get($baseUrl, $relativeUrl, $data)
    {
        return self::exec(self::GET, $baseUrl, $relativeUrl, $data);
    }

    public static function post($baseUrl, $relativeUrl, $data)
    {
        return self::exec(self::POST, $baseUrl, $relativeUrl, $data);
    }

    public static function exec($method, $baseUrl, $relativeUrl, $data)
    {
        $token = TokenManager::createMicroserviceToken($baseUrl);
        $totalUrl = removeSlash($baseUrl, SLASH_ENDING)."/".removeSlash($relativeUrl, SLASH_STARTING);

        // use key 'http' even if you send the request to https://...
        $options = array(
          'http' => array(
              'header'  => "Authorization: Bearer ".$token."\r\n"."Content-type: application/json\r\n",
              'method'  => $method
            )
        );
        if ($method == self::GET) {
            $totalUrl .="?".http_build_query($data);
        } else {
            $options['http']['content'] = json_encode($data);
        }
        
        $context  = stream_context_create($options);
        $result = file_get_contents($totalUrl, false, $context);
        if ($result === false) { /* Handle error */
            return null;
        }
        return json_decode($result);
    }
}

// May be useful in the future
/**
 * Sends a message to all clients.
 *
 * @param method GET, POST, PATCH, DELETE
 * @param relativeUrl the relative url of the request. The apiUrl will be pre-pended automatically.
 * @param data the data to send
 *
 * @return the result of the last error or, if no error occurred, the result of the last request.
 */
// private static function informClients($method, $relativeUrl, $data=array())
//     {
//         global $config;
//         $lastResult = new class {
//             public $status = 200;
//         };
//         $lastUrl = "";
//         foreach ($config[CF_AUTH_AUDIENCES] as $api => $secret) {
//             if (!array_key_exists($api, $config[CF_AUTH_MICROSERVICES])) {
//                 throw new RuntimeException("Every client must have a shared secret for microservice functionality, too!");
//             }
//             $result = MicroService::exec($method, $api, $relativeUrl, $data);
//             if ($lastResult->status < 300 || $result->status >= 300) {
//                 $lastResult = $result;
//                 $lastUrl = $api."/".$relativeUrl;
//             }
//         }
//         if ($lastResult->status >=300) {
//             throw new RuntimeException("There has been a problem with informing at least one client! Last error-request was '$lastUrl'.");
//         }
//         return $lastResult;
//     }
