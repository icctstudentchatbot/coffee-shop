
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

// Delete product using POST
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_product_id"])) {

    $delete_id = (int) $_POST["delete_product_id"];

    if ($delete_id > 0) {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: admin_products.php?deleted=1");
    exit();
}

$result = $conn->query(
    "SELECT id, name, description, price, image
     FROM products ORDER BY id DESC"
);

if (!$result) {
    die("Unable to load products: " . htmlspecialchars($conn->error));
}

$products = [];
while ($product = $result->fetch_assoc()) {
    $products[] = $product;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Coffee Shop</title>

    <style>
        * { box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background: #f8f3ed;
            color: #4a3428;
            margin: 0;
            padding: 25px 15px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        h1, h2 { color: #4b2e24; }

        .nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
        }

        .button {
            display: inline-block;
            padding: 10px 14px;
            background: #7a4e3a;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .button:hover { background: #4b2e24; }

        .delete-button {
            background: #b33a3a;
        }

        .delete-button:hover {
            background: #872727;
        }

        .panel {
            background: #fffdf9;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(75,46,36,.08);
        }

        .table-wrapper { overflow-x: auto; }

        table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #eadfd4;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #4b2e24;
            color: white;
        }

        .description {
            max-width: 260px;
            overflow-wrap: anywhere;
        }

        .actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .actions form { margin: 0; }

        .notice {
            padding: 12px;
            border-radius: 6px;
            margin: 15px 0;
            background: #e7f5e8;
            color: #245b2a;
        }

        .empty { padding: 20px 0; }

        @media (max-width: 600px) {
            .panel { padding: 14px; }
        }
    </style>
</head>

<body>
<div class="container">

    <h1>☕ Manage Coffee Products</h1>

    <p>
        Welcome,
        <?= htmlspecialchars($_SESSION["user_name"] ?? "Admin") ?>!
    </p>

    <div class="nav">
        <a class="button" href="admin.php">← Admin Dashboard</a>
        <a class="button" href="add_product.php">+ Add Product</a>
        <a class="button" href="index.php">View Coffee Shop</a>
    </div>

    <?php if (isset($_GET["deleted"])): ?>
        <div class="notice">Product deleted successfully.</div>
    <?php endif; ?>

    <div class="panel">
        <h2>Product List</h2>

        <p>Total Products: <strong><?= count($products) ?></strong></p>

        <?php if (count($products) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Action</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= (int) $product["id"] ?></td>

                            <td>
                                <?= htmlspecialchars($product["name"]) ?>
                            </td>

                            <td class="description">
                                <?= htmlspecialchars($product["description"] ?? "") ?>
                            </td>

                            <td>
                                ₱<?= number_format((float) $product["price"], 2) ?>
                            </td>

                            <td>
                                <div class="actions">
                                    <a class="button"
                                       href="edit_product.php?id=<?= (int) $product["id"] ?>">
                                        Edit
                                    </a>

                                    <form method="POST"
                                          onsubmit="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">
                                        <input type="hidden"
                                               name="delete_product_id"
                                               value="<?= (int) $product["id"] ?>">

                                        <button type="submit"
                                                class="button delete-button">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="empty">
                No products found. Click Add Product to add your first coffee.
            </p>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
