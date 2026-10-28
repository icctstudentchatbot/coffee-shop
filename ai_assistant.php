
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Coffee Assistant | Coffee Shop</title>

    <script src="https://js.puter.com/v2/"></script>

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

        .header {
            background: #4b2e24;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .header h1 {
            margin: 0 0 8px;
        }

        .header p {
            margin: 0;
            color: #eadfd4;
        }

        .navigation {
            background: #3e2723;
            padding: 12px;
            text-align: center;
        }

        .navigation a {
            color: white;
            text-decoration: none;
            margin: 0 12px;
            font-size: 14px;
        }

        .container {
            max-width: 700px;
            margin: 30px auto;
            padding: 20px;
        }

        .chat-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .chat-heading {
            background: #e8ded2;
            padding: 18px;
        }

        .chat-heading h2 {
            margin: 0 0 6px;
        }

        .chat-heading p {
            margin: 0;
            font-size: 14px;
        }

        #chatMessages {
            height: 380px;
            overflow-y: auto;
            padding: 18px;
        }

        .message {
            padding: 12px 15px;
            margin-bottom: 12px;
            border-radius: 12px;
            max-width: 90%;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            line-height: 1.5;
        }

        .bot {
            background: #f1e8df;
            margin-right: auto;
        }

        .user {
            background: #4b2e24;
            color: white;
            margin-left: auto;
        }

        .input-area {
            display: flex;
            gap: 10px;
            padding: 15px;
            border-top: 1px solid #eee;
        }

        #userInput {
            flex: 1;
            min-width: 0;
            padding: 13px;
            border: 1px solid #d6c6b8;
            border-radius: 8px;
            font-size: 15px;
        }

        #sendButton {
            background: #4b2e24;
            color: white;
            border: none;
            padding: 12px 18px;
            border-radius: 8px;
            cursor: pointer;
        }

        #sendButton:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 0 15px 15px;
        }

        .suggestions button {
            background: #fffaf5;
            color: #4b2e24;
            border: 1px solid #d6c6b8;
            border-radius: 20px;
            padding: 8px 12px;
            cursor: pointer;
        }

        .footer {
            text-align: center;
            color: #806d60;
            font-size: 13px;
            padding: 20px;
        }

        @media (max-width: 500px) {
            .navigation a {
                display: inline-block;
                margin: 5px;
            }

            .container {
                padding: 12px;
            }

            #chatMessages {
                height: 350px;
            }

            .input-area {
                gap: 6px;
            }

            #sendButton {
                padding: 10px;
            }
        }
    </style>
</head>

<body>

<header class="header">
    <h1>☕ Coffee Shop</h1>
    <p>Your personal AI Coffee Assistant</p>
</header>

<nav class="navigation">
    <a href="index.php">Home</a>
    <a href="index.php#menu">Menu</a>
    <a href="cart.php">Cart</a>
    <a href="my_orders.php">My Orders</a>
</nav>

<main class="container">
    <section class="chat-card">
        <div class="chat-heading">
            <h2>☕ AI Coffee Assistant</h2>
            <p>Ask about our coffee or get a recommendation!</p>
        </div>

        <div id="chatMessages" aria-live="polite">
            <div class="message bot">Hi! Welcome to our Coffee Shop! ☕

I can help you choose a coffee based on your taste.

Do you prefer something sweet, creamy, strong, or refreshing?</div>
        </div>

        <div class="suggestions">
            <button type="button" data-question="Recommend a sweet coffee.">
                🍯 Sweet coffee
            </button>
            <button type="button" data-question="Recommend a creamy coffee.">
                🥛 Creamy coffee
            </button>
            <button type="button" data-question="Recommend a strong coffee.">
                ☕ Strong coffee
            </button>
            <button type="button" data-question="What iced coffee do you recommend?">
                🧊 Iced coffee
            </button>
        </div>

        <form class="input-area" id="chatForm">
            <input
                type="text"
                id="userInput"
                placeholder="Ask me about coffee..."
                autocomplete="off"
                required
            >
            <button type="submit" id="sendButton">Send</button>
        </form>
    </section>

    <p class="footer">
        Powered by Puter AI
    </p>
