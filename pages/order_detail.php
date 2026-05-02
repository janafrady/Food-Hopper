<?php
// pages/order_detail.php  –  Full order details
// getOrderDetails($oid) and updateOrderStatus() 

session_start();
require_once '../php/db.php';
require_once '../php/auth.php';
require_once '../php/queries.php';

$user = requireLogin();   // must be logged in

$orderId = (int)($_GET['order_id'] ?? 0);
if (!$orderId) { header('Location: dashboard.php'); exit; }

// Handle status update (restaurant / contractor action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
    updateOrderStatus($orderId, $_POST['new_status']);
    header("Location: order_detail.php?order_id=$orderId");
    exit;
}

// Handle payment completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_method'])) {
    completePayment($orderId, $_POST['pay_method']);
    header("Location: order_detail.php?order_id=$orderId&paid=1");
    exit;
}

// Dynamic join query – pulls Order + Customer + Restaurant + Contractor + Items
$data  = getOrderDetails($orderId);
$order = $data['order'];
$items = $data['items'];

if (!$order) { header('Location: dashboard.php'); exit; }

$placed = isset($_GET['placed']);
$paid   = isset($_GET['paid']);

function statusBadge(string $status): string {
    $cls = 'badge-' . str_replace(' ', '-', $status);
    return "<span class='badge $cls'>$status</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order #<?= $orderId ?> - Food Hopper</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<nav>
  <a class="brand" href="../index.php">Food Hopper</a>
  <div>
    <a href="dashboard.php">Dashboard</a>
    <a href="logout.php">Log Out</a>
  </div>
</nav>

<div class="container">

  <?php if ($placed): ?>
    <div class="alert alert-success">Order placed successfully!</div>
  <?php endif; ?>
  <?php if ($paid): ?>
    <div class="alert alert-success">Payment confirmed!</div>
  <?php endif; ?>

  <div class="flex mb-1">
    <h2>Order #<?= $orderId ?></h2>
    <div class="ml-auto"><?= statusBadge($order['order_status']) ?></div>
  </div>
  <p style="color:var(--gray);font-size:.9rem;margin-bottom:1.2rem">
    Placed <?= date('M j, Y g:i A', strtotime($order['order_datetime'])) ?>
  </p>

  <!-- Detail grid – result of JOIN query Q4 -->
  <div class="order-detail-grid">
    <div class="detail-box">
      <h4>Restaurant</h4>
      <p><strong><?= htmlspecialchars($order['restaurant_name']) ?></strong></p>
      <p><?= htmlspecialchars($order['restaurant_address']) ?></p>
      <p><?= htmlspecialchars($order['restaurant_phone']) ?></p>
    </div>
    <div class="detail-box">
      <h4>Customer</h4>
      <p><strong><?= htmlspecialchars($order['customer_name']) ?></strong></p>
      <p><?= htmlspecialchars($order['customer_phone']) ?></p>
    </div>
    <div class="detail-box">
      <h4>Delivery Driver</h4>
      <?php if ($order['contractor_name']): ?>
        <p><strong><?= htmlspecialchars($order['contractor_name']) ?></strong></p>
        <p><?= htmlspecialchars($order['contractor_phone']) ?></p>
        <p><?= htmlspecialchars($order['contractor_location'] ?? 'En route') ?></p>
      <?php else: ?>
        <p style="color:var(--gray)">Not yet assigned</p>
      <?php endif; ?>
    </div>
    <div class="detail-box">
      <h4>Payment</h4>
      <p><strong><?= htmlspecialchars($order['payment_method'] ?? 'Pending') ?></strong></p>
      <p><?= statusBadge($order['payment_status'] ?? 'pending') ?></p>
    </div>
  </div>

  <!-- Line items (from OrderItem JOIN Item) -->
  <h3 class="section-title">Items Ordered</h3>
  <table class="menu-table" style="margin-bottom:1.5rem">
    <thead>
      <tr><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $li): ?>
      <tr>
        <td><?= htmlspecialchars($li['item_name']) ?></td>
        <td>$<?= number_format($li['item_price'], 2) ?></td>
        <td><?= $li['quantity'] ?></td>
        <td><strong>$<?= number_format($li['line_total'], 2) ?></strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="background:#fafafa">
        <td colspan="3" style="text-align:right;padding:.75rem 1rem;font-weight:700">Total</td>
        <td style="padding:.75rem 1rem;font-weight:800;color:var(--orange)">
          $<?= number_format($order['total_cost'], 2) ?>
        </td>
      </tr>
    </tfoot>
  </table>

  <!-- Q6: Dynamic status update (restaurant/contractor) -->
  <?php if ($user['type'] === 'restaurant' || $user['type'] === 'contractor'): ?>
    <div class="detail-box mb-1">
      <h4>Update Order Status</h4>
      <form method="POST" class="flex mt-1">
        <select name="new_status" class="form-group" style="margin:0;width:auto">
          <?php foreach (['in-process','ready','out-for-delivery','completed','canceled'] as $s): ?>
            <option value="<?= $s ?>" <?= $order['order_status']===$s?'selected':'' ?>>
              <?= ucwords(str_replace('-', ' ', $s)) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-dark btn-sm">Update</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- Payment form (customer) -->
  <?php if ($user['type'] === 'customer' && ($order['payment_status'] ?? '') !== 'completed'): ?>
    <div class="detail-box">
      <h4>Complete Payment</h4>
      <form method="POST" class="flex mt-1">
        <select name="pay_method" style="padding:.4rem .8rem;border:1px solid #ddd;border-radius:6px">
          <option value="Visa">Visa</option>
          <option value="Mastercard">Mastercard</option>
          <option value="PayPal">PayPal</option>
          <option value="Apple Pay">Apple Pay</option>
          <option value="Google Pay">Google Pay</option>
        </select>
        <button type="submit" class="btn btn-success btn-sm">Pay $<?= number_format($order['total_cost'],2) ?></button>
      </form>
    </div>
  <?php endif; ?>

  <div class="mt-3">
    <a href="dashboard.php" class="btn btn-dark">← Back to Dashboard</a>
  </div>
</div>

<footer>© <?= date('Y') ?> Food Hopper</footer>
</body>
</html>
