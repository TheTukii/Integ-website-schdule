<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use League\OAuth2\Client\Provider\Google;

if (GOOGLE_OAUTH_CLIENT_ID === '' || GOOGLE_OAUTH_CLIENT_SECRET === '') {
    $_SESSION['login_error'] = 'Google sign-in is not configured. Add your OAuth client ID and secret in config.php.';
    header('Location: login-page.php');
    exit;
}

$provider = new Google([
    'clientId'     => GOOGLE_OAUTH_CLIENT_ID,
    'clientSecret' => GOOGLE_OAUTH_CLIENT_SECRET,
    'redirectUri'  => GOOGLE_OAUTH_REDIRECT_URI,
]);

$authUrl = $provider->getAuthorizationUrl();
$_SESSION['oauth2state'] = $provider->getState();
header('Location: ' . $authUrl);
exit;
