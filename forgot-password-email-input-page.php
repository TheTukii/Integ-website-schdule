<?php
session_start();
$error = $_SESSION['forgot_error'] ?? '';
$success = $_SESSION['forgot_success'] ?? '';
unset($_SESSION['forgot_error'], $_SESSION['forgot_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BukSU — Forgot Password</title>
  <meta name="description" content="Reset your BukSU Classroom Finder password. Enter your email to receive a verification code.">
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
      <h1 class="hero-title">Don't worry,<br>we've got you.</h1>
      <p class="hero-subtitle">Enter your email and we'll send a code to help you reset your password and get back in.</p>
    </div>
  </div>

  <!-- RIGHT — FORGOT PASSWORD FORM -->
  <div class="Inner">
    <div class="card-login">
      <div>

        <div class="text-center">
          <img class="login-logo" src="includes/img/Logov2.png" alt="BukSU Logo">
        </div>

        <!-- Step indicator -->
        <div class="step-indicator">
          <div class="step active">
            <div class="step-dot">1</div>
            <span>Email</span>
          </div>
          <div class="step-line"></div>
          <div class="step">
            <div class="step-dot">2</div>
            <span>Verify</span>
          </div>
          <div class="step-line"></div>
          <div class="step">
            <div class="step-dot">3</div>
            <span>Reset</span>
          </div>
        </div>

        <h2 class="login-heading">Forgot password</h2>
        <p class="login-sub">Enter your email to receive a reset code</p>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
            <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div class="alert alert-success py-2 px-3 mb-3" role="alert">
            <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
          </div>
        <?php endif; ?>

        <!-- FORM -->
        <form id="forgotForm" action="forgot_password_validate.php" method="POST">

          <div class="input-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" class="input-email" placeholder="you@buksu.edu.ph" required>
          </div>

          <div class="btn-group" style="margin-top: 0.5rem;">
            <button type="submit" class="btn-login" id="sendCodeBtn">
              <i class="bi bi-envelope"></i>
              Send Code
            </button>

            <div class="separator"><span>or</span></div>

            <a href="login-page.php" class="btn-register" id="backToLoginBtn">
              <i class="bi bi-arrow-left"></i>
              Back to Log in
            </a>
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

document.querySelectorAll('a[href]').forEach(function(link) {
    link.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href && !href.startsWith('#') && !href.startsWith('javascript')) {
            e.preventDefault();
            navigateTo(href);
        }
    });
});

document.getElementById("forgotForm").addEventListener("submit", function(event) {
    let email = document.getElementById("email").value.trim();
    if (email === "") {
        event.preventDefault();
        alert("Please enter your email address!");
    }
});
</script>

</body>
</html>