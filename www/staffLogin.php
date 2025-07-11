<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UDM Clinic</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f0f8ff;
            background-image: url('images/UDMCLINIC_LOGO.png');
            background-size: 800px;
            background-repeat: no-repeat;
            background-position: 50px center;
            opacity: 0.9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-left: auto;
            margin-right: 250px;
        }
        .login-form {
            padding: 20px;
        }
        .input-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        .login-form input {
            width: 335px;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        .eye-icon {
            position: absolute;
            right: 10px;
            cursor: pointer;
            color: gray;
        }
        .login-form button {
            width: 100%;
            padding: 10px;
            background-color: #20B2AA;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .login-form button:hover {
            background-color: #1c9a94;
        }
        .login-form .forgot-password {
            display: block;
            margin-top: 10px;
            text-align: center;
            font-size: 14px;
            color: #20B2AA;
            text-decoration: none;
            transition: color 0.3s;
        }
        .login-form .forgot-password:hover {
            color: #1c9a94;
        }
        .error-message {
            color: red;
            font-size: 14px;
            margin-bottom: 10px;
            text-align: center;
        }
        .rocker {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.2em; /* Smaller font size */
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            width: 8em; /* Smaller width */
            height: 2.5em; /* Smaller height */
            overflow: hidden;
            border-bottom: 0.3em solid #eee;
        }

        .rocker-small {
            font-size: 1em;
            margin: 0.5em;
        }

        .rocker::before {
            content: "";
            position: absolute;
            top: 0.3em;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #999;
            border: 0.3em solid #eee;
            border-bottom: 0;
        }

        .rocker input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .switch-left,
        .switch-right {
            cursor: pointer;
            position: absolute;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 1.8em; /* Smaller buttons */
            width: 3.5em; /* Smaller width */
            transition: 0.3s;
            user-select: none;
        }

        .switch-left {
            height: 1.8em;
            width: 3.5em;
            left: 0.6em;
            bottom: 0.2em;
            background-color: #ddd;
            transform: rotate(10deg) skewX(10deg);
        }

        .switch-right {
            right: 0.3em;
            bottom: 0;
            background-color: #32CD32;
            color: #fff;
        }

        .switch-left::before,
        .switch-right::before {
            content: "";
            position: absolute;
            width: 0.3em;
            height: 1.8em;
            bottom: -0.3em;
            background-color: #ccc;
            transform: skewY(-55deg);
        }

        .switch-left::before {
            left: -0.3em;
        }

        .switch-right::before {
            right: -0.3em;
            background-color: transparent;
            transform: skewY(55deg);
        }

        /* Adjust switch movement */
        input:checked + .switch-left {
            background-color: #0084d0;
            color: #fff;
            bottom: 0px;
            left: 0.3em;
            height: 1.8em;
            width: 3.5em;
            transform: rotate(0deg) skewX(0deg);
        }

        input:checked + .switch-left::before {
            background-color: transparent;
            width: 2.5em;
        }

        input:checked + .switch-left + .switch-right {
            background-color: #ddd;
            color: #888;
            bottom: 0.2em;
            right: 0.5em;
            height: 1.8em;
            width: 3.5em;
            transform: rotate(-10deg) skewX(-10deg);
        }

        input:checked + .switch-left + .switch-right::before {
            background-color: #ccc;
        }

        input:checked + .switch-left + .switch-right::before {
            background-color: #ccc;
        }

        /* Keyboard Users */
        input:focus + .switch-left {
            color: #333;
        }

        input:checked:focus + .switch-left {
            color: #fff;
        }

        input:focus + .switch-left + .switch-right {
            color: #fff;
        }

        input:checked:focus + .switch-left + .switch-right {
            color: #333;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const roleSwitch = document.getElementById("roleSwitch");

            roleSwitch.addEventListener("change", function () {
                if (roleSwitch.checked) {
                    window.location.href = "admin/login.php"; // Redirect to Admin login (assuming 'Admin' is checked means switching to Admin path)
                } else {
                    window.location.href = "staffLogin.php"; // Redirect to Staff login (assuming 'Staff' is not checked means switching to Staff path)
                }
            });

            // Get the forgot password link
            const forgotPasswordLink = document.getElementById("forgotPasswordLink");

            // Add click event listener to the link
            forgotPasswordLink.addEventListener("click", function(event) {
                event.preventDefault(); // Prevent the default link behavior (navigating to forgotPassword.php)
                this.textContent = "Contact your Admin"; // Change the text of the link
                // Optional: You might want to disable further clicks or remove the link if it's a one-time message
                // this.style.pointerEvents = 'none'; // Makes it unclickable
                // this.removeAttribute('href'); // Removes the href attribute
            });
        });

        function togglePassword() {
            var passwordField = document.getElementById("password");
            var eyeIcon = document.getElementById("eye-icon");
            if (passwordField.type === "password") {
                passwordField.type = "text";
                eyeIcon.textContent = "👁️‍🗨️";
            } else {
                passwordField.type = "password";
                eyeIcon.textContent = "👁️";
            }
        }
    </script>
</head>
<body>
    <label class="rocker rocker-small">
        <input type="checkbox" id="roleSwitch">
        <span class="switch-left">Admin</span>
        <span class="switch-right">Staff</span>
    </label>

    <div class="login-container">
        <form class="login-form" method="post" action="staffLoginBackEnd.php">
            <input type="text" name="username" placeholder="Username" required>
            <div class="input-container">
                <input type="password" id="password" name="password" placeholder="Password" required>
                <span class="eye-icon" id="eye-icon" onclick="togglePassword()">👁️</span>
            </div>
            
            <?php
            if (isset($_GET['error'])) {
                echo "<div class='error-message'>" . htmlspecialchars($_GET['error']) . "</div>";
            }
            ?>

            <button type="submit">Login</button>
            <a href="forgotPassword.php" class="forgot-password" id="forgotPasswordLink">Forgot Password?</a>
        </form>
    </div>
</body>
</html>