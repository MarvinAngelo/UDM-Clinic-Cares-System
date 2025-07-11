<?php
function import_database() {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db = 'clinic_data';
    $sql_file = __DIR__ . '/db/clinic_data.sql';
    $schema_file = __DIR__ . '/db/schema.sql';

    // Check if SQL files exist
    if (!file_exists($sql_file)) {
        die('Data SQL file not found at: ' . $sql_file);
    }
    if (!file_exists($schema_file)) {
        die('Schema SQL file not found at: ' . $schema_file);
    }

    $mysqli = new mysqli($host, $user, $pass);
    if ($mysqli->connect_errno) {
        die('Failed to connect to MySQL: ' . $mysqli->connect_error);
    }

    // Set proper character set
    $mysqli->set_charset("utf8mb4");

    // Check if database exists
    $db_exists = $mysqli->query("SHOW DATABASES LIKE '$db'")->num_rows > 0;
    if (!$db_exists) {
        if (!$mysqli->query("CREATE DATABASE `$db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
            die('Failed to create database: ' . $mysqli->error);
        }
    }
    
    if (!$mysqli->select_db($db)) {
        die('Failed to select database: ' . $mysqli->error);
    }

    // Check if any tables exist
    $result = $mysqli->query("SHOW TABLES");
    if ($result && $result->num_rows > 0) {
        // Database already imported
        $mysqli->close();
        return true;
    }

    // First import schema
    $schema = file_get_contents($schema_file);
    if ($schema === false) {
        die('Failed to read schema file');
    }

    if (!$mysqli->multi_query($schema)) {
        die('Error importing schema: ' . $mysqli->error);
    }

    // Process all schema results
    do {
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
    } while ($mysqli->next_result());

    if ($mysqli->error) {
        die('Error in schema import: ' . $mysqli->error);
    }

    // Now import data
    $handle = fopen($sql_file, 'r');
    if ($handle === false) {
        die('Failed to open data file');
    }

    $query = '';
    $inString = false;
    $stringChar = '';
    $escaped = false;
    $lineNumber = 0;
    $successCount = 0;
    $errorCount = 0;

    while (!feof($handle)) {
        $line = fgets($handle);
        $lineNumber++;
        if ($line === false) continue;

        // Skip comments and empty lines
        if (trim($line) === '' || strpos(trim($line), '--') === 0 || strpos(trim($line), '/*') === 0) {
            continue;
        }

        // Process the line character by character
        for ($i = 0; $i < strlen($line); $i++) {
            $char = $line[$i];

            // Handle string literals
            if (($char === "'" || $char === '"') && !$escaped) {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar) {
                    $inString = false;
                }
            }

            // Handle escape characters
            if ($char === '\\' && !$escaped) {
                $escaped = true;
                continue;
            }

            $escaped = false;
            $query .= $char;
        }

        // If we have a complete query (ends with semicolon and not in a string)
        if (!$inString && substr(trim($query), -1) === ';') {
            // Prepare the query for execution
            $query = trim($query);
            
            // Skip empty queries
            if (empty($query)) {
                continue;
            }
            
            // Execute the query directly
            if (!$mysqli->query($query)) {
                echo "Error executing query at line $lineNumber: " . $mysqli->error . "<br>";
                echo "Query: " . substr($query, 0, 100) . "...<br>";
                $errorCount++;
            } else {
                $successCount++;
                // Show progress every 10 queries
                if ($successCount % 10 === 0) {
                    echo "Progress: $successCount queries executed successfully.<br>";
                }
            }
            $query = '';
        }
    }

    fclose($handle);

    if ($query !== '') {
        // Execute any remaining query
        if (!$mysqli->query($query)) {
            echo "Error executing final query: " . $mysqli->error . "<br>";
            $errorCount++;
        } else {
            $successCount++;
        }
    }

    echo "Data import completed.<br>";
    echo "Successfully executed $successCount queries.<br>";
    if ($errorCount > 0) {
        echo "Encountered $errorCount errors during import.<br>";
    }

    // Log the successful import
    $backup_type = 'Initial Import';
    $backup_status = 'Success';
    $file_name = basename($sql_file);
    $log_message = "Database '$db' imported successfully from $file_name";
    
    $log_query = "INSERT INTO backup_logs (backup_datetime, backup_type, backup_status, file_name, log_message) 
                  VALUES (NOW(), ?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($log_query);
    if ($stmt) {
        $stmt->bind_param('ssss', $backup_type, $backup_status, $file_name, $log_message);
        $stmt->execute();
        $stmt->close();
    }

    $mysqli->close();
    return true;
}

// Function to check if database needs initialization
function check_database_initialization() {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db = 'clinic_data';

    // First connect without database
    $mysqli = new mysqli($host, $user, $pass);
    if ($mysqli->connect_errno) {
        return false;
    }

    // Check if database exists
    $result = $mysqli->query("SHOW DATABASES LIKE '$db'");
    if (!$result || $result->num_rows === 0) {
        $mysqli->close();
        return false;
    }

    // Try to select the database
    if (!$mysqli->select_db($db)) {
        $mysqli->close();
        return false;
    }

    // Check if any tables exist
    $result = $mysqli->query("SHOW TABLES");
    $has_tables = ($result && $result->num_rows > 0);
    $mysqli->close();
    
    return $has_tables;
} 