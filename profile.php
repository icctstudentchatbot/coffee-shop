<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile - Coffee Shop</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f1eb;
            margin: 0;
            padding: 40px;
        }

        .profile-box {
            max-width: 500px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        }

        h1 {
            text-align: center;
            color: #3e2723;
        }

        .info {
            margin: 20px 0;
        }

        .info p {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 20px;
        }

        a {
            color: #3e2723;
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="profile-box">

    <h1>My Profile</h1>

    <div class="info">

        <p>
            <strong>Name:</strong>
            <?php echo htmlspecialchars($user['name']); ?>
        </p>

        <p>
            <strong>Email:</strong>
            <?php echo htmlspecialchars($user['email']); ?>
        </p>

        <p>
            <strong>Account Type:</strong>
            <?php echo htmlspecialchars($user['role']); ?>
        </p>

    </div>

    <div class="back">
        <a href="index.php">← Back to Home</a>
    </div>

</div>

</body>
</html>