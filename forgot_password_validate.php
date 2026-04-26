<?php
session_start();
require_once 'config.php';

// Include PHPMailer autoloader
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: forgot-password-email-input-page.php");
    exit;
}

$email = trim($_POST['email'] ?? '');

if ($email === '') {
    $_SESSION['forgot_error'] = "Please enter your email address.";
    header("Location: forgot-password-email-input-page.php");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['forgot_error'] = "Please enter a valid email address.";
    header("Location: forgot-password-email-input-page.php");
    exit;
}

// Check if email exists in database
$stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['forgot_error'] = "We couldn't find an account with that email.";
    $stmt->close();
    header("Location: forgot-password-email-input-page.php");
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Generate a 6-digit verification code
$reset_code = sprintf("%06d", mt_rand(1, 999999));

// Store in session for verification
$_SESSION['reset_code'] = $reset_code;
$_SESSION['reset_email'] = $email;

// Send email via PHPMailer
$mail = new PHPMailer(true);

try {
    // Server settings
    // TODO: Update these with your actual SMTP credentials (e.g., Gmail App Password)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';             // Set the SMTP server to send through
    $mail->SMTPAuth   = true;                         // Enable SMTP authentication
    $mail->Username   = 'kobicabunoc5@gmail.com';       // SMTP username
    $mail->Password   = 'lhfp pkks ghbz ggok';          // SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
    $mail->Port       = 587;                          // TCP port to connect to

    // Recipients
    $mail->setFrom('noreply@buksu.edu.ph', 'BukSU Rooms');
    $mail->addAddress($email, $user['name']);         // Add a recipient

    // Content
    $mail->isHTML(true);                              // Set email format to HTML
    $mail->Subject = 'Password Reset Code - BukSU Rooms';
    $mail->Body    = "Hello {$user['name']},<br><br>" .
                     "We received a request to reset your password.<br>" .
                     "Your verification code is: <b style='font-size:24px; letter-spacing:4px;'>{$reset_code}</b><br><br>" .
                     "If you didn't request this, you can safely ignore this email.";
    $mail->AltBody = "Hello {$user['name']},\n\nWe received a request to reset your password.\nYour verification code is: {$reset_code}\n\nIf you didn't request this, you can safely ignore this email.";

    $mail->send();
    
    // Redirect to the code input page
    header("Location: code-input-page.php");
    exit;
} catch (Exception $e) {
    $_SESSION['forgot_error'] = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    header("Location: forgot-password-email-input-page.php");
    exit;
}
