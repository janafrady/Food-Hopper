<?php
// queries.php  –  All dynamic database queries

// Queries (5 dynamic + 2 with JOIN):
//  Q1  searchRestaurants($term)         – dynamic WHERE on name/cuisine
//  Q2  getMenuByRestaurant($rid)        – dynamic WHERE by restaurant_id
//  Q3  getOrderHistory($cid)            – JOIN Order+Restaurant+Payment  [JOIN 1]
//  Q4  getOrderDetails($oid)            – JOIN Order+Restaurant+Customer+Contractor+Items [JOIN 2]
//  Q5  placeOrder($cid,$rid,$items)     – dynamic INSERT with computed total
//  Q6  updateOrderStatus($oid,$status)  – dynamic UPDATE
//  Q7  searchOrdersByStatus($uid,$type,$status) – dynamic filter

require_once __DIR__ . '/db.php';


// Search restaurants by name OR cuisine type  
// Dynamic: user-supplied search term matched against two columns
function searchRestaurants(string $term): array {
    $pdo  = getDB();
    $like = '%' . $term . '%';
    // Dynamic: $term drives the WHERE clause at runtime
    $stmt = $pdo->prepare(
        "SELECT restaurant_id, restaurant_name, restaurant_address,
                cuisine_type, restaurant_rating
         FROM   Restaurant
         WHERE  restaurant_name LIKE :name
            OR  cuisine_type    LIKE :cuisine
         ORDER BY restaurant_rating DESC"
    );
    $stmt->execute([':name' => $like, ':cuisine' => $like]);
    return $stmt->fetchAll();
}


// Get menu items for a specific restaurant  
// Dynamic: restaurant_id is provided at runtime (user selection)
function getMenuByRestaurant(int $restaurantId): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT item_id, item_name, item_price, item_category, is_available
         FROM   Item
         WHERE  restaurant_id = :rid
           AND  is_available  = 1
         ORDER BY item_category, item_name"
    );
    $stmt->execute([':rid' => $restaurantId]);
    return $stmt->fetchAll();
}


