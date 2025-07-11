<?php

session_start();

require_once 'backup.php'; // Adjust path if backup.php is in a different directory
// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Redirect to the login page with an error message
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit();
}   

$servername = "localhost";
$username = "root";
$password = "";
$database = "clinic_data";

// Create database connection
$connection = new mysqli($servername, $username, $password, $database);

if ($connection->connect_error) {
    die("Connection Failed: " . $connection->connect_error);
}

// 🛠️ Deletion logic moved outside the error block
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $connection->real_escape_string($_POST['delete_id']);

    // Delete dependent records
    $deleteConsultations = "DELETE FROM consultations WHERE PatientID = '$delete_id'";
    if (!$connection->query($deleteConsultations)) {
        echo "<script>alert('Error deleting consultations: " . $connection->error . "');</script>";
    }

    // Delete the patient
    $deleteQuery = "DELETE FROM patients WHERE PatientID = '$delete_id'";
    if ($connection->query($deleteQuery) === TRUE) {
        echo "success";
        exit();
    } else {
        echo "error";
        exit();
    }
}

// 🛠️ Renumbering logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renumber_patient_ids'])) {
    // Step 1: Turn off foreign key checks
    $connection->query("SET FOREIGN_KEY_CHECKS=0");

    // Fetch all existing patient data ordered by old PatientID
    $result = $connection->query("SELECT * FROM patients ORDER BY PatientID ASC");

    if ($result && $result->num_rows > 0) {
        $patients = $result->fetch_all(MYSQLI_ASSOC);

        // Create a mapping of old ID to new ID
        $new_id = 1;
        foreach ($patients as $patient) {
            $old_id = $patient['PatientID'];
            $connection->query("UPDATE patients SET PatientID = $new_id WHERE PatientID = $old_id");
            $new_id++;
        }

        // Reset auto-increment to the next available ID
        $connection->query("ALTER TABLE patients AUTO_INCREMENT = $new_id");

        echo "success_renumber"; // Change to success string for AJAX
    } else {
        echo "no_patients_renumber"; // Change to specific string for AJAX
    }
    
    // Re-enable foreign key checks
    $connection->query("SET FOREIGN_KEY_CHECKS=1");
    exit(); // Exit after AJAX response
}

