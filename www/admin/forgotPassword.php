<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - UDM Clinic</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f7f9fc;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .forgot-password-container {
            width: 100%;
            max-width: 400px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .forgot-password-form {
            padding: 20px;
        }
        .forgot-password-form select, .forgot-password-form input {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        .forgot-password-form button {
            width: 100%;
            padding: 10px;
            background-color: #4682B4;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .forgot-password-form button:hover {
            background-color: #4169E1;
        }
        .forgot-password-form .back-to-login {
            display: block;
            margin-top: 10px;
            text-align: center;
            font-size: 14px;
            color: #4682B4;
            text-decoration: none;
            transition: color 0.3s;
        }
        .forgot-password-form .back-to-login:hover {
            color: #4169E1;
        }
        .error-message {
            color: red;
            font-size: 14px;
            margin-bottom: 10px;
            text-align: center;
        }
        .success-message {
            color: green;
            font-size: 14px;
            margin-bottom: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <form class="forgot-password-form" method="post" action="forgotPasswordBackEnd.php">
            <h2 style="text-align: center; color: #4682B4;">Forgot Password</h2>

            <!-- Dropdown for usernames -->
            <select name="username" required>
                <option value="" disabled selected>Select a username</option>
                <?php
                // Populate the dropdown with usernames
                $conn = new mysqli("localhost", "root", "", "clinic_data");
                if ($conn->connect_error) {
                    die("Connection failed: " . $conn->connect_error);
                }
                $result = $conn->query("SELECT name FROM account");
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<option value='" . htmlspecialchars($row['name']) . "'>" . htmlspecialchars($row['name']) . "</option>";
                    }
                } else {
                    echo "<option disabled>No usernames found</option>";
                }
                $conn->close();
                ?>
            </select>

            <input type="password" name="new_password" placeholder="Enter new password" required>

            <!-- Display error or success message -->
            <?php
            if (isset($_GET['error'])) {
                echo "<div class='error-message'>" . htmlspecialchars($_GET['error']) . "</div>";
            } elseif (isset($_GET['success'])) {
                echo "<div class='success-message'>" . htmlspecialchars($_GET['success']) . "</div>";
            }
            ?>

            <button type="submit">Reset Password</button>
            <a href="login.php" class="back-to-login">Back to Login</a>
        </form>
    </div>
</body>
</html>
