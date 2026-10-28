
<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: cart.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$cart_id = (int) $_GET["id"];

if ($cart_id <= 0) {
    header("Location: cart.php");
    exit();
}

$stmt = $conn->prepare(
    "DELETE FROM cart WHERE id = ? AND user_id = ?"
);

$stmt->bind_param("ii", $cart_id, $user_id);
$stmt->execute();
$stmt->close();

header("Location: cart.php");
exit();
?>
