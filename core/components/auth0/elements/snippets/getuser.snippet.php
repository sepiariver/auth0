<?php
/**
 * auth0.getUser
 *
 * Fetches an Auth0 user
 *
 * OPTIONS:
 * redirectUnauthorized -   (int) Sends unauthorized response, preventing anything below this Snippet
 *                          in the Resource/Template from being processed. If disabled, exit() WILL NOT
 *                          be called!. Return values can be customized in the properties below. Default 1
 * redirectTo -             (string) Accepts either 'error' or 'unauthorized'. Both methods call exit().
 *                          Default 'unauthorized'
 * returnOnUnauthorized -   (mixed) Specify a return value if request is unauthorized. Default 0
 * returnOnSuccess -        (mixed) Specify a return value if request is successfully verified. Default 1
 *
 * @package Auth0
 * @author @sepiariver <info@sepiariver.com>
 * Copyright 2018 by YJ Tso
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 **/

// Options


// Paths
$auth0Path = $modx->getOption('auth0.core_path', null, $modx->getOption('core_path') . 'components/auth0/');
$auth0Path .= 'model/auth0/';

// Get Class
if (file_exists($auth0Path . 'auth0.class.php')) $Auth0 = $modx->getService('auth0', 'Auth0', $auth0Path, $scriptProperties);
if (!($Auth0 instanceof Auth0)) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] could not load the required class!');
    return;
}

// Init
$auth0 = $Auth0->init();

$userInfo = $auth0->getUser();

if (!$userInfo) {
    $auth0->login();
} else {
    var_dump($userInfo);
}

if ($_GET['logout']) $auth0->logout();