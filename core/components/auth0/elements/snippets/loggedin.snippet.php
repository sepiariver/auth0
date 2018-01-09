<?php
/**
 * auth0.loggedIn
 *
 * Check user login state and show content or redirect accordingly
 *
 * OPTIONS:
 * &loginUnauthorized -     (bool) Enable/disable redirect to Auth0 if not logged-in. Default true
 * &loggedInTpl -           (string) Chunk TPL to render when logged in. Default '@INLINE ...'
 * &anonymousTpl -          (string) Chunk TPL to render when not logged in. Default '@INLINE ...'
 * &redirect_uri -          (string) Auth0 redirect URI. Default {current Resource's URI}
 * &debug -                 (bool) Enable debug output. Default false
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
$loggedInTpl = $modx->getOption('loggedInTpl', $scriptProperties, '@INLINE You\'re logged in.');
$anonymousTpl = $modx->getOption('anonymousTpl', $scriptProperties, '@INLINE Login required.');
$auth0_redirect_uri = $modx->getOption('redirect_uri', $scriptProperties, $modx->makeUrl($modx->resource->get('id'), '', '', 'full'));
$debug = $modx->getOption('debug', $scriptProperties, false);

// Paths
$auth0Path = $modx->getOption('auth0.core_path', null, $modx->getOption('core_path') . 'components/auth0/');
$auth0Path .= 'model/auth0/';

// Get Class
if (file_exists($auth0Path . 'auth0.class.php')) $auth0 = $modx->getService('auth0', 'Auth0', $auth0Path, ['redirect_uri' => $auth0_redirect_uri]);
if (!($auth0 instanceof Auth0)) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[auth0.loggedIn] could not load the required class!');
    return;
}

// Check for session
if ($modx->user->hasSessionContext($modx->context->key)) {
    return $auth0->getChunk($loggedInTpl, $scriptProperties);
} else {
    if ($loginUnauthorized) {
        $auth0->api->login();
        return;
    } else {
        return $auth0->getChunk($anonymousTpl, $scriptProperties);
    }
}
