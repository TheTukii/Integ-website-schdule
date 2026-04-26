<?php
session_start();
// If there's no email in the session, they shouldn't be here
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot-password-email-input-page.php");
    exit;
}

$error = $_SESSION['code_error'] ?? '';
unset($_SESSION['code_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BukSU — Enter Verification Code</title>
  <meta name="description" content="Enter the verification code sent to your email to continue resetting your password.">
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
      <h1 class="hero-title">Check your<br>inbox.</h1>
      <p class="hero-subtitle">We've sent a 6-digit verification code to your email. Enter it here to continue.</p>
    </div>
  </div>

  <!-- RIGHT — CODE INPUT FORM -->
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
          <div class="step active">
            <div class="step-dot">2</div>
            <span>Verify</span>
          </div>
          <div class="step-line"></div>
          <div class="step">
            <div class="step-dot">3</div>
            <span>Reset</span>
          </div>
        </div>

        <h2 class="login-heading">Enter code</h2>
        <p class="login-sub">We sent a verification code to your email</p>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
            <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <!-- FORM -->
        <form id="codeForm" action="code_validate.php" method="POST">

          <div class="input-group">
            <label for="verifyCode">Verification Code</label>
            <input type="text" id="verifyCode" name="verifyCode" class="input-email" placeholder="Enter 6-digit code" maxlength="6" style="letter-spacing: 0.3em; text-align: center; font-weight: 600;" required>
          </div>

          <p class="resend-text">Didn't receive it? <a href="#" id="resendLink">Resend code</a></p>

          <div class="btn-group" style="margin-top: 0.5rem;">
            <button type="submit" class="btn-login" id="verifyBtn">
              <i class="bi bi-shield-check"></i>
              Verify
            </button>

            <a href="forgot-password-email-input-page.php" class="btn-register" id="backBtn">
              <i class="bi bi-arrow-left"></i>
              Go back
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
    if (link.id === 'resendLink') return;
    link.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href && !href.startsWith('#') && !href.startsWith('javascript')) {
            e.preventDefault();
            navigateTo(href);
        }
    });
});

document.getElementById('resendLink').addEventListener('click', function(e) {
    e.preventDefault();
    alert('A new code has been sent to your email!');
});

document.getElementById("codeForm").addEventListener("submit", function(event) {
    let code = document.getElementById("verifyCode").value.trim();
    if (code === "" || code.length < 6) {
        event.preventDefault();
        alert("Please enter the full 6-digit code!");
    }
});
</script>

</body>
</html>