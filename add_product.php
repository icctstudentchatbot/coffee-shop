
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

$message = "";
$message_type = "";

$name = "";
$description = "";
$price = "";
$image = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price_input = $_POST["price"] ?? "";
    $image = trim($_POST["image"] ?? "");

    if (
        $name === "" ||
        $description === "" ||
        !is_numeric($price_input) ||
        (float)$price_input <= 0
    ) {
        $message = "Please complete all required fields correctly.";
        $message_type = "error";
    } else {
        $price = (float)$price_input;

        $stmt = $conn->prepare(
            "INSERT INTO products (name, description, price, image)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param("ssds", $name, $description, $price, $image);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: admin_products.php?added=1");
            exit();
        } else {
            $message = "Failed to add product. Please try again.";
            $message_type = "error";
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
    <title>Add Product | Coffee Shop</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            font-family: Arial, sans-serif;
            background: #f8f3ed;
            color: #4a3428;
        }

        .form-box {
            max-width: 550px;
            margin: 20px auto;
            padding: 30px;
            background: #fffdf9;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(75, 46, 36, 0.10);
        }

        h1 {
            margin-top: 0;
            color: #4b2e24;
            text-align: center;
        }

        .subtitle {
            margin-bottom: 25px;
            color: #725c4c;
            text-align: center;
            line-height: 1.5;
        }

        label {
            display: block;
            margin: 17px 0 7px;
            font-weight: bold;
        }

        input,
        textarea {
            display: block;
            width: 100%;
            padding: 12px;
            border: 1px solid #d8c6b8;
            border-radius: 7px;
            background: white;
            color: #4a3428;
            font: inherit;
        }

        input:focus,
        textarea:focus {
            outline: 2px solid #b78b70;
            border-color: #7a4e3a;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .hint {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #725c4c;
        }

        button,
        .back {
            display: block;
            width: 100%;
            padding: 13px;
            margin-top: 20px;
            border: none;
            border-radius: 7px;
            background: #7a4e3a;
            color: white;
            text-align: center;
            text-decoration: none;
            font-size: 15px;
            cursor: pointer;
        }

        button:hover,
        .back:hover {
            background: #4b2e24;
        }

        .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 7px;
            text-align: center;
        }

        .error {
            background: #fbe9e7;
            color: #9c2f26;
        }

        @media (max-width: 480px) {
            .form-box {
                padding: 22px 18px;
            }
        }
    </style>
</head>

<body>
    <div class="form-box">
        <h1>☕ Add New Coffee</h1>

        <p class="subtitle">
            Add a coffee product to your shop menu.
        </p>

        <?php if ($message !== ""): ?>
            <div class="message <?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="add_product.php">
            <label for="name">Product Name</label>
            <input
                type="text"
                id="name"
                name="name"
                placeholder="Example: Vanilla Cream Latte"
                value="<?= htmlspecialchars($name) ?>"
                maxlength="150"
                required
            >

            <label for="description">Description</label>
            <textarea
                id="description"
                name="description"
                placeholder="Describe your coffee"
                required
            ><?= htmlspecialchars($description) ?></textarea>

            <label for="price">Price (₱)</label>
            <input
                type="number"
                id="price"
                name="price"
                step="0.01"
                min="0.01"
                placeholder="Example: 129.00"
                value="<?= htmlspecialchars((string)$price) ?>"
                required
            >

            <label for="image">Image File Name</label>
            <input
                type="text"
                id="image"
                name="image"
                placeholder="Example: vanilla-latte.jpg"
                value="<?= htmlspecialchars($image) ?>"
                maxlength="255"
            >
            <span class="hint">
                Optional. Enter the image filename used by your project.
            </span>

            <button type="submit">＋ Add Product</button>
        </form>

        <a class="back" href="admin_products.php">
            ← Back to Product Management
        </a>
    </div>
</body>
</html>
