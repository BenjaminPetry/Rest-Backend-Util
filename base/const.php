<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */

// current working directory
if (!defined("CWD")) {
    define("CWD", realpath(dirname(__FILE__)."/../../"));
}
define("FRAMEWORK_CWD", realpath(dirname(__FILE__)."/../"));

 // For slash removal
define("SLASH_STARTING", 1);
define("SLASH_ENDING", 2);
define("SLASH_BOTH", 3);

// Authentication methods for the config file
define("AUTH_NONE", 0);
define("AUTH_SERVER", 1);
define("AUTH_CLIENT", 2);

// Authentication roles
define("AUTH_ROLE_NONE", "none");
define("AUTH_ROLE_MICROSERVICE", "microservice"); // another service accessing this service (those tokens are only valid a minute and only for the requested URL)
define("AUTH_ROLE_USER", "user"); // default if $config["auth"] is not 'none'
define("AUTH_ROLE_ADMIN", "admin"); // admin
define("AUTH_ROLE_SUPERADMIN", "superadmin"); // admin
define("AUTH_ROLE_SELF", "self"); // requires a logged in user and provides the current user as the variable $user (the user's ID)

// Standard Config fields (CF)
define("CF_VERBOSE", "verbose");
define("CF_TEST", "test");
define("CF_FORCE_RUN_LOCAL", "run-local");
define("CF_ERRORS_DISPLAY", "display-errors");

define("CF_NAME", "name");
define("CF_CORS_ACCEPT_ORIGINS", "cors-accept-origins");

// paths must start with 'path-'!
define("CF_PATH_CACHE", "path-cache");
define("CF_PATH_TMP", "path-tmp");
define("CF_PATH_LOG", "path-log");

define("CF_EMAIL_DEFAULT_SENDER", "email-default-sender");
define("CF_EMAIL_LOG_RECEIVER", "email-log-receiver");

define("CF_DB", "db");
define("CF_DB_CONFIG_DEFAULT", "db-config-default");
define("CF_DB_CONFIG_TEST", "db-config-test");

define("CF_DB_HOST", "db-host");
define("CF_DB_USER", "db-user");
define("CF_DB_PASSWORD", "db-password");
define("CF_DB_NAME", "db-name");

define("CF_SECRET", "db-name");

define("CF_ENABLE_TOOLS", "enable-tools");

define("CF_AUTH", "auth-method");
define("CF_AUTH_SUPERADMIN", "auth-superadmin");
define("CF_AUTH_ISS", "auth-iss");
define("CF_AUTH_CLIENTS", "auth-clients");
define("CF_AUTH_CLIENTS_AUDIENCE", "audience");
define("CF_AUTH_CLIENTS_AUTH_URL", "authorize-url");
define("CF_AUTH_AUDIENCES", "auth-audiences");
define("CF_AUTH_MICROSERVICES", "auth-microservices");
define("CF_AUTH_MICROSERVICES_PERMISSIONS", "auth-microservices-permissions");
