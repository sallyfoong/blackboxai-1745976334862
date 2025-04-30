<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

// Basic routing based on 'action' parameter
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        // Handle member registration
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['name'], $input['email'], $input['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            break;
        }
        $name = trim($input['name']);
        $email = trim($input['email']);
        $password = $input['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
            break;
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM members WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
            break;
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Generate referral code (simple random string)
        $referral_code = substr(bin2hex(random_bytes(5)), 0, 10);

        // Insert new member
        $stmt = $pdo->prepare("INSERT INTO members (name, email, password_hash, referral_code) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$name, $email, $password_hash, $referral_code]);
            echo json_encode(['status' => 'success', 'message' => 'Registration successful']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'login':
        // Handle member login
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['email'], $input['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            break;
        }
        $email = trim($input['email']);
        $password = $input['password'];

        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, member_level, points FROM members WHERE email = ?");
        $stmt->execute([$email]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$member || !password_verify($password, $member['password_hash'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
            break;
        }

        // Return member info (excluding password hash)
        unset($member['password_hash']);
        echo json_encode(['status' => 'success', 'message' => 'Login successful', 'member' => $member]);
        break;

    case 'forgot_password':
        // Handle password reset (placeholder)
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['email'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing email']);
            break;
        }
        $email = trim($input['email']);
        // TODO: Implement password reset email sending
        echo json_encode(['status' => 'success', 'message' => 'Password reset instructions sent if email exists']);
        break;

    case 'make_order':
        // Handle order creation
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['member_id'], $input['items'], $input['shipping_address'], $input['total_amount'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            break;
        }
        $member_id = (int)$input['member_id'];
        $items = $input['items']; // array of {product_name, quantity, price}
        $shipping_address = trim($input['shipping_address']);
        $total_amount = (float)$input['total_amount'];
        $points_redeemed = isset($input['points_redeemed']) ? (int)$input['points_redeemed'] : 0;

        try {
            $pdo->beginTransaction();

            // Insert order
            $stmt = $pdo->prepare("INSERT INTO orders (member_id, total_amount, shipping_address, points_redeemed, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$member_id, $total_amount, $shipping_address, $points_redeemed]);
            $order_id = $pdo->lastInsertId();

            // Insert order items
            $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_name, quantity, price) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmt_item->execute([$order_id, $item['product_name'], $item['quantity'], $item['price']]);
            }

            // TODO: Deduct points from member if points_redeemed > 0
            // TODO: Update member points based on order

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Order created successfully', 'order_id' => $order_id]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
    case 'order_history':
        // Handle fetching order history
        $member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
        if ($member_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid member_id']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE member_id = ? ORDER BY order_date DESC");
            $stmt->execute([$member_id]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch order items for each order
            $stmt_items = $pdo->prepare("SELECT product_name, quantity, price FROM order_items WHERE order_id = ?");
            foreach ($orders as &$order) {
                $stmt_items->execute([$order['id']]);
                $order['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode(['status' => 'success', 'orders' => $orders]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'subscribe':
        // Handle subscription (email or phone)
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || (!isset($input['email']) && !isset($input['phone'])) || !isset($input['member_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            break;
        }
        $member_id = (int)$input['member_id'];
        $email = isset($input['email']) ? trim($input['email']) : null;
        $phone = isset($input['phone']) ? trim($input['phone']) : null;

        // Generate verification code
        $verification_code = substr(bin2hex(random_bytes(3)), 0, 6);

        try {
            // Insert or update subscription
            $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE member_id = ? AND (email = ? OR phone = ?)");
            $stmt->execute([$member_id, $email, $phone]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $pdo->prepare("UPDATE subscriptions SET verified = 0, verification_code = ?, created_at = NOW() WHERE id = ?");
                $stmt->execute([$verification_code, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO subscriptions (member_id, email, phone, verified, verification_code) VALUES (?, ?, ?, 0, ?)");
                $stmt->execute([$member_id, $email, $phone, $verification_code]);
            }

            // TODO: Send verification code via email or SMS

            echo json_encode(['status' => 'success', 'message' => 'Subscription created. Verification code sent.']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'update_profile':
        // Update member profile information
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['id'], $input['name'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            break;
        }
        $id = (int)$input['id'];
        $name = trim($input['name']);
        $phone = isset($input['phone']) ? trim($input['phone']) : null;
        $birthday = isset($input['birthday']) ? trim($input['birthday']) : null;
        $gender = isset($input['gender']) ? trim($input['gender']) : null;
        $address = isset($input['address']) ? trim($input['address']) : null;

        try {
            $stmt = $pdo->prepare("UPDATE members SET name = ?, phone = ?, birthday = ?, gender = ?, address = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $birthday, $gender, $address, $id]);

            // Return updated member info
            $stmt = $pdo->prepare("SELECT id, name, email, phone, birthday, gender, address, member_level, points FROM members WHERE id = ?");
            $stmt->execute([$id]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'success', 'message' => 'Profile updated', 'member' => $member]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'redeem_points':
        // Redeem points for member
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['member_id'], $input['points'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            break;
        }
        $member_id = (int)$input['member_id'];
        $points = (int)$input['points'];
        if ($points <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid points value']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT points FROM members WHERE id = ?");
            $stmt->execute([$member_id]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$member || $member['points'] < $points) {
                echo json_encode(['status' => 'error', 'message' => 'Insufficient points']);
                break;
            }
            $stmt = $pdo->prepare("UPDATE members SET points = points - ? WHERE id = ?");
            $stmt->execute([$points, $member_id]);
            echo json_encode(['status' => 'success', 'message' => 'Points redeemed successfully']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'referral_link':
        // Generate referral link for member
        $member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
        if ($member_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid member_id']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT referral_code FROM members WHERE id = ?");
            $stmt->execute([$member_id]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$member) {
                echo json_encode(['status' => 'error', 'message' => 'Member not found']);
                break;
            }
            $referral_code = $member['referral_code'];
            $referral_link = "https://yourdomain.com/register?ref=" . urlencode($referral_code);
            echo json_encode(['status' => 'success', 'referral_link' => $referral_link]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'mall_items':
        // List mall items available for redemption
        try {
            $stmt = $pdo->query("SELECT id, name, description, points_required, stock, active FROM mall_items WHERE active = 1");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'items' => $items]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'redeem_mall_item':
        // Redeem points for a mall item (create mall order)
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['member_id'], $input['mall_item_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            break;
        }
        $member_id = (int)$input['member_id'];
        $mall_item_id = (int)$input['mall_item_id'];

        try {
            $pdo->beginTransaction();

            // Check member points
            $stmt = $pdo->prepare("SELECT points FROM members WHERE id = ?");
            $stmt->execute([$member_id]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$member) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Member not found']);
                break;
            }

            // Check mall item availability and points required
            $stmt = $pdo->prepare("SELECT points_required, stock FROM mall_items WHERE id = ? AND active = 1");
            $stmt->execute([$mall_item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Mall item not found or inactive']);
                break;
            }
            if ($item['stock'] <= 0) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Mall item out of stock']);
                break;
            }
            if ($member['points'] < $item['points_required']) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Insufficient points']);
                break;
            }

            // Deduct points from member
            $stmt = $pdo->prepare("UPDATE members SET points = points - ? WHERE id = ?");
            $stmt->execute([$item['points_required'], $member_id]);

            // Decrease mall item stock
            $stmt = $pdo->prepare("UPDATE mall_items SET stock = stock - 1 WHERE id = ?");
            $stmt->execute([$mall_item_id]);

            // Create mall order
            $stmt = $pdo->prepare("INSERT INTO mall_orders (member_id, mall_item_id, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$member_id, $mall_item_id]);

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Mall item redeemed successfully']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'mall_orders':
        // List mall orders for a member
        $member_id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
        if ($member_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid member_id']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT mo.id, mo.order_date, mo.status, mi.name, mi.description FROM mall_orders mo JOIN mall_items mi ON mo.mall_item_id = mi.id WHERE mo.member_id = ? ORDER BY mo.order_date DESC");
            $stmt->execute([$member_id]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'orders' => $orders]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}
?>
