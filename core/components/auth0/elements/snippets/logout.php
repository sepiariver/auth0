<?php
/**
 * auth0.logout
 *
 * Logout an Auth0 user
 *
 * OPTIONS:
 * &logoutResourceId -  (int) ID of Resource to redirect user on successful login. Default 0 (no redirect)
 * &logoutContexts -    (string) CSV of context keys, to login user (in addition to current context). Default ''
 * &debug -             (bool) Enable debug output. Default false
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

$logoutResourceId = abs(intval($modx->getOption('logoutResourceId', $scriptProperties, 0)));
$logoutContexts = $modx->getOption('logoutContexts', $scriptProperties, '');
$debug = $modx->getOption('debug', $scriptProperties, false);

// Add current context
$logoutContexts = $modx->context->key . ',' . $logoutContexts;
$logoutContexts = $auth0->explodeAndClean($logoutContexts);

$auth0->logout($logoutContexts);
$logoutResourceUrl = (!empty($logoutResourceId)) ? $modx->makeUrl($logoutResourceId) : false;
$modx->sendRedirect($logoutResourceUrl);
