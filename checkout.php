
<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

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
    $row["addons_unit_total"] = 0.0;

    $addon_stmt = $conn->prepare(
        "SELECT addons.name, addons.price, cart_addons.quantity
         FROM cart_addons
         INNER JOIN addons ON cart_addons.addon_id = addons.id
         WHERE cart_addons.cart_id = ? AND addons.is_active = 1
         ORDER BY addons.name ASC"
    );

    $addon_stmt->bind_param("i", $cart_id);
    $addon_stmt->execute();
    $addon_result = $addon_stmt->get_result();

    while ($addon = $addon_result->fetch_assoc()) {
        $addon["price"] = (float) $addon["price"];
        $addon["quantity"] = max(1, (int) $addon["quantity"]);
        $addon["subtotal"] = $addon["price"] * $addon["quantity"];

        if (in_array($addon["name"], $milk_names, true)) {
            $row["milk_option"] = $addon;
            $row["milk_price"] = $addon["subtotal"];
        } else {
            $row["addons"][] = $addon;
            $row["addons_unit_total"] += $addon["subtotal"];
        }
    }

    $addon_stmt->close();

    $row["quantity"] = max(1, (int) $row["quantity"]);
    $row["price"] = (float) $row["price"];

    $row["unit_total"] =
        $row["price"] +
        $row["milk_price"] +
        $row["addons_unit_total"];

    $row["subtotal"] = $row["unit_total"] * $row["quantity"];

    $total += $row["subtotal"];
    $cart_items[] = $row;
}

$stmt->close();

if (empty($cart_items)) {
    header("Location: cart.php");
    exit;
}

$customer_name = $_SESSION["user_name"]
    ?? $_SESSION["name"]
    ?? $_SESSION["username"]
    ?? "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout | Coffee Shop</title>

