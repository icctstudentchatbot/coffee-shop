
<?php
session_start();
include "db.php";

$result = $conn->query("SELECT * FROM products");

$addons = [];
$milkAddons = [];
$milkNames = ['Regular Milk', 'Oat Milk', 'Almond Milk', 'Non-Fat Milk'];

$addonsResult = $conn->query(
    "SELECT * FROM addons WHERE is_active = 1 ORDER BY id"
);

if ($addonsResult) {
    while ($addonRow = $addonsResult->fetch_assoc()) {
        if (in_array($addonRow['name'], $milkNames, true)) {
            $milkAddons[] = $addonRow;
        } else {
            $addons[] = $addonRow;
        }
    }
}

/* Actual product data for AI */
$aiProducts = [];
$aiProductsResult = $conn->query(
    "SELECT name, description, price FROM products"
);

if ($aiProductsResult) {
    while ($productRow = $aiProductsResult->fetch_assoc()) {
        $aiProducts[] = [
            "name" => $productRow["name"],
            "description" => $productRow["description"] ?? "",
            "price" => (float) $productRow["price"]
        ];
    }
}

/* Regular add-ons, excluding milk options */
$aiAddons = [];

foreach ($addons as $addon) {
    $aiAddons[] = [
        "name" => $addon["name"],
        "price" => (float) $addon["price"]
    ];
}

/* Milk options are separate from optional add-ons */
$aiMilkOptions = [];

foreach ($milkAddons as $milk) {
    $aiMilkOptions[] = [
        "name" => $milk["name"],
        "price" => (float) $milk["price"]
    ];
}

$aiMenuJson = json_encode(
    [
        "products" => $aiProducts,
        "addons" => $aiAddons,
        "milk_options" => $aiMilkOptions
    ],
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
    JSON_HEX_QUOT |
    JSON_UNESCAPED_UNICODE
);

