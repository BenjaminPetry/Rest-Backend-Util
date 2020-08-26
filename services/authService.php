<?php
/**
 * Copyright 2020 by Benjamin Petry (www.bpetry.de).
 * This software is provided on an "AS IS" BASIS,
 * without warranties or conditions of any kind, either express or implied.
 */
require_once("baseService.php");
require_once("userService.php");
require_once("sessionService.php");

/**
 * The authorization services provides SSO-login functionality.
 * It uses the SessionService-class to create and delete sessions and tokens.
 * @see SessionService for login and logout interactions
 *
 * Several methods of the AuthService require a $request_url. The $request_url is the url of the frontend, that
 * is also registered in the config's CF_AUTH_CLIENTS array with its corresponding audience (backend) and
 * auth-url (where redirects go.)
 *
 * Every redirect to the auth-url contains the response_type field, which can be one of the following parameters:
 * - access_code: the url also contains a field "access_code" with an access code which can be exchanged for an access token
 * - login_required: the "silent" request for an access code failed and a normal login is required
 * - logout_successful: the logout action was successful
 * - logout_failed: the logout action failed
 *
 * @auth none
 */
class AuthService
{
    private static $RESPONSE_TYPE_FIELD = "response_type";
    private static $RESPONSE_TYPE_ACCESS_CODE = "access_code";
    private static $RESPONSE_TYPE_LOGIN_REQUIRED = "login_required";
    private static $RESPONSE_TYPE_LOGOUT_SUCCESSFUL = "logout_successful";
    private static $RESPONSE_TYPE_LOGOUT_FAILED = "logout_failed";

    /**
     * Checks if a user is already logged in. Otherwise it displays the login form.
     *
     * @param request_url the base url that requests an access code
     * @param email (optional) an initial email to login
     * @param silent (optional) if silent is set to true, no login form will be displayed. Instead a redirect to the auth-url is done with the parameter '?login=required'
     *
     * @return void will display HTML code!
     *
     * @url GET /authorize/login?request_url=$request_url&email=$email&silent=$silent
     */
    public static function default($request_url="", $email="", $silent=false)
    {
        global $config;
        // 1. check request url and get its audience
        $request_info = self::checkRequestUrl($request_url);
        $audience = $request_info[CF_AUTH_CLIENTS_AUDIENCE];
        $auth_url = $request_info[CF_AUTH_CLIENTS_AUTH_URL];
        

        // 2. check if the user is already logged in
        if (!is_null(SessionService::getCurrentGuid())) {
            try {
                $access_code = SessionService::createNewAccessCode($request_url, $audience);
                self::writeRedirect("Login still active", $auth_url, self::$RESPONSE_TYPE_ACCESS_CODE, array("access_code" => $access_code));
            } catch (Exception $e) {
                self::writeForm($request_url, $email, $e->getMessage());
            }
            exit();
        }

        // 3. if no user is logged in and silent is set to true
        if ($silent) {
            self::writeRedirect("Login required", $auth_url, self::$RESPONSE_TYPE_LOGIN_REQUIRED);
            exit();
        }

        // 4. if no user is logged in and silent is false, display loggin form
        self::writeForm($request_url, $email, "");
        exit();
    }


    /**
     * Handles when the form has been submitted with login information
     *
     * @param request_url the base url that requests an access code
     *
     * @return void will display HTML code!
     *
     * @url POST /authorize/login?request_url=$request_url
     */
    public static function onLogin($request_url)
    {
        $request_info = self::checkRequestUrl($request_url);
        $audience = $request_info[CF_AUTH_CLIENTS_AUDIENCE];
        $auth_url = $request_info[CF_AUTH_CLIENTS_AUTH_URL];

        $post = filter_input_array(INPUT_POST);
        $email = array_key_exists("email", $post) ? $post["email"] : "";
        $password = array_key_exists("password", $post) ? $post["password"] : "";
      
        if ($email == "" || $password == "") {
            self::writeForm($request_url, $email, "Please provide your e-mail and your password to login!");
            exit();
        }
        if (UserService::checkPassword($email, $password, true)) {
            $user = UserService::get($email, true, true);
            if (SessionService::create($user)) {
                $access_code = SessionService::createNewAccessCode($request_url, $audience);
                self::writeRedirect("Login successful", $auth_url, self::$RESPONSE_TYPE_ACCESS_CODE, array("access_code" => $access_code));
            } else {
                self::writeForm($request_url, $email, "There has been an internal error! Please try again later.");
            }
        } else {
            self::writeForm($request_url, $email, "User does not exist or password is wrong!");
        }
        exit();
    }