// View order history for a customer 
// JOIN: Order -> Restaurant -> Payment
// Dynamic: customer_id from session; optional status filter
function getOrderHistory(int $customerId, string $statusFilter = ''): array {
    $pdo = getDB();
    $sql = "SELECT o.order_id,
                   o.order_datetime,
                   o.order_status,
                   o.total_cost,
                   r.restaurant_name,
                   r.cuisine_type,
                   p.payment_method,
                   p.payment_status
            FROM   `Order`  o
            JOIN   Restaurant r ON o.restaurant_id = r.restaurant_id
            LEFT JOIN Payment  p ON o.order_id      = p.order_id
            WHERE  o.customer_id = :cid";

    $params = [':cid' => $customerId];

    // Dynamic optional filter – added only when the user selects a status
    if ($statusFilter !== '') {
        $sql .= " AND o.order_status = :status";
        $params[':status'] = $statusFilter;
    }

    $sql .= " ORDER BY o.order_datetime DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}


// Get full details of one order
// JOIN: Order -> Customer -> Restaurant -> Contractor -> OrderItem -> Item
// Dynamic: order_id supplied at runtime
function getOrderDetails(int $orderId): array {
    $pdo = getDB();

    // Order header with related entities
    $stmt = $pdo->prepare(
        "SELECT o.order_id,
                o.order_datetime,
                o.order_status,
                o.total_cost,
                c.customer_name,
                c.customer_phone,
                r.restaurant_name,
                r.restaurant_address,
                r.restaurant_phone,
                con.contractor_name,
                con.contractor_phone,
                con.location        AS contractor_location,
                p.payment_method,
                p.payment_status
         FROM   `Order`    o
         JOIN   Customer   c   ON o.customer_id   = c.customer_id
         JOIN   Restaurant r   ON o.restaurant_id = r.restaurant_id
         LEFT JOIN Contractor con ON o.contractor_id = con.contractor_id
         LEFT JOIN Payment    p   ON o.order_id      = p.order_id
         WHERE  o.order_id = :oid"
    );
    $stmt->execute([':oid' => $orderId]);
    $order = $stmt->fetch();

    // Line items
    $stmt2 = $pdo->prepare(
        "SELECT i.item_name, i.item_price, oi.quantity,
                (i.item_price * oi.quantity) AS line_total
         FROM   OrderItem oi
         JOIN   Item i ON oi.item_id = i.item_id
         WHERE  oi.order_id = :oid"
    );
    $stmt2->execute([':oid' => $orderId]);
    $items = $stmt2->fetchAll();

    return ['order' => $order, 'items' => $items];
}


// Place a new order  
// Dynamic: customer, restaurant, and item list come from user input
// ------------------------------------------------------------------
function placeOrder(int $customerId, int $restaurantId, array $itemQuantities): int|false {
    // $itemQuantities = [ item_id => quantity, ... ]
    if (empty($itemQuantities)) return false;

    $pdo = getDB();
    $pdo->beginTransaction();
    try {
        // Compute total from live item prices (never trust client-side totals)
        $ids        = array_keys($itemQuantities);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $priceStmt  = $pdo->prepare(
            "SELECT item_id, item_price FROM Item
             WHERE  item_id IN ($placeholders) AND restaurant_id = ? AND is_available = 1"
        );
        $priceStmt->execute([...$ids, $restaurantId]);
        $prices = [];
        foreach ($priceStmt->fetchAll() as $row) {
            $prices[$row['item_id']] = (float)$row['item_price'];
        }

        $total = 0.0;
        foreach ($itemQuantities as $iid => $qty) {
            if (!isset($prices[$iid])) {
                throw new \RuntimeException("Item $iid not available.");
            }
            $total += $prices[$iid] * (int)$qty;
        }

        // Dynamic INSERT – values built from user selections
        $ins = $pdo->prepare(
            "INSERT INTO `Order` (customer_id, restaurant_id, order_status, total_cost)
             VALUES (:cid, :rid, 'in-process', :total)"
        );
        $ins->execute([':cid' => $customerId, ':rid' => $restaurantId, ':total' => round($total, 2)]);
        $orderId = (int)$pdo->lastInsertId();

        $oi = $pdo->prepare(
            "INSERT INTO OrderItem (order_id, item_id, quantity) VALUES (:oid, :iid, :qty)"
        );
        foreach ($itemQuantities as $iid => $qty) {
            $oi->execute([':oid' => $orderId, ':iid' => $iid, ':qty' => (int)$qty]);
        }

        // Create a pending payment record
        $pay = $pdo->prepare(
            "INSERT INTO Payment (order_id, payment_method, payment_status)
             VALUES (:oid, 'pending', 'pending')"
        );
        $pay->execute([':oid' => $orderId]);

        $pdo->commit();
        return $orderId;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        return false;
    }
}


// Update order status  
// Dynamic: both order_id and new status are runtime variables
// ------------------------------------------------------------------
function updateOrderStatus(int $orderId, string $newStatus): bool {
    $allowed = ['in-process','ready','out-for-delivery','completed','canceled'];
    if (!in_array($newStatus, $allowed, true)) return false;

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "UPDATE `Order` SET order_status = :status WHERE order_id = :oid"
    );
    $stmt->execute([':status' => $newStatus, ':oid' => $orderId]);
    return $stmt->rowCount() > 0;
}


