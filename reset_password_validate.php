<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: new-password-page.php");
    exit;
}

// Check if they are verified
if (!isset($_SESSION['reset_email']) || empty($_SESSION['code_verified'])) {
    header("Location: forgot-password-email-input-page.php");
    exit;
}

$new_password = trim($_POST['newPassword'] ?? '');
$confirm_password = trim($_POST['confirmPassword'] ?? '');

if ($new_password === '' || $confirm_password === '') {
    $_SESSION['reset_error'] = "Please fill in both password fields.";
    header("Location: new-password-page.php");
    exit;
}

if ($new_password !== $confirm_password) {
    $_SESSION['reset_error'] = "Passwords do not match.";
    header("Location: new-password-page.php");
    exit;
}

if (strlen($new_password) < 8) {
    $_SESSION['reset_error'] = "Password must be at least 8 characters.";
    header("Location: new-password-page.php");
    exit;
}

$email = $_SESSION['reset_email'];
$hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

// Update password in the database
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $hashed_password, $email);

if ($stmt->execute()) {
    $stmt->close();

    // Mark token as used in the database
    if (isset($_SESSION['reset_email']) && isset($_SESSION['reset_code'])) {
        $updateStmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND token = ?");
        $updateStmt->bind_param("ss", $_SESSION['reset_email'], $_SESSION['reset_code']);
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Clear reset sessions
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_code']);
    unset($_SESSION['code_verified']);
    
    // Set success message for login page
    $_SESSION['register_success'] = "Password reset successfully! You can now log in with your new password.";
    
    header("Location: login-page.php");
    exit;
} else {
    $_SESSION['reset_error'] = "Something went wrong. Please try again.";
    $stmt->close();
    header("Location: new-password-page.php");
    exit;
}