if ($aiMenuJson === false) {
    $aiMenuJson = '{"products":[],"addons":[],"milk_options":[]}';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffee Shop</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8f3ed;
            color: #4a3428;
        }

        header {
            background: #4b2e24;
            color: white;
            padding: 18px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            box-shadow: 0 3px 12px rgba(75,46,36,.18);
        }

        header h1 {
            margin: 0;
            font-size: 25px;
            letter-spacing: .5px;
        }

        nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 16px;
        }

        nav a {
            color: #fffaf5;
            text-decoration: none;
            font-size: 14px;
            transition: .3s;
        }

        nav a:hover {
            color: #e8c9a8;
        }

        .user-info {
            margin-top: 7px;
            font-size: 13px;
            color: #e8c9a8;
        }

        .hero {
            text-align: center;
            padding: 75px 20px;
            background: #ead8c5;
        }

        .hero h2 {
            font-size: 42px;
            margin: 0 0 15px;
            color: #4b2e24;
        }

        .hero p {
            font-size: 18px;
            color: #6b4a3a;
            margin: 0;
        }

        .products {
            padding: 45px 30px;
        }

        .products h2 {
            text-align: center;
            margin: 0 0 35px;
            color: #4b2e24;
            font-size: 28px;
        }

        /* FOUR PRODUCT CARDS PER ROW ON DESKTOP */
        .product-container {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 22px;
            align-items: stretch;
            width: 100%;
            max-width: 1800px;
            margin: 0 auto;
        }

        .product-card {
            background: #fffdf9;
            width: 100%;
            min-width: 0;
            padding: 18px;
            border: 1px solid #eee2d7;
            border-radius: 15px;
            box-shadow: 0 5px 18px rgba(75,46,36,.08);
            text-align: center;
            transition: transform .25s, box-shadow .25s;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 22px rgba(75,46,36,.14);
        }

        .product-card h3 {
            color: #4b2e24;
            font-size: 19px;
            margin: 0 0 10px;
            overflow-wrap: anywhere;
        }

        .product-card p {
            color: #765b4c;
            line-height: 1.5;
            font-size: 13px;
        }

        .price {
            font-size: 21px !important;
            font-weight: bold;
            color: #9a6348 !important;
            margin: 14px 0 18px;
        }

        .option-group {
            margin-bottom: 13px;
            text-align: left;
        }

        .option-group > label {
            display: block;
            font-size: 12px;
            font-weight: bold;
            color: #654838;
            margin-bottom: 6px;
        }

        .option-group select {
            display: block;
            width: 100%;
            max-width: 100%;
            padding: 9px 8px;
            border: 1px solid #dfcbbb;
            border-radius: 7px;
            background: #fffaf5;
            color: #4b2e24;
            font-family: inherit;
            font-size: 12px;
            outline: none;
            cursor: pointer;
        }

        .option-group select:focus {
            border-color: #9a6348;
        }

        .addon-list,
        .milk-list {
            border: 1px solid #ead8c5;
            background: #fffaf5;
            border-radius: 8px;
            padding: 8px;
            margin-top: 6px;
        }

        .addon-option,
        .milk-option {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            margin: 9px 0;
            font-size: 11px;
            color: #654838;
            line-height: 1.4;
            text-align: left;
            overflow-wrap: anywhere;
        }

        .addon-option input,
        .milk-option input {
            margin-top: 2px;
            accent-color: #7a4e3a;
            flex-shrink: 0;
        }

        .addon-price,
        .milk-price {
            margin-left: auto;
            white-space: nowrap;
            font-weight: bold;
            color: #9a6348;
            font-size: 11px;
        }

        .pump-quantity {
            width: 100%;
            padding: 7px;
            margin-top: 5px;
            border: 1px solid #dfcbbb;
            border-radius: 6px;
            background: #fffdf9;
        }

        .custom-note {
            font-size: 11px !important;
            color: #8b7565 !important;
            margin: 7px 0 12px !important;
        }

        button {
            width: 100%;
            padding: 11px 12px;
            background: #7a4e3a;
            color: white;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-size: 13px;
            transition: background .25s;
        }

        button:hover {
            background: #5d3b2d;
        }

        .about-text,
        .contact-text {
            max-width: 700px;
            margin: auto;
            text-align: center;
            color: #765b4c;
            line-height: 1.7;
        }

        footer {
            background: #4b2e24;
            color: #ead8c5;
            text-align: center;
            padding: 22px;
            margin-top: 20px;
        }

        footer p {
            margin: 0;
            font-size: 13px;
        }

        #coffee-ai-toggle {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 9999;
            width: auto;
            min-width: 145px;
            padding: 15px 19px;
            border-radius: 35px;
            background: #4b2e24;
            color: white;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 5px 18px rgba(0,0,0,.22);
        }

        #coffee-ai-toggle:hover {
            background: #7a4e3a;
        }

        #coffee-ai-panel {
            display: none;
            position: fixed;
            right: 22px;
            bottom: 88px;
            width: 350px;
            max-width: calc(100vw - 28px);
            height: 470px;
            max-height: calc(100vh - 125px);
            z-index: 9999;
            background: #fffdf9;
            border: 1px solid #e5d5c7;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,.2);
            flex-direction: column;
            font-family: Arial, sans-serif;
        }

        #coffee-ai-panel.open {
            display: flex;
        }

        .coffee-ai-header {
            background: #4b2e24;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .coffee-ai-header strong {
            font-size: 16px;
        }

        .coffee-ai-subtitle {
            font-size: 12px;
            margin-top: 4px;
            color: #ead8c5;
        }

        #coffee-ai-close {
            width: auto;
            padding: 4px 9px;
            background: transparent;
            font-size: 24px;
        }

        #coffee-ai-messages {
            flex: 1;
            overflow-y: auto;
            padding: 13px;
            background: #fffdf9;
        }

        .coffee-ai-message {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            line-height: 1.5;
            font-size: 14px;
            padding: 10px 12px;
            border-radius: 12px;
            margin-bottom: 10px;
        }

        .coffee-ai-bot {
            background: #f1e8df;
            color: #4a3428;
            margin-right: 20px;
        }

        .coffee-ai-user {
            background: #7a4e3a;
            color: white;
            margin-left: 20px;
        }

        .coffee-ai-form {
            display: flex;
            gap: 7px;
            padding: 10px;
            border-top: 1px solid #ead8c5;
        }

        #coffee-ai-input {
            flex: 1;
            min-width: 0;
            border: 1px solid #dfcbbb;
            border-radius: 8px;
            padding: 11px;
            font-size: 14px;
            outline: none;
        }

        #coffee-ai-input:focus {
            border-color: #9a6348;
        }

        #coffee-ai-send {
            width: auto;
            padding: 10px 13px;
            border-radius: 8px;
        }

        #coffee-ai-send:disabled {
            opacity: .6;
            cursor: wait;
        }

        /* Three cards on medium-sized screens */
        @media (max-width: 1250px) {
            .product-container {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .products {
                padding: 40px 24px;
            }
        }

        /* Two cards on tablets and smaller laptops */
        @media (max-width: 950px) {
            header {
                padding: 18px 25px;
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            nav {
                justify-content: center;
            }

            .product-container {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .hero h2 {
                font-size: 34px;
            }
        }

        /* One card per row on mobile phones */
        @media (max-width: 600px) {
            .products {
                padding: 35px 16px;
            }

            .product-container {
                grid-template-columns: minmax(0, 1fr);
                max-width: 420px;
            }

            .product-card {
                padding: 20px;
            }

            .hero {
                padding: 60px 18px;
            }

            .hero h2 {
                font-size: 29px;
            }

            nav {
                gap: 12px;
            }

            #coffee-ai-toggle {
                right: 14px;
                bottom: 14px;
            }

            #coffee-ai-panel {
                right: 14px;
                bottom: 78px;
            }
        }
    </style>
</head>

<body>

<header>
    <div>
        <h1>☕ Coffee Shop</h1>

        <?php if (isset($_SESSION['user_name'])): ?>
            <div class="user-info">
                Welcome, <?= htmlspecialchars($_SESSION['user_name']); ?>!
            </div>
        <?php endif; ?>
    </div>

    <nav>
        <a href="index.php">Home</a>
        <a href="#menu">Menu</a>
        <a href="#about">About</a>
        <a href="#contact">Contact</a>
        <a href="cart.php">Cart 🛒</a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php">My Profile</a>

            <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                <a href="admin.php">Admin Dashboard</a>
            <?php endif; ?>

            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>

<section class="hero">
    <h2>Welcome to Our Coffee Shop</h2>
    <p>Fresh coffee, good vibes, and great moments.</p>
</section>

<section class="products" id="menu">
    <h2>Our Coffee Menu</h2>

    <div class="product-container">

        <?php if ($result && $result->num_rows > 0): ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <div class="product-card">
                    <h3><?= htmlspecialchars($row['name']); ?></h3>

                    <p>
                        <?= htmlspecialchars($row['description'] ?? ''); ?>
                    </p>

                    <p class="price">
                        ₱<?= number_format((float)$row['price'], 2); ?>
                    </p>

                    <form action="add_to_cart.php" method="POST">

                        <input
                            type="hidden"
                            name="product_id"
                            value="<?= (int)$row['id']; ?>"
                        >

                        <div class="option-group">
                            <label for="temperature_<?= (int)$row['id']; ?>">
                                Temperature
                            </label>

                            <select
                                name="temperature"
                                id="temperature_<?= (int)$row['id']; ?>"
                                onchange="updateVolume(this)"
                                required
                            >
                                <option value="Iced" selected>Iced - 16 oz</option>
                                <option value="Hot">Hot - 8 oz</option>
                            </select>

                            <input
                                type="hidden"
                                name="volume"
                                value="16 oz"
                                class="volume-input"
                            >
                        </div>

                        <div class="option-group">
                            <label for="shot_<?= (int)$row['id']; ?>">
                                Espresso Shot
                            </label>

                            <select
                                name="shot_type"
                                id="shot_<?= (int)$row['id']; ?>"
                                required
                            >
                                <option value="No Espresso">No Espresso</option>
                                <option value="Ristretto" selected>
                                    Ristretto - Single Shot
                                </option>
                                <option value="Doppio">
                                    Doppio - Double Shot
                                </option>
                            </select>
                        </div>

                        <div class="option-group">
                            <label>Milk Option</label>

                            <div class="milk-list">

                                <?php if (!empty($milkAddons)): ?>

                                    <?php
                                    $regularMilkFound = false;

                                    foreach ($milkAddons as $milk):
                                        if ($milk['name'] === 'Regular Milk') {
                                            $regularMilkFound = true;
                                        }
                                    ?>

                                        <label class="milk-option">
                                            <input
                                                type="radio"
                                                name="milk_option_id"
                                                value="<?= (int)$milk['id']; ?>"
                                                <?= $milk['name'] === 'Regular Milk' ? 'checked' : ''; ?>
                                                required
                                            >

                                            <span>
                                                <?= htmlspecialchars($milk['name']); ?>
                                            </span>

                                            <span class="milk-price">
                                                <?php if ((float)$milk['price'] > 0): ?>
                                                    +₱<?= number_format((float)$milk['price'], 2); ?>
                                                <?php elseif ($milk['name'] === 'Regular Milk'): ?>
                                                    Included
                                                <?php else: ?>
                                                    Free
                                                <?php endif; ?>
                                            </span>
                                        </label>

                                    <?php endforeach; ?>

                                    <?php if (!$regularMilkFound): ?>
                                        <p class="custom-note">
                                            Regular Milk is not available. Please check the addons table.
                                        </p>
                                    <?php endif; ?>

                                <?php else: ?>

                                    <p class="custom-note">
                                        Milk options are unavailable. Check the addons table in phpMyAdmin.
                                    </p>

                                <?php endif; ?>

                            </div>

                            <p class="custom-note">
                                Choose one milk option only.
                            </p>
                        </div>

                        <div class="option-group">
                            <label for="sugar_<?= (int)$row['id']; ?>">
                                Sugar Level
                            </label>

                            <select
                                name="sugar_level"
                                id="sugar_<?= (int)$row['id']; ?>"
                                required
                            >
                                <option value="0%">0% - No Sugar</option>
                                <option value="25%">25% - Less Sugar</option>
                                <option value="50%" selected>50% - Regular</option>
                                <option value="75%">75% - More Sugar</option>
                                <option value="100%">100% - Full Sugar</option>
                            </select>
                        </div>

                        <div class="option-group">
                            <label>Optional Add-ons</label>

                            <div class="addon-list">

                                <?php if (!empty($addons)): ?>

                                    <?php foreach ($addons as $addon): ?>

                                        <label class="addon-option">
                                            <input
                                                type="checkbox"
                                                name="addon_ids[]"
                                                value="<?= (int)$addon['id']; ?>"
                                                class="addon-checkbox"
                                                data-addon-name="<?= htmlspecialchars($addon['name'], ENT_QUOTES); ?>"
                                                onchange="toggleSyrupPump(this)"
                                            >

                                            <span>
                                                <?= htmlspecialchars($addon['name']); ?>
                                            </span>

                                            <span class="addon-price">
                                                ₱<?= number_format((float)$addon['price'], 2); ?>
                                            </span>
                                        </label>

                                        <?php if ($addon['name'] === 'Syrup Pump'): ?>

                                            <div
                                                class="syrup-quantity-wrap"
                                                style="display:none; margin: 0 0 10px 25px;"
                                            >
                                                <label for="syrup_quantity_<?= (int)$row['id']; ?>">
                                                    Number of pumps
                                                </label>

                                                <select
                                                    name="syrup_pump_quantity"
                                                    id="syrup_quantity_<?= (int)$row['id']; ?>"
                                                    class="pump-quantity"
                                                    disabled
                                                >
                                                    <option value="1">1 pump</option>
                                                    <option value="2">2 pumps</option>
                                                    <option value="3">3 pumps</option>
                                                    <option value="4">4 pumps</option>
                                                    <option value="5">5 pumps</option>
                                                </select>
                                            </div>

                                        <?php endif; ?>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <p class="custom-note">
                                        No add-ons available. Check the addons table in phpMyAdmin.
                                    </p>

                                <?php endif; ?>

                            </div>
                        </div>

                        <p class="custom-note">
                            Customize your coffee and choose optional add-ons.
                        </p>

                        <button type="submit">＋ Add to Cart</button>

                    </form>
                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p>No coffee products available yet.</p>

        <?php endif; ?>

    </div>
</section>

<section class="products" id="about">
    <h2>About Us</h2>

    <p class="about-text">
        Our coffee shop provides quality coffee
        in a comfortable and friendly environment.
    </p>
</section>

<section class="products" id="contact">
    <h2>Contact Us</h2>

    <p class="contact-text">
        Visit us and enjoy your favorite coffee with us!
    </p>
</section>

<footer>
    <p>© 2026 Coffee Shop. All Rights Reserved.</p>
</footer>

<button
    type="button"
    id="coffee-ai-toggle"
    aria-expanded="false"
    aria-controls="coffee-ai-panel"
>
    ☕ Ask AI
</button>

<section id="coffee-ai-panel" aria-label="AI Coffee Assistant">

    <div class="coffee-ai-header">
        <div>
            <strong>☕ AI Coffee Assistant</strong>

            <div class="coffee-ai-subtitle">
                Find your perfect coffee
            </div>
        </div>

        <button
            type="button"
            id="coffee-ai-close"
            aria-label="Close chat"
        >×</button>
    </div>

    <div id="coffee-ai-messages" aria-live="polite">
        <div class="coffee-ai-message coffee-ai-bot">Hi! Welcome to our Coffee Shop! ☕

Do you like your coffee sweet, creamy, strong, or refreshing? Tell me your preference and I'll recommend a coffee.</div>
    </div>

    <form id="coffee-ai-form" class="coffee-ai-form">
        <input
            id="coffee-ai-input"
            type="text"
            placeholder="Ask about coffee..."
            autocomplete="off"
            required
        >

        <button id="coffee-ai-send" type="submit">Send</button>
    </form>

</section>

<script src="https://js.puter.com/v2/"></script>

<script>
const coffeeMenuData = <?= $aiMenuJson ?>;

function updateVolume(select) {
    const form = select.closest('form');
    const volumeInput = form.querySelector('.volume-input');

    volumeInput.value = select.value === 'Hot' ? '8 oz' : '16 oz';
}

function toggleSyrupPump(checkbox) {
    if (checkbox.dataset.addonName !== 'Syrup Pump') {
        return;
    }

    const form = checkbox.closest('form');
    const quantityWrap = form.querySelector('.syrup-quantity-wrap');

    if (!quantityWrap) {
        return;
    }

    const quantitySelect = quantityWrap.querySelector('select');

    quantityWrap.style.display = checkbox.checked ? 'block' : 'none';
    quantitySelect.disabled = !checkbox.checked;
}

/* AI COFFEE ASSISTANT */
(function () {
    const toggle = document.getElementById('coffee-ai-toggle');
    const panel = document.getElementById('coffee-ai-panel');
    const close = document.getElementById('coffee-ai-close');
    const messages = document.getElementById('coffee-ai-messages');
    const form = document.getElementById('coffee-ai-form');
    const input = document.getElementById('coffee-ai-input');
    const send = document.getElementById('coffee-ai-send');

    const history = [];

    const coffeeInstructions = `
You are the friendly AI Coffee Assistant for this coffee shop.

ACTUAL DATABASE MENU, ADD-ONS, AND MILK OPTIONS:
${JSON.stringify(coffeeMenuData, null, 2)}

Speak naturally in simple English or Taglish, like a friendly barista.

REPLY STYLE:
Use plain text only. Do not use Markdown, asterisks, bullet points,
numbered lists, headings, or decorative formatting.
Keep replies short, natural, and easy to understand, usually 1 to 3 sentences.
Ask one brief follow-up question only when useful.

PRICE RULES:
Use actual product, add-on, and milk-option prices from the database data.
Oat Milk costs an additional ₱59.00 and Almond Milk costs an additional ₱59.00
when these prices are confirmed by the data.
Regular Milk is included when its price is zero.
Non-Fat Milk is free when its price is zero.
Calculate the product price plus the requested add-on and milk-option prices.
Do not invent prices, ingredients, stock availability, or promotions.
If a price or ingredient cannot be confirmed, say so.

MILK AND LACTOSE GUIDANCE:
If a customer says they are lactose intolerant, suggest Oat Milk or Almond Milk
if available, and explain any additional price using the actual menu data.
Non-Fat Milk is still dairy milk and is not a suitable lactose-free alternative.
Do not guarantee that oat or almond milk is free from dairy cross-contact.
Tell the customer to confirm ingredients and cross-contact information with staff.
Do not assume that choosing No Espresso removes dairy or makes a drink caffeine-free.

Hot drinks are 8 oz and iced drinks are 16 oz.
You cannot place orders, process payments, or access customer accounts.
Be friendly, concise, and helpful.
`;

    function cleanReply(text) {
        return String(text)
            .replace(/\*\*(.*?)\*\*/gs, '$1')
            .replace(/__(.*?)__/gs, '$1')
            .replace(/(?<!\*)\*(?!\s)(.*?)(?<!\s)\*(?!\*)/gs, '$1')
            .replace(/^#{1,6}\s+/gm, '')
            .replace(/^\s*[-•]\s+/gm, '')
            .replace(/^\s*\d+[.)]\s+/gm, '')
            .replace(/`([^`]+)`/g, '$1')
            .replace(/[ \t]+\n/g, '\n')
            .replace(/\n{2,}/g, ' ')
            .replace(/[ \t]{2,}/g, ' ')
            .trim();
    }

    function addMessage(text, sender) {
        const message = document.createElement('div');
        message.className = 'coffee-ai-message coffee-ai-' + sender;
        message.textContent = text;
        messages.appendChild(message);
        messages.scrollTop = messages.scrollHeight;
        return message;
    }

    function extractAnswer(response) {
        const content = response?.message?.content;

        if (typeof content === 'string') {
            return content;
        }

        if (Array.isArray(content)) {
            return content
                .filter(part => part.type === 'text')
                .map(part => part.text)
                .join('\n');
        }

        if (typeof response?.text === 'string') {
            return response.text;
        }

        return '';
    }

    toggle.addEventListener('click', function () {
        const isOpen = panel.classList.toggle('open');
        toggle.setAttribute('aria-expanded', String(isOpen));

        if (isOpen) {
            input.focus();
        }
    });

    close.addEventListener('click', function () {
        panel.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const question = input.value.trim();

        if (!question || send.disabled) {
            return;
        }

        addMessage(question, 'user');

        input.value = '';
        send.disabled = true;
        send.textContent = '...';

        const waiting = addMessage(
            'Thinking of the perfect coffee for you...',
            'bot'
        );

        try {
            if (!window.puter || !puter.ai) {
                throw new Error(
                    'Puter AI did not load. Check your internet connection and refresh the page.'
                );
            }

            const messagesToSend = [
                {
                    role: 'system',
                    content: coffeeInstructions
                },
                ...history.slice(-8),
                {
                    role: 'user',
                    content: question
                }
            ];

            const response = await puter.ai.chat(messagesToSend);
            const answer = extractAnswer(response);

            if (!answer) {
                throw new Error('No answer was received. Please try again.');
            }

            const cleanAnswer = cleanReply(answer);
            waiting.textContent = cleanAnswer;

            history.push(
                { role: 'user', content: question },
                { role: 'assistant', content: cleanAnswer }
            );

            if (history.length > 12) {
                history.splice(0, history.length - 12);
            }

        } catch (error) {
            waiting.textContent = cleanReply(
                "Sorry, I couldn't connect to the AI right now. " +
                (error.message || 'Please try again later.')
            );
        } finally {
            send.disabled = false;
            send.textContent = 'Send';
            messages.scrollTop = messages.scrollHeight;
            input.focus();
        }
    });
})();
</script>

</body>
</html>
