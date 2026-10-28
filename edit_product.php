
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

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    die("Invalid product ID.");
}

$message = "";
$name = "";
$description = "";
$price = "";
$image = "";

$stmt = $conn->prepare(
    "SELECT id, name, description, price, image FROM products WHERE id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    die("Product not found.");
}

$name = $product["name"];
$description = $product["description"] ?? "";
$price = $product["price"];
$image = $product["image"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = trim($_POST["price"] ?? "");
    $image = trim($_POST["image"] ?? "");

    if ($name === "" || $price === "" ||
        !is_numeric($price) || (float)$price <= 0) {
        $message = "Please enter a product name and a valid price.";
    } else {
        $priceValue = (float)$price;

        $stmt = $conn->prepare(
            "UPDATE products
             SET name = ?, description = ?, price = ?, image = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "ssdsi",
            $name,
            $description,
            $priceValue,
            $image,
            $id
        );

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: admin_products.php?updated=1");
            exit();
        } else {
            $message = "Unable to update product. Please try again.";
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Coffee Shop</title>

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
            max-width: 600px;
            margin: auto;
        }

        .panel {
            background: #fffdf9;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 10px rgba(75, 46, 36, .08);
        }

        h1 { color: #4b2e24; }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            padding: 11px;
            margin-top: 7px;
            border: 1px solid #d8c6b8;
            border-radius: 6px;
            font: inherit;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .button {
            display: inline-block;
            margin-top: 18px;
            padding: 12px 17px;
            background: #7a4e3a;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .button:hover { background: #4b2e24; }

        .message {
            color: #b00020;
            margin-top: 12px;
        }
    </style>
</head>

<body>
<div class="container">
    <div class="panel">
        <h1>☕ Edit Coffee Product</h1>

        <p>Product ID: <?= (int)$id ?></p>

        <?php if ($message !== ""): ?>
            <p class="message">
                <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <label for="name">Product Name</label>
            <input
                type="text"
                id="name"
                name="name"
                maxlength="255"
                value="<?= htmlspecialchars($name) ?>"
                required
            >

            <label for="description">Description</label>
            <textarea
                id="description"
                name="description"
            ><?= htmlspecialchars($description) ?></textarea>

            <label for="price">Price (₱)</label>
            <input
                type="number"
                id="price"
                name="price"
                min="0.01"
                step="0.01"
                value="<?= htmlspecialchars((string)$price) ?>"
                required
            >

            <label for="image">Image filename or path (optional)</label>
            <input
                type="text"
                id="image"
                name="image"
                value="<?= htmlspecialchars($image) ?>"
            >

            <button class="button" type="submit">
                Save Changes
            </button>

            <a class="button" href="admin_products.php">
                Cancel
            </a>
        </form>
    </div>
</div>
</body>
</html>
