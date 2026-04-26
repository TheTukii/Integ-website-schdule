<?php
session_start();
// Check if they are verified
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
  <meta name="description" content="Set a new password for your BukSU Classroom Finder account.">
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
      <h1 class="hero-title">Almost there,<br>set a new password.</h1>
      <p class="hero-subtitle">Choose a strong password to keep your account secure. You'll be redirected to log in once done.</p>
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
        <p class="login-sub">Enter and confirm your new password</p>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 px-3 mb-3" role="alert">
            <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <!-- FORM -->
        <form id="newPasswordForm" action="reset_password_validate.php" method="POST">

          <div class="input-group">
            <label for="newPassword">New Password</label>
            <input type="password" id="newPassword" name="newPassword" class="input-password" placeholder="Enter new password" required>
          </div>

          <div class="input-group">
            <label for="confirmPassword">Confirm Password</label>
            <input type="password" id="confirmPassword" name="confirmPassword" class="input-password" placeholder="Re-enter new password" required>
          </div>

          <div class="btn-group" style="margin-top: 0.5rem;">
            <button type="submit" class="btn-login" id="resetBtn">
              <i class="bi bi-check-circle"></i>
              Reset Password
            </button>

            <a href="code-input-page.php" class="btn-register" id="backBtn">
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
    link.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href && !href.startsWith('#') && !href.startsWith('javascript')) {
            e.preventDefault();
            navigateTo(href);
        }
    });
});

document.getElementById("newPasswordForm").addEventListener("submit", function(event) {
    let newPass = document.getElementById("newPassword").value.trim();
    let confirmPass = document.getElementById("confirmPassword").value.trim();

    if (newPass === "" || confirmPass === "") {
        event.preventDefault();
        alert("Please fill in both password fields!");
        return;
    }
    if (newPass !== confirmPass) {
        event.preventDefault();
        alert("Passwords do not match!");
        return;
    }
});
</script>

</body>
</html>