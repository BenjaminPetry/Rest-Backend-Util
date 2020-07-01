<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */
define("META_UTIL_VERSION", "0.61");
define("META_UTIL_COPYRIGHT", "2020 by Benjamin Petry");
require_once("base/const.php"); // add constants

// #############################################################################################
// ######################################### BASE ##############################################
// #############################################################################################
require_once("base/00_util.php");
require_once("base/01_config.php");
require_once("base/02_mail.php");
require_once("base/03_log.php");
require_once("base/04_database.php");

// INIT

// Check whether the expected backend version is correct
if (defined("EXPECTED_BACKEND_UTIL_VERSION") && !checkVersion(EXPECTED_BACKEND_UTIL_VERSION, META_UTIL_VERSION)) {
    throw new RuntimeException("Wrong version of the backend-util framework detected. The backend-util framework version is ".META_UTIL_VERSION." but your application expects a version ".EXPECTED_BACKEND_UTIL_VERSION.". Maybe pulling the submodule of the backend-util framework solves this issue. Otherwise change the expected version of your application to: define(\"EXPECTED_BACKEND_UTIL_VERSION\", >=".META_UTIL_VERSION.");");
}

if (DISPLAY_ERRORS) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

Log::debug("-------------------- START --------------------");

// Init the database
if (!is_null(DATABASE_CONFIG)) {
    Log::debug("** INIT Database **");
    $dbConfig = $config[CF_DB][DATABASE_CONFIG];
    DB::Init(new PDO(
        "mysql:dbname=".$dbConfig[CF_DB_NAME].";host=".$dbConfig[CF_DB_HOST],
        $dbConfig[CF_DB_USER],
        $dbConfig[CF_DB_PASSWORD]
    ));
}


// #############################################################################################
// ######################################### REST ##############################################
// #############################################################################################

require_once("rest/00_exceptions.php");
require_once("rest/01_request.php");
require_once("rest/02_response.php");
require_once("rest/03_url_manager.php");
require_once("rest/04_controller.php");
require_once("rest/05_rest_server.php");

// INIT
Log::debug("** INIT Request **");
Request::init();
Log::debug("** INIT Response **");
Response::init();
Log::debug("** INIT URL Manager **");
UrlManager::init(PATH_CACHE."/"."urlMap.cache");
Log::debug("** INIT REST Server **");
RestServer::init();

// #############################################################################################
// ###################################### AUTHENTICATION #######################################
// #############################################################################################
require_once("auth/01_auth.php");
require_once("auth/02_token_manager.php");
require_once("auth/03_microservice.php");
require_once("auth/04_basic_auth_handler.php");

// INIT
Log::debug("** INIT Basic Authentication **");
$authHandler = new BasicAuthHandler();
RestServer::setAuthHandler($authHandler);

// #############################################################################################
// ################################### DEFAULT CONTROLLERS #####################################
// #############################################################################################

RestServer::controller("ToolService", FRAMEWORK_CWD."/services/toolService.php");
