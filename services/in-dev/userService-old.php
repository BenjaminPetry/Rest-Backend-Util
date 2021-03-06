<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */
require_once("baseService.php");
require_once("roleService.php");

/**
 * The user service handles CRUD actions for the user.
 * The me/ endpoint is used to retrieve basic information about the user
 * IMPORTANT: the 'username' is unique, but the user's ID is used as unique ID as the username could change. However, most methods in this class can be used with the username as well.
 * @see SessionService for login and logout interactions
 *
 * On default all these interactions are only allowed by an admin
 * @auth admin
 */
class UserService
{
    /**
     * Creates a new user. Admin only.
     *
     * @param   username  name of the new user
     * @param   email     email of the new user
     * @param   password  the password of the new user
     *
     * @url POST users/ username=$username,email=$email,password=$password
     */
    public function create($username, $email, $password)
    {
        if ($username == "") {
            throw new RestException(422, "The username must not be empty.");
        }
        if ($password == "") {
            throw new RestException(422, "The password must not be empty.");
        }
        if (preg_match("/^[0-9]+?$/", $username)) {
            throw new RestException(422, "The username '$username' must not consist only of numbers.");
        }
        if (self::exists($username)) {
            throw new RestException(409, "A user with the username '$username' already exists.");
        }
        if (self::emailExists($email)) {
            throw new RestException(409, "A user with the email '$email' already exists.");
        }

        $sha_passwordSalt = self::encryptPassword($password);
        $userId = BaseService::create("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)", array("username"=>$username, "email"=>$email, "password"=>$sha_passwordSalt));
        if (!$userId) {
            throw new RuntimeException("Could not create user with the username '$username'.");
        }
        Response::setStatus(201, "users/$username");
        return self::get($username);
    }

    private static function encryptPassword($password)
    {
        $sha_salt = randomHashString();
        $sha_password = hash('sha512', $password.$sha_salt);
        return $sha_password.$sha_salt;
    }

    /**
     * Checks whether a user already exists. Admin only.
     *
     * @param   user  the name (or ID) of the user to check
     *
     * @url GET users/$user/exists
     */
    public function exists($user)
    {
        return BaseService::count("SELECT COUNT(*) FROM users WHERE ".self::userWhere($user), array("user"=>$user)) > 0;
    }

    /**
     * Checks whether an email already exists. Admin only.
     *
     * @param   email  the name of the email to check
     *
     * @url GET emails/$email/exists
     */
    public function emailExists($email)
    {
        return BaseService::count("SELECT COUNT(*) FROM users WHERE email=:email", array("email"=>$email)) > 0;
    }

    /**
     * Retrieves a list of all users. Admin only.
     *
     * @url GET users/
     */
    public function list()
    {
        return BaseService::getList("SELECT ID, username, email, created_at, user_state FROM users ORDER BY username DESC;");
    }

    /**
     * Retrieves information about a user
     *
     * @param   user      id of the user (must be numeric) otherwise it will be treated as username
     * @param   throw404  if true, an exception will be thrown if no user with the username exists
     *
     * @auth self,admin
     * @url GET me/
     * @url GET users/$user
     */
    public function get($user, $throw404=true)
    {
        return BaseService::get(
            "SELECT ID, username, email, created_at, user_state FROM users WHERE ".self::userWhere($user)." ORDER BY username DESC;",
            array("user"=>$user),
            $throw404,
            "user",
            $user
        );
    }

    /**
     * Updates the current password.
     *
     * @param user        id of the user (must be numeric) otherwise it will be treated as username
     * @param password    current password of the user (except for admins)
     * @param newPassword the new password of the user
     *
     * @auth self,admin
     * @url PATCH /users/$user/password newPassword=$newPassword,password=$password?
     */
    public static function updatePassword($user, $newPassword, $password="")
    {
        $adminChangesUsersPassword = Auth::hasRole(AUTH_ROLE_ADMIN) && !Auth::hasRole(AUTH_ROLE_SELF, $user);
        if (!$adminChangesUsersPassword && !self::checkPassword($user, $password)) {
            throw new RestException(401, "Invalid password.");
        }
        $sha_passwordSalt = password_hash($newPassword); //self::encryptPassword($newPassword);
        if (!BaseService::execute("UPDATE users SET `password` = :password WHERE ".self::userWhere($user), array("user"=>$user,"password" => $sha_passwordSalt))) {
            throw new RuntimeException("Could not change the password.");
        }
        Response::setStatus(204);
        return true;
    }

