<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */
require_once("baseService.php");

/**
 * This class is for role management. Admin only.
 *
 * @auth admin
 */
class RoleService
{

    /**
     * Creates a new role
     *
     * @param name the name of the new role
     *
     * @auth superadmin
     * @url POST /roles name=$name
     */
    public static function create($name)
    {
        $name = strtolower($name);
        if ($name == "") {
            throw new FieldValidationException("The role's name must not be empty");
        }
        if ($name == AUTH_ROLE_SUPERADMIN || $name == AUTH_ROLE_ADMIN || $name == AUTH_ROLE_SELF || $name == AUTH_ROLE_NONE || $name == AUTH_ROLE_USER || preg_match(Auth::EXPR_MICROSERVICE, $name)) {
            throw new FieldValidationException("A role must not be named 'superadmin', 'admin', 'self', 'user', 'none' or starts with 'microservice'!");
        }
        if (preg_match("/[^A-Za-z0-9]/s", $name)) {
            throw new FieldValidationException("The role's name must consist only of letters and numbers!");
        }
        if (self::get($name, false) != null) {
            throw new RestException(409, "Role '$name' already exists.");
        }
        $newId = BaseService::create("INSERT INTO roles (`role_name`) VALUE (:role_name);", array("role_name" => $name));
        Response::setStatus(201, "roles/$name");
        return self::get($name);
    }

    /**
     * Provides a list of assignable roles.
     *
     * @url GET /roles
     */
    public static function list()
    {
        $tmp = BaseService::getList("SELECT `role_name` FROM roles ORDER BY `role_name` ASC;", array(), "role_name");
        array_unshift($tmp, AUTH_ROLE_ADMIN);
        return $tmp;
    }

    /**
     * Returns the database information of a role
     *
     * @param name the name of the role
     *
     * @url GET /roles/$name
     */
    public static function get($name, $throw404=true)
    {
        if ($name == AUTH_ROLE_ADMIN) {
            return array("ID"=>0,"role_name"=>AUTH_ROLE_ADMIN);
        }
        return BaseService::get("SELECT `ID`, `role_name` FROM roles WHERE `role_name`=:role_name;", array("role_name"=>$name), $throw404, "role", $name);
    }

    /**
     * Deletes a role. Superadmin only.
     * IMPORTANT NOTE: This will also affect the user_to_roles table from the UserService!
     *
     * @param name the name of the role
     *
     * @auth superadmin
     * @url DELETE /roles/$name
     */
    public static function delete($name)
    {
        $role = self::get($name);
        if (!BaseService::execute("DELETE FROM roles WHERE `role_name`=:role_name;", array("role_name"=>$name))) {
            throw new RuntimeException("Could not delete role '$name'.");
        }
        if (!BaseService::execute("DELETE FROM user_to_roles WHERE `role_name`=:role_name;", array("role_name"=>$name))) {
            throw new RuntimeException("Could not delete role '$name' from user_to_roles table!.");
        }
        Response::setStatus(204);
        return true;
    }
}
