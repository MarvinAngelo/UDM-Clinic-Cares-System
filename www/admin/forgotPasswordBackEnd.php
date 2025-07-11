<?php
session_start();

require_once 'backup.php'; // Adjust path if backup.php is in a different directory
// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$database = "clinic_data";

// Create a connection
$conn = new mysqli($servername, $username, $password, $database);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the username and new password from the form
    $username = trim($_POST['username']);
    $new_password = trim($_POST['new_password']);

    // Query to check if the username exists
    $stmt = $conn->prepare("SELECT * FROM account WHERE TRIM(name) = TRIM(?)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        // Update the password for the user (without hashing)
        $update_stmt = $conn->prepare("UPDATE account SET Password = ? WHERE TRIM(name) = TRIM(?)");
        $update_stmt->bind_param("ss", $new_password, $username);

        if ($update_stmt->execute()) {
            $success = "Password successfully updated!";
            header("Location: forgotPassword.php?success=" . urlencode($success));
            exit();
        } else {
            $error = "Error updating password: " . $conn->error;
            header("Location: forgotPassword.php?error=" . urlencode($error));
            exit();
        }
    } else {
        $error = "Username not found!";
        header("Location: forgotPassword.php?error=" . urlencode($error));
        exit();
    }

    // Close statements
    $stmt->close();
    $update_stmt->close();
}

// Close the connection
$conn->close();
?>
