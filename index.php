<?php
// index.php  –  Food Hopper Homepage

session_start();
require_once 'php/db.php';
require_once 'php/auth.php';
require_once 'php/queries.php';

$user        = currentUser();
$searchTerm  = trim($_GET['q'] ?? '');
$restaurants = $searchTerm ? searchRestaurants($searchTerm) : getAllRestaurants();
$error       = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Food Hopper – Order Food Online</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Navigation -->
<nav>
  <a class="brand" href="index.php">🍔 Food Hopper</a>
  <div>
    <?php if ($user): ?>
      <a href="pages/dashboard.php">Dashboard</a>
      <a href="pages/logout.php">Log Out (<?= htmlspecialchars($user['name']) ?>)</a>
    <?php else: ?>
      <a href="pages/login.php">Log In</a>
      <a href="pages/register.php">Sign Up</a>
    <?php endif; ?>
  </div>
</nav>

<!-- Hero -->
<section class="hero">
  <h1>Hungry? <span>Food Hopper</span> Has You Covered.</h1>
  <p>Order from local restaurants and get it delivered fast.</p>

  <!-- Search restaurants (dynamic query on name / cuisine) -->
  <form action="index.php" method="GET" class="search-bar">
    <input type="text" name="q" placeholder="Search by restaurant or cuisine…"
           value="<?= htmlspecialchars($searchTerm) ?>">
    <button type="submit">Search</button>
  </form>
</section>

<!-- Restaurant Listing -->
<div class="container">

  <?php if ($error === 'login_required'): ?>
    <div class="alert alert-error">You must be logged in to do that.</div>
  <?php endif; ?>

  <?php if ($searchTerm): ?>
    <p class="section-title">Results for "<?= htmlspecialchars($searchTerm) ?>" (<?= count($restaurants) ?>)</p>
  <?php else: ?>
    <p class="section-title">All Restaurants</p>
  <?php endif; ?>

  <?php if (empty($restaurants)): ?>
    <div class="alert alert-info">No restaurants found. Try a different search.</div>
  <?php else: ?>
    <div class="cards">
      <?php foreach ($restaurants as $r): ?>
        <a href="pages/menu.php?restaurant_id=<?= $r['restaurant_id'] ?>" style="text-decoration:none;">
          <div class="card">
            <div class="card-body">
              <div class="card-title"><?= htmlspecialchars($r['restaurant_name']) ?></div>
              <div class="card-sub"><?= htmlspecialchars($r['cuisine_type']) ?></div>
              <div class="card-sub"><?= htmlspecialchars($r['restaurant_address']) ?></div>
              <div class="card-rating">★ <?= number_format($r['restaurant_rating'], 1) ?></div>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<footer>© <?= date('Y') ?> Food Hopper · All rights reserved</footer>
</body>
</html>
