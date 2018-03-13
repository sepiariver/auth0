# Auth0

Auth0 integration for MODX CMS.

## Description
This initial pre-release version is the most basic integration. The auth0.login Snippet calls the Auth0 API using the credentials you provide. Once Auth0 identifies a User by their email address, AND IF a modUser already exists with that email, the auth0.login Snippet adds the chosen MODX Context(s) to the User's session.

As of version 0.5.x, optionally create modUser records and sync with Auth0.

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

@TODO

### MODX Setup

After installing the Auth0 Extra, add the credentials from your Auth0 Client App to the relevant System Settings. They will be under the namespace "auth0".

![system settings](https://www.sepiariver.ca/assets/uploads/images/Screenshot%202018-01-08%2018.33.17.png)

The "audience" setting will be your Auth0 domain `/userinfo`. For example: `https://example.auth0.com/userinfo`

The "redirect_uri" setting will be the Resource on which you call the "auth0.login" Snippet, for example: `http://localhost:3000/login.html`

The "redirect_uri" must be web-accessible, as Auth0 will redirect your Users there. The above is just an example.

Note the setting for "scope" includes `openid profile email address phone`. `openid` and `email` at the very least, are required for MODX to identify your User.

Once configured, your login Resource will either redirect Users to an Auth0 login page, or if they're already logged-in to Auth0 the Snippet will attempt to add the current Context to the User's session.

### auth0.login

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

### auth0.loggedIn

This Snippet tests for logged-in state and provides options for what to render in each scenario:

- &forceLogin -    (bool) Enable/disable forwarding to Auth0 for login if anonymous. &anonymousTpl will not be displayed if this is true. Default true
- &loggedInTpl -   (string) Chunk TPL to render when logged in. Default '@INLINE ...'
- &auth0UserTpl -  (string) Chunk TPL to render when logged into Auth0 but not MODX. Default '@INLINE ...'
- &anonymousTpl -  (string) Chunk TPL to render when not logged in. Default '@INLINE ...'
- &debug -         (bool) Enable debug output. Default false

## Considerations

This Extra is a work-in-progress. The code is managed [on Github](https://github.com/sepiariver/auth0). Feel free to start [Issue threads](https://github.com/sepiariver/auth0) to discuss and contribute to the roadmap.

Some things to consider:

1. A feature to create MODX Users seems a logical next step, but what kind of security implications would there be? A mis-configured Auth0 Client App could let anyone with a Google or Facebook account, create a User on your site. Do we give people enough rope to hang themselves?

2. Auth0 has features that allow the use of arbitrary database stores for provision of identity. Is this a useful feature to integrate?

3. Auth0 has premium features in their paid plans. Are any of those features important for this Extra to support?

Thanks for using MODX and the Auth0 Extra!
