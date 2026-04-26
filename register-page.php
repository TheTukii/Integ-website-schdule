<?php
session_start();

$error   = $_SESSION['register_error']   ?? '';
$success = $_SESSION['register_success'] ?? '';
unset($_SESSION['register_error'], $_SESSION['register_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BukSU — Classroom Finder · Register</title>
  <meta name="description" content="Create your BukSU Classroom Finder account to access room schedules, availability, and reservations.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="includes/css/login-page-css.css">
  <link rel="stylesheet" href="includes/css/register-page-css.css">
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
      <h1 class="hero-title">Start your journey<br>at BukSU.</h1>
      <p class="hero-subtitle">Create an account to find rooms, manage your class schedule, and reserve campus spaces.</p>
    </div>
  </div>

  <!-- RIGHT — REGISTER FORM -->
  <div class="Inner">
    <div class="card-login">
      <div>

        <div class="text-center">
          <img class="login-logo" src="includes/img/Logov2.png" alt="BukSU Logo">
        </div>

        <h2 class="login-heading">Create account</h2>
        <p class="login-sub">Fill in your details to get started</p>

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

        <!-- FORM — posts to register_validate.php -->
        <form id="registerForm" action="register_validate.php" method="POST">

          <div class="input-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" class="input-email" placeholder="Juan Dela Cruz" required>
          </div>

          <div class="input-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="input-email" placeholder="you@buksu.edu.ph" required>
          </div>

          <div class="input-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="input-password" placeholder="Min. 8 characters" required>
          </div>

          <div class="input-group">
            <label for="confirmPassword">Confirm Password</label>
            <input type="password" id="confirmPassword" name="confirmPassword" class="input-password" placeholder="Re-enter your password" required>
          </div>

          <div class="btn-group" style="margin-top: 0.5rem;">
            <button type="submit" class="btn-login" id="registerBtn">
              <i class="bi bi-person-check"></i>
              Register
            </button>

            <div class="separator"><span>already have an account?</span></div>

            <a href="login-page.html" class="btn-register" id="backToLoginBtn">
              <i class="bi bi-box-arrow-in-right"></i>
              Back to Log in
            </a>
          </div>

        </form>

      </div>
    </div>
  </div>

</div>

<!-- CLIENT-SIDE PASSWORD MATCH CHECK -->
<script>
document.getElementById("registerForm").addEventListener("submit", function(e) {
    const pw  = document.getElementById("password").value;
    const cpw = document.getElementById("confirmPassword").value;

    if (pw.length < 8) {
        e.preventDefault();
        alert("Password must be at least 8 characters.");
        return;
    }

    if (pw !== cpw) {
        e.preventDefault();
        alert("Passwords do not match!");
    }
});

// Smooth exit transitions for back-to-login link
document.querySelectorAll('a[href]').forEach(function(link) {
    link.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href && !href.startsWith('#') && !href.startsWith('javascript')) {
            e.preventDefault();
            document.body.classList.add('page-exit');
            setTimeout(() => { window.location.href = href; }, 280);
        }
    });
});
</script>

</body>
</html>