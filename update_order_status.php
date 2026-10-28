
<?php
session_start();
include "db.php";

// Check if the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// Only admins can update order status
if (($_SESSION["user_role"] ?? "") !== "admin") {
    die("Access denied. Admin only.");
}

// Accept POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: admin.php");
    exit();
}

$order_id = filter_var(
    $_POST["order_id"] ?? null,
    FILTER_VALIDATE_INT
);

$status = $_POST["status"] ?? "";

$allowed_statuses = [
    "Pending",
    "Preparing",
    "Ready",
    "Out for Delivery",
    "Completed"
];

if (
    $order_id === false ||
    $order_id === null ||
    $order_id <= 0 ||
    !in_array($status, $allowed_statuses, true)
) {
    die("Invalid order ID or status.");
}

// Check whether the order exists
$check = $conn->prepare(
    "SELECT id FROM orders WHERE id = ?"
);
$check->bind_param("i", $order_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    $check->close();
    die("Order not found.");
}
$check->close();

// Save the new status
$stmt = $conn->prepare(
    "UPDATE orders SET status = ? WHERE id = ?"
);
$stmt->bind_param("si", $status, $order_id);

if ($stmt->execute()) {
    $stmt->close();
    header("Location: admin.php");
    exit();
}

$stmt->close();
die("Unable to update order status.");
?>