// Search orders for restaurant/contractor by status
// Dynamic: user_id, user type, and status filter supplied at runtime
// ------------------------------------------------------------------
function getOrdersByStatus(int $userId, string $userType, string $status = ''): array {
    $pdo = getDB();

    if ($userType === 'restaurant') {
        $col = 'o.restaurant_id';
    } elseif ($userType === 'contractor') {
        $col = 'o.contractor_id';
    } else {
        $col = 'o.customer_id';
    }

    $sql = "SELECT o.order_id,
                   o.order_datetime,
                   o.order_status,
                   o.total_cost,
                   c.customer_name,
                   r.restaurant_name
            FROM   `Order`    o
            JOIN   Customer   c ON o.customer_id   = c.customer_id
            JOIN   Restaurant r ON o.restaurant_id = r.restaurant_id
            WHERE  $col = :uid";

    $params = [':uid' => $userId];

    if ($status !== '') {
        $sql .= " AND o.order_status = :status";
        $params[':status'] = $status;
    }

    $sql .= " ORDER BY o.order_datetime DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}


// Helper Function: Register a new customer
function registerCustomer(array $data): int|false {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "INSERT INTO Customer
            (customer_name, customer_address, customer_email,
             customer_phone, credit_card, account_password)
         VALUES (:name, :addr, :email, :phone, :card, MD5(:pass))"
    );
    try {
        $stmt->execute([
            ':name'  => $data['name'],
            ':addr'  => $data['address'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':card'  => $data['card'],
            ':pass'  => $data['password'],
        ]);
        return (int)$pdo->lastInsertId();
    } catch (\PDOException $e) {
        return false;   // duplicate email/phone
    }
}


// Helper Function: Authenticate a customer login
function authenticateCustomer(string $email, string $password): ?array {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT customer_id, customer_name
         FROM   Customer
         WHERE  customer_email    = :email
           AND  account_password  = MD5(:pass)"
    );
    $stmt->execute([':email' => $email, ':pass' => $password]);
    $row = $stmt->fetch();
    return $row ?: null;
}


// Helper Function: Authenticate a restaurant login
function authenticateRestaurant(string $email, string $password): ?array {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT restaurant_id, restaurant_name
         FROM   Restaurant
         WHERE  restaurant_email  = :email
           AND  account_password  = MD5(:pass)"
    );
    $stmt->execute([':email' => $email, ':pass' => $password]);
    $row = $stmt->fetch();
    return $row ?: null;
}


// Helper Function: Get all restaurants (for homepage browse)
function getAllRestaurants(): array {
    return getDB()->query(
        "SELECT restaurant_id, restaurant_name, cuisine_type, restaurant_rating, restaurant_address
         FROM   Restaurant
         ORDER BY restaurant_rating DESC"
    )->fetchAll();
}


// Helper Function: Add menu item
function addMenuItem(int $restaurantId, array $data): int|false {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "INSERT INTO Item (restaurant_id, item_name, item_price, item_category, is_available)
         VALUES (:rid, :name, :price, :cat, 1)"
    );
    try {
        $stmt->execute([
            ':rid'   => $restaurantId,
            ':name'  => $data['name'],
            ':price' => $data['price'],
            ':cat'   => $data['category'],
        ]);
        return (int)getDB()->lastInsertId();
    } catch (\PDOException $e) {
        return false;
    }
}


// Helper Function: Toggle item availability
function toggleItemAvailability(int $itemId, int $available): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "UPDATE Item SET is_available = :avail WHERE item_id = :iid"
    );
    $stmt->execute([':avail' => $available, ':iid' => $itemId]);
    return $stmt->rowCount() > 0;
}


// Helper Function: Assign contractor to order
function assignContractor(int $orderId, int $contractorId): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "UPDATE `Order` SET contractor_id = :cid WHERE order_id = :oid"
    );
    $stmt->execute([':cid' => $contractorId, ':oid' => $orderId]);
    return $stmt->rowCount() > 0;
}


// Helper Function: Complete payment
function completePayment(int $orderId, string $method): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "UPDATE Payment
         SET payment_method = :method, payment_status = 'completed', payment_date = NOW()
         WHERE order_id = :oid"
    );
    $stmt->execute([':method' => $method, ':oid' => $orderId]);
    return $stmt->rowCount() > 0;
}
