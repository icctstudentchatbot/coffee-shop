
<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: cart.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$cart_id = (int) ($_POST["cart_id"] ?? 0);
$quantity = filter_var(
    $_POST["quantity"] ?? null,
    FILTER_VALIDATE_INT
);

if ($cart_id <= 0 || $quantity === false ||
    $quantity === null || $quantity < 1 || $quantity > 99) {
    header("Location: cart.php");
    exit();
}

$stmt = $conn->prepare(
    "UPDATE cart
     SET quantity = ?
     WHERE id = ? AND user_id = ?"
);

$stmt->bind_param("iii", $quantity, $cart_id, $user_id);
$stmt->execute();
$stmt->close();

header("Location: cart.php");
exit();
?>