    /**
     * Sets the admin state of a user. Superadmin only.
     *
     * @auth superadmin
     * @url PATCH /users/$user/admin  isAdmin=$isAdmin
     */
    public static function setAdmin($user, $isAdmin)
    {
        if (!BaseService::execute("UPDATE users SET `is_admin` = :isAdmin WHERE ".self::userWhere($user), array("user"=>$user,"isAdmin" => $isAdmin))) {
            throw new RuntimeException("Could not change admin state.");
        }
    }

    /**
     * Checking the password of a user
     *
     * @param   user      id of the user (must be numeric) otherwise it will be treated as username
     * @param   password  the password of the user
     */
    public static function checkPassword($user, $password)
    {
        $user = BaseService::get("SELECT `password` FROM users WHERE ".self::userWhere($user)." LIMIT 1;", array("user"=>$user));
        $checkPassword = $user["password"];
        $sha_password = substr($checkPassword, 0, 128);
        $sha_salt = substr($checkPassword, 128);
        $comparePassword = hash('sha512', $password.$sha_salt);
        return $comparePassword == $sha_password;
    }

    private static function userWhere($user)
    {
        return preg_match("/^[0-9]+?$/", $user) ? "id=:user" : "username=:user";
    }


    // /**
    //  * Adds a role to a user. The admin role can only be added from a superuser
    //  *
    //  * @url POST /users/$username/roles role_name=$roleName
    //  */
    // public static function addRole($username, $roleName)
    // {
    //     $roleName = strtolower($roleName);
    //     if ($roleName == AUTH_ROLE_SUPERADMIN) {
    //         throw new RestException(403, "'Superadmin' is not a role to be set.");
    //     }
    //     $role = RoleService::get($roleName, false);
    //     if ($role == null) {
    //         throw new FieldValidationException("The role '$rolename' is not valid");
    //     }
    //     if ($roleName == AUTH_ROLE_ADMIN && !Auth::hasRole(AUTH_ROLE_SUPERADMIN)) {
    //         throw new RestException(403, "The role 'Admin' can only be set by the superadmin!");
    //     }
    //     $id = BaseService::execute("INSERT INTO user_to_roles (username,role_name) VALUES (:username,:rolename)", array("username"=>$username,"rolename"=>$rolename));
    //     if (!$id) {
    //         throw new RuntimeException("Could not set role '$roleName' for user '$username'");
    //     }
    //     Response::setStatus(204);
    //     return true;
    // }
    
    // /**
    //  * Removes a role to the current user. The admin role can only be added from a superuser
    //  *
    //  * @url POST /users/$username/roles role_name=$roleName
    //  */
    // public static function removeRole($username, $roleName)
    // {
    //     $roleName = strtolower($roleName);
        
    //     // TODO
    // }

    /**
     * Retrieves the roles of a user
     *
     * @auth self,admin
     * @url GET me/roles
     * @url GET users/$user/roles
     */
    public static function getRoles($user)
    {
        global $config;
        $list = BaseService::getList("SELECT role_name as `role` FROM user_to_roles WHERE username=:user ORDER BY role_name ASC;", array("user"=>$user), "role");
        if ($user == $config["auth-superadmin"]) {
            $list[] = AUTH_ROLE_SUPERADMIN;
            if (!in_array(AUTH_ROLE_ADMIN, $list)) {
                $list[] = AUTH_ROLE_ADMIN;
            }
        } else {
            $list = array_filter($list, function ($element) {
                return $element != AUTH_ROLE_SUPERADMIN;
            });
        }
        if (self::exists($user)) {
            $list[] = AUTH_ROLE_USER;
        }
        return $list;
    }
};


        
//         public static function delete($id, $password)
//         {
//             self::authenticate($id, $password, true);
//             if (LoginManagement::isCurrentUser(self::retrieveById($id)) && LoginManagement::isCurrentUserAdmin())
//             {
//                 throw new UserInputException("Admin cannot be deleted");
//             }
//             if (!DB::execute(self::$deleteUser,array("id" => $id)))
//             {
//                 throw new UserInputException("User could not be deleted.");
//             }
//             if (!LoginManagement::isCurrentUserAdmin())
//             {
//                 LoginManagement::logout();
//             }
//             return true;
//         }
