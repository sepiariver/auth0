<?php
/**
 * @package auth0
 */
require_once (strtr(realpath(dirname(dirname(__FILE__))), '\\', '/') . '/auth0xtoken.class.php');
class Auth0XToken_mysql extends Auth0XToken {}
?>