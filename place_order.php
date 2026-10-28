<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: checkout.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

$customer_name = trim($_POST["customer_name"] ?? "");
$contact_number = trim($_POST["contact_number"] ?? "");
$order_type = $_POST["order_type"] ?? "";
$delivery_address = trim($_POST["delivery_address"] ?? "");

if ($customer_name === "" || strlen($customer_name) > 100) {
    die("Please enter a valid full name.");
}

if (!preg_match('/^09[0-9]{9}$/', $contact_number)) {
    die("Please enter a valid 11-digit Philippine mobile number.");
}

if (!in_array($order_type, ["Pickup", "Delivery"], true)) {
    die("Invalid order type.");
}

if ($order_type === "Delivery" && $delivery_address === "") {
    die("Please enter your delivery address.");
}

if (strlen($delivery_address) > 500) {
    die("Delivery address is too long.");
}

if ($order_type === "Pickup") {
    $delivery_address = "";
}

$order_id = 0;
$items = [];
$total = 0.0;

$conn->begin_transaction();

try {
    // Load this customer's cart and include each cart row's ID.
    $cart_sql = "SELECT
                    cart.id AS cart_id,
                    cart.product_id,
                    products.name AS product_name,
                    products.price,
                    cart.quantity,
                    cart.size,
                    cart.shot_type,
                    cart.sugar_level
                 FROM cart
                 INNER JOIN products ON cart.product_id = products.id
                 WHERE cart.user_id = ?
                 ORDER BY cart.id ASC
                 FOR UPDATE";

    $cart_stmt = $conn->prepare($cart_sql);
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();

    while ($row = $cart_result->fetch_assoc()) {
        $quantity = (int) $row["quantity"];
        $price = (float) $row["price"];

        if ($quantity < 1 || $price < 0) {
            throw new Exception("Invalid cart item.");
        }

        $row["cart_id"] = (int) $row["cart_id"];
        $row["quantity"] = $quantity;
        $row["price"] = $price;
        $row["addons"] = [];
        $row["addon_unit_total"] = 0.0;
        $items[] = $row;
    }

    $cart_stmt->close();

    if (empty($items)) {
        throw new Exception("Your cart is empty. Please add coffee before checkout.");
    }

    // Load add-ons selected for each cart item.
    $addon_sql = "SELECT
                    cart_addons.cart_id,
                    addons.name AS addon_name,
                    addons.price AS addon_price,
                    cart_addons.quantity AS addon_quantity
                  FROM cart_addons
                  INNER JOIN addons ON cart_addons.addon_id = addons.id
                  WHERE addons.is_active = 1
                  ORDER BY cart_addons.id ASC";

    $addon_result = $conn->query($addon_sql);
    if (!$addon_result) {
        throw new Exception("Unable to load selected add-ons. Please check your add-ons tables.");
    }

    $addons_by_cart_id = [];
    while ($addon = $addon_result->fetch_assoc()) {
        $cart_id = (int) $addon["cart_id"];
        $addon_quantity = (int) $addon["addon_quantity"];
        $addon_price = (float) $addon["addon_price"];

        if ($addon_quantity < 1 || $addon_price < 0) {
            throw new Exception("Invalid add-on quantity or price.");
        }

        if (!isset($addons_by_cart_id[$cart_id])) {
            $addons_by_cart_id[$cart_id] = [];
        }

        $addons_by_cart_id[$cart_id][] = [
            "name" => $addon["addon_name"],
            "price" => $addon_price,
            "quantity" => $addon_quantity
        ];
    }

    // Add-on quantity is per cup, so multiply the customized cup price by cart quantity.
    foreach ($items as &$item) {
        $cart_id = (int) $item["cart_id"];
        $item_addons = $addons_by_cart_id[$cart_id] ?? [];
        $addon_unit_total = 0.0;

        foreach ($item_addons as $addon) {
            $addon_unit_total += $addon["price"] * $addon["quantity"];
        }

        $item["addons"] = $item_addons;
        $item["addon_unit_total"] = $addon_unit_total;
        $item["unit_total"] = $item["price"] + $addon_unit_total;
        $item["subtotal"] = $item["unit_total"] * $item["quantity"];
        $total += $item["subtotal"];
    }
    unset($item);

    // Create the main order with coffee and add-on totals included.
    $order_sql = "INSERT INTO orders
                    (user_id, customer_name, contact_number,
                     order_type, delivery_address, total_amount, status)
                  VALUES (?, ?, ?, ?, ?, ?, 'Pending')";

    $order_stmt = $conn->prepare($order_sql);
    $order_stmt->bind_param(
        "issssd",
        $user_id,
        $customer_name,
        $contact_number,
        $order_type,
        $delivery_address,
        $total
    );

    if (!$order_stmt->execute()) {
        throw new Exception("Unable to create the order.");
    }

    $order_id = (int) $conn->insert_id;
    $order_stmt->close();

    // Save each ordered coffee and its customizations.
    $item_sql = "INSERT INTO order_items
                    (order_id, product_id, product_name, price,
                     quantity, size, shot_type, sugar_level)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $item_stmt = $conn->prepare($item_sql);

    // Save add-ons separately so order history can display them accurately.
    $order_addon_sql = "INSERT INTO order_item_addons
                          (order_item_id, addon_name, price, quantity)
                        VALUES (?, ?, ?, ?)";
    $order_addon_stmt = $conn->prepare($order_addon_sql);

    foreach ($items as $item) {
        $product_id = (int) $item["product_id"];
        $product_name = $item["product_name"];
        $price = (float) $item["price"];
        $quantity = (int) $item["quantity"];
        $size = $item["size"] ?? "";
        $shot_type = $item["shot_type"] ?? "Ristretto";
        $sugar_level = $item["sugar_level"] ?? "100%";

        if (!in_array($shot_type, ["Ristretto", "Doppio"], true)) {
            $shot_type = "Ristretto";
        }

        if (!in_array($sugar_level, ["0%", "25%", "50%", "75%", "100%"], true)) {
            $sugar_level = "100%";
        }

        $item_stmt->bind_param(
            "iisdiiss",
            $order_id,
            $product_id,
            $product_name,
            $price,
            $quantity,
            $size,
            $shot_type,
            $sugar_level
        );

        if (!$item_stmt->execute()) {
            throw new Exception("Unable to save an ordered coffee.");
        }

        $order_item_id = (int) $conn->insert_id;

        foreach ($item["addons"] as $addon) {
            $addon_name = $addon["name"];
            $addon_price = (float) $addon["price"];
            $addon_quantity = (int) $addon["quantity"];

            $order_addon_stmt->bind_param(
                "isdi",
                $order_item_id,
                $addon_name,
                $addon_price,
                $addon_quantity
            );

            if (!$order_addon_stmt->execute()) {
                throw new Exception("Unable to save an order add-on.");
            }
        }
    }

    $item_stmt->close();
    $order_addon_stmt->close();

    // Remove selected add-ons before deleting this customer's cart rows.
    $delete_addons_stmt = $conn->prepare(
        "DELETE cart_addons
         FROM cart_addons
         INNER JOIN cart ON cart_addons.cart_id = cart.id
         WHERE cart.user_id = ?"
    );
    $delete_addons_stmt->bind_param("i", $user_id);

    if (!$delete_addons_stmt->execute()) {
        throw new Exception("Unable to clear cart add-ons.");
    }
    $delete_addons_stmt->close();

    // Clear only this customer's cart after saving the order.
    $delete_stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $delete_stmt->bind_param("i", $user_id);

    if (!$delete_stmt->execute()) {
        throw new Exception("Unable to clear the shopping cart.");
    }
    $delete_stmt->close();

    $conn->commit();

} catch (Throwable $e) {
    $conn->rollback();
    error_log("Place order error: " . $e->getMessage());
    die("Order could not be placed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8"));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Successful | Coffee Shop</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 25px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            background: #f8f3ed;
            color: #4a3428;
        }
        .success-card {
            width: 100%;
            max-width: 700px;
            padding: 30px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(60, 38, 25, 0.1);
        }
        .success-icon { font-size: 42px; margin-bottom: 8px; }
        h1 { margin: 0 0 10px; color: #4b2e24; }
        .muted { color: #806b5d; line-height: 1.6; }
        .order-info {
            margin-top: 22px;
            padding: 18px;
            background: #f8f3ed;
            border-radius: 8px;
            line-height: 1.9;
            overflow-wrap: anywhere;
        }
        .ordered-item { padding: 14px 0; border-bottom: 1px solid #eee4da; }
        .item-heading {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-weight: bold;
        }
        .item-details {
            margin-top: 6px;
            color: #806b5d;
            font-size: 13px;
            line-height: 1.8;
        }
        .total {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 20px;
            font-size: 20px;
            font-weight: bold;
        }
        .button {
            display: inline-block;
            margin: 20px 8px 0 0;
            padding: 11px 16px;
            background: #4b2e24;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
        }
        .secondary { background: #e8ded2; color: #4b2e24; }
        @media (max-width: 500px) {
            .success-card { padding: 20px; }
            .item-heading { flex-direction: column; gap: 5px; }
        }
    </style>
</head>
<body>
<div class="success-card">
    <div class="success-icon">☕ ✓</div>
    <h1>Order Successful!</h1>
    <p class="muted">
        Thank you, <?= htmlspecialchars($customer_name, ENT_QUOTES, "UTF-8"); ?>!
        Your coffee order has been placed successfully.
    </p>

    <div class="order-info">
        <strong>Order #<?= (int) $order_id; ?></strong><br>
        Order Type: <strong><?= htmlspecialchars($order_type, ENT_QUOTES, "UTF-8"); ?></strong><br>
        <?php if ($order_type === "Delivery"): ?>
            Delivery Address:
            <strong><?= htmlspecialchars($delivery_address, ENT_QUOTES, "UTF-8"); ?></strong><br>
        <?php endif; ?>
        Contact Number: <strong><?= htmlspecialchars($contact_number, ENT_QUOTES, "UTF-8"); ?></strong><br>
        Status: <strong>Pending</strong>
    </div>

    <h2>Order Details</h2>

    <?php foreach ($items as $item): ?>
        <div class="ordered-item">
            <div class="item-heading">
                <span><?= htmlspecialchars($item["product_name"], ENT_QUOTES, "UTF-8"); ?></span>
                <span>₱<?= number_format($item["subtotal"], 2); ?></span>
            </div>

            <div class="item-details">
                Temperature: <?= htmlspecialchars($item["size"] ?? "", ENT_QUOTES, "UTF-8"); ?><br>
                Espresso Shot: <?= htmlspecialchars($item["shot_type"] ?? "Ristretto", ENT_QUOTES, "UTF-8"); ?><br>
                Sugar Level: <?= htmlspecialchars($item["sugar_level"] ?? "100%", ENT_QUOTES, "UTF-8"); ?><br>
                Quantity: <?= (int) $item["quantity"]; ?><br>
                Coffee Unit Price: ₱<?= number_format((float) $item["price"], 2); ?><br>

                <?php if (!empty($item["addons"])): ?>
                    <strong>Add-ons per cup:</strong><br>
                    <?php foreach ($item["addons"] as $addon): ?>
                        <?= htmlspecialchars($addon["name"], ENT_QUOTES, "UTF-8"); ?>
                        (<?= (int) $addon["quantity"]; ?> × ₱<?= number_format((float) $addon["price"], 2); ?>)
                        — ₱<?= number_format((float) $addon["price"] * (int) $addon["quantity"], 2); ?><br>
                    <?php endforeach; ?>
                    Add-ons per cup total: ₱<?= number_format((float) $item["addon_unit_total"], 2); ?><br>
                    Total per cup: ₱<?= number_format((float) $item["unit_total"], 2); ?>
                <?php else: ?>
                    Add-ons: None<br>
                    Total per cup: ₱<?= number_format((float) $item["unit_total"], 2); ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="total">
        <span>Total Amount</span>
        <span>₱<?= number_format($total, 2); ?></span>
    </div>

    <a href="index.php" class="button">Back to Home</a>
    <a href="order_history.php" class="button secondary">My Orders</a>
</div>
</body>
</html>
