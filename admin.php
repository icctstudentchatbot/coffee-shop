
<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION["user_role"] ?? "") !== "admin") {
    die("Access denied. Admin only.");
}

$sql = "SELECT id, customer_name, contact_number, order_type,
               delivery_address, total_amount, status, created_at
        FROM orders
        ORDER BY id DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Unable to load orders.");
}

$total_orders = $result->num_rows;
$pending_orders = 0;
$preparing_orders = 0;
$completed_orders = 0;
$orders = [];

while ($order = $result->fetch_assoc()) {
    $orders[] = $order;

    if ($order["status"] === "Pending") {
        $pending_orders++;
    } elseif ($order["status"] === "Preparing") {
        $preparing_orders++;
    } elseif ($order["status"] === "Completed") {
        $completed_orders++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Coffee Shop</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px 15px;
            font-family: Arial, sans-serif;
            background: #f8f3ed;
            color: #4a3428;
        }

        .dashboard {
            max-width: 1200px;
            margin: auto;
        }

        h1, h2 {
            color: #4b2e24;
        }

        .welcome {
            margin-bottom: 20px;
        }

        .navigation {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 20px 0 30px;
        }

        .nav-button {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 7px;
            background: #7a4e3a;
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .nav-button:hover {
            background: #4b2e24;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .stat-card {
            padding: 22px;
            background: #fffdf9;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(75, 46, 36, 0.08);
        }

        .stat-card p {
            margin: 0 0 10px;
            color: #725c4c;
        }

        .stat-card strong {
            font-size: 28px;
            color: #4b2e24;
        }

        .orders-panel {
            background: #fffdf9;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(75, 46, 36, 0.08);
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 13px 12px;
            border-bottom: 1px solid #eadfd4;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #4b2e24;
            color: white;
        }

        tr:hover td {
            background: #faf4ed;
        }

        select {
            padding: 9px;
            border: 1px solid #d8c6b8;
            border-radius: 6px;
            background: white;
            color: #4a3428;
        }

        button, .back {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 6px;
            background: #7a4e3a;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        button:hover, .back:hover {
            background: #4b2e24;
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .empty {
            padding: 25px 0;
            text-align: center;
        }

        .address {
            max-width: 220px;
            overflow-wrap: anywhere;
        }

        @media (max-width: 600px) {
            .orders-panel {
                padding: 15px;
            }

            h1 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>
<div class="dashboard">

    <h1>☕ Admin Dashboard</h1>

    <p class="welcome">
        Welcome, <?= htmlspecialchars($_SESSION["user_name"] ?? "Admin") ?>!
    </p>

    <div class="navigation">
        <a class="nav-button" href="admin_products.php">
            ☕ Manage Products
        </a>

        <a class="nav-button" href="index.php">
            🏠 View Coffee Shop
        </a>

        <a class="nav-button" href="#customer-orders">
            📋 Customer Orders
        </a>
    </div>

    <div class="stats">
        <div class="stat-card">
            <p>Total Orders</p>
            <strong><?= $total_orders ?></strong>
        </div>

        <div class="stat-card">
            <p>Pending Orders</p>
            <strong><?= $pending_orders ?></strong>
        </div>

        <div class="stat-card">
            <p>Preparing Orders</p>
            <strong><?= $preparing_orders ?></strong>
        </div>

        <div class="stat-card">
            <p>Completed Orders</p>
            <strong><?= $completed_orders ?></strong>
        </div>
    </div>

    <div class="orders-panel" id="customer-orders">
        <h2>Customer Orders</h2>

        <?php if (count($orders) > 0): ?>

            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Type</th>
                        <th>Delivery Address</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= (int) $order["id"] ?></td>

                            <td>
                                <?= htmlspecialchars($order["customer_name"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($order["contact_number"]) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($order["order_type"]) ?>
                            </td>

                            <td class="address">
                                <?php if ($order["order_type"] === "Delivery"): ?>
                                    <?= htmlspecialchars($order["delivery_address"] ?? "") ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td>
                                ₱<?= number_format((float) $order["total_amount"], 2) ?>
                            </td>

                            <td>
                                <form
                                    class="status-form"
                                    action="update_order_status.php"
                                    method="POST"
                                >
                                    <input
                                        type="hidden"
                                        name="order_id"
                                        value="<?= (int) $order["id"] ?>"
                                    >

                                    <select name="status" required>
                                        <?php
                                        $statuses = [
                                            "Pending",
                                            "Preparing",
                                            "Ready",
                                            "Out for Delivery",
                                            "Completed"
                                        ];

                                        foreach ($statuses as $status):
                                        ?>
                                            <option
                                                value="<?= htmlspecialchars($status) ?>"
                                                <?= $order["status"] === $status ? "selected" : "" ?>
                                            >
                                                <?= htmlspecialchars($status) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                            </td>

                            <td>
                                    <button type="submit">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="empty">
                <h3>No Orders Found</h3>
                <p>Customer orders will appear here after they place an order.</p>
            </div>
        <?php endif; ?>

        <a class="back" href="index.php">← Back to Home</a>
    </div>

</div>
</body>
</html>
