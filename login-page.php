<?php
session_start();
require_once __DIR__ . '/config.php';
$error   = $_SESSION['login_error']   ?? '';
$success = $_SESSION['register_success'] ?? ''; // shown after successful registration
unset($_SESSION['login_error'], $_SESSION['register_success']);
$recaptcha_enabled = RECAPTCHA_SITE_KEY !== '' && RECAPTCHA_SECRET_KEY !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BukSU — Classroom Finder · Log In</title>
  <meta name="description" content="Log in to BukSU Classroom Finder to check room availability, view schedules, and reserve classrooms.">
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
      <h1 class="hero-title">Find your classroom,<br>right on campus.</h1>
      <p class="hero-subtitle">Check real-time room availability, view your daily schedule, and reserve spaces — all in one place.</p>
    </div>
  </div>

  <!-- RIGHT — LOGIN FORM -->
  <div class="Inner">
    <div class="card-login">
      <div>

        <div class="text-center">
          <img class="login-logo" src="includes/img/Logov2.png" alt="BukSU Logo">
        </div>

        <h2 class="login-heading">Welcome back</h2>
        <p class="login-sub">Sign in to your account to continue</p>

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

        <!-- FORM — posts to login_validate.php -->
        <form id="loginForm" action="login_validate.php" method="POST">

          <div class="input-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="input-email" placeholder="you@buksu.edu.ph" required>
          </div>

          <div class="input-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="input-password" placeholder="Enter your password" required>
          </div>

          <div class="forgot-row">
            <a href="forgot-password-email-input-page.php">Forgot password?</a>
          </div>

          <div class="btn-group">
            <button type="submit" class="btn-login" id="loginBtn">
              <i class="bi bi-box-arrow-in-right"></i>
              Log in
            </button>

            <?php if ($recaptcha_enabled): ?>
            <div class="recaptcha-wrap">
              <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8') ?>"></div>
            </div>
            <?php endif; ?>

            <a href="google-oauth-start.php" class="btn-google direct-nav" id="googleSignInBtn">
              <svg class="btn-google-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
              Continue with Google
            </a>

            <div class="separator"><span>or</span></div>

            <a href="register-page.php" class="btn-register" id="registerBtn">
              <i class="bi bi-person-plus"></i>
              Create an account
            </a>
          </div>

        </form>

      </div>
    </div>
  </div>

</div>

<!-- TRANSITION + VALIDATION SCRIPT -->
<script>
const recaptchaRequired = <?= $recaptcha_enabled ? 'true' : 'false' ?>;

// Smooth page-exit transition helper
function navigateTo(url) {
    document.body.classList.add('page-exit');
    setTimeout(function() {
        window.location.href = url;
    }, 280);
}

// Intercept anchor links for smooth transition (skip form targets)
document.querySelectorAll('a[href]').forEach(function(link) {
    link.addEventListener('click', function(e) {
        if (this.classList.contains('direct-nav')) {
            return;
        }
        const href = this.getAttribute('href');
        if (href && !href.startsWith('#') && !href.startsWith('javascript')) {
            e.preventDefault();
            navigateTo(href);
        }
    });
});

// Client-side blank-field guard (server does the real auth)
document.getElementById("loginForm").addEventListener("submit", function(event) {
    const email    = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value.trim();
    if (email === "" || password === "") {
        event.preventDefault();
        alert("Please fill in both email and password!");
        return;
    }
    if (recaptchaRequired && typeof grecaptcha !== "undefined" && grecaptcha.getResponse() === "") {
        event.preventDefault();
        alert("Please complete the CAPTCHA.");
    }
});
</script>
<?php if ($recaptcha_enabled): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>

</body>
</html>
