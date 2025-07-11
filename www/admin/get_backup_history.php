<?php
header('Content-Type: application/json');

// Database connection details (consistent with staff_creation.php)
$servername = "localhost";
$username_db = "root";
$password_db = "";
$database = "clinic_data";

$connection = new mysqli($servername, $username_db, $password_db, $database);

if ($connection->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $connection->connect_error]);
    exit();
}

$backup_history = [];

// IMPORTANT: Ensure you have a 'backup_logs' table in your clinic_data database.
// If not, you will need to create it. Example SQL provided in previous response.
$query = "SELECT backup_datetime, backup_type, backup_status, file_name FROM backup_logs ORDER BY backup_datetime DESC";

$result = $connection->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Format the datetime for better readability in the frontend
        $formatted_datetime = date('Y-m-d h:i A', strtotime($row['backup_datetime']));
        $backup_history[] = [
            "dateTime" => $formatted_datetime,
            "type" => $row['backup_type'],
            "status" => $row['backup_status'],
            "fileName" => $row['file_name']
        ];
    }
    $result->free();
}

$connection->close();

echo json_encode($backup_history);

?>