</main>

<script>
    const chatMessages = document.getElementById("chatMessages");
    const chatForm = document.getElementById("chatForm");
    const userInput = document.getElementById("userInput");
    const sendButton = document.getElementById("sendButton");

    const conversation = [];

    const systemPrompt = `
You are the AI Coffee Assistant for a student coffee shop ordering
and management system.

Your main responsibilities are:
1. Answer basic questions about coffee and the shop menu.
2. Recommend coffee based on customer preferences.
3. Help customers choose between hot and iced coffee.
4. Ask simple follow-up questions when preferences are unclear.

The coffee menu includes:
- Mocha Coffee
- Spanish Coffee
- Buttermilk Latte
- Nutty Latte
- Americano
- Tiramisu Latte
- Ube Latte
- Vanilla Cream Latte
- Toasty Mallow Latte
- Iced Coffee
- Caramel Coffee

Customers can choose hot or iced when supported by the menu.
Hot drinks are automatically 8 oz, while iced drinks are 16 oz.
The displayed size is Regular; customers do not select different sizes.

Recommendation guidance:
- For strong, less-sweet coffee, consider Americano.
- For chocolate flavor, consider Mocha Coffee.
- For caramel flavor, consider Caramel Coffee.
- For creamy drinks, consider a latte.
- For nutty flavor, consider Nutty Latte.
- For a dessert-inspired flavor, consider Tiramisu Latte.
- For a sweet, distinctive flavor, consider Ube Latte.

Only discuss menu details and prices that are known.
Do not invent product prices, ingredients, stock availability,
or promotions. If asked for an exact price not provided here,
tell the customer to check the current menu.

Be friendly, concise, and helpful. You may respond in English
or natural Taglish based on the customer's language.

Do not claim to place orders, process payments, or access
customer accounts. You are currently a recommendation assistant.
`;

    function addMessage(text, sender) {
        const message = document.createElement("div");
        message.className = "message " + sender;
        message.textContent = text;
        chatMessages.appendChild(message);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return message;
    }

    function getResponseText(response) {
        const content = response?.message?.content;

        if (typeof content === "string") {
            return content;
        }

        if (Array.isArray(content)) {
            return content
                .filter(item => item.type === "text")
                .map(item => item.text)
                .join("\n");
        }

        if (typeof response?.text === "string") {
            return response.text;
        }

        return "";
    }

    async function sendMessage(messageText) {
        const question = messageText.trim();

        if (!question || sendButton.disabled) {
            return;
        }

        addMessage(question, "user");
        userInput.value = "";
        sendButton.disabled = true;
        sendButton.textContent = "...";

        const loadingMessage = addMessage(
            "Thinking of the perfect coffee for you...",
            "bot"
        );

        try {
            if (typeof puter === "undefined" || !puter.ai) {
                throw new Error(
                    "Puter AI could not load. Check your internet connection and refresh the page."
                );
            }

            conversation.push({
                role: "user",
                content: question
            });

            const response = await puter.ai.chat(
                [
                    {
                        role: "system",
                        content: systemPrompt
                    },
                    ...conversation.slice(-10)
                ]
            );

            const answer = getResponseText(response);

            if (!answer) {
                throw new Error("The AI returned an empty response. Please try again.");
            }

            loadingMessage.textContent = answer;

            conversation.push({
                role: "assistant",
                content: answer
            });

        } catch (error) {
            loadingMessage.textContent =
                "Sorry, I couldn't connect to the AI right now.\n\n" +
                (error.message || "Please check your internet connection and try again.");

            // Remove the unanswered user message from AI conversation history.
            if (
                conversation.length > 0 &&
                conversation[conversation.length - 1].role === "user"
            ) {
                conversation.pop();
            }
        } finally {
            sendButton.disabled = false;
            sendButton.textContent = "Send";
            userInput.focus();
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    chatForm.addEventListener("submit", function(event) {
        event.preventDefault();
        sendMessage(userInput.value);
    });

    document.querySelectorAll(".suggestions button").forEach(button => {
        button.addEventListener("click", function() {
            sendMessage(this.dataset.question);
        });
    });
</script>

</body>
</html>
