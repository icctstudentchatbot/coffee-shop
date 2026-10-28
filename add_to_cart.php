
<?php
session_start();
require_once "db.php";

/* Require login */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

$temperature = $_POST['temperature'] ?? '';
$volume = $temperature === 'Hot' ? '8 oz' : '16 oz';
$shot_type = $_POST['shot_type'] ?? '';
$sugar_level = $_POST['sugar_level'] ?? '';
$milk_option_id = filter_input(
    INPUT_POST,
    'milk_option_id',
    FILTER_VALIDATE_INT
);

$allowed_temperatures = ['Hot', 'Iced'];
$allowed_shots = ['No Espresso', 'Ristretto', 'Doppio'];
$allowed_sugar_levels = ['0%', '25%', '50%', '75%', '100%'];

if (!$product_id || $product_id < 1) {
    die("Invalid coffee product.");
}

if (!in_array($temperature, $allowed_temperatures, true)) {
    die("Invalid temperature selection.");
}

if (!in_array($shot_type, $allowed_shots, true)) {
    die("Invalid espresso shot selection.");
}

if (!in_array($sugar_level, $allowed_sugar_levels, true)) {
    die("Invalid sugar level selection.");
}

if (!$milk_option_id || $milk_option_id < 1) {
    die("Please select a milk option.");
}

/* Validate product */
$productStmt = $conn->prepare(
    "SELECT id FROM products WHERE id = ?"
);

if (!$productStmt) {
    die("Unable to check product.");
}

$productStmt->bind_param("i", $product_id);
$productStmt->execute();
$productResult = $productStmt->get_result();
$productExists = $productResult->num_rows > 0;
$productStmt->close();

if (!$productExists) {
    die("The selected coffee product does not exist.");
}

/* Validate selected milk option */
$allowed_milk_names = [
    'Regular Milk',
    'Oat Milk',
    'Almond Milk',
    'Non-Fat Milk'
];

$milkStmt = $conn->prepare(
    "SELECT id, name, price
     FROM addons
     WHERE id = ? AND is_active = 1"
);

if (!$milkStmt) {
    die("Unable to check milk option.");
}

$milkStmt->bind_param("i", $milk_option_id);
$milkStmt->execute();
$milkResult = $milkStmt->get_result();
$milk = $milkResult->fetch_assoc();
$milkStmt->close();

if (
    !$milk ||
    !in_array($milk['name'], $allowed_milk_names, true)
) {
    die("Invalid milk option. Please return to the menu and try again.");
}

/* Collect regular add-ons */
$submitted_addon_ids = $_POST['addon_ids'] ?? [];

if (!is_array($submitted_addon_ids)) {
    die("Invalid add-on selection.");
}

$submitted_addon_ids = array_values(array_unique(array_filter(
    array_map('intval', $submitted_addon_ids),
    function ($id) {
        return $id > 0;
    }
)));

/*
 * Validate selected add-ons against active database records.
 * Milk options are handled separately and cannot be added twice.
 */
$selected_addons = [];

if (!empty($submitted_addon_ids)) {
    $placeholders = implode(
        ',',
        array_fill(0, count($submitted_addon_ids), '?')
    );

    $addonSql = "
        SELECT id, name, price
        FROM addons
        WHERE is_active = 1
          AND id IN ($placeholders)
    ";

    $addonStmt = $conn->prepare($addonSql);

    if (!$addonStmt) {
        die("Unable to check selected add-ons.");
    }

    $types = str_repeat('i', count($submitted_addon_ids));
    $addonStmt->bind_param($types, ...$submitted_addon_ids);
    $addonStmt->execute();

    $addonResult = $addonStmt->get_result();

    while ($addon = $addonResult->fetch_assoc()) {
        if (in_array($addon['name'], $allowed_milk_names, true)) {
            continue;
        }

        $selected_addons[] = $addon;
    }

    $addonStmt->close();
}

/* Validate Syrup Pump quantity */
$syrup_quantity = isset($_POST['syrup_pump_quantity'])
    ? (int) $_POST['syrup_pump_quantity']
    : 1;

if ($syrup_quantity < 1 || $syrup_quantity > 5) {
    die("Invalid Syrup Pump quantity.");
}

/* Save cart and selections together */
$conn->begin_transaction();

try {
    $cartStmt = $conn->prepare(
        "INSERT INTO cart
            (user_id, product_id, quantity, size, shot_type, sugar_level)
         VALUES (?, ?, 1, ?, ?, ?)"
    );

    if (!$cartStmt) {
        throw new Exception("Unable to prepare cart item.");
    }

    $cartStmt->bind_param(
        "iisss",
        $user_id,
        $product_id,
        $volume,
        $shot_type,
        $sugar_level
    );

    if (!$cartStmt->execute()) {
        throw new Exception("Unable to add coffee to cart.");
    }

    $cart_id = $conn->insert_id;
    $cartStmt->close();

    /* Save chosen milk option as a cart add-on */
    $milkQuantity = 1;

    $milkInsertStmt = $conn->prepare(
        "INSERT INTO cart_addons (cart_id, addon_id, quantity)
         VALUES (?, ?, ?)"
    );

    if (!$milkInsertStmt) {
        throw new Exception("Unable to prepare milk option.");
    }

    $milkInsertStmt->bind_param(
        "iii",
        $cart_id,
        $milk_option_id,
        $milkQuantity
    );

    if (!$milkInsertStmt->execute()) {
        throw new Exception("Unable to save milk option.");
    }

    $milkInsertStmt->close();

    /* Save the other selected add-ons */
    $addonInsertStmt = $conn->prepare(
        "INSERT INTO cart_addons (cart_id, addon_id, quantity)
         VALUES (?, ?, ?)"
    );

    if (!$addonInsertStmt) {
        throw new Exception("Unable to prepare add-ons.");
    }

    foreach ($selected_addons as $addon) {
        $addon_id = (int) $addon['id'];

        $addon_quantity = (
            $addon['name'] === 'Syrup Pump'
            && in_array($addon_id, $submitted_addon_ids, true)
        ) ? $syrup_quantity : 1;

        $addonInsertStmt->bind_param(
            "iii",
            $cart_id,
            $addon_id,
            $addon_quantity
        );

        if (!$addonInsertStmt->execute()) {
            throw new Exception("Unable to save selected add-ons.");
        }
    }

    $addonInsertStmt->close();

    $conn->commit();

    header("Location: cart.php?added=1");
    exit;

} catch (Throwable $e) {
    $conn->rollback();

    error_log("Add to cart error: " . $e->getMessage());

    die(
        "Unable to add this coffee to your cart. " .
        "Please check your database setup and try again."
    );
}
?>
