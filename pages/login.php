<?php
// pages/login.php  –  Unified login for all user types

session_start();
require_once '../php/db.php';
require_once '../php/auth.php';
require_once '../php/queries.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type     = $_POST['user_type'] ?? 'customer';
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($type === 'customer') {
        $row = authenticateCustomer($email, $password);
        if ($row) {
            loginCustomer($row['customer_id'], $row['customer_name']);
            header('Location: dashboard.php');
            exit;
        }
    } elseif ($type === 'restaurant') {
        $row = authenticateRestaurant($email, $password);
        if ($row) {
            loginRestaurant($row['restaurant_id'], $row['restaurant_name']);
            header('Location: dashboard.php');
            exit;
        }
    }
    $error = 'Invalid credentials. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Log In – Food Hopper</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<nav>
  <a class="brand" href="../index.php">Food Hopper</a>
  <div><a href="../index.php">← Home</a></div>
</nav>

<div class="container">
<div class="form-card">
  <h2>Welcome Back</h2>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>I am a…</label>
      <select name="user_type">
        <option value="customer">Customer</option>
        <option value="restaurant">Restaurant</option>
      </select>
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required placeholder="you@example.com">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required placeholder="••••••••">
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%">Log In</button>
  </form>

  <p class="text-center mt-2" style="font-size:.9rem">
    Don't have an account? <a href="register.php">Sign up</a>
  </p>
</div>
</div>

<footer>© <?= date('Y') ?> Food Hopper</footer>
</body>
</html>
