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
 * @var modX $modx
 * @var array $scriptProperties
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

$forceLogin = $modx->getOption('forceLogin', $scriptProperties, true);
$remoteAuth = $modx->getOption('remoteAuth', $scriptProperties, true);
$loggedInTpl = $modx->getOption('loggedInTpl', $scriptProperties, '@INLINE You\'re logged in.');
$auth0UserTpl = $modx->getOption('auth0UserTpl', $scriptProperties, '@INLINE Your Auth0 user isn\'t valid here. Try logging in again.');
$anonymousTpl = $modx->getOption('anonymousTpl', $scriptProperties, '@INLINE Login required.');
$debug = $modx->getOption('debug', $scriptProperties, '');

// Expose properties for TPL
$props = $scriptProperties;

$corePath = $modx->getOption('auth0.core_path', null, $modx->getOption('core_path', null, MODX_CORE_PATH) . 'components/auth0/');
/** @var Auth0 $auth0 */
$auth0 = $modx->getService('auth0', 'Auth0', $corePath . 'model/auth0/', ['core_path' => $corePath]);

if (!($auth0 instanceof Auth0) || !$auth0->init()) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[auth0.loggedIn] could not load the required class on line: ' . __LINE__);

    // MODX session is the record of truth for logged-in state
    if ($modx->user && $modx->user->hasSessionContext($modx->context->key)) {
        return $modx->getChunk($loggedInTpl, $props);
    } else {
        $modx->sendUnauthorizedPage();
        return;
    }
}

// Call Auth0 for userinfo if configured
if ($remoteAuth) {
    $userInfo = $auth0->getUser($forceLogin);
    if (!empty($userInfo) && is_array($userInfo)) {
        $props = array_merge($props, $userInfo);
    }
}

// Debug info
if ($debug) {
    $props['caller'] = 'auth0.loggedIn';
    $props['context_key'] = $modx->context->key;
    if ($modx->resource) $props['resource_id'] = $modx->resource->id;
}

// Check for session
if ($modx->user->hasSessionContext($modx->context->key)) {
    // Has session
    if ($debug) {
        return $auth0->debug($props);
    }
    // MODX session is the record of truth for logged-in state
    // Without remoteAuth, we lack $userInfo in $props though
    return $auth0->getChunk($loggedInTpl, $props);
} else {
    // No session
    if (!$remoteAuth) {
        // We're not configured to check remotely so local session has final say
        if ($debug) {
            return $auth0->debug($props);
        }
        // We don't know if user is logged-in to Auth0
        return $auth0->getChunk($anonymousTpl, $props);
    } else {
        if (!empty($userInfo) && is_array($userInfo)) {
            if ($debug) {
                return $auth0->debug($props);
            }
            // We checked and the User is logged-in to Auth0 but not MODX
            return $auth0->getChunk($auth0UserTpl, $props);
        } else {
            if ($debug) {
                return $auth0->debug($props);
            }
            // We checked and the User is not logged-in to Auth0
            return $auth0->getChunk($anonymousTpl, $props);
        }
    }
}