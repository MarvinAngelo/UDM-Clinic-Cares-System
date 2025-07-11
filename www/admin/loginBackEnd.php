<?php
session_start(); // Start a session to store user information after login

// Include database initialization
require_once __DIR__ . '/../db.php';

// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$database = "clinic_data";

// First, ensure database exists and is initialized
if (!check_database_initialization()) {
    if (!import_database()) {
        die("Failed to initialize database. Please contact administrator.");
    }
}

// Create a connection
$conn = new mysqli($servername, $username, $password, $database);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['name']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // Query to check if the username and password match
    $sql = "SELECT * FROM account WHERE name = '$username' AND Password = '$password'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows == 1) {
        // Successful login
        $user = $result->fetch_assoc();

        // Store user information in the session
        $_SESSION['name'] = $user['name'];
        $_SESSION['loggedin'] = true; // Set a logged-in flag

        // Redirect to the dashboard or another page
        header("Location: dashboard.php");
        exit();
    } else {
        // Redirect back to login page with error message
        $error = "Invalid Username or Password!";
        header("Location: login.php?error=" . urlencode($error));
        exit();
    }
}

$conn->close();
?>
