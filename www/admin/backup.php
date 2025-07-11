<?php
session_start();

// Set the default timezone to Asia/Manila (Philippines)
date_default_timezone_set('Asia/Manila');

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit();
}

$servername = "localhost";
$username_db = "root";
$password_db = "";
$database = "clinic_data";

// Google Drive backup folder (ensure this is correct and accessible)
// It's better to explicitly define this once.
$googleDriveBackupBase = 'C:\\My Drive\\ClinicCaresBackup'; // Adjust this path if your Google Drive is on another letter or path

// Path to mysqldump executable
$mysqldump_path = "C:/xampp/mysql/bin/mysqldump.exe"; // Adjust if necessary

// Number of days to retain backups
$backupRetentionDays = 10;

// --- Function to log backup events to the database ---
function logBackupEvent($servername, $username_db, $password_db, $database, $datetime, $type, $status, $fileName, $message) {
    $conn_log = new mysqli($servername, $username_db, $password_db, $database);

    if ($conn_log->connect_error) {
        error_log("Failed to connect to database for backup logging: " . $conn_log->connect_error);
        return;
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

// --- Function to perform the actual backup ---
function performBackup($servername, $username_db, $password_db, $database, $googleDrivePath, $mysqldump_path, $backup_type) {
    $current_datetime = date('Y-m-d H:i:s');
    $backup_file_name = $database . '_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_file_name_for_log = $backup_file_name; // Store for logging
    $backup_status = 'Failed';
    $log_message = '';

    // Create the backup folder if it doesn't exist
    if (!is_dir($googleDrivePath)) {
        if (!mkdir($googleDrivePath, 0755, true)) {
            $log_message = "Failed to create Google Drive backup folder: {$googleDrivePath}.";
            logBackupEvent($servername, $username_db, $password_db, $database, $current_datetime, $backup_type, $backup_status, $backup_file_name_for_log, $log_message);
            return ['status' => false, 'message' => $log_message];
        }
    }

    $backup_path = $googleDrivePath . '\\' . $backup_file_name;

    $password_arg = !empty($password_db) ? "--password={$password_db}" : "";
    $command = "\"{$mysqldump_path}\" --user={$username_db} {$password_arg} {$database} > \"{$backup_path}\"";

    exec($command, $output, $return_var);

    if ($return_var === 0) {
        $backup_status = 'Success';
        $log_message = "Backup successfully created at " . $backup_path;
        $return_data = ['status' => true, 'message' => "Backup saved to Google Drive: {$backup_path}"];
    } else {
        $error_output = implode('<br>', $output);
        $backup_status = 'Failed';
        $log_message = "Backup failed. Return code: {$return_var}. Output: {$error_output}";
        $return_data = ['status' => false, 'message' => "Error creating backup. Return code: {$return_var}. Output: " . $error_output];
    }

    logBackupEvent($servername, $username_db, $password_db, $database, $current_datetime, $backup_type, $backup_status, $backup_file_name_for_log, $log_message);
    return $return_data;
}

// --- Function to detect Google Drive path ---
function detectGoogleDrivePath($baseFolder) {
    foreach (range('C', 'Z') as $driveLetter) {
        $path = $driveLetter . ':\\';
        if (
            is_dir($path . 'My Drive') ||
            is_dir($path . 'Google Drive') ||
            is_dir($path . 'DriveFS') ||
            is_dir($path . 'Shared drives') ||
            is_dir($path . 'Google Drive for desktop')
        ) {
            return $path . 'My Drive' . DIRECTORY_SEPARATOR . basename($baseFolder);
        }
    }
    return null;
}

// --- Function to delete old backups ---
function deleteOldBackups($backupPath, $retentionDays) {
    $files = glob($backupPath . '/*.sql');
    $now = time();
    $deletedCount = 0;

    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                // Extract the date from the filename (e.g., clinic_data_backup_YYYY-MM-DD_HH-MM-SS.sql)
                if (preg_match('/_backup_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})\.sql$/', basename($file), $matches)) {
                    $fileDateStr = $matches[1];
                    $fileTimeStr = $matches[2];
                    $fileTimestamp = strtotime("{$fileDateStr} {$fileTimeStr}");

                    // Check if the timestamp is valid and not in the future
                    if ($fileTimestamp !== false && $fileTimestamp < $now) {
                        $diffSeconds = $now - $fileTimestamp;
                        $diffDays = floor($diffSeconds / (60 * 60 * 24)); // Calculate full days difference

                        if ($diffDays >= $retentionDays) {
                            if (unlink($file)) {
                                error_log("Deleted old backup: " . $file);
                                $deletedCount++;
                            } else {
                                error_log("Failed to delete old backup: " . $file);
                            }
                        }
                    } else {
                        error_log("Invalid timestamp parsed from filename or file is from the future: " . basename($file));
                    }
                } else {
                    error_log("Filename does not match expected pattern for date extraction: " . basename($file));
                }
            }
        }
    }
    return $deletedCount;
}

