<?php

/**
 * Auth0 class for MODX.
 * @package Auth0
 *
 * @author @sepiariver <info@sepiariver.com>
 * Copyright 2017 by YJ Tso
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

class Auth0
{
    public $modx = null;
    public $namespace = 'auth0';
    public $options = array();

    public function __construct(modX &$modx, array $options = array())
    {
        $this->modx =& $modx;
        $this->namespace = $this->getOption('namespace', $options, 'auth0');

        $corePath = $this->getOption('core_path', $options, $this->modx->getOption('core_path', null, MODX_CORE_PATH) . 'components/auth0/');
        $assetsPath = $this->getOption('assets_path', $options, $this->modx->getOption('assets_path', null, MODX_ASSETS_PATH) . 'components/auth0/');
        $assetsUrl = $this->getOption('assets_url', $options, $this->modx->getOption('assets_url', null, MODX_ASSETS_URL) . 'components/auth0/');
        $dbPrefix = $this->getOption('table_prefix', $options, $this->modx->getOption('table_prefix', null, 'modx_'));

        /* load config defaults */
        $this->options = array_merge(array(
            'namespace' => $this->namespace,
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'vendorPath' => $corePath . 'model/vendor/',
            'chunksPath' => $corePath . 'elements/chunks/',
            'snippetsPath' => $corePath . 'elements/snippets/',
            'templatesPath' => $corePath . 'templates/',
            'assetsPath' => $assetsPath,
            'assetsUrl' => $assetsUrl,
            'jsUrl' => $assetsUrl . 'js/',
            'cssUrl' => $assetsUrl . 'css/',
            'connectorUrl' => $assetsUrl . 'connector.php',
            'defaults' => array(
                'domain' => $this->getOption('domain', $options, ''),
                'client_id' => $this->getOption('client_id', $options, ''),
                'client_secret' => $this->getOption('client_secret', $options, ''),
                'redirect_uri' => $this->getOption('redirect_uri', $options, ''),
                'audience' => $this->getOption('audience', $options, ''),
                'scope' => 'openid profile',
                'persist_id_token' => false,
                'persist_access_token' => false,
                'persist_refresh_token' => false,
            ),

        ), $options);

        /* load table names for OAuth2 PDO driver */
        $this->tablenames = array(
            'client_table' => $dbPrefix . 'auth0_clients',
            'access_token_table' => $dbPrefix . 'auth0_access_tokens',
            'refresh_token_table' => $dbPrefix . 'auth0_refresh_tokens',
            'code_table' => $dbPrefix . 'auth0_authorization_codes',
            'jwt_table'  => $dbPrefix . 'auth0_jwt',
            'scope_table'  => $dbPrefix . 'auth0_scopes',
        );

        $this->modx->addPackage('auth0', $this->options['modelPath'], $this->modx->config['table_prefix']);
        $this->modx->lexicon->load('auth0:default');

        // Load OAuth2
        require_once($this->options['oauth2Path'] . 'Autoloader.php');
        OAuth2\Autoloader::register();

    }

    /**
     * Create an OAuth2 Server
     *
     */
    public function createServer()
    {

        // Init storage
        $storage = new OAuth2\Storage\Pdo($this->modx->config['connections'][0], $this->tablenames);
        if (!$storage instanceof OAuth2\Storage\Pdo) {

            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] could not load a valid storage class!');
            return null;

        }
        // Init server
        $server = new OAuth2\Server($storage, $this->options['server']);

        if (!$server instanceof OAuth2\Server) {

            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] could not load a valid server class!');
            return null;

        }

        // Supported Grant Types
        $server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage, $this->options['server']));
        $server->addGrantType(new OAuth2\GrantType\RefreshToken($storage, $this->options['server']));
        $server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage, $this->options['server']));

        return $server;

    }

    /**
     * Create an OAuth2 Request Object
     *
     */
    public function createRequest() {

        $request = OAuth2\Request::createFromGlobals();
        if ((!$request instanceof OAuth2\Request)) {

            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] could not create a valid request object!');
            return null;

        }
        return $request;

    }

    /**
     * Create an OAuth2 Response Object
     *
     */
    public function createResponse() {

        $response = new OAuth2\Response();
        if ((!$response instanceof OAuth2\Response)) {

            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] could not create a valid response object!');
            return null;

        }
        return $response;

    }

    /**
     * Send unauthorized without redirect, and exit.
     *
     */
    public function sendUnauthorized($exit = true) {
        if (!$exit) {
            $this->modx->sendUnauthorizedPage();
        } else {
            header('HTTP/1.1 401 Unauthorized');
            @session_write_close();
            exit(0);
        }
    }


    /* UTILITY METHODS (@theboxer) */
    /**
     * Get a local configuration option or a namespaced system setting by key.
     *
     * @param string $key The option key to search for.
     * @param array $options An array of options that override local options.
     * @param mixed $default The default value returned if the option is not found locally or as a
     * namespaced system setting; by default this value is null.
     * @return mixed The option value or the default value specified.
     */
    public function getOption($key, $options = array(), $default = null)
    {
        $option = $default;
        if (!empty($key) && is_string($key)) {
            if ($options != null && array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif (array_key_exists($key, $this->options)) {
                $option = $this->options[$key];
            } elseif (array_key_exists("{$this->namespace}.{$key}", $this->modx->config)) {
                $option = $this->modx->getOption("{$this->namespace}.{$key}");
            }
        }
        return $option;
    }

    public function explodeAndClean($array, $delimiter = ',')
    {
        $array = explode($delimiter, $array);     // Explode fields to array
        $array = array_map('trim', $array);       // Trim array's values
        $array = array_keys(array_flip($array));  // Remove duplicate fields
        $array = array_filter($array);            // Remove empty values from array

        return $array;
    }
    public function getChunk($tpl, $phs)
    {
        if (strpos($tpl, '@INLINE ') !== false) {
            $content = str_replace('@INLINE', '', $tpl);
            /** @var \modChunk $chunk */
            $chunk = $this->modx->newObject('modChunk', array('name' => 'inline-' . uniqid()));
            $chunk->setCacheable(false);

            return $chunk->process($phs, $content);
        }

        return $this->modx->getChunk($tpl, $phs);
    }
}
