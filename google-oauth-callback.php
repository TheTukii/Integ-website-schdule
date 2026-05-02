<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use League\OAuth2\Client\Provider\Google;

function oauth_redirect_dashboard(string $role): void
{
    if ($role === 'admin') {
        header('Location: admin-dashboard.php');
        exit;
    }
    if ($role === 'instructor') {
        header('Location: instructor-dashboard.php');
        exit;
    }
    header('Location: student-dashboard.php');
    exit;
}

if (GOOGLE_OAUTH_CLIENT_ID === '' || GOOGLE_OAUTH_CLIENT_SECRET === '') {
    $_SESSION['login_error'] = 'Google sign-in is not configured.';
    header('Location: login-page.php');
    exit;
}

if (isset($_GET['error'])) {
    $_SESSION['login_error'] = 'Google sign-in was cancelled or could not be completed.';
    header('Location: login-page.php');
    exit;
}

if (
    empty($_GET['code'])
    || empty($_GET['state'])
    || empty($_SESSION['oauth2state'])
    || $_GET['state'] !== $_SESSION['oauth2state']
) {
    unset($_SESSION['oauth2state']);
    $_SESSION['login_error'] = 'Invalid sign-in session. Please try again.';
    header('Location: login-page.php');
    exit;
}
unset($_SESSION['oauth2state']);

$provider = new Google([
    'clientId'     => GOOGLE_OAUTH_CLIENT_ID,
    'clientSecret' => GOOGLE_OAUTH_CLIENT_SECRET,
    'redirectUri'  => GOOGLE_OAUTH_REDIRECT_URI,
]);

try {
    $token = $provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
    $owner = $provider->getResourceOwner($token);
} catch (Throwable $e) {
    $_SESSION['login_error'] = 'Could not complete Google sign-in. Please try again.';
    header('Location: login-page.php');
    exit;
}

$email = $owner->getEmail();
if ($email === null || $email === '') {
    $_SESSION['login_error'] = 'No email address was returned from Google.';
    header('Location: login-page.php');
    exit;
}

if ($owner->getEmailVerified() !== true) {
    $_SESSION['login_error'] = 'Your Google email must be verified to sign in.';
    header('Location: login-page.php');
    exit;
}

$data = $owner->toArray();
$name = trim((string)($data['name'] ?? $data['given_name'] ?? ''));
if ($name === '') {
    $name = strstr($email, '@', true) ?: 'User';
}

$stmt = $conn->prepare('SELECT id, name, role, is_active FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $dummyHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
    $role = 'student';
    $ins = $conn->prepare('INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)');
    $ins->bind_param('ssss', $name, $email, $dummyHash, $role);
    if (!$ins->execute()) {
        $ins->close();
        $conn->close();
        $_SESSION['login_error'] = 'Could not create your account. Please try again or contact support.';
        header('Location: login-page.php');
        exit;
    }
    $userId = (int) $conn->insert_id;
    $ins->close();

    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_role'] = $role;
    $conn->close();
    oauth_redirect_dashboard($role);
}

$user = $result->fetch_assoc();
$stmt->close();

if (!(bool) $user['is_active']) {
    $conn->close();
    $_SESSION['login_error'] = 'Your account is not active. Please contact the administrator.';
    header('Location: login-page.php');
    exit;
}

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];
$conn->close();

oauth_redirect_dashboard($user['role']);
