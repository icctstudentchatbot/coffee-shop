<?php
include "db.php";

$message = "";

if (isset($_POST['register'])) {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {

        $message = "Email is already registered.";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, password) VALUES (?, ?, ?)"
        );

        $stmt->bind_param("sss", $name, $email, $password);

        if ($stmt->execute()) {
            $message = "Registration successful!";
        } else {
            $message = "Registration failed.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Coffee Shop</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f1eb;
            margin: 0;
            padding: 40px;
        }

        .register-box {
            max-width: 400px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
        }

        h1 {
            text-align: center;
            color: #3e2723;
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 8px 0 15px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #3e2723;
            color: white;
            border: none;
            cursor: pointer;
        }

        .message {
            text-align: center;
            margin-bottom: 15px;
        }

        .login {
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>

<body>

<div class="register-box">

    <h1>Create Account</h1>

    <?php if ($message != ""): ?>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Name</label>
        <input type="text" name="name" required>

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit" name="register">
            Register
        </button>

    </form>

    <div class="login">
        Already have an account?
        <a href="login.php">Login</a>
    </div>

</div>

</body>
</html>