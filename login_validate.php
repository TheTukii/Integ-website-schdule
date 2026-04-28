<?php
session_start();
require_once 'config.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login-page.php");
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

// Basic validation
if ($email === '' || $password === '') {
    $_SESSION['login_error'] = "Please fill in both email and password.";
    header("Location: login-page.php");
    exit;
}

// Look up user by email
$stmt = $conn->prepare("SELECT id, name, password, role, is_active FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['login_error'] = "Invalid email or password.";
    $stmt->close();
    header("Location: login-page.php");
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!password_verify($password, $user['password'])) {
    $_SESSION['login_error'] = "Invalid email or password.";
    header("Location: login-page.php");
    exit;
}

// Check if account is active
if (!$user['is_active']) {
    $_SESSION['login_error'] = "Your account is not active. Please contact the administrator.";
    header("Location: login-page.php");
    exit;
}

// All good — store session data
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_role'] = $user['role'];
$conn->close();

// Both admin and instructor use the same scheduling map interface
header("Location: comlab-map.php");
exit;
