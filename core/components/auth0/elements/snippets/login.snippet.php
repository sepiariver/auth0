<?php
/**
 * auth0.login
 *
 * Login an Auth0 user
 *
 * OPTIONS:
 * &loginResourceId -       (int) ID of Resource to redirect user on successful login. Default 0 (no redirect)
 * &loginContexts -         (string) CSV of context keys, to login user (in addition to current context). Default ''
 * &requireVerifiedEmail -  (bool) Require verified_email from ID provider. Default true
 * &unverifiedEmailTpl -    (string) Chunk TPL to render when unverified email. Default '@INLINE ...'
 * &userNotFoundTpl -       (string) Chunk TPL to render when no MODX user found. Default '@INLINE ...'
 * &alreadyLoggedInTpl -    (string) Chunk TPL to render when MODX user already logged-in. Default '@INLINE ...'
 * &successfulLoginTpl -    (string) Chunk TPL to render when Auth0 login successful. Default '@INLINE ...'
 * &logoutParam -           (string) Key of GET param to trigger logout. Default 'logout'
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
$loginResourceId = (int) $modx->getOption('loginResourceId', $scriptProperties, 0);
$loginContexts = $modx->getOption('loginContexts', $scriptProperties, '');
$requireVerifiedEmail = $modx->getOption('requireVerifiedEmail', $scriptProperties, true);
$tpls['unverifiedEmailTpl'] = $modx->getOption('unverifiedEmailTpl', $scriptProperties, '@INLINE Email verification is required.');
$tpls['userNotFoundTpl'] = $modx->getOption('userNotFoundTpl', $scriptProperties, '@INLINE User with email [[+email]] not found.');
$alreadyLoggedInTpl = $modx->getOption('alreadyLoggedInTpl', $scriptProperties, '@INLINE Already logged-in.');
$successfulLoginTpl = $modx->getOption('successfulLoginTpl', $scriptProperties, '@INLINE Successfully logged-in.');
$failedLoginTpl = $modx->getOption('failedLoginTpl', $scriptProperties, '@INLINE Login failed. Please contact the system administrator.');
$logoutParam = $modx->getOption('logoutParam', $scriptProperties, 'logout');
$auth0_redirect_uri = $modx->getOption('redirect_uri', $scriptProperties, $modx->makeUrl($modx->resource->get('id'), '', '', 'full'));
$debug = $modx->getOption('debug', $scriptProperties, false);

// Paths
$auth0Path = $modx->getOption('auth0.core_path', null, $modx->getOption('core_path') . 'components/auth0/');
$auth0Path .= 'model/auth0/';

// Get Class
if (file_exists($auth0Path . 'auth0.class.php')) $auth0 = $modx->getService('auth0', 'Auth0', $auth0Path, [
    'redirect_uri' => $auth0_redirect_uri,
    'requireVerifiedEmail' => $requireVerifiedEmail,
]);
if (!($auth0 instanceof Auth0)) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[auth0.login] could not load the required class on line: ' . __LINE__);
    return;
}

// Setup
if (!$auth0->init()) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[auth0.login] could not setup Auth0 on line: ' . __LINE__);
    return;
}

// Add current context
$loginContexts = $modx->context->key . ',' . $loginContexts;
$loginContexts = $auth0->explodeAndClean($loginContexts);

// If logout param is true
if (!empty($logoutParam) && $_REQUEST[$logoutParam]) {
    // Log the user out
    $auth0->logout();
}

// Normalize loginResourceId
$loginResourceId = abs($loginResourceId);
$loginResourceUrl = ($loginResourceId) ? $modx->makeUrl($loginResourceId) : false;

// If already logged into current context
if ($modx->user->hasSessionContext($modx->context->key)) {
    if ($loginResourceUrl) {
        $modx->sendRedirect($loginResourceUrl);
        return;
    } else {
        return $auth0->getChunk($alreadyLoggedInTpl);
    }
}

// Check login status, redirect to login if no userInfo
$userInfo = $auth0->getUser(true);

// Verify User
$verifiedState = $auth0->verify($userInfo);

if ($verifiedState !== true) {
    return $auth0->getChunk($tpls[$verifiedState . 'Tpl'], $userInfo);
}

// If we got this far, we have a MODX user. Log them in.
$response = $auth0->modxLogin($loginContexts, $userInfo['email']);

// If successful login
if (!empty($response) && !$response->isError()) {
    if ($loginResourceUrl) {
        $modx->sendRedirect($loginResourceUrl);
        return;
    } else {
        return $auth0->getChunk($successfulLoginTpl);
    }
} else {
    return $auth0->getChunk($failedLoginTpl);
}
