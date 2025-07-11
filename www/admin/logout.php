<?php
session_start();
session_unset(); // Unset all session variables
session_destroy(); // Destroy the session
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Logging Out...</title>
    <script>
        // Clear chatbot data from localStorage
        localStorage.removeItem("chatbotHistory");
        localStorage.removeItem("isChatbotOpen");

        // Redirect to login page with message
        window.location.href = "login.php?error=" + encodeURIComponent("You have been logged out.");
    </script>
</head>
<body>
    Logging out...
</body>
</html>
