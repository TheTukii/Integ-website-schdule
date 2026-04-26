<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: code-input-page.php");
    exit;
}

$submitted_code = trim($_POST['verifyCode'] ?? '');
$session_code = $_SESSION['reset_code'] ?? null;

if (!$session_code) {
    // Session expired or they bypassed the first step
    $_SESSION['forgot_error'] = "Your session has expired. Please try again.";
    header("Location: forgot-password-email-input-page.php");
    exit;
}

if ($submitted_code === '') {
    $_SESSION['code_error'] = "Please enter the verification code.";
    header("Location: code-input-page.php");
    exit;
}

if ($submitted_code === $session_code) {
    // Code matches! Set a flag so new-password-page.php knows they are verified.
    $_SESSION['code_verified'] = true;
    header("Location: new-password-page.php");
    exit;
} else {
    $_SESSION['code_error'] = "The code you entered is incorrect. Please try again.";
    header("Location: code-input-page.php");
    exit;
}