    /**
     * Deletes the current login, if existing
     *
     * @param request_url the base url that requests an access code
     *
     * @return void will display HTML code!
     *
     * @url DELETE /authorize?request_url=$request_url
     * @url GET /authorize/logout?request_url=$request_url
     */
    public static function onLogout($request_url="")
    {
        $request_info = self::checkRequestUrl($request_url);
        $audience = $request_info[CF_AUTH_CLIENTS_AUDIENCE];
        $auth_url = $request_info[CF_AUTH_CLIENTS_AUTH_URL];
      
        $guid = SessionService::getCurrentGuid();
        if (!is_null($guid)) {
            SessionService::delete($guid);
        }
        self::writeRedirect("Logout successful", $auth_url, self::$RESPONSE_TYPE_LOGOUT_SUCCESSFUL);
        exit();
    }

    /**
     * Returns a token, given an access code
     *
     * @param access_code the access code that should be exchanged for an access token
     * @param nonce the nonce (a random number) that will be integrated into the token
     *
     * @url GET authorize/$access_code/use?nonce=$nonce
     */
    public static function useAccessCode($access_code, $nonce)
    {
        $token = SessionService::getAccessToken($access_code, $nonce);
        return array("access_token"=>$token);
    }


    /**
     * Revokes a token from this audience
     *
     * @auth microservice
     * @url POST ms/authorize/access_tokens/$token_id/revoke
     */
    public static function revokeToken($token_id)
    {
        return AccessToken::revokeTokenByMS($token_id);
    }

    /**
     * Checks, whether the request url exists and returns its audience
     *
     * @param request_url the url to check
     * @param write_error if set to true, the error will be written as HTML and the scripted exited. Otherwise null is returned.
     *
     * @return audience of the request_url or null if the request_url is invalid and $write_error is set to false
     */
    private static function checkRequestUrl($request_url, $write_error=true)
    {
        global $config;
        if ($request_url=="" || !array_key_exists($request_url, $config[CF_AUTH_CLIENTS])) {
            if ($write_error) {
                self::writeError();
                exit();
            }
            return null;
        }
        if (!array_key_exists(CF_AUTH_CLIENTS_AUDIENCE, $config[CF_AUTH_CLIENTS][$request_url])) {
            throw new RuntimeException("Audience has not been defined for request_url '$request_url'!");
        }
        if (!array_key_exists(CF_AUTH_CLIENTS_AUTH_URL, $config[CF_AUTH_CLIENTS][$request_url])) {
            throw new RuntimeException("Authorize-url has not been defined for request_url '$request_url'!");
        }
        return $config[CF_AUTH_CLIENTS][$request_url];
    }

    // ######################################################################
    // ############################## HTML PART #############################
    // ######################################################################

    private static function writeError($error="Invalid usage of the authorization Service.")
    {
        self::writeHTMLStart(); ?> 
       <div class="error container">
       <h1 class="error__header heading">Error</h1>
         <div class="error__message">
           <?php echo($error); ?>
         </div>
       </div>
             <?php
        self::writeHTMLEnd();
    }

    public static function writeForm($request_url, $login_email, $login_error)
    {
        self::writeHTMLStart(); ?>
<form method="POST" action="?request_url=<?php echo($request_url) ?>">
  <div class="login container">
      <h1 class="login__header heading">Login</h1>
      <?php if ($login_error != "") {?>
        <div class="login__error"><?php echo($login_error)?></div>
      <?php } ?>
      <div class="login__input-container">
        <div class="login__email-container login__container">
          <input type="email" class="login__input" placeholder="E-Mail" value="<?php echo($login_email); ?>" name="email" autocomplete="email" autofocus required id="email">
          <label for="email" class="login__label">E-Mail</label>
        </div>
        <div class="login__password-container login__container">
          <input type="password" class="login__input" required placeholder="Password" name="password" autocomplete="password" id="password">
          <label for="password" class="login__label">Password</label>
        </div>
      </div>
      <button class="btn--primary">Login</button>
  </div>
</form>
      <?php
      self::writeHTMLEnd();
    }

