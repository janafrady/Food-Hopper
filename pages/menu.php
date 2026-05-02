<?php
// pages/menu.php  –  View restaurant menu + place order
// getMenuByRestaurant($rid) and placeOrder(...) used 

session_start();
require_once '../php/db.php';
require_once '../php/auth.php';
require_once '../php/queries.php';

$rid = (int)($_GET['restaurant_id'] ?? 0);
if (!$rid) { header('Location: ../index.php'); exit; }

$user  = currentUser();
$error = '';
$info  = '';

// Dynamic query – fetch menu for THIS restaurant ---
$menuItems = getMenuByRestaurant($rid);

// Get restaurant info
$pdo   = getDB();
$stmt  = $pdo->prepare("SELECT * FROM Restaurant WHERE restaurant_id = :rid");
$stmt->execute([':rid' => $rid]);
$rest  = $stmt->fetch();
if (!$rest) { header('Location: ../index.php'); exit; }

// Group items by category for display
$grouped = [];
foreach ($menuItems as $item) {
    $grouped[$item['item_category']][] = $item;
}

// Handle order placement 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!$user || $user['type'] !== 'customer') {
        header('Location: login.php');
        exit;
    }
    // Collect quantities from form
    $quantities = [];
    foreach ($_POST['qty'] ?? [] as $itemId => $qty) {
        $qty = (int)$qty;
        if ($qty > 0) $quantities[(int)$itemId] = $qty;
    }

    if (empty($quantities)) {
        $error = 'Please select at least one item.';
    } else {
        // Q5: Dynamic INSERT – build order from user selections
        $orderId = placeOrder($user['id'], $rid, $quantities);
        if ($orderId) {
            header("Location: order_detail.php?order_id=$orderId&placed=1");
            exit;
        } else {
            $error = 'Could not place order. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($rest['restaurant_name']) ?> – Food Hopper</title>
<link rel="stylesheet" href="../css/style.css">
<style>
  .menu-section { margin-bottom: 2rem; }
  .menu-section h3 { font-size: 1.1rem; color: var(--orange); margin-bottom: .8rem; text-transform: uppercase; letter-spacing: .06em; }
  .qty-input { width: 60px; padding: .3rem .5rem; border: 1px solid #ddd; border-radius: 5px; text-align: center; }
  .sticky-footer { position: sticky; bottom: 0; background: var(--dark); color: var(--white); padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; }
  .price-col { font-weight: 700; color: var(--orange); }
</style>
</head>
<body>
<nav>
  <a class="brand" href="../index.php">Food Hopper</a>
  <div>
    <?php if ($user): ?>
      <a href="dashboard.php">Dashboard</a>
      <a href="logout.php">Log Out</a>
    <?php else: ?>
      <a href="login.php">Log In</a>
      <a href="register.php">Sign Up</a>
    <?php endif; ?>
  </div>
</nav>

<div class="container">
  <!-- Restaurant header -->
  <div class="dash-header mt-2">
    <div>
      <h2><?= htmlspecialchars($rest['restaurant_name']) ?></h2>
      <small><?= htmlspecialchars($rest['cuisine_type']) ?> · <?= htmlspecialchars($rest['restaurant_address']) ?></small>
    </div>
    <div style="font-size:1.6rem; color:var(--orange)">★ <?= number_format($rest['restaurant_rating'],1) ?></div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (empty($menuItems)): ?>
    <div class="alert alert-info">This restaurant has no available items right now.</div>
  <?php else: ?>

  <form method="POST" id="order-form">
    <?php foreach ($grouped as $category => $items): ?>
      <div class="menu-section">
        <h3><?= htmlspecialchars($category ?: 'Other') ?></h3>
        <table class="menu-table">
          <thead>
            <tr>
              <th>Item</th>
              <th>Price</th>
              <th>Qty</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td><?= htmlspecialchars($item['item_name']) ?></td>
              <td class="price-col">$<?= number_format($item['item_price'], 2) ?></td>
              <td>
                <input type="number" class="qty-input" name="qty[<?= $item['item_id'] ?>]"
                       min="0" max="10" value="0" oninput="updateTotal()">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>

    <!-- Sticky order bar -->
    <div class="sticky-footer">
      <span>Total: <strong id="total-display">$0.00</strong></span>
      <?php if ($user && $user['type'] === 'customer'): ?>
        <button type="submit" name="place_order" class="btn btn-primary">Place Order →</button>
      <?php else: ?>
        <a href="login.php" class="btn btn-primary">Log In to Order</a>
      <?php endif; ?>
    </div>
  </form>

  <?php endif; ?>
</div>

<script>
// Build price map from PHP
const prices = {
  <?php foreach ($menuItems as $item): ?>
  <?= $item['item_id'] ?>: <?= $item['item_price'] ?>,
  <?php endforeach; ?>
};

function updateTotal() {
  let total = 0;
  document.querySelectorAll('.qty-input').forEach(input => {
    const match = input.name.match(/\[(\d+)\]/);
    if (match) {
      const id  = parseInt(match[1]);
      const qty = parseInt(input.value) || 0;
      total += (prices[id] || 0) * qty;
    }
  });
  document.getElementById('total-display').textContent = '$' + total.toFixed(2);
}
</script>
<footer>© <?= date('Y') ?> Food Hopper</footer>
</body>
</html>
