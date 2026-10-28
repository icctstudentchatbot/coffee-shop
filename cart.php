
<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

/* UPDATE QUANTITY */
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["quantity_action"], $_POST["cart_id"])
) {
    $cart_id = filter_input(INPUT_POST, "cart_id", FILTER_VALIDATE_INT);
    $action = $_POST["quantity_action"];

    if (
        $cart_id && $cart_id > 0
        && in_array($action, ["increase", "decrease"], true)
    ) {
        $stmt = $conn->prepare(
            "SELECT quantity FROM cart WHERE id = ? AND user_id = ?"
        );
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();
        $cart_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($cart_row) {
            $quantity = (int) $cart_row["quantity"];

            if ($action === "increase") {
                $quantity++;
            } elseif ($quantity > 1) {
                $quantity--;
            }

            $update = $conn->prepare(
                "UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?"
            );
            $update->bind_param("iii", $quantity, $cart_id, $user_id);
            $update->execute();
            $update->close();
        }
    }

    header("Location: cart.php");
    exit;
}

/* LOAD CART */
$sql = "SELECT
            cart.id,
            products.name,
            products.price,
            cart.quantity,
            cart.size,
            cart.shot_type,
            cart.sugar_level
        FROM cart
        INNER JOIN products ON cart.product_id = products.id
        WHERE cart.user_id = ?
        ORDER BY cart.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$total = 0.0;

$milk_names = [
    "Regular Milk",
    "Oat Milk",
    "Almond Milk",
    "Non-Fat Milk"
];

