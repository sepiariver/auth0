# Auth0

Auth0 integration for MODX CMS.

## What does it do?

- Log in to MODX using any/all of the Identify Providers (IdPs) supported by Auth0, such as Google, Facebook, Twitter, Github, Microsoft, Dropbox, and [dozens of others](https://auth0.com/docs/identityproviders), including enterprise services.
- Synchronize MODX User records, and User Groups, across multiple MODX sites and the Auth0 User database.
- Log in to MODX with a one-time-use JWT.
- Practically any of the [features and use cases](https://auth0.com/docs/getting-started/overview) that Auth0 supports.

In its most basic implementation, the auth0.login Snippet redirects the User to your Auth0 domain's login page, then calls the Auth0 API to identify the User by their verified email address. If a MODX User record exists with that email, the auth0.login Snippet attempts to verify the User against the MODX User records, and if successful, adds the MODX Context(s) specified in the Snippet properties, to the User's session.

You can read the blog post [here](https://www.sepiariver.ca/blog/modx-web/auth0-for-modx-cms/).

## Installation

Install via MODX Extras Installer. Or you can download from the [_packages](_packages) directory.

## Usage

### Auth0 Client Configuration

Go to [auth0.com](https://auth0.com) and register an account. A generous "free" plan is available.

Once signed-in to the dashboard at [https://manage.auth0.com/](https://manage.auth0.com/) click the "New Client" button.

![new client](https://www.sepiariver.ca/assets/uploads/images/Screenshot%202018-01-08%2018.25.37.png)

Give your Client App a name, select the option "Regular Web Applications" and click "Create".

![create client](https://www.sepiariver.ca/assets/uploads/images/Screenshot%202018-01-08%2018.27.32.png)

In the "Settings" tab of your new Client App, copy the "Domain", "Client ID" and "Client Secret" into the relevant System Settings in your MODX install. (See below under "MODX Setup")

![credentials](https://www.sepiariver.ca/assets/uploads/images/Screenshot%202018-01-08%2018.29.10.png)

Scroll down the Client App settings view to configure at least one "Allowed Callback URL". This is usually the URL of the Resource on which you call the "auth0.login" Snippet.

![url configs](https://www.sepiariver.ca/assets/uploads/images/Screenshot%202018-01-08%2018.36.10.png)

Other configs are optional. Scroll to the bottom and click "Save Changes".

Next, choose the "Connections" tab and ensure you have at least one identity provider selected.

![ID providers](https://www.sepiariver.ca/assets/uploads/images/Screenshot%202018-01-08%2018.38.01.png)

This should complete the Auth0 Client App setup.

### Auth0 Management API Setup

To enable the user profile sync features, you must authorize your Auth0 Client App to access the Auth0 Management API. Go to the APIs section in your [Auth0 Dashboard](https://manage.auth0.com/). Edit the Auth0 Management API settings, and in the "Non Interactive Clients" tab, authorize the Auth0 Client App that you intend to integrate with MODX. Be sure to grant the Client App the following scopes: `read:users update:users read:users_app_metadata update:users_app_metadata`.

### MODX System Settings

#### Area: Auth0

After installing the Auth0 Extra, add the credentials from your Auth0 Client App to the relevant System Settings: "client_id", "client_secret", and "domain". All System Settings will be under the namespace "auth0".

![system settings](https://www.sepiariver.ca/assets/uploads/images/Screenshot%202018-01-08%2018.33.17.png)

The "audience" setting will be your Auth0 domain `/userinfo`. For example: `https://example.auth0.com/userinfo`

The "redirect_uri" setting will be the Resource on which you call the "auth0.login" Snippet, for example: `http://localhost:3000/login.html`

The "redirect_uri" must be web-accessible, as Auth0 will redirect your Users there. The above is just an example.

Note the setting for "scope" includes `openid profile email address phone`. `openid` and `email` at the very least, are required for MODX to identify your User.

If the "jwt_key" setting is set, logging in via JSON Web Token is enabled, at the URL of any Resource where you call the auth0.JWTLogin Snippet. Please see the section below for **important information** about the use of auth0.JWTLogin.

The "metadata_email_key" is the key of the property in the app_metadata object provided by Auth0, that can serve as an email address "override", for some IdPs that do not supply a verified email. Using this feature would require an Admin of the Auth0 domain, manually add the email address to the User record via the Auth0 Dashboard, for those who log in with such IdPs. It's not scalable, but if you need to get someone access, pronto, and they can only use one of "those" IdPs, it's a functional workaround.

System Settings "persist_id_token", "persist_access_token", and "persist_refresh_token" simply expose the settings of the same name in the Auth0 SDK, but are not implemented at this time. Recommended value for these settings is `false`.

#### Area: Synchronization

The following boolean flags enable or disable synchronization features. **Be careful** when enabling these! It means you trust your Auth0 domain's user registration logic completely.

- "create_user" enables creation of new MODX User records from Auth0 records.  Newly created Users will not be able to login via MODX, and **MUST** login via Auth0, due to the custom Auth0 hash_class set for such Users. Without any of the following flags enabled, the User would not be added to any User Groups.
- "sync_user_groups" enables two-way syncing of User Group names between MODX and Auth0. Names of User Groups to sync, must be stored in the "user_groups" property of the User's "app_metadata" object in Auth0.
- "create_user_groups" enables the creation of User Groups in MODX from Auth0 records, when such User Groups currently do not exist in MODX. The MODX User will be adjoined to these User Groups.
- "pull_profile" enables updating a MODX User record with data from the "profile" key of the Auth0 User Record's "app_metadata" object.
- "push_profile" enables updating the "profile" key of the Auth0 User record's "app_metadata" object, with data from the MODX User record.

These synchronization features are useful for cases where the same User Permissions schemes should be synchronized across multiple MODX installs, that are integrated with the same Auth0 tenant domain. Coupled with the JWT login flow, you can deliver a seamless SSO experience for User across such MODX sites.

### Snippet: auth0.login

This Snippet has the following options:

- &loginResourceId -       (int) ID of Resource to redirect user on successful login. Default 0 (no redirect)
- &loginContexts -         (string) CSV of context keys, to login user (in addition to current context). Default ''
- &requireVerifiedEmail -  (bool) Require verified_email from ID provider. Default true
- &unverifiedEmailTpl -    (string) Chunk TPL to render when unverified email. Default '@INLINE ...'
- &userNotFoundTpl -       (string) Chunk TPL to render when no MODX user found. Default '@INLINE ...'
- &alreadyLoggedInTpl -    (string) Chunk TPL to render when MODX user already logged-in. Default '@INLINE ...'
- &successfulLoginTpl -    (string) Chunk TPL to render when Auth0 login successful. Default '@INLINE ...'
- &logoutParam -           (string) Key of GET param to trigger logout. Default 'logout'
- &redirect_uri -          (string) Auth0 redirect URI. Default {current Resource's URI}
- &debug -                 (bool) Enable debug output. Default false

### Snippet: auth0.loggedIn

Tests for logged-in state and provides options for what to render in each scenario:

- &forceLogin -    (bool) Enable/disable forwarding to Auth0 for login if anonymous. &anonymousTpl will not be displayed if this is true. Default true
- &loggedInTpl -   (string) Chunk TPL to render when logged in. Default '@INLINE ...'
- &auth0UserTpl -  (string) Chunk TPL to render when logged into Auth0 but not MODX. Default '@INLINE ...'
- &anonymousTpl -  (string) Chunk TPL to render when not logged in. Default '@INLINE ...'
- &debug -         (bool) Enable debug output. Default false

### Snippet: auth0.JWTLogin

Logs a user in with a JWT token. IMPORTANT: this Snippet "trusts" any JWT token signed with the value in the `auth0.jwt_key` System Setting. The Auth0 class has a config option for the minimum length of this key. It should be as cryptographically strong as feasible, because the "trust" in the token it signs, is nearly complete.

The JWT payload must have the following claims:
- 'email' must be a valid email address, of an existing MODX User. If a MODX User doesn't exist and the `auth0.create_user` System Setting is enabled, it will create one with this email.
- 'sub' expects a `user_id` from Auth0, but it could be any string to be used as the `remote_key` for the MODX User
- 'exp' must be a valid UNIX timestamp, after which the token will be invalid
- 'aud' must equal the value of the `auth0.client_id` System Setting. This is the only other defense against a compromised `auth0.jwt_key`.

If the above conditions are met in the payload, the Snippet will process the contents of the payload as trusted.

- &loginContexts (string) CSV of context keys, to login user (in addition to current context). Default ''
- &continueAuth0 (bool) enable redirecting to Auth0's "continue" endpoint. For use with [Auth0's Redirect from Rules](https://auth0.com/docs/rules/current/redirect) feature. Default true
- &errorTpl (string) Chunk name or '@INLINE' TPL to use when the Snippet encounters an error. Default ''.
- &successTpl (string) Chunk name or '@INLINE' TPL to use when the Snippet logs in successfully, and `continueAuth0` is false. Default ''.

### Plugin: auth0

Pushes user data to Auth0 if the relevant System Settings are enabled.

## Considerations

This Extra is a work-in-progress. The code is managed [on Github](https://github.com/sepiariver/auth0). Feel free to start [Issue threads](https://github.com/sepiariver/auth0) to discuss and contribute to the roadmap.

Some things to consider:

1. Auth0 has features that allow the use of arbitrary database stores for provision of identity. Is this a useful feature to integrate?

2. Auth0 has premium features in their paid plans. Are any of those features important for this Extra to support?

Thanks to @theboxer for all his invaluable advice and guidance. This Extra wouldn't be possible without his input.

Thanks to you, for using MODX and the Auth0 Extra :D 
