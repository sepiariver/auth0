<?php
/**
 * auth0.login
 *
 * Login an Auth0 user.
 * Attempts to get user info from Auth0. Redirects to Auth0 login or shows failure content.
 * With Auth0 user info, attempts to establish local session and optionally creates modx user profile.
 *
 * OPTIONS:
 * &loginResourceId -       (int) ID of Resource to redirect user on successful login. Default 0 (no redirect)
 * &loginContexts -         (string) CSV of context keys, to login user (in addition to current context). Default ''
 * &logoutParam -           (string) Key of GET param to trigger logout. Default 'logout'
 * &reverify -              (bool) If true, force check user state and existence in modx DB, regardless of local session. Default false
 * &logoutOnFailedVerification (bool) If true, logout local session and Auth0 if login fails. Else show error/failure content. Default false
 * &additionalParams -      (string) JSON string to pass to Auth0's login method as additionalParameters. Default ''
 * TPLs:
 * &failedLoginTpl -        (string) Chunk TPL to render when login fails. Default '@INLINE ...'
 * &cannotVerifyTpl -       (string) Chunk TPL to render when verification fails. Default '@INLINE ...'
 * &unverifiedEmailTpl -    (string) Chunk TPL to render when unverified email. Default '@INLINE ...'
 * &userNotFoundTpl -       (string) Chunk TPL to render when no MODX user found. Default '@INLINE ...'
 * &alreadyLoggedInTpl -    (string) Chunk TPL to render when MODX user already logged-in. Default '@INLINE ...'
 * &successfulLoginTpl -    (string) Chunk TPL to render when Auth0 login successful. Default '@INLINE ...'
 * &debug -                 (bool) Enable debug output. Default false
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

$corePath = $modx->getOption('auth0.core_path', null, $modx->getOption('core_path', null, MODX_CORE_PATH) . 'components/auth0/');
/** @var Auth0 $auth0 */
$auth0 = $modx->getService('auth0', 'Auth0', $corePath . 'model/auth0/', ['core_path' => $corePath]);

if (!($auth0 instanceof Auth0) || !$auth0->init()) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[auth0.login] could not load the required class on line: ' . __LINE__);
    return;
}

// OPTIONS
$loginResourceId = abs(intval($modx->getOption('loginResourceId', $scriptProperties, 0)));
$loginContexts = $modx->getOption('loginContexts', $scriptProperties, '');
$logoutParam = $modx->getOption('logoutParam', $scriptProperties, 'logout');
$reVerify = $modx->getOption('reverify', $scriptProperties, false);
$logoutOnFailedVerification = $modx->getOption('logoutOnFailedVerification', $scriptProperties, false);
$additionalParams = $modx->fromJSON($modx->getOption('additionalParams', $scriptProperties, ''));

$alreadyLoggedInTpl = $modx->getOption('alreadyLoggedInTpl', $scriptProperties, '@INLINE Already logged-in.');
$failedLoginTpl = $modx->getOption('failedLoginTpl', $scriptProperties, '@INLINE Login failed. Please contact the system administrator.');
$successfulLoginTpl = $modx->getOption('successfulLoginTpl', $scriptProperties, '@INLINE Successfully logged-in.');

$TPLs['cannotVerifyTpl'] = $modx->getOption('cannotVerifyTpl', $scriptProperties, '@INLINE Cannot verify user. Please contact the system administrator.');
$TPLs['unverifiedEmailTpl'] = $modx->getOption('unverifiedEmailTpl', $scriptProperties, '@INLINE Email verification is required.');
$TPLs['userNotFoundTpl'] = $modx->getOption('userNotFoundTpl', $scriptProperties, '@INLINE User with email [[+email]] not found.');

$debug = $modx->getOption('debug', $scriptProperties, false);

// Add current context
$loginContexts = $modx->context->key . ',' . $loginContexts;
$loginContexts = $auth0->explodeAndClean($loginContexts);

// If logout param is present
if (!empty($logoutParam) && !empty($_REQUEST[$logoutParam])) {
    $auth0->logout($loginContexts);
}

$loginResourceUrl = (!empty($loginResourceId)) ? $modx->makeUrl($loginResourceId) : false;

// If already logged into current context
if ($modx->user->hasSessionContext($modx->context->key) && !$reVerify) {
    if ($loginResourceUrl) {
        $modx->sendRedirect($loginResourceUrl);
        return;
    } else {
        return $auth0->getChunk($alreadyLoggedInTpl);
    }
}

if (!is_array($additionalParams)) {
    $additionalParams = [];
}
$loggedIn = $auth0->login($loginContexts, true, $reVerify, $additionalParams);
if ($loggedIn === true) {
    if ($loginResourceUrl) {
        $modx->sendRedirect($loginResourceUrl);
        return;
    } else {
        return $auth0->getChunk($successfulLoginTpl);
    }
}

if ($logoutOnFailedVerification) $auth0->logout($loginContexts);

$userState = $auth0->getUserState();
if ($userState === Auth0::STATE_VERIFIED) {
    return $auth0->getChunk($failedLoginTpl);
}

return $auth0->getChunk($TPLs[$userState . 'Tpl'], $auth0->getUser(false, false));