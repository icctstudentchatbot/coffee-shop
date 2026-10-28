<?php
session_start();
include "db.php";

$message = "";

if (isset($_POST['login'])) {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            header("Location: index.php");
            exit();

        } else {
            $message = "Incorrect password.";
        }

    } else {
        $message = "Account not found.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Coffee Shop</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f1eb;
            margin: 0;
            padding: 40px;
        }

        .login-box {
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
            color: #b00020;
        }

        .register {
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>

<body>

<div class="login-box">

    <h1>Login</h1>

    <?php if ($message != ""): ?>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <label>Email</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit" name="login">
            Login
        </button>

    </form>

    <div class="register">
        Don't have an account?
        <a href="register.php">Create Account</a>
    </div>

</div>

</body>
</html>