<?php
// pages/register.php  –  Customer registration

session_start();
require_once '../php/db.php';
require_once '../php/auth.php';
require_once '../php/queries.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'     => trim($_POST['name'] ?? ''),
        'address'  => trim($_POST['address'] ?? ''),
        'email'    => trim($_POST['email'] ?? ''),
        'phone'    => trim($_POST['phone'] ?? ''),
        'card'     => trim($_POST['card'] ?? ''),
        'password' => $_POST['password'] ?? '',
    ];

    foreach ($data as $k => $v) {
        if ($v === '') { $error = 'All fields are required.'; break; }
    }

    if (!$error) {
        $newId = registerCustomer($data);
        if ($newId) {
            loginCustomer($newId, $data['name']);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Email or phone already registered. Please log in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sign Up - Food Hopper</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<nav>
  <a class="brand" href="../index.php">Food Hopper</a>
  <div><a href="login.php">Log In</a></div>
</nav>

<div class="container">
<div class="form-card">
  <h2>Create Account</h2>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="name" required placeholder="Jane Doe">
    </div>
    <div class="form-group">
      <label>Delivery Address</label>
      <input type="text" name="address" required placeholder="123 Main St, City, State">
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required placeholder="you@example.com">
    </div>
    <div class="form-group">
      <label>Phone Number</label>
      <input type="tel" name="phone" required placeholder="9135551234">
    </div>
    <div class="form-group">
      <label>Credit Card Number</label>
      <input type="text" name="card" required maxlength="20" placeholder="4111 1111 1111 1111">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required placeholder="Choose a password">
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%">Create Account</button>
  </form>

  <p class="text-center mt-2" style="font-size:.9rem">
    Already have an account? <a href="login.php">Log in</a>
  </p>
</div>
</div>

<footer>© <?= date('Y') ?> Food Hopper</footer>
</body>
</html>