<style>
* { box-sizing: border-box; }
body { margin: 0; font-family: Arial, sans-serif; background: #f8f3ed; color: #4a3428; }
header { background: #4b2e24; color: white; padding: 18px 7%; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
header h2 { margin: 0; font-size: 23px; }
nav { display: flex; gap: 18px; flex-wrap: wrap; }
nav a { color: white; text-decoration: none; font-size: 14px; }
nav a:hover { color: #e8c9a8; }
.container { width: 92%; max-width: 1050px; margin: 35px auto; }
h1 { margin-bottom: 8px; }
.subtitle { color: #806b5d; margin-bottom: 25px; }
.checkout-layout { display: grid; grid-template-columns: 1.1fr .9fr; gap: 24px; align-items: start; }
.panel { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 4px 15px rgba(60,38,25,.08); }
.panel h2 { margin-top: 0; font-size: 20px; }
label { display: block; margin: 15px 0 6px; font-size: 14px; font-weight: bold; }
input, select, textarea { width: 100%; padding: 11px; border: 1px solid #d9c9ba; border-radius: 6px; background: #fffdfb; color: #4a3428; font-size: 14px; }
textarea { min-height: 85px; resize: vertical; }
.order-item { padding: 14px 0; border-bottom: 1px solid #eee4da; }
.item-top { display: flex; justify-content: space-between; gap: 12px; font-weight: bold; }
.item-details { margin-top: 7px; color: #806b5d; font-size: 13px; line-height: 1.8; overflow-wrap: anywhere; }
.addon-list { margin: 5px 0 0 14px; padding: 0; }
.total-row { display: flex; justify-content: space-between; gap: 15px; margin-top: 20px; font-size: 20px; font-weight: bold; }
.button { display: inline-block; width: 100%; margin-top: 20px; padding: 12px 16px; border: none; border-radius: 6px; background: #4b2e24; color: white; font-size: 15px; cursor: pointer; text-align: center; text-decoration: none; }
.button:hover { background: #684333; }
.back-link { display: inline-block; margin-top: 18px; color: #684333; text-decoration: none; font-size: 14px; }
#map { width: 100%; height: 320px; border-radius: 8px; margin-top: 10px; background: #eee4da; }
.map-help { font-size: 13px; color: #806b5d; line-height: 1.5; }
#mapStatus { font-size: 13px; color: #806b5d; margin-top: 8px; }
footer { margin-top: 50px; padding: 20px; text-align: center; background: #4b2e24; color: white; font-size: 13px; }
@media (max-width: 750px) {
    .checkout-layout { grid-template-columns: 1fr; }
    .container { width: 94%; }
    .panel { padding: 18px; }
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
<h1>Checkout</h1>
<p class="subtitle">Confirm your order details before placing your order.</p>

<div class="checkout-layout">
<section class="panel">
    <h2>Customer Information</h2>

    <form action="place_order.php" method="POST" id="checkoutForm">
        <label for="customer_name">Full Name</label>
        <input type="text" id="customer_name" name="customer_name"
            value="<?= htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8'); ?>"
            maxlength="100" required>

        <label for="contact_number">Contact Number</label>
        <input type="tel" id="contact_number" name="contact_number"
            pattern="09[0-9]{9}" maxlength="11"
            placeholder="09XXXXXXXXX"
            title="Enter an 11-digit Philippine mobile number starting with 09."
            required>

        <label for="order_type">Order Type</label>
        <select name="order_type" id="order_type" required>
            <option value="Pickup">Pickup</option>
            <option value="Delivery">Delivery</option>
        </select>

        <div id="addressContainer" style="display:none;">
            <label for="delivery_address">Delivery Address</label>
            <p class="map-help">
                Click your delivery location on the map. The selected coordinates
                will be added below. Please also type your complete address,
                including house number, street, and barangay.
            </p>

            <div id="map"></div>
            <p id="mapStatus">Loading Google Maps...</p>

            <label for="delivery_address">Complete Address and Selected Location</label>
            <textarea name="delivery_address" id="delivery_address"
                maxlength="500"
                placeholder="Select a location on the map, then enter your complete address"></textarea>
        </div>

        <button type="submit" class="button">
            Place Order — ₱<?= number_format($total, 2); ?>
        </button>

        <a href="cart.php" class="back-link">← Back to Shopping Cart</a>
    </form>
</section>

<section class="panel">
    <h2>Order Summary</h2>

    <?php foreach ($cart_items as $item): ?>
    <div class="order-item">
        <div class="item-top">
            <span><?= htmlspecialchars($item["name"], ENT_QUOTES, 'UTF-8'); ?></span>
            <span>₱<?= number_format($item["subtotal"], 2); ?></span>
        </div>

        <div class="item-details">
            Temperature:
            <?= ($item["size"] ?? "") === "8 oz" ? "Hot" : "Iced"; ?><br>

            Size:
            <?= htmlspecialchars($item["size"] ?? "", ENT_QUOTES, 'UTF-8'); ?><br>

            Espresso Shot:
            <?= htmlspecialchars($item["shot_type"] ?? "No Espresso", ENT_QUOTES, 'UTF-8'); ?><br>

            Sugar Level:
            <?= htmlspecialchars($item["sugar_level"] ?? "100%", ENT_QUOTES, 'UTF-8'); ?><br>

            Quantity: <?= (int) $item["quantity"]; ?><br>
            Coffee Unit Price: ₱<?= number_format($item["price"], 2); ?><br>

            <strong>Milk Option:</strong><br>
            <?php if ($item["milk_option"]): ?>
                <?= htmlspecialchars($item["milk_option"]["name"], ENT_QUOTES, 'UTF-8'); ?>
                — ₱<?= number_format($item["milk_price"], 2); ?><br>
            <?php else: ?>
                Not recorded<br>
            <?php endif; ?>

            <strong>Optional Add-ons:</strong>
            <?php if (!empty($item["addons"])): ?>
                <ul class="addon-list">
                <?php foreach ($item["addons"] as $addon): ?>
                    <li>
                        <?= htmlspecialchars($addon["name"], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($addon["quantity"] > 1): ?>
                            × <?= (int) $addon["quantity"]; ?>
                        <?php endif; ?>
                        — ₱<?= number_format($addon["subtotal"], 2); ?>
                    </li>
                <?php endforeach; ?>
                </ul>
                Optional add-ons per cup:
                ₱<?= number_format($item["addons_unit_total"], 2); ?><br>
            <?php else: ?>
                None<br>
            <?php endif; ?>

            Unit total: ₱<?= number_format($item["unit_total"], 2); ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="total-row">
        <span>Total</span>
        <span>₱<?= number_format($total, 2); ?></span>
    </div>

    <p style="color:#806b5d;font-size:13px;">
        Espresso shot and sugar level selections have no additional charge.
        Milk options and optional add-ons are included in the total.
    </p>
</section>
</div>
</div>

<footer>© <?= date("Y"); ?> Coffee Shop. All rights reserved.</footer>

<script>
const orderType = document.getElementById("order_type");
const addressContainer = document.getElementById("addressContainer");
const deliveryAddress = document.getElementById("delivery_address");
const mapStatus = document.getElementById("mapStatus");

let map;
let marker;
let mapsReady = false;
let selectedCoordinates = "";

function updateAddressField() {
    const isDelivery = orderType.value === "Delivery";

    addressContainer.style.display = isDelivery ? "block" : "none";
    deliveryAddress.required = isDelivery;

    if (!isDelivery) {
        deliveryAddress.value = "";
        selectedCoordinates = "";
        return;
    }

    if (mapsReady && map) {
        google.maps.event.trigger(map, "resize");
        map.setCenter({ lat: 14.5895, lng: 121.1760 });
    }
}

orderType.addEventListener("change", updateAddressField);

async function initMap() {
    try {
        const { Map } = await google.maps.importLibrary("maps");
        const { AdvancedMarkerElement } =
            await google.maps.importLibrary("marker");

        map = new Map(document.getElementById("map"), {
            center: { lat: 14.5895, lng: 121.1760 },
            zoom: 14,
            mapId: "DEMO_MAP_ID"
        });

        marker = new AdvancedMarkerElement({
            map: map,
            position: { lat: 14.5895, lng: 121.1760 },
            gmpDraggable: false,
            title: "Selected delivery location"
        });

        map.addListener("click", function(event) {
            const lat = event.latLng.lat();
            const lng = event.latLng.lng();

            marker.position = { lat: lat, lng: lng };

            selectedCoordinates =
                "Map coordinates: " + lat.toFixed(6) + ", " +
                lng.toFixed(6);

            const currentText = deliveryAddress.value
                .replace(/\n?Map coordinates: [-0-9., ]+/g, "")
                .trim();

            deliveryAddress.value = currentText
                ? currentText + "\n" + selectedCoordinates
                : selectedCoordinates;

            mapStatus.textContent =
                "Location selected. Please complete your address in the text box.";
        });

        mapsReady = true;
        mapStatus.textContent =
            "Map loaded. Click the map to select your delivery location.";

        updateAddressField();
    } catch (error) {
        console.error("Google Maps loading error:", error);
        mapStatus.textContent =
            "The map could not load. You can still enter your complete address manually.";
    }
}

window.initMap = initMap;

document.getElementById("checkoutForm").addEventListener("submit", function(event) {
    if (orderType.value === "Delivery" && deliveryAddress.value.trim() === "") {
        event.preventDefault();
        alert("Please select a map location and enter your complete delivery address.");
        deliveryAddress.focus();
    }
});

updateAddressField();
</script>

<script async
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBeh0DiiEMqy12eBmEX13JKE66uirsg7PA&loading=async&libraries=maps,marker&callback=initMap">
</script>

</body>
</html>
