<?php
// Include database initialization
require_once __DIR__ . '/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if database needs initialization
$needs_init = !check_database_initialization();
$import_complete = false;
$error_message = '';

if ($needs_init) {
    try {
        $import_complete = import_database();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - UDM Clinic</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f0f8ff; /* Light blue background */
            background-image: url('images/UDMCLINIC_LOGO.png'); /* Add UDM logo as background */
            background-size: 800px; /* Adjust the size of the logo */
            background-repeat: no-repeat;
            background-position: 50px center;
            opacity: 0.9; /* Add slight transparency to the background */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .welcome-container {
            width: 100%;
            max-width: 400px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-left: auto;
            margin-right: 250px;
            text-align: center;
            padding: 30px;
        }
        .welcome-title {
            color: #20B2AA;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .welcome-message {
            color: #666;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        .proceed-button {
            width: 100%;
            padding: 12px;
            background-color: #20B2AA;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .proceed-button:hover {
            background-color: #1c9a94;
        }
        .loading-message {
            color: #20B2AA;
            font-size: 16px;
            margin: 20px 0;
        }
        .error-message {
            color: #ff0000;
            font-size: 14px;
            margin: 20px 0;
        }
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #20B2AA;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <h1 class="welcome-title">Welcome to UDM Clinic Cares System</h1>
        <p class="welcome-message">
            Welcome to UDM Clinic Cares System. Click the button below to proceed to the login page.
        </p>
        
        <?php if ($needs_init && !$import_complete): ?>
            <div class="loading-message">Initializing database...</div>
            <div class="loading-spinner"></div>
            <script>
                // Refresh the page every 2 seconds to check import status
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            </script>
        <?php elseif ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <a href="index.php" class="proceed-button">Retry</a>
        <?php else: ?>
            <a href="admin/login.php" class="proceed-button">Proceed to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>
