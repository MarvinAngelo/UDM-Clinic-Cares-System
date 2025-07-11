<?php
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

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = $connection->real_escape_string($_POST['delete_id']);
    $deleteQuery = "DELETE FROM patients WHERE PatientID = ?";
    $stmt = $connection->prepare($deleteQuery);
    $stmt->bind_param("i", $deleteId);

    if ($stmt->execute()) {
        echo '<div class="alert alert-success">Record removed successfully!</div>';
    } else {
        echo '<div class="alert alert-danger">Error removing record: ' . $connection->error . '</div>';
    }

    $stmt->close();
}

// Fetch patients data
$sql = "SELECT PatientID, CONCAT(LastName, ', ', FirstName, ' ', MiddleInitial) AS full_name FROM patients";
$result = $connection->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
        echo '<a href="view_details.php?id=' . $row['PatientID'] . '" class="text-primary">' . htmlspecialchars($row['full_name']) . '</a>';
        echo '<div>';
        echo '<a href="edit_patient.php?id=' . $row['PatientID'] . '" class="btn btn-warning btn-sm">Edit</a>';
        echo '<form method="POST" action="" style="display:inline;" onsubmit="return confirm(\'Are you sure you want to delete this record?\');">';
        echo '<input type="hidden" name="delete_id" value="' . $row['PatientID'] . '">';
        echo '<button type="submit" class="btn btn-danger btn-sm">Remove</button>';
        echo '</form>';
        echo '</div>';
        echo '</li>';
    }
} else {
    echo '<li class="list-group-item text-muted">No patients found.</li>';
}

$connection->close();
?>