while ($row = $result->fetch_assoc()) {
    $cart_id = (int) $row["id"];

    $row["milk_option"] = null;
    $row["milk_price"] = 0.0;
    $row["addons"] = [];
    $row["addon_total_per_cup"] = 0.0;

    $addon_sql = "SELECT
                      addons.name,
                      addons.price,
                      cart_addons.quantity
                  FROM cart_addons
                  INNER JOIN addons ON cart_addons.addon_id = addons.id
                  WHERE cart_addons.cart_id = ?
                    AND addons.is_active = 1
                  ORDER BY addons.id ASC";

    $addon_stmt = $conn->prepare($addon_sql);
    $addon_stmt->bind_param("i", $cart_id);
    $addon_stmt->execute();
    $addon_result = $addon_stmt->get_result();

    while ($addon = $addon_result->fetch_assoc()) {
        $addon["price"] = (float) $addon["price"];
        $addon["quantity"] = max(1, (int) $addon["quantity"]);
        $addon["line_total"] = $addon["price"] * $addon["quantity"];

        if (in_array($addon["name"], $milk_names, true)) {
            $row["milk_option"] = $addon;
            $row["milk_price"] = $addon["line_total"];
        } else {
            $row["addons"][] = $addon;
            $row["addon_total_per_cup"] += $addon["line_total"];
        }
    }

    $addon_stmt->close();

    $row["price"] = (float) $row["price"];
    $row["quantity"] = max(1, (int) $row["quantity"]);

    $row["unit_total"] =
        $row["price"] +
        $row["milk_price"] +
        $row["addon_total_per_cup"];

    $row["subtotal"] = $row["unit_total"] * $row["quantity"];

    $total += $row["subtotal"];
    $cart_items[] = $row;
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Shopping Cart | Coffee Shop</title>

<style>
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, sans-serif; background: #f8f3ed; color: #4a3428; }
header { background: #4b2e24; color: white; padding: 18px 7%; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
header h2 { margin: 0; font-size: 23px; }
nav { display: flex; gap: 18px; flex-wrap: wrap; }
nav a { color: white; text-decoration: none; font-size: 14px; }
nav a:hover { color: #e8c9a8; }
.container { width: 92%; max-width: 1150px; margin: 35px auto; }
h1 { margin-bottom: 8px; }
.subtitle { color: #806b5d; margin-bottom: 25px; }
.table-wrapper { overflow-x: auto; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(60,38,25,.08); }
table { width: 100%; border-collapse: collapse; min-width: 1000px; }
th, td { padding: 15px 12px; text-align: left; vertical-align: top; border-bottom: 1px solid #eee4da; font-size: 14px; }
th { background: #eee2d5; color: #4b2e24; }
.customization { line-height: 1.8; color: #725a49; font-size: 13px; min-width: 230px; }
.addon-list { margin: 5px 0; padding-left: 20px; line-height: 1.8; }
.price { white-space: nowrap; }
.quantity-control { display: flex; align-items: center; gap: 10px; }
.quantity-control form { margin: 0; }
.quantity-button { width: 32px; height: 32px; border: none; border-radius: 6px; background: #4b2e24; color: white; font-size: 20px; cursor: pointer; }
.quantity-button:hover:not(:disabled) { background: #684333; }
.quantity-button:disabled { background: #d8cfc6; color: #897c72; cursor: not-allowed; }
.quantity-number { min-width: 20px; text-align: center; font-weight: bold; }
.total-section { margin-top: 22px; margin-left: auto; max-width: 390px; background: white; padding: 22px; border-radius: 12px; box-shadow: 0 4px 15px rgba(60,38,25,.08); }
.total-row { display: flex; justify-content: space-between; gap: 15px; font-size: 20px; font-weight: bold; margin-bottom: 18px; }
.button { display: inline-block; padding: 11px 16px; border: none; border-radius: 6px; background: #4b2e24; color: white; text-decoration: none; font-size: 14px; cursor: pointer; }
.button:hover { background: #684333; }
.secondary { background: #e8ded2; color: #4b2e24; }
.actions { display: flex; flex-wrap: wrap; gap: 10px; }
.empty { background: white; padding: 35px 20px; text-align: center; border-radius: 12px; box-shadow: 0 4px 15px rgba(60,38,25,.08); }
footer { margin-top: 50px; padding: 20px; text-align: center; background: #4b2e24; color: white; font-size: 13px; }
@media (max-width: 600px) {
    header { padding: 18px 5%; }
    .container { width: 94%; margin-top: 25px; }
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
<h1>My Shopping Cart</h1>
<p class="subtitle">Review your coffee and customization before checkout.</p>

<?php if (empty($cart_items)): ?>
    <div class="empty">
        <h2>Your cart is empty ☕</h2>
        <p>Add your favorite coffee from our menu.</p>
        <a href="index.php#menu" class="button">Browse Coffee Menu</a>
    </div>
<?php else: ?>

<div class="table-wrapper">
<table>
<thead>
<tr>
    <th>Coffee</th>
    <th>Customization</th>
    <th>Price per Cup</th>
    <th>Quantity</th>
    <th>Subtotal</th>
    <th>Action</th>
</tr>
</thead>
<tbody>

<?php foreach ($cart_items as $item): ?>
<tr>
<td><strong><?= htmlspecialchars($item["name"]); ?></strong></td>

<td class="customization">
    <div><strong>Temperature:</strong>
        <?= ($item["size"] ?? "") === "8 oz" ? "Hot" : "Iced"; ?>
    </div>
    <div><strong>Size:</strong>
        <?= htmlspecialchars($item["size"] ?? ""); ?>
    </div>
    <div><strong>Espresso Shot:</strong>
        <?= htmlspecialchars($item["shot_type"] ?? "No Espresso"); ?>
    </div>
    <div><strong>Sugar Level:</strong>
        <?= htmlspecialchars($item["sugar_level"] ?? "100%"); ?>
    </div>

    <div><strong>Milk Option:</strong></div>
    <?php if ($item["milk_option"]): ?>
        <div>
            <?= htmlspecialchars($item["milk_option"]["name"]); ?>
            — ₱<?= number_format($item["milk_price"], 2); ?>
        </div>
    <?php else: ?>
        <div>Not recorded</div>
    <?php endif; ?>

    <div><strong>Optional Add-ons:</strong></div>
    <?php if (!empty($item["addons"])): ?>
        <ul class="addon-list">
        <?php foreach ($item["addons"] as $addon): ?>
            <li>
                <?= htmlspecialchars($addon["name"]); ?>
                <?php if ($addon["quantity"] > 1): ?>
                    × <?= (int) $addon["quantity"]; ?>
                <?php endif; ?>
                — ₱<?= number_format($addon["line_total"], 2); ?>
            </li>
        <?php endforeach; ?>
        </ul>
        <div><strong>Optional add-ons per cup:</strong>
            ₱<?= number_format($item["addon_total_per_cup"], 2); ?>
        </div>
    <?php else: ?>
        <div>No optional add-ons</div>
    <?php endif; ?>
</td>

<td class="price">
    <div>Base: ₱<?= number_format($item["price"], 2); ?></div>
    <div>Milk: ₱<?= number_format($item["milk_price"], 2); ?></div>
    <div>Other add-ons: ₱<?= number_format($item["addon_total_per_cup"], 2); ?></div>
    <div>With options: <strong>₱<?= number_format($item["unit_total"], 2); ?></strong></div>
</td>

<td>
    <div class="quantity-control">
        <form method="POST" action="cart.php">
            <input type="hidden" name="cart_id" value="<?= (int) $item["id"]; ?>">
            <button type="submit" name="quantity_action" value="decrease"
                class="quantity-button" <?= $item["quantity"] <= 1 ? "disabled" : ""; ?>>−</button>
        </form>

        <span class="quantity-number"><?= (int) $item["quantity"]; ?></span>

        <form method="POST" action="cart.php">
            <input type="hidden" name="cart_id" value="<?= (int) $item["id"]; ?>">
            <button type="submit" name="quantity_action" value="increase"
                class="quantity-button">+</button>
        </form>
    </div>
</td>

<td class="price"><strong>₱<?= number_format($item["subtotal"], 2); ?></strong></td>

<td>
    <a href="remove_from_cart.php?id=<?= (int) $item["id"]; ?>"
       class="button"
       onclick="return confirm('Remove this coffee from your cart?');">Remove</a>
</td>
</tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

<div class="total-section">
    <div class="total-row">
        <span>Total:</span>
        <span>₱<?= number_format($total, 2); ?></span>
    </div>
    <div class="actions">
        <a href="index.php#menu" class="button secondary">Continue Shopping</a>
        <a href="checkout.php" class="button">Proceed to Checkout</a>
    </div>
</div>

<?php endif; ?>
</div>

<footer>© <?= date("Y"); ?> Coffee Shop. All rights reserved.</footer>
</body>
</html>
