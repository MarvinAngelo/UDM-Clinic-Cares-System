<?php

session_start();

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Redirect to the login page with an error message
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit();
}



// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "clinic_data";

$connection = new mysqli($servername, $username, $password, $database);

// Check connection
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize form data
    $patientID = $connection->real_escape_string($_POST['patient_id']);
    $date = $connection->real_escape_string($_POST['date']);
    $timeIn = $connection->real_escape_string($_POST['time_in']);
    $timeOut = $connection->real_escape_string($_POST['time_out']);
    $subjective = $connection->real_escape_string($_POST['subjective']);
    $objective = $connection->real_escape_string($_POST['objective']);
    $assessment = $connection->real_escape_string($_POST['assessment']);
    $plan = $connection->real_escape_string($_POST['plan']);
    $planDate = $connection->real_escape_string($_POST['plan_date']);
    $savedBy = $connection->real_escape_string($_POST['saved_by']);

    // Insert data into the consultation table
    $sql = "INSERT INTO consultations (PatientID, Date, TimeIn, TimeOut, Subjective, Objective, Assessment, Plan, PlanDate, SavedBy)
            VALUES ('$patientID', '$date', '$timeIn', '$timeOut', '$subjective', '$objective', '$assessment', '$plan', '$planDate', '$savedBy')";

    if ($connection->query($sql) === TRUE) {
        // Display success message with JavaScript
        echo "<script>
                alert('Consultation record saved successfully.');
                window.location.href = 'frontconsult.php'; // Redirect to a desired page
              </script>";
    } else {
        // Display error message with JavaScript
        echo "<script>
                alert('Error: " . addslashes($connection->error) . "');
              </script>";
    }

    $connection->close();
}
?>
