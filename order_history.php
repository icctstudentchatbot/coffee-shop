<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

$sql = "SELECT *
        FROM orders
        WHERE user_id = ?
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];

while ($order = $result->fetch_assoc()) {
    $order_id = (int) $order["id"];

    $items_sql = "SELECT
                    id,
                    product_name,
                    price,
                    quantity,
                    size,
                    shot_type,
                    sugar_level
                  FROM order_items
                  WHERE order_id = ?
                  ORDER BY id ASC";

    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();

    $order["items"] = [];

    while ($item = $items_result->fetch_assoc()) {
        $item_id = (int) $item["id"];
        $item["price"] = (float) $item["price"];
        $item["quantity"] = (int) $item["quantity"];
        $item["addons"] = [];
        $item["addon_unit_total"] = 0.0;

        $addons_sql = "SELECT addon_name, price, quantity
                       FROM order_item_addons
                       WHERE order_item_id = ?
                       ORDER BY id ASC";
        $addons_stmt = $conn->prepare($addons_sql);
        $addons_stmt->bind_param("i", $item_id);
        $addons_stmt->execute();
        $addons_result = $addons_stmt->get_result();

        while ($addon = $addons_result->fetch_assoc()) {
            $addon["price"] = (float) $addon["price"];
            $addon["quantity"] = (int) $addon["quantity"];
            $item["addon_unit_total"] += $addon["price"] * $addon["quantity"];
            $item["addons"][] = $addon;
        }

        $addons_stmt->close();

        $item["unit_total"] = $item["price"] + $item["addon_unit_total"];
        $item["subtotal"] = $item["unit_total"] * $item["quantity"];
        $order["items"][] = $item;
    }

    $items_stmt->close();
    $orders[] = $order;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Coffee Shop</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8f3ed;
            color: #4a3428;
        }
        header {
            background: #4b2e24;
            color: white;
            padding: 18px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        header h2 { margin: 0; font-size: 23px; }
        nav { display: flex; gap: 18px; flex-wrap: wrap; }
        nav a { color: white; text-decoration: none; font-size: 14px; }
        nav a:hover { color: #e8c9a8; }
        .container { width: 92%; max-width: 950px; margin: 35px auto; }
        h1 { margin-bottom: 8px; }
        .subtitle { color: #806b5d; margin-bottom: 25px; }
        .order-card {
            background: white;
            margin-bottom: 22px;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(60, 38, 25, 0.08);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            flex-wrap: wrap;
            padding-bottom: 16px;
            border-bottom: 1px solid #eee4da;
        }
        .order-header h2 { margin: 0 0 7px; font-size: 19px; }
        .muted { color: #806b5d; font-size: 13px; line-height: 1.7; }
        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #eee2d5;
            color: #4b2e24;
            font-size: 12px;
            font-weight: bold;
        }
        .order-info { padding: 15px 0; font-size: 14px; line-height: 1.9; overflow-wrap: anywhere; }
        .item { padding: 14px 0; border-top: 1px solid #eee4da; }
        .item-heading { display: flex; justify-content: space-between; gap: 12px; font-weight: bold; }
        .item-details { margin-top: 6px; color: #806b5d; font-size: 13px; line-height: 1.8; }
        .total {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            border-top: 1px solid #eee4da;
            padding-top: 17px;
            margin-top: 10px;
            font-size: 19px;
            font-weight: bold;
        }
        .empty { background: white; padding: 35px 20px; text-align: center; border-radius: 12px; }
        .button {
            display: inline-block;
            margin-top: 18px;
            padding: 11px 16px;
            background: #4b2e24;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
        }
        footer { margin-top: 50px; padding: 20px; text-align: center; background: #4b2e24; color: white; font-size: 13px; }
        @media (max-width: 600px) {
            .container { width: 94%; margin-top: 25px; }
            .order-card { padding: 18px; }
            .item-heading { flex-direction: column; gap: 5px; }
        }
    </style>
</head>
<body>
<header>
    <h2>☕ Coffee Shop</h2>
    <nav>
        <a href="index.php">Home</a>
        <a href="index.php#menu">Menu</a>
        <a href="cart.php">Cart</a>
        <a href="order_history.php">My Orders</a>
    </nav>
</header>

<div class="container">
    <h1>My Orders</h1>
    <p class="subtitle">View your previous coffee orders and their details.</p>

    <?php if (empty($orders)): ?>
        <div class="empty">
            <h2>No orders yet ☕</h2>
            <p>Your placed orders will appear here.</p>
            <a href="index.php#menu" class="button">Browse Coffee Menu</a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <h2>Order #<?= (int) $order["id"]; ?></h2>
                        <div class="muted">
                            Date: <?= htmlspecialchars($order["created_at"] ?? $order["order_date"] ?? "Not available", ENT_QUOTES, "UTF-8"); ?>
                        </div>
                    </div>
                    <span class="status"><?= htmlspecialchars($order["status"] ?? "Pending", ENT_QUOTES, "UTF-8"); ?></span>
                </div>

                <div class="order-info">
                    <strong>Customer:</strong> <?= htmlspecialchars($order["customer_name"] ?? "", ENT_QUOTES, "UTF-8"); ?><br>
                    <strong>Contact:</strong> <?= htmlspecialchars($order["contact_number"] ?? "", ENT_QUOTES, "UTF-8"); ?><br>
                    <strong>Order Type:</strong> <?= htmlspecialchars($order["order_type"] ?? "", ENT_QUOTES, "UTF-8"); ?><br>
                    <?php if (($order["order_type"] ?? "") === "Delivery"): ?>
                        <strong>Delivery Address:</strong> <?= htmlspecialchars($order["delivery_address"] ?? "", ENT_QUOTES, "UTF-8"); ?><br>
                    <?php endif; ?>
                </div>

                <h3>Ordered Coffee</h3>

                <?php if (!empty($order["items"])): ?>
                    <?php foreach ($order["items"] as $item): ?>
                        <div class="item">
                            <div class="item-heading">
                                <span><?= htmlspecialchars($item["product_name"], ENT_QUOTES, "UTF-8"); ?> × <?= (int) $item["quantity"]; ?></span>
                                <span>₱<?= number_format((float) $item["subtotal"], 2); ?></span>
                            </div>
                            <div class="item-details">
                                Temperature: <?= htmlspecialchars($item["size"] ?? "", ENT_QUOTES, "UTF-8"); ?><br>
                                Espresso Shot: <?= htmlspecialchars($item["shot_type"] ?? "Ristretto", ENT_QUOTES, "UTF-8"); ?><br>
                                Sugar Level: <?= htmlspecialchars($item["sugar_level"] ?? "100%", ENT_QUOTES, "UTF-8"); ?><br>
                                Coffee Unit Price: ₱<?= number_format((float) $item["price"], 2); ?><br>
                                Quantity: <?= (int) $item["quantity"]; ?><br>

                                <?php if (!empty($item["addons"])): ?>
                                    <strong>Add-ons per cup:</strong><br>
                                    <?php foreach ($item["addons"] as $addon): ?>
                                        <?= htmlspecialchars($addon["addon_name"], ENT_QUOTES, "UTF-8"); ?>
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
                <?php else: ?>
                    <p class="muted">
                        Item details are unavailable for this order. This may be an older order created before order details were saved separately.
                    </p>
                <?php endif; ?>

                <div class="total">
                    <span>Total Amount</span>
                    <span>₱<?= number_format((float) ($order["total_amount"] ?? $order["total"] ?? 0), 2); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<footer>© <?= date("Y"); ?> Coffee Shop. All rights reserved.</footer>
</body>
</html>
