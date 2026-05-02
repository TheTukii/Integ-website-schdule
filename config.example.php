<?php

$servername = 'localhost';
$username   = 'root';
$password   = '';
$dbname     = 'database_csf';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die('connection failed' . $conn->connect_error);
}

// Google Sign-In — Authorized redirect URI must match GOOGLE_OAUTH_REDIRECT_URI exactly.
define('GOOGLE_OAUTH_CLIENT_ID', '');
define('GOOGLE_OAUTH_CLIENT_SECRET', '');
define('GOOGLE_OAUTH_REDIRECT_URI', 'http://localhost/Integ-Website-Schdule/google-oauth-callback.php');

// Google reCAPTCHA v2 Checkbox — https://www.google.com/recaptcha/admin — leave empty to disable.
define('RECAPTCHA_SITE_KEY', '');
define('RECAPTCHA_SECRET_KEY', '');
