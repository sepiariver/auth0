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
    const STATE_VERIFIED = 'verified';
    const STATE_USER_NOT_FOUND = 'userNotFound';
    const STATE_UNVERIFIED_EMAIL = 'unverifiedEmail';
    const STATE_CANNOT_VERIFY = 'cannotVerify';
    const SETTING_DOT_REPLACEMENT = '--';

    /** @var modX */
    public $modx = null;

    /** @var string  */
    public $namespace = 'auth0';

    /** @var array */
    public $options = [];

    /** @var \Auth0\SDK\Auth0  */
    protected $api = null;

    /** @var \Auth0\SDK\API\Management */
    protected $managementApi = null;

    /** @var \Auth0\SDK\API\Authentication */
    protected $authApi = null;

    /** @var array */
    protected $userInfo = [];

    /** @var string string */
    protected $userState = '';

    /** @var string string */
    public $domain = '';

    public function __construct(modX &$modx, array $options = array())
    {
        $this->modx =& $modx;

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
            'jwtLeeway' => 60,
            'jwtKeyMinLength' => 32,
        ), $options);

        $this->modx->addPackage('auth0', $this->options['modelPath'], $dbPrefix);
        $this->modx->lexicon->load('auth0:default');

        require_once($this->options['vendorPath'] . 'autoload.php');
    }

    /**
     * Create an Auth0 & Management instance
     */
    public function init()
    {
        try {
            $this->domain = $this->getSystemSetting('domain');
            $customDomain = $this->getSystemSetting('custom_domain');
            if (empty($customDomain)) {
                $customDomain = $this->domain;
            }
            // Configure Auth0 API client
            $config = [
                'domain' => $customDomain,
                'client_id' => $this->getSystemSetting('client_id', ''),
                'client_secret' => $this->getSystemSetting('client_secret', ''),
                'redirect_uri' => $this->getSystemSetting('redirect_uri', ''),
                'audience' => $this->getSystemSetting('audience', ''),
                'scope' => $this->getSystemSetting('scope', 'openid profile email address phone'),
                'persist_id_token' => $this->getSystemSetting('persist_id_token', false),
                'persist_access_token' => $this->getSystemSetting('persist_access_token', true),
                'persist_refresh_token' => $this->getSystemSetting('persist_refresh_token', false),
            ];
            $this->api = new Auth0\SDK\Auth0($config);

            // Configure Management API client
            $this->authApi = new Auth0\SDK\API\Authentication($this->domain, $config['client_id'], $config['client_secret']);
            $credentials = $this->authApi->client_credentials([
                'audience' => 'https://' . $this->domain . '/api/v2/',
                'scope' => 'read:users read:users_app_metadata update:users update:users_app_metadata',
            ]);
            $this->managementApi = new Auth0\SDK\API\Management($credentials['access_token'], $this->domain);

        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, $e->getMessage());
        }

        if (!$this->api instanceof Auth0\SDK\Auth0 || !$this->managementApi instanceof Auth0\SDK\API\Management) {

            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] could not load Auth0\SDK\Auth0!');
            return false;

        }

        return true;
    }

    /**
     * Get USer Info
     * @param bool $forceLogin
     * @param bool $reVerify
     * @return array|false
     */
    public function getUser($forceLogin = false, $reVerify = false, array $additionalParams = [])
    {
        if ($this->userInfo) return $this->userInfo;

        try {
            $userInfo = $this->api->getUser();
            if (empty($userInfo)) {
                if ($forceLogin) {
                    $this->api->login(null, null, $additionalParams);
                    return false;
                }

                return false;
            }

            $this->userInfo = $userInfo;

            $this->getAppMetadata();
            $this->verifyUser($reVerify);

            return $this->userInfo;
        } catch (Exception $e) {
            $this->userState = self::STATE_CANNOT_VERIFY;
            $this->modx->log(modX::LOG_LEVEL_ERROR, $e->getMessage());

            return false;
        }
    }

    /**
     * Get User Info from JWT
     * @param bool $forceLogin
     * @param bool $reVerify
     * @return array|false
     */
    public function getUserFromJWT($jwt)
    {
        // Required
        $key = $this->getSystemSetting('jwt_key');
        if (empty($key) || (strlen($key) < $this->getOption('jwtKeyMinLength')) || empty($jwt)) return false;

        // Check Token
        $xToken = $this->modx->getCount('Auth0XToken', ['x_token' => $jwt]);
        if ($xToken !== 0) {
            $this->modx->log(modX::LOG_LEVEL_INFO, '[Auth0->getUserFromJWT] found x_token: ' . $jwt);
            return false;
        }

        // Decode
        Firebase\JWT\JWT::$leeway = $this->getOption('jwtLeeway');
        try {
            $payload = (array) Firebase\JWT\JWT::decode($jwt, $key, ['HS256']);
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, $e->getMessage());
            return false;
        }

        // Validate
        if (
            !is_array($payload) ||
            empty($payload['email']) ||
            !filter_var($payload['email'], FILTER_VALIDATE_EMAIL) ||
            empty($payload['aud']) ||
            $payload['aud'] !== $this->getOption('client_id') ||
            empty($payload['exp']) ||
            empty($payload['sub'])
        ) {
            $this->modx->log(modX::LOG_LEVEL_WARN, '[Auth0->getUserFromJWT] received an invalid jwt payload.');
            return false;
        }

        // Invalidate Token
        $invalidated = $this->modx->newObject('Auth0XToken');
        $invalidated->fromArray([
            'x_token' => $jwt,
            'timestamp' => time(),
            'expires' => $payload['exp'],
        ]);
        if (!$invalidated->save()) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0->getUserFromJWT] error saving x_token: ' . $jwt);
            return false;
        }

        // Set the userInfo
        $this->userInfo['email'] = $payload['email'];
        $this->userInfo['email_verified'] = true;
        $this->userInfo['sub'] = $payload['sub'];
        return $this->userInfo;
    }

    /**
     * Merge app_metadata to User Info
     */
    protected function getAppMetadata()
    {
        $this->userInfo['app_metadata'] = [];

        $userId = htmlspecialchars_decode($this->userInfo['sub']);
        if (!$userId) return;
        try {
            $data = $this->managementApi->users()->get($userId);
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, $e->getMessage());
        }
        if (empty($data) || !is_array($data)) return;

        $this->userInfo['app_metadata'] = $data['app_metadata'];
    }

    /**
     * Verify User exists in MODX and create one if it doesn't and is allowed by system setting
     *
     * @param bool $reVerify
     * @return string
     */
    public function verifyUser($reVerify = false)
    {
        if (!empty($this->userState) && !$reVerify) return $this->userState;

        // Need user info from Auth0
        if (empty($this->userInfo)) {
            $this->userState = self::STATE_CANNOT_VERIFY;
            return $this->userState;
        }

        // Require email verification
        if (!$this->userInfo['email'] || !$this->userInfo['email_verified']) {

            // Try manually administered app_metadata via Management API
            $emailKey = $this->getOption('metadata_email_key');

            if (!empty($emailKey) && !empty($this->userInfo['app_metadata'][$emailKey])) {
                $metaEmail = filter_var(trim($this->userInfo['app_metadata'][$emailKey]), FILTER_VALIDATE_EMAIL);
                if ($metaEmail) {
                    $this->userInfo['email'] = $metaEmail;
                    $this->userInfo['email_verified'] = 'app_metadata';
                }
            }

            if (!$this->userInfo['email'] || !$this->userInfo['email_verified']) {
                $this->userState = self::STATE_UNVERIFIED_EMAIL;
                return $this->userState;
            }
        }

        // Check MODX User exists
        $userExists = $this->modx->getCount('modUser', [
            'username' => $this->userInfo['email']
        ]);

        if (!$userExists) {
            /** @var \modUserProfile $profile */
            $userExists = $this->modx->getCount('modUserProfile', ['email' => $this->userInfo['email']]);
        }

        if (!$userExists) {
            $createUser = (int)$this->getSystemSetting('create_user');
            if ($createUser === 1) {
                if ($this->createUser()) {
                    $this->userState = self::STATE_VERIFIED;
                    return $this->userState;
                }
            }

            $this->userState = self::STATE_USER_NOT_FOUND;
            return $this->userState;
        }

        $this->userState = self::STATE_VERIFIED;
        return $this->userState;

    }

    /**
     * Creates new User in MODX from Auth0 data
     *
     * @return bool
     */
    protected function createUser()
    {
        /** @var modUser $user */
        $user = $this->modx->newObject('modUser');
        $user->set('username', $this->userInfo['email']);
        $user->set('hash_class', 'auth0hash');
        $user->set('remote_key', $this->userInfo['sub']);
        $user->setSudo(false);

        /** @var modUserProfile $profile */
        $profile = $this->modx->newObject('modUserProfile');
        $profile->set('email', $this->userInfo['email']);

        $user->addOne($profile,'Profile');

        $saved = $user->save();

        if ($saved) {
            $this->pullUserData($user);

            return true;
        }

        return false;
    }

    public function pullUserData($user) {
        try {
            $pullProfile = (int)$this->getSystemSetting('pull_profile');
            $pullSettings = (int)$this->getSystemSetting('pull_settings');
            $syncUserGroups = (int)$this->getSystemSetting('sync_user_groups');

            $data = $this->managementApi->users()->get($this->userInfo['sub']);

            $appMeta = isset($data['app_metadata']) ? $data['app_metadata'] : [];

            if ($pullProfile === 1) {
                $this->pullProfile($user, $appMeta);
            }

            if ($pullSettings === 1) {
                $this->pullSettings($user, $appMeta);
            }

            if ($syncUserGroups === 1) {
                $this->pullUserGroups($user, $appMeta);
            }
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] Failed to pull user data with error: ' . $e->getMessage());
        }
    }

    /**
     * @param modUser $user
     */
    public function pushUserData($user) {
        if (empty($user)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] User not given.');
            return;
        }

        $remoteKey = $user->remote_key;

        if (empty($remoteKey)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] Remote key to set for user: ' . $user->id . '.');
            return;
        }

        $this->handleUserDataPush($remoteKey, $user);
    }

    public function pushCurrentUserData()
    {
        if (empty($this->modx->user)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] Current user is empty.');
            return;
        }

        $remoteKey = $this->modx->user->remote_key;

        if (empty($remoteKey)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] Remote key to set for user: ' . $this->modx->user->id . '.');
            return;
        }

        $this->handleUserDataPush($remoteKey, $this->modx->user);
    }

    protected function handleUserDataPush($id, $user)
    {
        try {
            $pushProfile = (int)$this->getSystemSetting('push_profile');
            $pushSettings = (int)$this->getSystemSetting('push_settings');
            $syncUserGroups = (int)$this->getSystemSetting('sync_user_groups');

            $data = $this->managementApi->users()->get($id);

            $appMeta = isset($data['app_metadata']) ? $data['app_metadata'] : [];

            if ($pushProfile === 1) {
                $appMeta = $this->pushProfile($appMeta, $id, $user);
            }

            if ($pushSettings === 1) {
                $appMeta = $this->pushSettings($appMeta, $id, $user);
            }

            if ($syncUserGroups === 1) {
                $appMeta = $this->pushUserGroups($appMeta, $id, $user);
            }

            $this->managementApi->users()->update($id, ['app_metadata' => $appMeta]);
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[Auth0] Failed to push user data with error: ' . $e->getMessage());
        }
    }

    /**
     * Pulls user profile from Auth0 to the given profile
     *
     * @param modUser $user
     * @param array $appMeta
     */
    protected function pullProfile($user, $appMeta)
    {
        $profile = $user->Profile;
        if (!$profile) return;

        if (empty($appMeta)) return;

        if (!empty($appMeta['profile'])) {
            $profileData = $appMeta['profile'];
            $profile->fromArray($profileData);
        }

        $profile->save();
    }

    /**
     * Pulls user settings from Auth0 to the given user
     *
     * @param modUser $user
     * @param array $appMeta
     */
    protected function pullSettings($user, $appMeta)
    {
        if (!($user instanceof modUser)) return;

        if (empty($appMeta)) return;

        $allowed = $this->getSystemSetting('allowed_pull_setting_keys');
        if (empty($allowed)) return;
        $allowed = $this->explodeAndClean($allowed);
        if (!is_array($allowed)) return;

        if (!empty($appMeta['user_settings'])) {
            foreach ($appMeta['user_settings'] as $key => $fields) {
                $key = $this->prepareKey($key, 'pull');
                if (!in_array($key, $allowed)) continue;
                unset($fields['user'], $fields['key']);
                $c = [
                    'user' => $user->get('id'),
                    'key' => $key
                ];
                $setting = $this->modx->getObject('modUserSetting', $c);
                if (!$setting) {
                    $setting = $this->modx->newObject('modUserSetting');
                    $fields = array_merge($fields, $c);
                }
                $setting->fromArray($fields, '', true); // set PKs
                $setting->save();
            }
        }
    }

    /**
     * Push user profile to the Auth0, if params are not given, current user is used
     *
     * @param array $appMeta
     * @param null|string $id
     * @param null|modUser $user
     * @return array
     */
    protected function pushProfile($appMeta, $id = null, $user = null)
    {
        if (empty($id)) {
            $id = $this->userInfo['sub'];
        }

        if (empty($user)) {
            $user = $this->modx->user;
        }

        $profile = $user->Profile;

        if (!$profile) return $appMeta;
        try {
            $data = $this->managementApi->users()->get($id);
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, $e->getMessage());
        }
        $appMeta = isset($data['app_metadata']) ? $data['app_metadata'] : [];

        $appMeta['profile'] = [
            'fullname' => $profile->fullname,
            'address' => $profile->address,
            'city' => $profile->city,
            'state' => $profile->state,
            'zip' => $profile->zip,
            'country' => $profile->country,
            'mobilephone' => $profile->mobilephone,
            'phone' => $profile->phone,
            'fax' => $profile->fax,
            'website' => $profile->website,
            'gender' => $profile->gender,
            'dob' => $profile->dob,
            'comment' => $profile->comment,
            'photo' => $profile->photo,
        ];

        return $appMeta;
    }

    /**
     * Push user settings to Auth0, if params are not given, current user is used
     *
     * @param array $appMeta
     * @param null|string $id
     * @param null|modUser $user
     * @return array
     */
    protected function pushSettings($appMeta, $id = null, $user = null)
    {
        if (empty($id)) {
            $id = $this->userInfo['sub'];
        }

        if (empty($user)) {
            $user = $this->modx->user;
        }

        if (!$user->id) return $appMeta;

        $allowed = $this->getSystemSetting('allowed_push_setting_keys');
        if (empty($allowed)) return $appMeta;
        $allowed = $this->explodeAndClean($allowed);
        if (!is_array($allowed)) return $appMeta;

        try {
            $data = $this->managementApi->users()->get($id);
            $appMeta = isset($data['app_metadata']) ? $data['app_metadata'] : [];
            $appMeta['user_settings'] = is_array($appMeta['user_settings']) ? $appMeta['user_settings'] : [];

            $settings = $this->modx->getCollection('modUserSetting', [
                'user' => $user->id,
                'key:IN' => $allowed,
            ]);

            foreach ($settings as $setting) {
                $fields = $setting->toArray();
                $key = $this->prepareKey($fields['key'], 'push');
                unset($fields['user'], $fields['key']);
                $appMeta['user_settings'][$key] = $fields;
            }

        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, $e->getMessage());
        }

        return $appMeta;
    }

    /**
     * @param modUser $user
     * @param array $appMeta
     */
    protected function pullUserGroups($user, $appMeta)
    {
        $createUserGroups = (int)$this->getSystemSetting('create_user_groups');

        if (!empty($appMeta['user_groups'])) {
            $groups = $appMeta['user_groups'];
            $currentGroups = $user->getUserGroupNames();
            $currentGroups = array_flip($currentGroups);

            foreach ($groups as $group) {
                unset ($currentGroups[$group['group']]);

                if ($createUserGroups === 1) {
                    $exists = $this->modx->getCount('modUserGroup', [
                        'name' => $group['group']
                    ]);

                    if ($exists === 0) {
                        $newGroup = $this->modx->newObject('modUserGroup');
                        $newGroup->set('name', $group['group']);
                        $newGroup->save();
                    }
                }

                $user->joinGroup($group['group']);
            }

            $currentGroups = array_flip($currentGroups);
            foreach ($currentGroups as $groupToRemove) {
                $user->leaveGroup($groupToRemove);
            }
        }
    }

    /**
     * @param array $appMeta
     * @param null|string $id
     * @param null|modUser $user
     * @return array
     */
    protected function pushUserGroups($appMeta, $id = null, $user = null)
    {
        if (empty($id)) {
            $id = $this->userInfo['sub'];
        }

        if (empty($user)) {
            $user = $this->modx->user;
        }

        $userGroupNames = $user->getUserGroupNames();
        $userGroups = [];

        foreach ($userGroupNames as $group) {
            $userGroups[] = [
                'group' => $group
            ];
        }

        $appMeta['user_groups'] = $userGroups;

        return $appMeta;
    }

    /**
     * Logs in user
     *
     * @param array $loginContexts
     * @param bool $forceLogin
     * @param bool $reVerify
     * @return bool $response
     */
    public function login($loginContexts = [], $forceLogin = true, $reVerify = false, array $additionalParams = [])
    {
        if (!is_array($loginContexts)) {
            $loginContexts = $this->explodeAndClean($loginContexts);
        }
        $this->getUser($forceLogin, $reVerify, $additionalParams);

        if ($this->userState !== self::STATE_VERIFIED) {
            return false;
        }

        $count = $this->modx->getCount('modUserProfile', array(
            'email' => $this->userInfo['email'],
        ));

        if ($count > 1) {
            $criteria = array ('modUser.username' => $this->userInfo['email']);
        } else {
            $criteria = array(
                array('modUser.username' => $this->userInfo['email']),
                array('OR:Profile.email:=' => $this->userInfo['email'])
            );
        }

        /** @var $user modUser */
        $user = $this->modx->getObjectGraph('modUser', '{"Profile":{},"UserSettings":{}}', $criteria);
        if (!$user) return false;

        /** @var modUserProfile $profile */
        $profile = $user->Profile;

        if (empty($user->get('remote_key'))) {
            $user->set('remote_key', $this->userInfo['sub']);
            $user->save();
            $this->pushCurrentUserData();
        } else {
            $this->pullUserData($user);
        }

        if (!$user->get('active')) {
            return false;
        }

        if ($profile->get('failed_logins') >= $this->modx->getOption('failed_login_attempts') &&
            $profile->get('blockeduntil') > time()) {
            return false;
        }

        if ($profile->get('failedlogincount') >= $this->modx->getOption('failed_login_attempts')) {
            $profile->set('failedlogincount', 0);
            $profile->set('blocked', 1);
            $profile->set('blockeduntil', time() + (60 * $this->modx->getOption('blocked_minutes')));
            $profile->save();
        }
        if ($profile->get('blockeduntil') != 0 && $profile->get('blockeduntil') < time()) {
            $profile->set('failedlogincount', 0);
            $profile->set('blocked', 0);
            $profile->set('blockeduntil', 0);
            $profile->save();
        }
        if ($profile->get('blocked')) {
            return false;
        }
        if ($profile->get('blockeduntil') > time()) {
            return false;
        }
        if ($profile->get('blockedafter') > 0 && $profile->get('blockedafter') < time()) {
            return false;
        }

        foreach ($user->UserSettings as $settingPK => $setting) {
            if ($setting->get('key') == 'allowed_ip') {
                $ip = $this->modx->request->getClientIp();
                $ip = $ip['ip'];
                if (!in_array($ip, explode(',', str_replace(' ', '', $setting->get('value'))))) {
                    return false;
                }
            }

            if ($setting->get('key') == 'allowed_days') {
                $date = getdate();
                $day = $date['wday'] + 1;
                if (strpos($setting->get('value'), "{$day}") === false) {
                    return false;
                }
            }
        }

        $lifetime = $this->modx->getOption('session_cookie_lifetime', null, 0);

        foreach ($loginContexts as $context) {
            $user->addSessionContext($context);
            $_SESSION["modx.{$context}.session.cookie.lifetime"] = $lifetime;
        }

        $this->modx->user = $user;
        return true;
    }

    /**
     * Logout
     *
     * @param array $loginContexts
     */
    public function logout($loginContexts = [])
    {
        /** @var modProcessorResponse $response */
        $this->modx->runProcessor('security/logout',array(
            'login_context' => array_shift($loginContexts),
            'add_contexts' => implode(',', $loginContexts),
        ));
        try {
            $this->api->logout();
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, $e->getMessage());
        }
    }

    /**
     * Debugging
     *
     * @param array $properties
     * @return string|void
     */
    public function debug($properties = [])
    {
        $debugInfo = (is_array($properties)) ? print_r($properties, true) : 'Auth0 unknown error on line: ' . __LINE__;
        if ($properties['debug'] === 'log') {
            $this->modx->log(modX::LOG_LEVEL_ERROR, $debugInfo);
            return;
        }
        if ($properties['debug'] === 'print') {
            return "<pre>{$debugInfo}</pre>";
        }
    }

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

    /**
     * Get a namespaced system setting directly from the modSystemSetting table.
     * Does not allow cascading Context, User Group, nor User settings, like the name suggests.
     *
     * @param string $key The option key to search for.
     * @param mixed $default The default value returned if the option is not found as a
     * namespaced system setting; by default this value is ''.
     * @return mixed The option value or the default value specified.
     */
    protected function getSystemSetting($key = '', $default = '')
    {
        if (empty($key)) return $default;
        $query = $this->modx->newQuery('modSystemSetting', [
            'key' => "{$this->namespace}.{$key}",
        ]);
        $query->select('value');
        $value = $this->modx->getValue($query->prepare());
        if ($value === false || $value === null) $value = $default;
        return $value;
    }

    /**
     * Prepare settings to sync with Auth0
     *
     * @param string $string
     * @param string $action ('push'|'pull')
     * @return string
     */
    protected function prepareKey($string, $action)
    {
        $string = (string) $string;
        if ($action === 'push') return str_replace('.', self::SETTING_DOT_REPLACEMENT, $string);
        if ($action === 'pull') return str_replace(self::SETTING_DOT_REPLACEMENT, '.', $string);
        $this->modx->log(modX::LOG_LEVEL_DEBUG, '[Auth0->prepareKey] invalid action.');
        return '';
    }

    /**
     * Transforms a string to an array with removing duplicates and empty values
     *
     * @param $string
     * @param string $delimiter
     * @return array
     */
    public function explodeAndClean($string, $delimiter = ',')
    {
        $string = (string) $string;
        $array = explode($delimiter, $string);    // Explode fields to array
        $array = array_map('trim', $array);       // Trim array's values
        $array = array_keys(array_flip($array));  // Remove duplicate fields
        $array = array_filter($array);            // Remove empty values from array

        return $array;
    }

    /**
     * Processes a chunk or given string
     *
     * @param string $tpl
     * @param array $phs
     * @return string
     */
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

    /**
     * Returns current user's state
     *
     * @return string
     */
    public function getUserState()
    {
        return $this->userState;
    }
}