// 🛠️ Merge Consultations Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['merge_student_num'])) {
    $studentNumToMerge = $connection->real_escape_string($_POST['merge_student_num']);

    // Get all PatientIDs associated with this Student_Num
    $getPatientIDsQuery = "SELECT PatientID FROM patients WHERE Student_Num = '$studentNumToMerge' ORDER BY PatientID ASC";
    $patientIDsResult = $connection->query($getPatientIDsQuery);

    if ($patientIDsResult->num_rows > 1) {
        $patientIDs = [];
        while ($row = $patientIDsResult->fetch_assoc()) {
            $patientIDs[] = $row['PatientID'];
        }

        $primaryPatientID = $patientIDs[0]; // The lowest PatientID becomes the primary
        $otherPatientIDs = array_slice($patientIDs, 1); // All other PatientIDs to be merged from

        // 1. Update consultations to point to the primary PatientID
        if (!empty($otherPatientIDs)) {
            $otherPatientIDsString = "'" . implode("','", $otherPatientIDs) . "'";
            $updateConsultationsQuery = "UPDATE consultations SET PatientID = '$primaryPatientID' WHERE PatientID IN ($otherPatientIDsString)";
            if (!$connection->query($updateConsultationsQuery)) {
                echo "error: Failed to update consultations: " . $connection->error;
                exit();
            }
        }

        // 2. Delete the duplicate patient records from the 'patients' table (except the primary one)
        if (!empty($otherPatientIDs)) {
            $deletePatientsQuery = "DELETE FROM patients WHERE PatientID IN ($otherPatientIDsString)";
            if ($connection->query($deletePatientsQuery) === TRUE) {
                echo "success";
            } else {
                echo "error: Failed to delete duplicate patient records: " . $connection->error;
            }
        } else {
            echo "success"; // No other patients to delete, only one patient for this student number
        }
    } else {
        echo "error: No duplicate patient records found for merging.";
    }
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patients</title>
    <link rel="stylesheet" href="../assets/css/v2/bootstrap.min.css">
    <style>
        body {
            background-color: #f0f8ff;
            margin: 0;
            overflow-x: hidden; /* Prevent horizontal scrollbar on zoom */
        }

        /* Header Styles */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 80px;
            background-color: #20B2AA;
            color: white;
            display: flex;
            align-items: center;
            padding: 0 20px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            box-sizing: border-box; /* Include padding in header's total width */
        }

        .header .logo {
            width: 50px;
            height: auto;
            margin-right: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        /* Sidebar styles */
        .sidebar {
            background-color: #aad8e6; /* Light blue sidebar */
            height: 100vh; /* Full viewport height */
            width: 250px; /* Sidebar width */
            position: fixed; /* Fixed position */
            top: 0;
            left: 0;
            padding-top: 111px; /* Add top padding to avoid overlap with the toggle button */
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 999; /* Below header */
            box-sizing: border-box; /* Include padding in sidebar's total width and height */
            overflow-y: auto; /* Enable scrolling for sidebar content if it overflows */
        }

        .sidebar a {
            display: block;
            color: #0066cc; /* Link color */
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .sidebar a:hover {
            background-color: #e3f2fd;
            color: #004b99;
        }

        .sidebar .active {
            background-color: #0066cc;
            color: white;
        }

        .sidebar-footer {
            position: absolute; /* Position relative to .sidebar */
            bottom: 0;
            width: 100%;
            background-color: #aad8e6; /* Match sidebar background */
            color: #0066cc; /* Match link color */
            padding: 10px 0;
            font-size: 0.8rem;
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.05);
        }

        /* Main Content container styles for responsiveness */
        .container-fluid { /* Changed from .container to .container-fluid for full width use */
            margin-left: 250px; /* Space for sidebar */
            padding: 20px;
            margin-top: 80px; /* Height of header */
            width: calc(100% - 250px); /* Full width minus sidebar width */
            max-width: none; /* Override Bootstrap's max-width for .container-fluid */
            box-sizing: border-box; /* Include padding in the element's total width and height */
            overflow-y: auto; /* Enable vertical scrolling for main content */
            min-height: calc(100vh - 80px); /* Ensure content takes at least remaining viewport height */
        }

        .panel {
            border-radius: 10px;
        }

        .panel-heading {
            background-color: #0066cc;
            color: white;
            padding: 10px;
            border-radius: 10px 10px 0 0;
            margin-top: 20px;
        }

        .form-control {
            margin-bottom: 10px;
        }

        .btn-primary {
            background-color: #0066cc;
            border: none;
        }

        .btn-primary:hover {
            background-color: #004b99;
        }

        .error, .text-danger {
            color: red;
        }

        .search-results {
            max-height: 400px;
            overflow-y: auto;
        }

        /* Media queries for responsiveness */
        @media (max-width: 992px) {
            .sidebar {
                width: 0; /* Collapse sidebar on smaller screens */
                overflow: hidden; /* Hide content that overflows */
                transition: width 0.3s ease-in-out; /* Smooth transition */
            }

            /* Optional: Add a toggle button for the sidebar on small screens */
            .sidebar-toggle-button {
                display: block; /* Show the button */
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001; /* Above header */
                background-color: #20B2AA;
                color: white;
                border: none;
                padding: 10px 15px;
                border-radius: 5px;
                cursor: pointer;
            }

            .container-fluid {
                margin-left: 0; /* Main content takes full width */
                width: 100%; /* Take full width */
            }

            /* Adjust header if needed for smaller screens */
            .header {
                height: 60px; /* Smaller header on smaller screens */
                padding-left: 70px; /* Make space for the toggle button */
            }

            .header .logo {
                width: 40px;
            }

            .sidebar.active { /* Class to be toggled by JS for opening sidebar */
                width: 250px; /* Expand sidebar */
            }
        }

        @media (max-width: 768px) {
            /* Further adjustments for even smaller screens */
            .header h1 {
                font-size: 1.5rem; /* Adjust font size for header title */
            }
            
            .panel {
                padding: 15px; /* Reduce padding on smaller screens */
            }
        }

          /* Chatbot button style */
        .chatbot-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #20B2AA;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 15px 25px;
            font-size: 1.1rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .chatbot-button:hover {
            background-color: #1a9c94;
        }

        /* Chatbot Popover Styles */
        .chatbot-popover {
            display: none; /* Hidden by default */
            position: fixed;
            bottom: 90px; /* Adjust based on button height + desired spacing */
            right: 20px;
            z-index: 1001;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            border-radius: 10px;
            background-color: #fefefe;
            width: 90%; /* Adjust width */
            max-width: 350px; /* Limit max width for popover */
            height: 450px; /* Fixed height for popover */
            flex-direction: column; /* Will be toggled to flex by JS if open */
            overflow: hidden; /* Ensure content inside respects border-radius */
        }

        .chatbot-header {
            background-color: #20B2AA; /* Clinic primary color */
            color: white;
            padding: 10px 15px;
            border-radius: 9px 9px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .chatbot-close-button {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .chatbot-messages {
            flex-grow: 1;
            padding: 15px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background-color: #e6f7ff; /* Light background for chat area */
        }

        .chatbot-input-area {
            display: flex;
            padding: 15px;
            border-top: 1px solid #eee;
            background-color: #fefefe;
            border-radius: 0 0 10px 10px;
        }

        .chatbot-input-area input {
            flex-grow: 1;
            padding: 10px 15px;
            border: 1px solid #aadddd;
            border-radius: 20px;
            margin-right: 10px;
            font-size: 1rem;
        }

        .chatbot-input-area button {
            background-color: #20B2AA;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 1rem;
            font-weight: bold;
        }

        .chatbot-input-area button:hover {
            background-color: #1a9c94;
        }

        /* Message bubble styles */
        .user-message {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border-radius: 15px 15px 0 15px;
            margin-bottom: 5px;
            align-self: flex-end;
            max-width: 75%;
            word-wrap: break-word;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .bot-message {
            background-color: #f0f0f0;
            color: #333;
            padding: 10px 15px;
            border-radius: 15px 15px 15px 0;
            margin-bottom: 5px;
            align-self: flex-start;
            max-width: 75%;
            word-wrap: break-word;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        /* Typing indicator styles */
        .typing-indicator .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            margin: 0 2px;
            background: #333;
            border-radius: 50%;
            opacity: 0;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-indicator .dot:nth-child(1) {
            animation-delay: 0s;
        }
        .typing-indicator .dot:nth-child(2) {
            animation-delay: 0.2s;
        }
        .typing-indicator .dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 80%, 100% {
                opacity: 0;
            }
            40% {
                opacity: 1;
            }
        }

    </style>
</head>
<body>
<div class="sidebar" id="mySidebar">
        <a href="dashboard.php">Dashboard</a>
        <a href="addpatient.php">Add Patient</a>
        <a href="viewpatient.php" class="active">View Patients</a>
        <a href="frontconsult.php">Consultation</a>
        <a href="recordTable.php">Patient Records</a>
        <a href="generate_qr_codes.php">Generate QR Codes</a>
        <a href="medinventory.php">MedInventory</a>
        <a href="medilog.php">Medical Logs</a>
        <a href="staff_creation.php">Manage Staff</a>
        <a href="logout.php">Logout</a>
        <div class="sidebar-footer text-center">
            <p>UDM Clinic Cares System</p>
        </div>
    </div>

    <button class="sidebar-toggle-button" onclick="toggleSidebar()">☰</button>

    <div class="header d-flex align-items-center">
        <img src="images/UDMCLINIC_LOGO.png" alt="Logo" class="logo">
        <h1>UDM Clinic Cares</h1>
    </div>

    <div class="container-fluid mt-5"> <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4><span class="glyphicon glyphicon-user"></span> Patient List</h4>
                    </div>
                    <div class="panel-body">
                        <form action="" method="GET">
                            <div class="form-group">
                                <label for="search">Search by Name or ID</label>
                                <input type="text" id="search" name="search" class="form-control" placeholder="Enter Name or ID" list="suggestions">
                                <datalist id="suggestions">
                                    <?php
                                    $suggestionQuery = "SELECT PatientID, FirstName, LastName FROM patients";
                                    $suggestionResult = $connection->query($suggestionQuery);
                                    while ($row = $suggestionResult->fetch_assoc()) {
                                        echo "<option value='" . $row['PatientID'] . " - " . $row['FirstName'] . " " . $row['LastName'] . "'>";
                                    }
                                    ?>
                                </datalist>
                            </div>
                            <button type="submit" class="btn btn-primary">Search</button>
                        </form>

                        <div class="search-results mt-3">
                            <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Patient ID</th>
                                    <th>Student Number</th>
                                    <th>First Name</th>
                                    <th>Middle Name</th>
                                    <th>Last Name</th>
                                    <th>Sex</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $searchQuery = "";
                                if (isset($_GET['search']) && !empty($_GET['search'])) {
                                    $searchTerm = $connection->real_escape_string($_GET['search']);
                                    $searchParts = explode(" ", $searchTerm);
                                    $conditions = [];

                                    foreach ($searchParts as $part) {
                                        $part = $connection->real_escape_string($part);
                                        $conditions[] = "PatientID LIKE '%$part%'";
                                        $conditions[] = "Student_Num LIKE '%$part%'";
                                        $conditions[] = "FirstName LIKE '%$part%'";
                                        $conditions[] = "LastName LIKE '%$part%'";
                                        $conditions[] = "CONCAT(FirstName, ' ', LastName) LIKE '%$part%'";
                                        $conditions[] = "CONCAT(FirstName, ' ', MiddleInitial, ' ', LastName) LIKE '%$part%'";
                                    }

                                    $searchQuery = " WHERE " . implode(" OR ", $conditions);
                                }

                                $query = "SELECT PatientID, Student_Num, FirstName, MiddleInitial, LastName, Sex FROM patients" . $searchQuery;
                                $result = $connection->query($query);

                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row['PatientID']) . "</td>";
                                        echo "<td><a href='view_details.php?id=" . htmlspecialchars($row['Student_Num']) . "'>" . htmlspecialchars($row['Student_Num']) . "</a></td>";
                                        echo "<td>" . htmlspecialchars($row['FirstName']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['MiddleInitial']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['LastName']) . "</td>";
                                        echo "<td>" . htmlspecialchars($row['Sex']) . "</td>";
                                        echo "<td>
                                            <a href='edit_patient.php?id=" . $row['PatientID'] . "' class='btn btn-warning btn-sm'>Edit</a>
                                            <button class='btn btn-danger btn-sm delete-btn' data-id='" . $row['PatientID'] . "'>Remove</button>
                                        </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7' class='text-center'>No data found</td></tr>";
                                }
                                ?>
                            </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-danger mt-3" data-toggle="modal" data-target="#renumberConfirmModal">
                            Renumber All Patient IDs
                        </button>

                        <a href="dashboard.php" class="btn btn-primary mt-3">Back to Dashboard</a>
                    </div>
                </div>
                <hr>
                <h5>Duplicate Patient Records (Grouped by Student Number)</h5>

                <?php
                $duplicateQuery = "
                    SELECT Student_Num 
                    FROM patients 
                    GROUP BY Student_Num 
                    HAVING COUNT(*) > 1
                ";
                $duplicateResult = $connection->query($duplicateQuery);

                if ($duplicateResult->num_rows > 0) {
                    while ($dup = $duplicateResult->fetch_assoc()) {
                        $studentNum = $dup['Student_Num'];
                        echo "<div class='card mb-4'>";
                        echo "<div class='card-header bg-info text-white d-flex justify-content-between align-items-center'>";
                        echo "<strong>Student Number: " . htmlspecialchars($studentNum) . "</strong>";
                        // MERGE BUTTON ADDED HERE
                        echo "<button class='btn btn-warning btn-sm merge-btn' data-student-num='" . htmlspecialchars($studentNum) . "' data-toggle='modal' data-target='#mergeConfirmModal'>Merge Consultations</button>";
                        echo "</div>"; // Close card-header
                        echo "<div class='card-body p-0'>";
                        echo "<table class='table table-bordered mb-0'>";
                        echo "<thead>
                                <tr>
                                    <th>Patient ID</th>
                                    <th>First Name</th>
                                    <th>Middle Name</th>
                                    <th>Last Name</th>
                                    <th>Sex</th>
                                    <th>Actions</th>
                                </tr>
                              </thead>
                              <tbody>";

                        $individualsQuery = "
                            SELECT PatientID, FirstName, MiddleInitial, LastName, Sex 
                            FROM patients 
                            WHERE Student_Num = '$studentNum'
                            ORDER BY PatientID ASC
                        ";
                        $individualsResult = $connection->query($individualsQuery);

                        $patientIDsForConsultations = []; // To store PatientIDs for fetching consultations

                        while ($row = $individualsResult->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['PatientID']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['FirstName']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['MiddleInitial']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['LastName']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['Sex']) . "</td>";
                            echo "<td>
                                <a href='edit_patient.php?id=" . $row['PatientID'] . "' class='btn btn-warning btn-sm'>Edit</a>
                                <button class='btn btn-danger btn-sm delete-btn' data-id='" . $row['PatientID'] . "'>Remove</button>
                            </td>";
                            echo "</tr>";
                            $patientIDsForConsultations[] = $row['PatientID'];
                        }

                        echo "</tbody></table></div>"; // Close card-body and table for patient details

                        // --- Start: Display Consultations for Duplicate Student ---
                        if (!empty($patientIDsForConsultations)) {
                            echo "<div class='card-footer'>";
                            echo "<h5>Consultations for this Student Number (" . htmlspecialchars($studentNum) . ")</h5>";
                            echo "<div class='table-responsive'>"; // Make the consultation table responsive
                            echo "<table class='table table-bordered table-sm'>";
                            echo "<thead>";
                            echo "<tr>";
                            echo "<th>No.</th>";
                            echo "<th>Patient ID</th>";
                            echo "<th>Student No.</th>";
                            echo "<th>Full Name</th>";
                            echo "<th>Program</th>";
                            echo "<th>Date</th>";
                            echo "<th>Time In</th>";
                            echo "<th>Time Out</th>";
                            echo "<th>Subjective</th>";
                            echo "<th>Objective</th>";
                            echo "<th>Assessment</th>";
                            echo "<th>Medicine Given</th>";
                            echo "<th>Quantity Given</th>";
                            echo "<th>Plan</th>";
                            echo "<th>Follow Up Date</th>";
                            echo "<th>Saved By</th>";
                            echo "</tr>";
                            echo "</thead>";
                            echo "<tbody>";

                            $consultationNo = 1;
                            $patientIDsString = "'" . implode("','", $patientIDsForConsultations) . "'";
                            $consultationsQuery = "
                                SELECT 
                                    c.ConsultationID,
                                    c.PatientID,
                                    p.Student_Num,
                                    CONCAT(p.FirstName, ' ', p.MiddleInitial, ' ', p.LastName) AS FullName,
                                    p.Program,
                                    c.Date,
                                    c.TimeIn,
                                    c.TimeOut,
                                    c.Subjective,
                                    c.Objective,
                                    c.Assessment,
                                    c.MedicineGiven,
                                    c.QuantityGiven,
                                    c.Plan,
                                    NULL AS FollowUpDate, -- Set to NULL or an empty string if no such column exists
                                    c.SavedBy
                                FROM consultations c
                                JOIN patients p ON c.PatientID = p.PatientID
                                WHERE c.PatientID IN ($patientIDsString)
                                ORDER BY c.Date DESC, c.TimeIn DESC
                            ";
                            $consultationsResult = $connection->query($consultationsQuery);

                            if ($consultationsResult->num_rows > 0) {
                                while ($consultationRow = $consultationsResult->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . $consultationNo++ . "</td>";
                                    echo "<td>" . htmlspecialchars($consultationRow['PatientID']) . "</td>";
                                    echo "<td>" . htmlspecialchars($consultationRow['Student_Num']) . "</td>";
                                    echo "<td>" . htmlspecialchars($consultationRow['FullName']) . "</td>";
                                    echo "<td>" . htmlspecialchars($consultationRow['Program']) . "</td>";
                                    echo "<td>" . htmlspecialchars($consultationRow['Date']) . "</td>";
                                    echo "<td>" . htmlspecialchars($consultationRow['TimeIn']) . "</td>";
                                    echo "<td>" . htmlspecialchars($consultationRow['TimeOut']) . "</td>";
                                    echo "<td>" . htmlspecialchars($consultationRow['Subjective']) . "</td>";
                                    echo "<td>" . htmlspecialchars($consultationRow['Objective']) . "</td>";
                                    echo "<td>" . htmlspecialchars($consultationRow['Assessment']) . "</td>";
                                   echo "<td>" . htmlspecialchars($consultationRow['MedicineGiven'] ?? '') . "</td>";
echo "<td>" . htmlspecialchars($consultationRow['QuantityGiven'] ?? '') . "</td>";

                                    echo "<td>" . htmlspecialchars($consultationRow['Plan']) . "</td>";
                                    echo "<td>" . htmlspecialchars($consultationRow['FollowUpDate'] ?? '') . "</td>"; // MODIFIED LINE
                                    echo "<td>" . htmlspecialchars($consultationRow['SavedBy']) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='16' class='text-center'>No consultations found for these duplicate records.</td></tr>";
                            }
                            echo "</tbody></table></div></div>"; // Close consultation table and card-footer
                        }
                        // --- End: Display Consultations for Duplicate Student ---

                        echo "</div>"; // Close card
                    }
                } else {
                    echo "<p class='text-muted'>No duplicate records found.</p>";
                }
                ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this record (Patient ID: <span id="patientIdToDelete"></span>)? This will also delete associated consultations.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="renumberConfirmModal" tabindex="-1" role="dialog" aria-labelledby="renumberConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="renumberConfirmModalLabel">Confirm Renumbering</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    This will renumber all Patient IDs starting from 1. Are you sure?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmRenumberBtn">Renumber</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="mergeConfirmModal" tabindex="-1" role="dialog" aria-labelledby="mergeConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mergeConfirmModalLabel">Confirm Merge</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to merge consultations for Student Number: <span id="studentNumToMerge"></span>? This will consolidate all consultations under one primary Patient ID and remove other duplicate patient records for this student number.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirmMergeBtn">Merge</button>
                </div>
            </div>
        </div>
    </div>

     <button class="chatbot-button" onclick="toggleChatbotPopover()">Ask UDM Cora?</button>

    <div id="chatbotPopover" class="chatbot-popover">
        <div class="chatbot-header">
            <h2>UDM Cora</h2>
            <span class="chatbot-close-button" onclick="toggleChatbotPopover()">&times;</span>
        </div>
        <div class="chatbot-messages" id="chatbotMessages">
            </div>
        <div class="chatbot-input-area">
            <input type="text" id="chatbotInput" placeholder="Type your question...">
            <button onclick="sendChatbotMessage()">Send</button>
        </div>
    </div>

   <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script> <script src="../assets/js/popper.min.js"></script>
    <script src="../assets/js/bootstrap.min.js"></script>
    <script>
        // JavaScript for sidebar toggle
        function toggleSidebar() {
            var sidebar = document.getElementById("mySidebar");
            sidebar.classList.toggle("active"); // "active" class will handle the width change
        }

        $(document).ready(function() {
            let currentPatientIdToDelete = null;
            let currentStudentNumToMerge = null;

            // Delete button functionality (opens modal)
            $('.delete-btn').click(function() {
                currentPatientIdToDelete = $(this).data('id');
                $('#patientIdToDelete').text(currentPatientIdToDelete);
                $('#deleteConfirmModal').modal('show');
            });

            // Confirm Delete button inside modal
            $('#confirmDeleteBtn').click(function() {
                $('#deleteConfirmModal').modal('hide'); // Hide modal immediately
                const $row = $('.delete-btn[data-id="' + currentPatientIdToDelete + '"]').closest('tr');

                $.ajax({
                    url: '', // same page
                    method: 'POST',
                    data: { delete_id: currentPatientIdToDelete },
                    success: function(response) {
                        if (response.trim() === 'success') {
                            alert('Record deleted successfully');
                            $row.fadeOut(300, function() { $(this).remove(); });
                            location.reload(); 
                        } else {
                            alert('Error deleting record: ' + response);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('AJAX error: ' + status + ' - ' + error);
                    }
                });
            });

            // Renumber button functionality (opens modal)
            $('#renumberConfirmModal').on('show.bs.modal', function (event) {
                // Any setup needed before showing the modal
            });

            // Confirm Renumber button inside modal
            $('#confirmRenumberBtn').click(function() {
                $('#renumberConfirmModal').modal('hide'); // Hide modal immediately

                fetch("", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: "renumber_patient_ids=true"
                })
                .then(response => response.text())
                .then(data => {
                    if (data.trim() === 'success_renumber') {
                         alert("Patient IDs successfully renumbered.");
                    } else if (data.trim() === 'no_patients_renumber') {
                        alert("No patients found to renumber.");
                    } else {
                        alert("Renumbering process completed. Please check console for details if any. Response: " + data);
                    }
                    location.reload(); // Reload the page to show updated IDs
                })
                .catch(error => {
                    alert("Error renumbering: " + error);
                });
            });

            // Merge Consultations Button Functionality (opens modal)
            $('.merge-btn').click(function() {
                currentStudentNumToMerge = $(this).data('student-num');
                $('#studentNumToMerge').text(currentStudentNumToMerge);
                $('#mergeConfirmModal').modal('show');
            });

            // Confirm Merge button inside modal
            $('#confirmMergeBtn').click(function() {
                $('#mergeConfirmModal').modal('hide'); // Hide modal immediately
                const $card = $('.merge-btn[data-student-num="' + currentStudentNumToMerge + '"]').closest('.card');

                $.ajax({
                    url: '', // same page
                    method: 'POST',
                    data: { merge_student_num: currentStudentNumToMerge },
                    success: function(response) {
                        if (response.trim() === 'success') {
                            alert('Consultations merged successfully for Student Number: ' + currentStudentNumToMerge);
                            $card.fadeOut(500, function() {
                                $(this).remove(); 
                            });
                            location.reload(); 
                        } else {
                            alert('Error merging consultations: ' + response);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('AJAX error during merge: ' + status + ' - ' + error);
                    }
                });
            });
        });

        // Chatbot Popover Functions
        const chatbotPopover = document.getElementById("chatbotPopover");
        const chatbotMessages = document.getElementById("chatbotMessages");
        const chatbotInput = document.getElementById("chatbotInput");

        // Load chat history and state from localStorage on page load
        document.addEventListener("DOMContentLoaded", () => {
            const chatHistory = JSON.parse(localStorage.getItem("chatbotHistory") || "[]");
            const isChatbotOpen = localStorage.getItem("isChatbotOpen") === "true";

            // Populate chat history
            if (chatHistory.length > 0) {
                chatHistory.forEach(msg => {
                    appendMessage(msg.text, msg.sender, false); // Don't save again
                });
            } else {
                // Add initial bot message if no history
                appendMessage("Hello! I'm UDM Cora. How can I help you today regarding clinic data?", "bot", true);
            }

            // Set chatbot visibility
            if (isChatbotOpen) {
                chatbotPopover.style.display = "flex";
                chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
            } else {
                chatbotPopover.style.display = "none";
            }
        });

        function toggleChatbotPopover() {
            if (chatbotPopover.style.display === "flex") {
                chatbotPopover.style.display = "none";
                localStorage.setItem("isChatbotOpen", "false");
            } else {
                chatbotPopover.style.display = "flex";
                localStorage.setItem("isChatbotOpen", "true");
                chatbotInput.focus();
                chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
            }
        }

        function appendMessage(text, sender, saveToHistory = true) {
            const messageDiv = document.createElement("div");
            messageDiv.classList.add(sender === "user" ? "user-message" : "bot-message");
            messageDiv.textContent = text;
            chatbotMessages.appendChild(messageDiv);
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;

            if (saveToHistory) {
                const chatHistory = JSON.parse(localStorage.getItem("chatbotHistory") || "[]");
                chatHistory.push({ text, sender });
                localStorage.setItem("chatbotHistory", JSON.stringify(chatHistory));
            }
        }

        async function sendChatbotMessage() {
            const message = chatbotInput.value.trim();
            if (message === "") return;

            appendMessage(message, "user");
            chatbotInput.value = "";

            // Add typing indicator
            const typingIndicatorDiv = document.createElement("div");
            typingIndicatorDiv.classList.add("bot-message", "typing-indicator");
            typingIndicatorDiv.innerHTML = '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
            chatbotMessages.appendChild(typingIndicatorDiv);
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;

            try {
                // Send message to backend
                const response = await fetch('chatbot_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'query=' + encodeURIComponent(message)
                });
                const data = await response.text(); // Bot's response

                // Remove typing indicator
                chatbotMessages.removeChild(typingIndicatorDiv);

                // Simulate typing effect
                const botMessageDiv = document.createElement("div");
                botMessageDiv.classList.add("bot-message");
                chatbotMessages.appendChild(botMessageDiv);
                chatbotMessages.scrollTop = chatbotMessages.scrollHeight;

                let i = 0;
                const typingSpeed = 5; // milliseconds per character

                function typeCharacter() {
                    if (i < data.length) {
                        botMessageDiv.textContent += data.charAt(i);
                        i++;
                        chatbotMessages.scrollTop = chatbotMessages.scrollHeight; // Keep scrolling as text appears
                        setTimeout(typeCharacter, typingSpeed);
                    } else {
                        appendMessage(data, "bot", true); // Save bot's full message to history
                        chatbotMessages.removeChild(botMessageDiv); // Remove the temporary typing div
                    }
                }
                typeCharacter(); // Start typing animation

            } catch (error) {
                console.error('Error:', error);
                // Remove typing indicator if an error occurred
                if (chatbotMessages.contains(typingIndicatorDiv)) {
                    chatbotMessages.removeChild(typingIndicatorDiv);
                }
                appendMessage("Sorry, something went wrong. Please try again.", "bot");
            }
        }

        // Allow sending message with Enter key
        chatbotInput.addEventListener("keypress", function(event) {
            if (event.key === "Enter") {
                event.preventDefault(); // Prevent default form submission if input is in a form
                sendChatbotMessage();
            }
        });
    </script>
</body>
</html>