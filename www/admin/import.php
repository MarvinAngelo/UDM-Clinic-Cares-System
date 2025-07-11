<?php
session_start();


// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit();
}

$servername = "localhost";
$username_db = "root";
$password_db = "";
$database = "clinic_data"; // The database to import into

// Initialize variables for logging
$import_status = 'Failed'; // Default to failed
$log_message = '';
$file_name_for_log = 'N/A'; // Default, will be updated if file is valid
$current_datetime = date('Y-m-d H:i:s'); // For logging the event time

if (isset($_POST['import_backup']) && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];

    // Update file name for log, even if it's an invalid type or error
    $file_name_for_log = $file['name'];

    // Handle file upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_code = $file['error'];
        $error_message = "File upload error: " . $error_code;
        // Translate common upload errors for better user feedback
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                $error_message .= " (File exceeds upload_max_filesize in php.ini)";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $error_message .= " (File exceeds MAX_FILE_SIZE in HTML form)";
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message .= " (File was only partially uploaded)";
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message .= " (No file was uploaded)";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message .= " (Missing a temporary folder)";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message .= " (Failed to write file to disk)";
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message .= " (A PHP extension stopped the file upload)";
                break;
            default:
                $error_message .= " (Unknown error)";
                break;
        }
        $_SESSION['message'] = "<div class='alert alert-danger'>" . $error_message . "</div>";
        $log_message = $error_message;
        $import_status = 'Failed'; // Explicitly set status for log
        // Log this failure before redirecting
        logBackupEvent($servername, $username_db, $password_db, $database, $current_datetime, 'Import', $import_status, $file_name_for_log, $log_message);
        header("Location: staff_creation.php");
        exit();
    }

    // Validate file type to ensure it's an SQL file
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($file_extension) !== 'sql') { // Use strtolower for case-insensitive check
        $_SESSION['message'] = "<div class='alert alert-danger'>Invalid file type. Please upload a .sql file.</div>";
        $log_message = "Invalid file type uploaded: ." . $file_extension;
        $import_status = 'Failed'; // Explicitly set status for log
        logBackupEvent($servername, $username_db, $password_db, $database, $current_datetime, 'Import', $import_status, $file_name_for_log, $log_message);
        header("Location: staff_creation.php");
        exit();
    }

    $temp_file_path = $file['tmp_name'];

    // --- Import using `mysql.exe` ---

    // Full path to mysql.exe (adjust if necessary for your XAMPP installation)
    $mysql_path = "C:/xampp/mysql/bin/mysql.exe";

    // Construct the command to import the SQL file into the *existing* database
    // escapeshellarg is crucial for handling file paths with spaces or special characters
    $password_arg = !empty($password_db) ? "--password={$password_db}" : "";
    $command = "\"{$mysql_path}\" --user={$username_db} {$password_arg} {$database} < " . escapeshellarg($temp_file_path);

    // Execute the command
    exec($command, $output, $return_var);

    // Check the return status of the command
    if ($return_var === 0) {
        $_SESSION['message'] = "<div class='alert alert-success'>Database imported successfully into '{$database}'.</div>";
        $import_status = 'Success';
        $log_message = "Database '{$database}' imported successfully from {$file_name_for_log}.";
    } else {
        // Provide more detailed error message if import fails
        $error_output = implode('<br>', $output);
        $_SESSION['message'] = "<div class='alert alert-danger'>Error importing database. Return code: {$return_var}. Output: " . $error_output . "</div>";
        $import_status = 'Failed';
        $log_message = "Database import failed. Return code: {$return_var}. Output: " . strip_tags($error_output); // strip_tags for cleaner log
    }

    // Clean up: delete the temporary uploaded file
    unlink($temp_file_path);

} else {
    // Handle cases where the form was not submitted correctly
    $_SESSION['message'] = "<div class='alert alert-danger'>Invalid request for database import.</div>";
    $log_message = "Invalid request for database import (form not submitted correctly or no file).";
    $import_status = 'Failed';
}

// Log the import event to the database
logBackupEvent($servername, $username_db, $password_db, $database, $current_datetime, 'Import', $import_status, $file_name_for_log, $log_message);


// Function to log backup/import events to the database
function logBackupEvent($servername, $username_db, $password_db, $database, $datetime, $type, $status, $fileName, $message) {
    $conn_log = new mysqli($servername, $username_db, $password_db, $database);

    if ($conn_log->connect_error) {
        // Log to file or error handler if database connection for logging fails
        error_log("Failed to connect to database for backup/import logging: " . $conn_log->connect_error);
        return; // Cannot log if connection fails
    }

    $stmt = $conn_log->prepare("INSERT INTO backup_logs (backup_datetime, backup_type, backup_status, file_name, log_message) VALUES (?, ?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("Prepare failed for backup_logs insertion: " . $conn_log->error);
        $conn_log->close();
        return;
    }

    $stmt->bind_param("sssss", $datetime, $type, $status, $fileName, $message);

    if (!$stmt->execute()) {
        error_log("Execute failed for backup_logs insertion: " . $stmt->error);
    }

    $stmt->close();
    $conn_log->close();
}

// Redirect back to the staff creation page, or wherever appropriate
header("Location: staff_creation.php");
exit();