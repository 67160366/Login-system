<?php require __DIR__ . '/config_mysqli.php'; require __DIR__ . '/csrf.php'; ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign up</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { min-height: 100vh; display:flex; align-items:center; }
    .register-card { max-width: 420px; width: 100%; }
  </style>
</head>
<body class="bg-light">
  <main class="container d-flex justify-content-center">
    <div class="card shadow-sm register-card p-3 p-md-4">
      <div class="card-body">
        <h1 class="h4 mb-3 text-center">Create Account ✨</h1>

        <?php if (!empty($_SESSION['flash'])): ?>
          <div class="alert alert-danger py-2"><?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
        <?php endif; ?>

        <form method="post" action="register_process.php" novalidate>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <div class="mb-3">
            <label class="form-label" for="display_name">Display Name</label>
            <input class="form-control" type="text" id="display_name" name="display_name" placeholder="Your Name" required value="<?php echo isset($_SESSION['old']['display_name']) ? htmlspecialchars($_SESSION['old']['display_name']) : '' ;?>">
          </div>
          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" placeholder="you@example.com" required value="<?php echo isset($_SESSION['old']['email']) ? htmlspecialchars($_SESSION['old']['email']) : '' ;?>">
          </div>
          <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" type="password" id="password" name="password" placeholder="••••••••" required minlength="6">
          </div>
          <div class="mb-3">
            <label class="form-label" for="confirm_password">Confirm Password</label>
            <input class="form-control" type="password" id="confirm_password" name="confirm_password" placeholder="••••••••" required>
          </div>
          <div class="d-grid mt-3">
            <button class="btn btn-primary" type="submit">Create Account</button>
          </div>
        </form>

        <p class="text-center text-muted mt-3 mb-0 small">
          Already have an account? <a href="login.php" class="text-decoration-none">Sign in</a>
        </p>
      </div>
    </div>
  </main>
  <?php unset($_SESSION['old']); ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // ตรวจสอบข้อมูลและแสดงแจ้งเตือน
    function validateForm() {
      const displayName = document.getElementById('display_name').value.trim();
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      
      let errors = [];
      
      // ตรวจสอบข้อมูลครบถ้วน
      if (!displayName) errors.push('Display name is required');
      if (!email) errors.push('Email is required');
      if (!password) errors.push('Password is required');
      if (!confirmPassword) errors.push('Confirm password is required');
      
      // ตรวจสอบความยาวรหัสผ่าน
      if (password && password.length < 6) {
        errors.push('Password must be at least 6 characters long');
      }
      
      // ตรวจสอบรหัสผ่านตรงกัน
      if (password && confirmPassword && password !== confirmPassword) {
        errors.push('Passwords do not match');
      }
      
      // ตรวจสอบรูปแบบอีเมล
      if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.push('Please enter a valid email address');
      }
      
      // แสดงข้อผิดพลาด
      if (errors.length > 0) {
        showAlert(errors.join('<br>'), 'danger');
        return false;
      }
      
      return true;
    }
    
    // แสดงแจ้งเตือน
    function showAlert(message, type = 'danger') {
      // ลบแจ้งเตือนเก่าถ้ามี
      const existingAlert = document.querySelector('.alert');
      if (existingAlert) {
        existingAlert.remove();
      }
      
      // สร้างแจ้งเตือนใหม่
      const alertDiv = document.createElement('div');
      alertDiv.className = `alert alert-${type} py-2`;
      alertDiv.innerHTML = message;
      
      // แทรกก่อนฟอร์ม
      const form = document.querySelector('form');
      form.parentNode.insertBefore(alertDiv, form);
      
      // เลื่อนไปยังแจ้งเตือน
      alertDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    
    // ตรวจสอบรหัสผ่านตรงกันแบบ real-time
    document.getElementById('confirm_password').addEventListener('input', function() {
      const password = document.getElementById('password').value;
      const confirmPassword = this.value;
      
      if (confirmPassword && password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
      } else {
        this.setCustomValidity('');
      }
    });
    
    // ตรวจสอบเมื่อส่งฟอร์ม
    document.querySelector('form').addEventListener('submit', function(e) {
      if (!validateForm()) {
        e.preventDefault();
      }
    });
  </script>
</body>
</html>
