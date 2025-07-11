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
            background-color: #f0f8ff; /* Light blue background */
            background-image: url('../images/UDMCLINIC_LOGO.png'); /* Add UDM logo as background */
            background-size: 800px; /* Adjust the size of the logo */
            background-repeat: no-repeat;
            background-position: 50px center;
            opacity: 0.9; /* Add slight transparency to the background */
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
        .login-form input {
            width: 335px;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-container input {
            width: 100%;
            padding-right: 40px;
        }
        .password-container .eye-icon {
            position: absolute;
            right: 10px;
            cursor: pointer;
            font-size: 18px;
            color: #888;
        }
        .login-form button {
            width: 100%;
            padding: 10px;
            background-color: #20B2AA; /* Sea green background */
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
  font-size: 1.2em;  /* Smaller font size */
  font-weight: bold;
  text-align: center;
  text-transform: uppercase;
  width: 8em;   /* Smaller width */
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
  width: 4em;
  right: 0.3em;
  bottom: 0.2em;
  background-color: #ddd;
  transform: rotate(-10deg) skewX(-10deg);
  left: auto;
}

.switch-right {
  left: 0.3em;
  bottom: 0;
  background-color: #0000FF;
  color: #fff;
  right: auto;
}

.switch-left::before {
  right: -0.25em;
  background-color: transparent;
  transform: skewY(60deg);
  left: auto;
}

.switch-right::before {
  left: -0.25em;
  background-color: #ccc;
  transform: skewY(-60deg);
  right: auto;
}

/* Reverse switch movement */
input:checked + .switch-left {
  background-color: #ddd;
  color: #888;
  bottom: 0.2em;
  right: 0.5em;
  height: 1.8em;
  width: 3.5em;
  transform: rotate(-10deg) skewX(-10deg);
  left: auto;
}

input:checked + .switch-left::before {
  background-color: #ccc;
}

input:checked + .switch-left + .switch-right {
  background-color: #0084d0;
  color: #fff;
  bottom: 0px;
  left: 0em;
  height: 1.8em;
  width: 3.7em;
  transform: rotate(0deg) skewX(0deg);
  right: auto;
}

input:checked + .switch-left + .switch-right::before {
  background-color: transparent;
  width: 2.5em;
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
            window.location.href = "../staffLogin.php"; // Redirect to Staff login
        } else {
            window.location.href = "login.php"; // Redirect to Admin login
        }
    });
});


        function togglePassword() {
            var passwordInput = document.getElementById("password");
            var eyeIcon = document.getElementById("eye-icon");

            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                eyeIcon.textContent = "👁️‍🗨️"; // Hide password icon
            } else {
                passwordInput.type = "password";
                eyeIcon.textContent = "👁️"; // Show password icon
            }
        }
    </script>
</head>
<body>
    <!-- Dropdown for role selection -->
    <!-- Rocker Switch for Role Selection -->
<label class="rocker rocker-small">
    <input type="checkbox" id="roleSwitch">
    <span class="switch-left">Staff</span>
    <span class="switch-right">Admin</span>
</label>


    <div class="login-container">
        <form class="login-form" method="post" action="loginBackEnd.php">
            <input type="text" name="name" placeholder="Username" required>

            <div class="password-container">
                <input type="password" id="password" name="password" placeholder="Password" required>
                <span class="eye-icon" id="eye-icon" onclick="togglePassword()">👁️</span>
            </div>

            <!-- Display error message -->
            <?php
            if (isset($_GET['error'])) {
                echo "<div class='error-message'>" . htmlspecialchars($_GET['error']) . "</div>";
            }
            ?>

            <button type="submit">Login</button>
            <a href="emailForgotPass.php" class="forgot-password">Forgot Password?</a>
        </form>
    </div>
</body>
</html>
