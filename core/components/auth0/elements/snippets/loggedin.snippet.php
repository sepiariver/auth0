<?php
/**
 * auth0.loggedIn
 *
 * Check user login state and show content or redirect accordingly
 *
 * OPTIONS:
 * &forceLogin -    (bool) Enable/disable forwarding to Auth0 for login if anonymous. &anonymousTpl will not be displayed if this is true. Default true
 * &loggedInTpl -   (string) Chunk TPL to render when logged in. Default '@INLINE ...'
 * &auth0UserTpl -  (string) Chunk TPL to render when logged into Auth0 but not MODX. Default '@INLINE ...'
 * &anonymousTpl -  (string) Chunk TPL to render when not logged in. Default '@INLINE ...'
 * &debug -         (bool) Enable debug output. Default false
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
$forceLogin = $modx->getOption('forceLogin', $scriptProperties, true);
$loggedInTpl = $modx->getOption('loggedInTpl', $scriptProperties, '@INLINE You\'re logged in.');
$auth0UserTpl = $modx->getOption('auth0UserTpl', $scriptProperties, '@INLINE Your Auth0 user isn\'t valid here. Try logging in again.');
$anonymousTpl = $modx->getOption('anonymousTpl', $scriptProperties, '@INLINE Login required.');
$debug = $modx->getOption('debug', $scriptProperties, false);

// Paths
$auth0Path = $modx->getOption('auth0.core_path', null, $modx->getOption('core_path') . 'components/auth0/');
$auth0Path .= 'model/auth0/';

// Get Class
if (file_exists($auth0Path . 'auth0.class.php')) $auth0 = $modx->getService('auth0', 'Auth0', $auth0Path);
if (!($auth0 instanceof Auth0)) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[auth0.loggedIn] could not load the required class on line: ' . __LINE__);
    return;
}

// Setup
if (!$auth0->init()) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[auth0.loggedIn] could not setup Auth0 on line: ' . __LINE__);
    return;
}

// Expose properties for TPL
$props = $scriptProperties;

// Call for userinfo
$userInfo = $auth0->getUser($forceLogin);
if ($userInfo) {
    $props = array_merge($props, $userInfo);
}

// Check for session
if ($modx->user->hasSessionContext($modx->context->key)) {
    // MODX session is the record of truth for logged-in state
    if ($debug) return '<pre>Line: ' . __LINE__ . PHP_EOL . print_r($props, true) . '</pre>';
    return $auth0->getChunk($loggedInTpl, $props);
} else {
    if ($userInfo) {
        // User logged-in to Auth0 but not MODX
        if ($debug) return '<pre>Line: ' . __LINE__ . PHP_EOL . print_r($props, true) . '</pre>';
        return $auth0->getChunk($auth0UserTpl, $props);
    } else {
        // User not logged-in to Auth0
        if ($debug) return '<pre>Line: ' . __LINE__ . PHP_EOL . print_r($props, true) . '</pre>';
        return $auth0->getChunk($anonymousTpl, $props);
    }
}
