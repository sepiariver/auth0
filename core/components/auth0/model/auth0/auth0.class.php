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
    public $api = null;

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
            'processorsPath' => $corePath . 'processors/',
            'chunksPath' => $corePath . 'elements/chunks/',
            'snippetsPath' => $corePath . 'elements/snippets/',
            'templatesPath' => $corePath . 'templates/',
            'assetsPath' => $assetsPath,
            'assetsUrl' => $assetsUrl,
            'jsUrl' => $assetsUrl . 'js/',
            'cssUrl' => $assetsUrl . 'css/',
            'connectorUrl' => $assetsUrl . 'connector.php',
            'auth0' => array(
                'domain' => $this->getOption('domain', $options, ''),
                'client_id' => $this->getOption('client_id', $options, ''),
                'client_secret' => $this->getOption('client_secret', $options, ''),
                'redirect_uri' => $this->getOption('redirect_uri', $options, ''),
                'audience' => $this->getOption('audience', $options, ''),
                'scope' => $this->getOption('scope', $options, 'openid profile email address phone'),
                'persist_id_token' => $this->getOption('persist_id_token', $options, false),
                'persist_access_token' => $this->getOption('persist_access_token', $options, false),
                'persist_refresh_token' => $this->getOption('persist_refresh_token', $options, false),
            ),

        ), $options);

        $this->modx->lexicon->load('auth0:default');

        // Load Auth0
        require_once($this->options['vendorPath'] . 'autoload.php');
        $this->init();

    }

    /**
     * Create an Auth0 instance
     *
     */
    private function init()
    {

        // Init Auth0
        $this->api = new Auth0\SDK\Auth0($this->options['auth0']);
        if (!$this->api instanceof Auth0\SDK\Auth0) {

            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] could not load Auth0\SDK\Auth0!');
            return null;

        }

    }

    /**
     * Login MODX User
     * WARNING: Logs-in any active, unblocked modUser WITHOUT A PASSWORD!
     */
    public function modxLogin($loginContexts = [], $username = '')
    {
        $properties = array(
            'login_context' => array_shift($loginContexts),
            'add_contexts'  => implode(',', $loginContexts),
            'username'      => $username,
        );
        $processorsPath = $this->getOption('processorsPath');
        return $this->modx->runProcessor('auth0bypassloginprocessor', $properties, array('processors_path' => $processorsPath));
    }

    /**
     * Logout MODX User
     *
     */
    public function modxLogout($loginContexts = [])
    {
        /* send to logout processor and handle response for each context */
        /** @var modProcessorResponse $response */
        $response = $this->modx->runProcessor('security/logout',array(
            'login_context' => array_shift($loginContexts),
            'add_contexts' => implode(',', $loginContexts),
        ));
        /* if successful logout */
        if (!empty($response) && !$response->isError()) {
        /* logout failed, output error */
        } else {
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

    public function getOption($key = '', $options = [], $default = null)
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

    public function explodeAndClean($array = [], $delimiter = ',')
    {
        $array = explode($delimiter, $array);     // Explode fields to array
        $array = array_map('trim', $array);       // Trim array's values
        $array = array_keys(array_flip($array));  // Remove duplicate fields
        $array = array_filter($array);            // Remove empty values from array

        return $array;
    }

    public function getChunk($tpl = '', $phs = [])
    {
        if (empty($tpl)) return '';
        if (!is_array($phs)) $phs = [];
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
