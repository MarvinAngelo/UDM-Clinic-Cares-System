<?php
$host = "127.0.0.1"; // usually localhost or 127.0.0.1
$user = "root";      // default for local MySQL
$pass = "";          // your MySQL password if any
$dbName = "clinic_data"; // change this to your target DB name
$backupFile = "../db/clinic_data.sql"; // path to SQL file
$schemaFile = "../db/schema.sql"; // path to schema file

// Check if SQL files exist
if (!file_exists($backupFile)) {
    die("Error: Data SQL file not found at: " . $backupFile);
}
if (!file_exists($schemaFile)) {
    die("Error: Schema SQL file not found at: " . $schemaFile);
}

// Check if SQL files are readable
if (!is_readable($backupFile) || !is_readable($schemaFile)) {
    die("Error: SQL files are not readable. Check file permissions.");
}

// Connect to MySQL
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to MySQL successfully.<br>";

// Set proper character set and collation
$conn->set_charset("utf8mb4");

// Create database if not exists
if ($conn->query("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
    echo "Database created or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select database
if (!$conn->select_db($dbName)) {
    die("Error selecting database: " . $conn->error);
}

echo "Selected database: $dbName<br>";

// First, import the schema
echo "Importing database schema...<br>";
$schema = file_get_contents($schemaFile);
if ($schema === false) {
    die("Error reading schema file");
}

// Execute schema queries
if ($conn->multi_query($schema)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    
    if ($conn->error) {
        echo "Warning: Some schema commands had errors: " . $conn->error . "<br>";
    } else {
        echo "Schema imported successfully!<br>";
    }
} else {
    echo "Error importing schema: " . $conn->error . "<br>";
}

// Now import the data using MySQL's native import
echo "Importing data...<br>";

// Read the entire SQL file
$sql = file_get_contents($backupFile);
if ($sql === false) {
    die("Error reading data file");
}

// Remove comments and normalize line endings
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove multi-line comments
$sql = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
$sql = str_replace(["\r\n", "\r"], "\n", $sql); // Normalize line endings

// Execute the SQL using multi_query
if ($conn->multi_query($sql)) {
    $successCount = 0;
    $errorCount = 0;
    
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
            $successCount++;
        }
        
        // Check for errors
        if ($conn->error) {
            echo "Error executing query: " . $conn->error . "<br>";
            $errorCount++;
        }
        
        // Show progress
        if ($successCount % 10 === 0) {
            echo "Progress: $successCount queries executed successfully.<br>";
        }
        
    } while ($conn->next_result());
    
    echo "Data import completed.<br>";
    echo "Successfully executed $successCount queries.<br>";
    if ($errorCount > 0) {
        echo "Encountered $errorCount errors during import.<br>";
    }
} else {
    echo "Error executing SQL: " . $conn->error . "<br>";
}

// Verify data import
$tables = ['account', 'backup_logs', 'consultations', 'email_logs', 'medicine_inventory', 'patients', 'staff', 'staffaccount'];
echo "<br>Verifying data import:<br>";

foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "Table '$table': " . $row['count'] . " records found<br>";
    } else {
        echo "Table '$table': Error checking records - " . $conn->error . "<br>";
    }
}

$conn->close();
echo "<br>Database connection closed.";
?>
