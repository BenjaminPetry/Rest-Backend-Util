<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

global $config; // project and server configuration

// default config
$config = array(
  CF_VERBOSE => false,
  CF_TEST => false,
  CF_FORCE_RUN_LOCAL => false,
  CF_NAME => "Unknown Project",
  CF_CORS_ACCEPT_ORIGINS => "http://localhost:3000",
  CF_PATH_CACHE => CWD."/cache",
  CF_PATH_TMP => CWD."/tmp",
  CF_PATH_LOG => CWD."/log",
  CF_EMAIL_DEFAULT_SENDER => null,
  CF_EMAIL_LOG_RECEIVER => null,
  CF_DB => array(),
  CF_SECRET => randomHashString(),
  CF_ENABLE_TOOLS => true,
  CF_AUTH => AUTH_SERVER,
  CF_AUTH_SUPERADMIN => 1,
  CF_AUTH_ISS => "http://localhost/example/v1",
  CF_AUTH_CLIENTS => array(),
  CF_AUTH_MICROSERVICES => array(),
  CF_AUTH_MICROSERVICES_PERMISSIONS => array(),
);

if (file_exists(CWD."/config.php")) {
    include(CWD."/config.php");
}
$is_production = false;
if (file_exists(CWD."/config.development.php")) {
    include(CWD."/config.development.php");
} elseif (file_exists(CWD."/config.production.php")) {
    include(CWD."/config.production.php");
    $is_production = true;
}

// ensure that paths have no ending slash
foreach ($config as $key => $value) {
    if (strtolower(substr($key, 0, 5)) == "path-") {
        $config[$key] = removeSlash($value);
    }
}

// true if the code is running on the localhost
$tmpHost = filter_input(INPUT_SERVER, 'SERVER_ADDR', FILTER_SANITIZE_URL);
$tmpServername = filter_input(INPUT_SERVER, 'SERVER_NAME', FILTER_SANITIZE_URL);

define("IS_PRODUCTION", $is_production);
define("IS_LOCALHOST", $tmpServername == "localhost" || $tmpHost=="127.0.0.1" || $tmpHost=="localhost" || $config[CF_FORCE_RUN_LOCAL]);
define('VERBOSE', $config[CF_VERBOSE]);
define('TEST', $config[CF_TEST]);
define("SECRET", $config[CF_SECRET]);

define("PATH_CACHE", $config[CF_PATH_CACHE]);
define("PATH_TMP", $config[CF_PATH_TMP]);
define("PATH_LOG", $config[CF_PATH_LOG]);

$tmpDatabaseConfigName = (TEST) ? CF_DB_CONFIG_TEST : CF_DB_CONFIG_DEFAULT;
define("DATABASE_CONFIG", array_key_exists($tmpDatabaseConfigName, $config[CF_DB])? $tmpDatabaseConfigName : null);

define("CONFIG_NAME", $config[CF_NAME]);
define("CONFIG_CORS_ACCEPT_ORIGINS", $config[CF_CORS_ACCEPT_ORIGINS]);
define("CONFIG_EMAIL_DEFAULT_SENDER", $config[CF_EMAIL_DEFAULT_SENDER]);
define("CONFIG_EMAIL_LOG_RECEIVER", $config[CF_EMAIL_LOG_RECEIVER]);

define("CONFIG_ENABLE_TOOLS", $config[CF_ENABLE_TOOLS]);

define("AUTH_MODE", $config[CF_AUTH]);
define("AUTH_ENABLED", AUTH_MODE != AUTH_NONE);
define("AUTH_SUPERADMIN", $config[CF_AUTH_SUPERADMIN]);
define("AUTH_ISSUER", $config[CF_AUTH_ISS]);
