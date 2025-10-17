<?php
require __DIR__ . '/config_mysqli.php';
require __DIR__ . '/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: register.php'); exit;
}
if (!csrf_check($_POST['csrf'] ?? '')) {
  $_SESSION['flash'] = 'Invalid request. Please try again.';
  header('Location: register.php'); exit;
}

$display_name = trim($_POST['display_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Server-side validation (mirrors client, but authoritative)
$errors = [];

if ($display_name === '') {
  $errors[] = 'Display name is required.';
} elseif (mb_strlen($display_name) > 100) {
  $errors[] = 'Display name must be at most 100 characters.';
}

if ($email === '') {
  $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $errors[] = 'Please enter a valid email address.';
}

if ($password === '') {
  $errors[] = 'Password is required.';
} elseif (strlen($password) < 8) {
  $errors[] = 'Password must be at least 8 characters long.';
}

if ($confirm_password === '') {
  $errors[] = 'Confirm password is required.';
} elseif ($password !== '' && $password !== $confirm_password) {
  $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
  $_SESSION['flash'] = implode('<br>', $errors);
  $_SESSION['old'] = [
    'display_name' => $display_name,
    'email' => $email,
  ];
  header('Location: register.php'); exit;
}

try {
  // Check duplicate email
  $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows > 0) {
    $_SESSION['flash'] = 'Email already exists. Please use a different email.';
    $_SESSION['old'] = [
      'display_name' => $display_name,
      'email' => $email,
    ];
    header('Location: register.php'); exit;
  }
  $stmt->close();

  // Hash password and insert
  $password_hash = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $mysqli->prepare('INSERT INTO users (display_name, email, password_hash) VALUES (?, ?, ?)');
  $stmt->bind_param('sss', $display_name, $email, $password_hash);
  $stmt->execute();
  $stmt->close();

  // Auto-login then redirect to dashboard
  $_SESSION['user_id'] = $mysqli->insert_id;
  $_SESSION['user_name'] = $display_name;
  $_SESSION['flash'] = 'Account created successfully! Welcome!';
  unset($_SESSION['old']);
  header('Location: dashboard.php'); exit;

} catch (Throwable $e) {
  $_SESSION['flash'] = 'Server error. Please try again.';
  $_SESSION['old'] = [
    'display_name' => $display_name,
    'email' => $email,
  ];
  header('Location: register.php'); exit;
}
