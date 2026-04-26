<?php
session_start();
require_once 'config.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: register-page.php");
    exit;
}

// Sanitize & collect inputs
$name     = trim($_POST['name']     ?? '');
$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');
$confirm  = trim($_POST['confirmPassword'] ?? '');
$role     = $_POST['role'] ?? 'student'; // default role

// --- Basic validation ---
if ($name === '' || $email === '' || $password === '' || $confirm === '') {
    $_SESSION['register_error'] = "Please fill in all fields.";
    header("Location: register-page.php");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['register_error'] = "Please enter a valid email address.";
    header("Location: register-page.php");
    exit;
}

if (strlen($password) < 8) {
    $_SESSION['register_error'] = "Password must be at least 8 characters.";
    header("Location: register-page.php");
    exit;
}

if ($password !== $confirm) {
    $_SESSION['register_error'] = "Passwords do not match.";
    header("Location: register-page.php");
    exit;
}

// Allowed roles
$allowed_roles = ['student', 'instructor', 'admin'];
if (!in_array($role, $allowed_roles)) {
    $role = 'student';
}

// --- Check for duplicate email ---
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $_SESSION['register_error'] = "An account with that email already exists.";
    $stmt->close();
    header("Location: register-page.php");
    exit;
}
$stmt->close();

// --- Insert new user ---
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare(
    "INSERT INTO users (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)"
);
$stmt->bind_param("ssss", $name, $email, $hashed_password, $role);

if ($stmt->execute()) {
    $_SESSION['register_success'] = "Account created! You can now log in.";
    $stmt->close();
    $conn->close();
    header("Location: login-page.php");
    exit;
} else {
    $_SESSION['register_error'] = "Registration failed. Please try again.";
    $stmt->close();
    $conn->close();
    header("Location: register-page.php");
    exit;
}
