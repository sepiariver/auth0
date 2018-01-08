<?php
/**
 * auth0.getUser
 *
 * Fetches an Auth0 user
 *
 * OPTIONS:
 * &loginUnauthorized - (bool) Enable/disable login when no authenticated user. Default true
 * &tpl -               (string) Chunk TPL for userinfo output. Default ''
 * &default -           (string) Default output. Default ''
 * &debug -             (bool) Enable debug output. Default false
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
$loginUnauthorized = $modx->getOption('loginUnauthorized', $scriptProperties, true);
$tpl = $modx->getOption('tpl', $scriptProperties, '');
$default = $modx->getOption('default', $scriptProperties, '');
$debug = $modx->getOption('debug', $scriptProperties, false);

// Paths
$auth0Path = $modx->getOption('auth0.core_path', null, $modx->getOption('core_path') . 'components/auth0/');
$auth0Path .= 'model/auth0/';

// Get Class
if (file_exists($auth0Path . 'auth0.class.php')) $auth0 = $modx->getService('auth0', 'Auth0', $auth0Path, $scriptProperties);
if (!($auth0 instanceof Auth0)) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] could not load the required class!');
    return;
}

// Init
$auth0->init();

// Call userinfo
$userInfo = $auth0->api->getUser();

// Call login if no user
if (!$userInfo) {
    if ($loginUnauthorized) {
        $auth0->api->login();
    } else {
        return $default;
    }
} else {
    // Display userinfo
    if (empty($tpl)) {
        if ($debug) return '<pre>' . print_r($userInfo, true) . '</pre>';
        return $default;
    }
    return $modx->getChunk($tpl, $userInfo);
}