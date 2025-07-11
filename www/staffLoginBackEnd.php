<?php
session_start(); // Start a session to store user information after login

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
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // Query to check if the username and password match
    $sql = "SELECT * FROM staffaccount WHERE Username = '$username' AND Password = '$password'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        // Successful login
        $user = $result->fetch_assoc();
    
        // Store user information in the session
        $_SESSION['username'] = $user['Username'];
        $_SESSION['staff_loggedin'] = true; // Add this line
    
        // Redirect to the dashboard or another page
        header("Location: staffdashboard.php");
        exit();
        
    } else {
        // Redirect back to login page with error message
        $error = "Invalid Username or Password!";
        header("Location: stafflogin.php?error=" . urlencode($error));
        exit();
    }

}

$conn->close();
?>
