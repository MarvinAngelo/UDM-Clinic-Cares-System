<?php
session_start();

require_once 'backup.php'; // Adjust path if backup.php is in a different directory
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

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Get the assessment from the URL
$assessment = isset($_GET['assessment']) ? $_GET['assessment'] : '';

if ($assessment) {
    // Fetch distinct patients with the selected assessment
    $query = "
        SELECT DISTINCT p.FirstName, p.MiddleInitial, p.LastName, p.Student_Num
        FROM consultations c
        JOIN patients p ON c.PatientID = p.PatientID
        WHERE c.Assessment = ?
    ";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $assessment);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Details</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Patients with Assessment: <?= htmlspecialchars($assessment) ?></h2>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>First Name</th>
                <th>Middle Initial</th>
                <th>Last Name</th>
                <th>Student Number</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                        <td>" . htmlspecialchars($row['FirstName']) . "</td>
                        <td>" . htmlspecialchars($row['MiddleInitial']) . "</td>
                        <td>" . htmlspecialchars($row['LastName']) . "</td>
                        <td>" . htmlspecialchars($row['Student_Num']) . "</td>
                    </tr>";
                }
            } else {
                echo "<tr><td colspan='4'>No patients found for this assessment.</td></tr>";
            }
            ?>
        </tbody>
    </table>
    <div class="no-print">
        <button class="btn btn-primary" onclick="window.print()">Print</button>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>
</body>
</html>
<?php $connection->close(); ?>