// --- Determine the actual Google Drive path ---
$googleDrivePath = detectGoogleDrivePath($googleDriveBackupBase);

if ($googleDrivePath === null) {
    // If Google Drive is not found, log an error.
    // For manual backup attempts, set a session message.
    if (isset($_POST['save_backup'])) {
        $_SESSION['message'] = "<div class='alert alert-danger'>Google Drive not found. Please ensure it's installed and synced.</div>";
        logBackupEvent($servername, $username_db, $password_db, $database, date('Y-m-d H:i:s'), 'Manual', 'Failed', '', "Google Drive not found or accessible for manual backup.");
        header("Location: staff_creation.php");
        exit();
    }
    error_log("Google Drive not found or accessible. Automatic backup skipped.");
} else {
    // --- Automatic Backup Logic ---
    // This part runs every time the page including this script is accessed.
    // It checks if it's past 3 PM and if a backup for today has already been made.
    $currentHour = (int)date('H'); // Current hour in 24-hour format
    $currentDate = date('Y-m-d');

    // To prevent multiple daily automatic backups, store the last auto backup date.
    // We'll use a simple text file for this. Make sure this file is writable by XAMPP.
    $lastBackupLogFile = __DIR__ . '/last_auto_backup_date.txt'; // __DIR__ refers to the directory of the current script

    $lastAutoBackupDate = '';
    if (file_exists($lastBackupLogFile)) {
        $lastAutoBackupDate = trim(file_get_contents($lastBackupLogFile));
    }

    // Check if it's 3 PM (15:00) or later AND no backup has been done for today
    if ($currentHour >= 15 && $currentDate !== $lastAutoBackupDate) {
        error_log("Attempting automatic backup for {$currentDate}...");
        $backupResult = performBackup($servername, $username_db, $password_db, $database, $googleDrivePath, $mysqldump_path, 'Automatic');

        if ($backupResult['status']) {
            // Update the last backup date only if successful
            file_put_contents($lastBackupLogFile, $currentDate);
            error_log("Automatic backup successful for {$currentDate}.");
            // After successful automatic backup, clean up old files
            $deletedCount = deleteOldBackups($googleDrivePath, $backupRetentionDays);
            error_log("Deleted {$deletedCount} old backup files from automatic run.");
        } else {
            error_log("Automatic backup failed: " . $backupResult['message']);
        }
    }

    // --- Manual Backup Logic (retained from original code) ---
    if (isset($_POST['save_backup'])) {
        $manualBackupResult = performBackup($servername, $username_db, $password_db, $database, $googleDrivePath, $mysqldump_path, 'Manual');

        if ($manualBackupResult['status']) {
            $_SESSION['message'] = "<div class='alert alert-success'>" . $manualBackupResult['message'] . "</div>";
            // Also clean up old files after a manual backup
            $deletedCount = deleteOldBackups($googleDrivePath, $backupRetentionDays);
            error_log("Deleted {$deletedCount} old backup files after manual backup.");
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>" . $manualBackupResult['message'] . "</div>";
        }
    }
}

// Redirect back to the staff creation page ONLY if this script was called by a form submission
// If this script is *included* on another page for automatic backup, do not exit here,
// allow the parent page to render normally.
if (isset($_POST['save_backup'])) {
    header("Location: staff_creation.php");
    exit();
}
?>