    public static function writeRedirect($title, $redirect_url, $response_type, $param=array())
    {
        $param[self::$RESPONSE_TYPE_FIELD] = $response_type;
        $url = $redirect_url."?".http_build_query($param);
        self::writeHTMLStart(); ?> 
<script type="text/javascript">window.location.href="<?php echo($url); ?>"</script>
<div class="redirect container">
<h1 class="redirect__header heading"><?php echo($title); ?></h1>
  <div class="redirect__message">
    You will be redirected to <a href="<?php echo($url); ?>"><?php echo($redirect_url); ?></a>.
  </div>
</div>
      <?php
      self::writeHTMLEnd();
    }

    
    private static function writeCss()
    {
        ?>
<style>
  *, *::before, ::after {
    margin:0;
    padding: 0;
    box-sizing: inherit;
  }
  html {
    font-size: 62.5%;
    width: 100%;
    height: 100%;
  }
  body {
    font-family: sans-serif;
    font-size: 1.6rem;
    box-sizing: border-box;
    color: #000;
    background-color: #e6e7e8;
    width: 100%;
    height: 100%;
  }

  form {
    width: 100%;
    height: 100%;
  }

  .container
  {
    background-color: #FFF;
    position: absolute;
    font-size: 1.4rem;
    width: 100%;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    padding: 3rem;
    border-radius: 3px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: stretch;
      box-shadow: 0 0 .5rem rgba(0,0,0,.5);
  }

  /* > 380 Pixel */
  @media (min-width: 23.75em) {
    .container {
      width: 37rem;
    }
   }

  .heading {
    font-size: 3rem;
    font-weight: 300;
    margin-bottom: 2rem;
  }

  .login__input-container {
    margin-top: 2rem;
    margin-bottom: 2rem;
  }

  .login__container:not(:last-child)
  {
    margin-bottom: 2rem;
  }

  .login__container
  {
    display: flex;
    flex-direction: column;
  }

  .login__input {
    font-family: inherit;
    font-size: 1.4rem;
    padding: 1rem;
    border: 1px solid #ccced0;
    background-color: #f3f4f5;
    margin-bottom: .4rem;
    width: 100%;
    flex: 1;
  }

  .login__input:not(:placeholder-shown):invalid {
    padding: 1rem;
    border: 1px solid #e63a2e;
  }
  .login__input:focus:not(:placeholder-shown):invalid {
    padding: calc(1rem - 1px);
    border: 2px solid #e63a2e;
  }

  .login__input::placeholder,
  .login__label {
    position: relative;
    font-size: 1.4rem;
    color: #808386;
  }

  .login__input::placeholder {
    visibility: hidden;
  }

  .login__input:focus {
    outline: none;
    border-bottom: 2px solid #000;
    padding-bottom: calc(1rem - 1px);
  }

  .login__label {
    font-size: 1.2rem;
    transition: all .2s;
    z-index: 1;
    margin-left: calc(1rem + 1px);
    cursor: text;
  }

  .login__input:placeholder-shown:not(:focus) + label {
    transform: translateY(-3.1rem);
  }

  .login__input:focus + label {
    transform: translateY(0);
  }

  .btn--primary {
    padding: 1.5rem 4rem;
    border-style: none;
    border-radius: 3px;
    background-color: #004037;
    color: #FFF;
    align-self: flex-end;
    cursor: pointer;
  }

  .login__error {
    display: inline-block;
    border-top: 1px solid #e63a2e;
    border-bottom: 1px solid #e63a2e;
    background-color: #E63A2E33;
    padding: 1rem;
    margin-left: -3rem;
    margin-right: -3rem;
    width: calc(100% + 6rem);
    word-wrap: wrap;
    font-size: 1.2rem;
  }

  .error {
    border: 1px solid #e63a2e;
    background-color: #E63A2E33;
  }

  .error__header {
  }

  .btn--primary:hover,
  .btn--primary:focus {
    outline: none;
    background-color: #006B5C;
  }

  .btn--primary:active {
    background-color: #004D42;
  }

  a:link,
  a:visited {
    color: #bf3900;
    text-decoration: none;
  }

  a:hover,
  a:focus {
    outline: none;
    color: #ff4c00;
  }

  a:active {
    color: #6b2000;
  }

  .redirect__header {
    color: #21a637;
  }

  

</style>
      <?php
    }

    private static function writeHTMLStart()
    {
        ?>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <link rel="icon" href="<?php echo(Request::$apiUrl); ?>/util/static/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="Login" />
    <title>Login</title>
    <?php self::writeCss(); ?>
  </head>
  <body>
        <?php
    }

    private static function writeHTMLEnd()
    {
        ?>  
  </body>
</html>
        <?php
    }
};
