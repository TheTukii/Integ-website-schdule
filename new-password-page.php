<?php
session_start();
// If they haven't verified the code, they shouldn't be here
if (!isset($_SESSION['reset_email']) || empty($_SESSION['code_verified'])) {
    header("Location: forgot-password-email-input-page.php");
    exit;
}

$error = $_SESSION['reset_error'] ?? '';
unset($_SESSION['reset_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BukSU — Create New Password</title>
  <meta name="description" content="Create a new password for your BukSU Rooms account.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="includes/css/login-page-css.css">
</head>
<body>

<div class="page-container">

  <!-- LEFT — HERO PANEL -->
  <div class="card-side">
    <div>
      <img class="welcome-image" src="includes/img/Welcome_Classroom.png" alt="BukSU Campus">
    </div>
    <div class="hero-content">
      <div class="hero-brand">
        <div class="logo-mark">Bu</div>
        <span>BukSU Rooms</span>
      </div>
      <h1 class="hero-title">Create a<br>new password.</h1>
      <p class="hero-subtitle">Your new password must be different from previous used passwords and at least 8 characters long.</p>
    </div>
  </div>

  <!-- RIGHT — NEW PASSWORD FORM -->
  <div class="Inner">
    <div class="card-login">
      <div>

        <div class="text-center">
          <img class="login-logo" src="includes/img/Logov2.png" alt="BukSU Logo">
        </div>

        <!-- Step indicator -->
        <div class="step-indicator">
          <div class="step completed">
            <div class="step-dot"><i class="bi bi-check2"></i></div>
            <span>Email</span>
          </div>
          <div class="step-line filled"></div>
          <div class="step completed">
            <div class="step-dot"><i class="bi bi-check2"></i></div>
            <span>Verify</span>
          </div>
          <div class="step-line filled"></div>
          <div class="step active">
            <div class="step-dot">3</div>
            <span>Reset</span>
          </div>
        </div>

        <h2 class="login-heading">New password</h2>
        <p class="login-sub">Please enter your new password</p>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
            <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <!-- FORM -->
        <form id="newPasswordForm" action="reset_password_validate.php" method="POST">

          <div class="input-group">
            <label for="newPassword">New Password</label>
            <div class="position-relative">
              <input type="password" id="newPassword" name="newPassword" class="input-email w-100" placeholder="••••••••" minlength="8" required>
              <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-decoration-none text-muted" onclick="togglePassword('newPassword', 'toggleNewIcon')">
                <i class="bi bi-eye-slash" id="toggleNewIcon"></i>
              </button>
            </div>
          </div>

          <div class="input-group mt-3">
            <label for="confirmPassword">Confirm Password</label>
            <div class="position-relative">
              <input type="password" id="confirmPassword" name="confirmPassword" class="input-email w-100" placeholder="••••••••" minlength="8" required>
              <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-decoration-none text-muted" onclick="togglePassword('confirmPassword', 'toggleConfirmIcon')">
                <i class="bi bi-eye-slash" id="toggleConfirmIcon"></i>
              </button>
            </div>
          </div>

          <div class="btn-group" style="margin-top: 1.5rem;">
            <button type="submit" class="btn-login" id="resetBtn">
              <i class="bi bi-check2-circle"></i>
              Reset Password
            </button>
          </div>

        </form>

      </div>
    </div>
  </div>

</div>

<!-- TRANSITION + VALIDATION SCRIPT -->
<script>
function navigateTo(url) {
    document.body.classList.add('page-exit');
    setTimeout(function() { window.location.href = url; }, 280);
}

function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    }
}

document.getElementById("newPasswordForm").addEventListener("submit", function(event) {
    let newPass = document.getElementById("newPassword").value.trim();
    let confirmPass = document.getElementById("confirmPassword").value.trim();
    
    if (newPass.length < 8) {
        event.preventDefault();
        alert("Password must be at least 8 characters long.");
        return;
    }
    
    if (newPass !== confirmPass) {
        event.preventDefault();
        alert("Passwords do not match.");
        return;
    }
});
</script>

</body>
</html>
