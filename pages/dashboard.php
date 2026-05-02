<?php
// pages/dashboard.php  –  User dashboard (all 3 user types)
// getOrderHistory($cid, $status) and getOrderByStatus($uid, $type, $status)

session_start();
require_once '../php/db.php';
require_once '../php/auth.php';
require_once '../php/queries.php';

$user = requireLogin();

// Dynamic filter – user selects a status to narrow the list
$statusFilter = $_GET['status'] ?? '';

if ($user['type'] === 'customer') {
    // JOIN Order + Restaurant + Payment, filtered by this customer + optional status
    $orders = getOrderHistory($user['id'], $statusFilter);
} else {
    // JOIN Order + Customer + Restaurant, filtered by restaurant/contractor + optional status
    $orders = getOrdersByStatus($user['id'], $user['type'], $statusFilter);
}

// For restaurant: also show menu management
$menuItems = [];
if ($user['type'] === 'restaurant') {
    $menuItems = getMenuByRestaurant($user['id']);
}

// Handle add-item form (restaurant only)
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['type'] === 'restaurant') {
    if (isset($_POST['add_item'])) {
        $res = addMenuItem($user['id'], [
            'name'     => trim($_POST['item_name'] ?? ''),
            'price'    => (float)($_POST['item_price'] ?? 0),
            'category' => trim($_POST['item_category'] ?? ''),
        ]);
        $msg = $res ? 'Item added!' : 'Failed to add item.';
        header("Location: dashboard.php?msg=" . urlencode($msg));
        exit;
    }
    if (isset($_POST['toggle_item'])) {
        toggleItemAvailability((int)$_POST['item_id'], (int)$_POST['available']);
        header('Location: dashboard.php');
        exit;
    }
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

function statusBadge(string $status): string {
    $cls = 'badge-' . str_replace([' ','/'], '-', $status);
    return "<span class='badge $cls'>$status</span>";
}
$statusOptions = ['','in-process','ready','out-for-delivery','completed','canceled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard – Food Hopper</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<nav>
  <a class="brand" href="../index.php">Food Hopper</a>
  <div>
    <a href="../index.php">Browse Restaurants</a>
    <a href="logout.php">Log Out</a>
  </div>
</nav>

<div class="container">

  <!-- Header -->
  <div class="dash-header">
    <div>
      <h2>Welcome, <?= htmlspecialchars($user['name']) ?></h2>
      <small><?= ucfirst($user['type']) ?> Dashboard</small>
    </div>
    <div style="font-size:2rem">
      <?= $user['type']==='customer'?'Customer':($user['type']==='restaurant'?'Restaurant':'Contractor') ?>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- ORDERS SECTION  (customer) / (restaurant, contractor)
       Both are dynamic: user ID + status filter at runtime -->

  <div class="flex mb-1">
    <h3 class="section-title" style="margin:0">
      <?= $user['type']==='customer' ? 'My Orders' : 'Orders' ?>
    </h3>

    <!-- dynamic status filter -->
    <form method="GET" class="flex ml-auto" style="gap:.4rem">
      <select name="status" onchange="this.form.submit()"
              style="padding:.4rem .8rem;border:1px solid #ddd;border-radius:6px;font-size:.85rem">
        <?php foreach ($statusOptions as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>>
            <?= $s==='' ? 'All Statuses' : ucwords(str_replace('-',' ',$s)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (empty($orders)): ?>
    <div class="alert alert-info">No orders found<?= $statusFilter?" with status \"$statusFilter\"":'' ?>.</div>
  <?php else: ?>
  <table class="menu-table" style="margin-bottom:2rem">
    <thead>
      <tr>
        <th>#</th>
        <th>Restaurant</th>
        <?php if ($user['type'] !== 'customer'): ?><th>Customer</th><?php endif; ?>
        <th>Date</th>
        <th>Total</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td><?= $o['order_id'] ?></td>
        <td><?= htmlspecialchars($o['restaurant_name']) ?></td>
        <?php if ($user['type'] !== 'customer'): ?>
          <td><?= htmlspecialchars($o['customer_name']) ?></td>
        <?php endif; ?>
        <td><?= date('M j, Y', strtotime($o['order_datetime'])) ?></td>
        <td>$<?= number_format($o['total_cost'],2) ?></td>
        <td><?= statusBadge($o['order_status']) ?></td>
        <td><a href="order_detail.php?order_id=<?= $o['order_id'] ?>" class="btn btn-dark btn-sm">View</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if ($user['type'] === 'customer'): ?>
    <div class="text-center">
      <a href="../index.php" class="btn btn-primary">+ Place New Order</a>
    </div>
  <?php endif; ?>

  <!-- ======================================================
       RESTAURANT MENU MANAGEMENT
  ====================================================== -->
  <?php if ($user['type'] === 'restaurant'): ?>
  <hr class="divider">
  <h3 class="section-title">Menu Management</h3>

  <!-- Add Item Form -->
  <div class="form-card" style="max-width:600px;margin:0 0 1.5rem">
    <h2 style="font-size:1.1rem;margin-bottom:1rem">Add Menu Item</h2>
    <form method="POST">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.8rem">
        <div class="form-group" style="margin:0">
          <label>Item Name</label>
          <input type="text" name="item_name" required placeholder="e.g. Veggie Burger">
        </div>
        <div class="form-group" style="margin:0">
          <label>Price ($)</label>
          <input type="number" name="item_price" step="0.01" min="0" required placeholder="9.99">
        </div>
        <div class="form-group" style="margin:0">
          <label>Category</label>
          <input type="text" name="item_category" placeholder="e.g. Burgers">
        </div>
      </div>
      <button type="submit" name="add_item" class="btn btn-primary mt-2">Add Item</button>
    </form>
  </div>

  <!-- Current Menu -->
  <?php if (!empty($menuItems)): ?>
  <table class="menu-table">
    <thead>
      <tr><th>Item</th><th>Category</th><th>Price</th><th>Available</th><th>Toggle</th></tr>
    </thead>
    <tbody>
      <?php foreach ($menuItems as $mi): ?>
      <tr>
        <td><?= htmlspecialchars($mi['item_name']) ?></td>
        <td><?= htmlspecialchars($mi['item_category']) ?></td>
        <td>$<?= number_format($mi['item_price'],2) ?></td>
        <td><?= $mi['is_available'] ? 'Yes' : 'No' ?></td>
        <td>
          <form method="POST">
            <input type="hidden" name="item_id" value="<?= $mi['item_id'] ?>">
            <input type="hidden" name="available" value="<?= $mi['is_available'] ? 0 : 1 ?>">
            <button type="submit" name="toggle_item" class="btn btn-sm <?= $mi['is_available']?'btn-danger':'btn-success' ?>">
              <?= $mi['is_available'] ? 'Disable' : 'Enable' ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?php endif; ?>

</div>

<footer>© <?= date('Y') ?> Food Hopper</footer>
</body>
</html>
