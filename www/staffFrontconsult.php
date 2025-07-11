<?php
session_start();

// Set the default timezone to Asia/Manila at the very beginning
date_default_timezone_set('Asia/Manila');

// Check if the staff is logged in
if (!isset($_SESSION['staff_loggedin']) || $_SESSION['staff_loggedin'] !== true) {
    // Redirect to the staff login page with an error message
    header("Location: staffLogin.php?error=" . urlencode("Please log in to access this page."));
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "clinic_data";

// Create connection (this will be the primary connection for both parts)
$connection = new mysqli($servername, $username, $password, $database);

// Check connection
if ($connection->connect_error) {
    error_log("Connection Failed: " . $connection->connect_error); // Log error instead of dying directly for AJAX
    // For non-AJAX requests, you might still want to die or show a user-friendly error.
    // For AJAX, we'll handle the error in the JSON response.
    if (isset($_POST['qr_data'])) {
        echo json_encode(['success' => false, 'message' => 'Database connection error.']);
        exit;
    } else {
        die("Connection Failed: " . $connection->connect_error);
    }
}

// This part of PHP will handle AJAX requests to fetch student data from qr_scanner.php
if (isset($_POST['qr_data'])) {
    // The connection check for AJAX is now inside the main connection check.
    // If the connection failed, the script would have exited already.

    $qr_data = htmlspecialchars($_POST['qr_data']); // Sanitize input immediately

    $stmt = $connection->prepare("SELECT PatientID, Student_Num, FirstName, MiddleInitial, LastName, Program FROM patients WHERE qr_code_id = ?");

    if ($stmt === false) {
        error_log("Prepare failed: " . htmlspecialchars($connection->error));
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
        // Do NOT close connection here, it needs to remain open for the main form submission.
        exit;
    }

    $stmt->bind_param("s", $qr_data);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    if ($student) {
        echo json_encode(['success' => true, 'student' => $student]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found with that QR code.']);
    }

    $stmt->close();
    // IMPORTANT: DO NOT close connection here. It needs to remain open for the main form submission.
    exit; // Crucial to exit after AJAX response
}

// Initialize variables for form values to keep them in inputs after submission attempts
// Default to current date/time for new consultations
// Now that default timezone is set, date() and new DateTime() will use Asia/Manila
$patientId = $_POST['patient_id'] ?? '';
$date = $_POST['date'] ?? date('Y-m-d'); // This will now correctly reflect Asia/Manila date
$timeIn = $_POST['time_in'] ?? (new DateTime('now'))->format('H:i'); // This will also use Asia/Manila
$timeOut = $_POST['time_out'] ?? '';
$subjective = $_POST['subjective'] ?? '';
$objective = $_POST['objective'] ?? '';
$assessment = $_POST['assessment'] ?? '';
$medicineGivenName = $_POST['medicine_given'] ?? ''; // Renamed from medicine_id
$quantityGiven = $_POST['quantity_given'] ?? 0;
$plan = $_POST['plan'] ?? '';
$planDate = $_POST['plan_date'] ?? '';
$savedBy = $_POST['saved_by'] ?? $_SESSION['username'] ?? ''; // Get from POST, then session

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_consultation'])) {
    // Sanitize and validate inputs
    // Using real_escape_string for basic sanitation, prepared statements later provide robust prevention
    $patientId = $connection->real_escape_string($_POST['patient_id']);
    $date = $connection->real_escape_string($_POST['date']);
    $timeIn = $connection->real_escape_string($_POST['time_in']);
    $timeOut = $connection->real_escape_string($_POST['time_out']);
    $subjective = $connection->real_escape_string($_POST['subjective']);
    $objective = $connection->real_escape_string($_POST['objective']);
    $assessment = $connection->real_escape_string($_POST['assessment']);
    $medicineGivenName = $connection->real_escape_string($_POST['medicine_given']);
    $quantityGiven = (int)$_POST['quantity_given'];
    $plan = $connection->real_escape_string($_POST['plan']);
    $planDate = $connection->real_escape_string($_POST['plan_date']);
    $savedBy = $connection->real_escape_string($_POST['saved_by']); // Get 'Saved By' from the form submission

    // Start a transaction for atomicity
    $connection->begin_transaction();

    try {
        // First, check if the patient ID exists in the patients table
        $checkPatientQuery = "SELECT PatientID FROM patients WHERE PatientID = ?";
        $stmt_check_patient = $connection->prepare($checkPatientQuery);
        if (!$stmt_check_patient) {
            throw new Exception("Prepare failed: " . $connection->error);
        }
        $stmt_check_patient->bind_param("s", $patientId);
        $stmt_check_patient->execute();
        $result_check_patient = $stmt_check_patient->get_result();

        if ($result_check_patient->num_rows === 0) {
            throw new Exception("Patient ID '" . htmlspecialchars($patientId) . "' does not exist. Please enter a valid Patient ID.");
        }

        // Handle medicine dispensing if a medicine is selected and quantity is greater than 0
        if (!empty($medicineGivenName) && $quantityGiven > 0) {
            // 1. Check if medicine exists and has enough quantity in inventory
            $checkInventoryQuery = "SELECT quantity FROM medicine_inventory WHERE medicine_name = ?";
            $stmt_inventory = $connection->prepare($checkInventoryQuery);
            if (!$stmt_inventory) {
                throw new Exception("Prepare failed (inventory check): " . $connection->error);
            }
            $stmt_inventory->bind_param("s", $medicineGivenName);
            $stmt_inventory->execute();
            $result_inventory = $stmt_inventory->get_result();
            $inventory = $result_inventory->fetch_assoc();

            if (!$inventory) {
                throw new Exception("Selected medicine not found in inventory: " . htmlspecialchars($medicineGivenName));
            }
            if ($inventory['quantity'] < $quantityGiven) {
                throw new Exception("Insufficient quantity of " . htmlspecialchars($medicineGivenName) . " in inventory. Available: " . htmlspecialchars($inventory['quantity']));
            }

            // 2. Dispense medicine (update inventory)
            $updateInventoryQuery = "UPDATE medicine_inventory SET quantity = quantity - ? WHERE medicine_name = ?";
            $stmt_update = $connection->prepare($updateInventoryQuery);
            if (!$stmt_update) {
                throw new Exception("Prepare failed (inventory update): " . $connection->error);
            }
            $stmt_update->bind_param("is", $quantityGiven, $medicineGivenName);
            $stmt_update->execute();

            if ($stmt_update->affected_rows === 0) {
                throw new Exception("Failed to update medicine inventory. No rows affected for medicine: " . htmlspecialchars($medicineGivenName));
            }
        }

        // 3. Insert consultation record
        $insertConsultationQuery = "INSERT INTO consultations (PatientID, Date, TimeIn, TimeOut, Subjective, Objective, Assessment, MedicineGiven, QuantityGiven, Plan, PlanDate, SavedBy) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_consultation = $connection->prepare($insertConsultationQuery);
        if (!$stmt_consultation) {
            throw new Exception("Prepare failed (consultation insert): " . $connection->error);
        }
        $stmt_consultation->bind_param("ssssssssisss",
            $patientId, $date, $timeIn, $timeOut,
            $subjective, $objective, $assessment,
            $medicineGivenName, $quantityGiven, $plan, $planDate, $savedBy
        );
        $stmt_consultation->execute();

        if ($stmt_consultation->affected_rows > 0) {
            $connection->commit(); // Commit transaction if all successful
            // Redirect to staffmedilog.php with the current date
            echo "<script>alert('Consultation saved successfully and medicine dispensed!'); window.location.href='staffmedilog.php?date=" . urlencode($date) . "';</script>";
            exit(); // Exit to prevent further execution of HTML below
        } else {
            throw new Exception("Failed to save consultation record.");
        }

    } catch (Exception $e) {
        $connection->rollback(); // Rollback on error
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}

// Fetch medicines from medicine_inventory table for dropdown
$medicines = [];
// Fetch all medicines, even if quantity is 0, to show them in the dropdown
// The JavaScript will handle showing available quantity and disabling the input if 0.
$medicineQuery = "SELECT medicine_id, medicine_name, quantity, unit FROM medicine_inventory ORDER BY medicine_name ASC";
$medicineResult = $connection->query($medicineQuery);

if ($medicineResult && $medicineResult->num_rows > 0) {
    while ($row = $medicineResult->fetch_assoc()) {
        $medicines[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Form</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/v2/all.min.css">
    <style>
        body {
            background-color: #f0f8ff; /* Light blue background */
            margin: 0;
            overflow-x: hidden; /* Prevent horizontal scrollbar on zoom */
        }

        /* Header Styles */
        .header {
            position: fixed; /* Fixed to the top */
            top: 0;
            left: 0;
            width: 100%;
            height: 80px; /* Adjust height as needed */
            background-color: #20B2AA;
            color: white;
            display: flex; /* Flexbox for alignment */
            align-items: center; /* Center vertically */
            padding: 0 20px; /* Add padding for spacing */
            z-index: 1000; /* Ensure it's above the sidebar */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Add subtle shadow */
            box-sizing: border-box; /* Include padding in header's total width */
        }

        .header .logo {
            width: 50px; /* Adjust logo size */
            height: auto;
            margin-right: 10px; /* Space between logo and text */
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
            transition: width 0.3s ease-in-out; /* Add transition for sidebar toggle */
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
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
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

        .card {
            margin-top: 20px; /* Adjust the margin to your preference */
        }

        /* QR Scanner specific styles */
        .scanner-section, .upload-section {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            margin-bottom: 30px;
        }
        .scanner-section h2, .upload-section h2 {
            color: #333;
            margin-bottom: 25px;
            font-size: 1.8rem;
        }
        video {
            width: 100%;
            max-width: 400px;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #000;
        }
        #qr-canvas { display: none; }
        .controls button, .upload-section input[type="file"] {
            padding: 10px 20px;
            margin: 10px 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .controls button.start { background-color: #28a745; color: white; }
        .controls button.start:hover { background-color: #218838; }
        .controls button.stop { background-color: #dc3545; color: white; }
        .controls button.stop:hover { background-color: #c82333; }
        .upload-section input[type="file"] {
            background-color: #007bff;
            color: white;
            display: inline-block;
            line-height: normal;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
        }
        .upload-section input[type="file"]:hover { background-color: #0056b3; }

        #student-info {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #e9ecef;
            text-align: left;
        }
        #student-info p { margin: 8px 0; font-size: 1.1em; color: #333; }
        #student-info strong { color: #555; }
        .error { color: #dc3545; font-weight: bold; margin-top: 10px; }
        .hidden { display: none; }


        
        /* Media queries for responsiveness */
        @media (max-width: 992px) {
            .sidebar {
                width: 0; /* Collapse sidebar on smaller screens */
                overflow: hidden; /* Hide content that overflows */
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
            .main-content.sidebar-active {
                margin-left: 250px;
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
            .controls button, .upload-section input[type="file"] {
                font-size: 14px; padding: 8px 15px;
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
        <a href="staffdashboard.php">Dashboard</a>
        <a href="staffaddpatient.php">Add Patient</a>
        <a href="staffviewpatient.php">View Patients</a>
        <a href="stafffrontconsult.php" class="active">Consultation</a>
        <a href="staffrecordTable.php">Patient Records</a>
        <a href="staffgenerate_qr_codes.php">Generate QR Codes</a>
        <a href="staffmedinventory.php">MedInventory</a>
        <a href="staffmedilog.php">Medical Logs</a>
        <a href="stafflogout.php">Logout</a>
        <div class="sidebar-footer text-center">
            <p>UDM Clinic Cares System</p>
        </div>
    </div>

    <button class="sidebar-toggle-button" onclick="toggleSidebar()">☰</button>

    <div class="header">
        <div class="d-flex align-items-center">
            <img src="images/UDMCLINIC_LOGO.png" alt="Logo" class="logo">
            <h1>UDM Clinic Cares Staff</h1>
        </div>
    </div>

    <div class="container-fluid mt-4" id="mainContent">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-primary text-white ">
                        Search Patient
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2>Patient QR ScanLog</h2>
                        </div>

                        <div class="scanner-section">
                            <h2>Scan with Camera</h2>
                            <div class="form-group">
                                <label for="camera-select">Select Camera:</label>
                                <select id="camera-select" class="form-control mb-3"></select>
                            </div>
                            <video id="qr-video" playsinline></video>
                            <div class="controls">
                                <button class="start" id="start-scanner">Start Scanner</button>
                                <button class="stop hidden" id="stop-scanner">Stop Scanner</button>
                            </div>
                            <div id="loading-camera" class="hidden error">Loading camera...</div>
                        </div>

                        <div class="upload-section">
                            <h2>Upload QR Code Image</h2>
                            <input type="file" id="qr-image-upload" accept="image/*">
                            <canvas id="qr-canvas"></canvas>
                            <div id="loading-upload" class="hidden error">Processing image...</div>
                        </div>

                        <div id="student-info">
                            <p>Scan a QR code or upload an image to see patient details.</p>
                        </div>
                        <div id="scan-result" class="error"></div>

                        <button type="button" class="btn btn-info mt-3 hidden" id="open-consultation-btn">
                            Use Scanned Patient Data
                        </button>
                        <hr class="my-4"> <form action="" method="GET">
                            <div class="form-group">
                                <label for="search">Search by Name or ID</label>
                                <input type="text" id="search" name="search" class="form-control" placeholder="Enter Name or ID" list="suggestions" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                <datalist id="suggestions">
                                    <?php
                                    $suggestionQuery = "SELECT PatientID, FirstName, LastName FROM patients";
                                    $suggestionResult = $connection->query($suggestionQuery);
                                    while ($row = $suggestionResult->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($row['PatientID']) . " - " . htmlspecialchars($row['FirstName']) . " " . htmlspecialchars($row['LastName']) . "'>";
                                    }
                                    ?>
                                </datalist>
                            </div>
                            <button type="submit" class="btn btn-primary">Search</button>
                        </form>

                        <div style="overflow-y: auto; max-height: 300px;">
                            <table class="table table-bordered table-striped mt-3">
                                <thead>
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Last Name</th>
                                        <th>Sex</th>
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
                                            $conditions[] = "(PatientID LIKE '%{$part}%' OR FirstName LIKE '%{$part}%' OR LastName LIKE '%{$part}%')";
                                        }
                                        $searchQuery = "SELECT PatientID, Student_Num, FirstName, MiddleInitial, LastName, Program, Sex FROM patients WHERE " . implode(" OR ", $conditions) . "";
                                    } else {
                                        // Initially show no results or a limited set
                                        $searchQuery = "SELECT PatientID, Student_Num, FirstName, MiddleInitial, LastName, Program, Sex FROM patients"; // Show some initial patients or make it empty
                                    }

                                    $result = $connection->query($searchQuery);
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<tr data-patient-id='" . htmlspecialchars($row['PatientID']) . "' data-student-num='" . htmlspecialchars($row['Student_Num']) . "' data-first-name='" . htmlspecialchars($row['FirstName']) . "' data-middle-initial='" . htmlspecialchars($row['MiddleInitial']) . "' data-last-name='" . htmlspecialchars($row['LastName']) . "' data-program='" . htmlspecialchars($row['Program']) . "'>";
                                            echo "<td>" . htmlspecialchars($row['PatientID']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['FirstName']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['MiddleInitial']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['LastName']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['Sex']) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5'>No patients found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-primary text-white">
                        Consultation Form
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <input type="hidden" id="patient_id" name="patient_id" value="<?= htmlspecialchars($patientId) ?>">

                            <div class="form-group">
                                <label for="patient_name_display">Patient Name:</label>
                                <input type="text" class="form-control" id="patient_name_display" readonly>
                            </div>

                            <div class="form-group">
                                <label for="student_num_display">Student Number/Patient Number:</label>
                                <input type="text" class="form-control" id="student_num_display" readonly>
                            </div>

                            <div class="form-group">
                                <label for="program_display">Program:</label>
                                <input type="text" class="form-control" id="program_display" readonly>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="date">Date:</label>
                                        <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($date) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="time_in">Time In:</label>
                                        <input type="time" class="form-control" id="time_in" name="time_in" value="<?= htmlspecialchars($timeIn) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="time_out">Time Out:</label>
                                        <input type="time" class="form-control" id="time_out" name="time_out" value="<?= htmlspecialchars($timeOut) ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="subjective">Subjective:</label>
                                <textarea class="form-control" id="subjective" name="subjective" rows="3" required><?= htmlspecialchars($subjective) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="objective">Objective:</label>
                                <textarea class="form-control" id="objective" name="objective" rows="3" required><?= htmlspecialchars($objective) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="assessment">Assessment:</label>
                                <select id="assessment" name="assessment" class="form-control" required>
                                    <option value="" disabled selected>Select an option</option>
                                    <option value="ophthalmology" <?= ($assessment == 'ophthalmology') ? 'selected' : '' ?>>Ophthalmology</option>
                                    <option value="cardiovascular" <?= ($assessment == 'cardiovascular') ? 'selected' : '' ?>>Cardiovascular</option>
                                    <option value="digestive" <?= ($assessment == 'digestive') ? 'selected' : '' ?>>Digestive/GIT</option>
                                    <option value="endocrine" <?= ($assessment == 'endocrine') ? 'selected' : '' ?>>Endocrine</option>
                                    <option value="integumentary" <?= ($assessment == 'integumentary') ? 'selected' : '' ?>>Integumentary</option>
                                    <option value="lymphatic" <?= ($assessment == 'lymphatic') ? 'selected' : '' ?>>Lymphatic</option>
                                    <option value="muscular" <?= ($assessment == 'muscular') ? 'selected' : '' ?>>Muscular</option>
                                    <option value="nervous" <?= ($assessment == 'nervous') ? 'selected' : '' ?>>Nervous</option>
                                    <option value="respiratory" <?= ($assessment == 'respiratory') ? 'selected' : '' ?>>Respiratory</option>
                                    <option value="reproductive" <?= ($assessment == 'reproductive') ? 'selected' : '' ?>>Reproductive</option>
                                    <option value="skeletal" <?= ($assessment == 'skeletal') ? 'selected' : '' ?>>Skeletal</option>
                                    <option value="urinary" <?= ($assessment == 'urinary') ? 'selected' : '' ?>>Urinary/GUT</option>
                                    <option value="dental" <?= ($assessment == 'dental') ? 'selected' : '' ?>>Dental</option>
                                    <option value="emergency" <?= ($assessment == 'emergency') ? 'selected' : '' ?>>Emergency</option>
                                    <option value="cases" <?= ($assessment == 'cases') ? 'selected' : '' ?>>Cases</option>
                                    <option value="animal_bite" <?= ($assessment == 'animal_bite') ? 'selected' : '' ?>>Animal Bite</option>
                                    <option value="teleconsult" <?= ($assessment == 'teleconsult') ? 'selected' : '' ?>>Teleconsult</option>
                                    <option value="teledentistry" <?= ($assessment == 'teledentistry') ? 'selected' : '' ?>>Teledentistry</option>
                                    <option value="medical_certificate" <?= ($assessment == 'medical_certificate') ? 'selected' : '' ?>>Medical Certificate</option>
                                    <option value="hospital_referral" <?= ($assessment == 'hospital_referral') ? 'selected' : '' ?>>Hospital Referral</option>
                                    <option value="ent" <?= ($assessment == 'ent') ? 'selected' : '' ?>>ENT</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="medicine_given">Medicine Given:</label>
                                <select class="form-control" id="medicine_given" name="medicine_given">
                                    <option value="">No Medicine</option>
                                    <?php foreach ($medicines as $med): ?>
                                        <option value="<?= htmlspecialchars($med['medicine_name']) ?>"
                                            data-quantity="<?= htmlspecialchars($med['quantity']) ?>"
                                            data-unit="<?= htmlspecialchars($med['unit']) ?>"
                                            <?= ($medicineGivenName == $med['medicine_name']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($med['medicine_name']) ?> (Available: <?= htmlspecialchars($med['quantity']) ?> <?= htmlspecialchars($med['unit']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="quantityHint" class="form-text text-muted">Select a medicine to see available quantity.</small>
                            </div>
                            <div class="form-group">
                                <label for="quantity_given">Quantity Given:</label>
                                <input type="number" class="form-control" id="quantity_given" name="quantity_given" min="0" value="<?= htmlspecialchars($quantityGiven) ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label for="plan">Plan:</label>
                                <textarea class="form-control" id="plan" name="plan" rows="3" required><?= htmlspecialchars($plan) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="plan_date">Follow Up Date:</label>
                                <input type="date" class="form-control" id="plan_date" name="plan_date" value="<?= htmlspecialchars($planDate) ?>">
                            </div>
                            <div class="form-group">
                                <label for="saved_by">Saved By:</label>
                                <select class="form-control" id="saved_by" name="saved_by" required>
                                    <option value="">Select Name</option>
                                    <option value="DR. M. Isidro" <?= ($savedBy == 'DR. M. Isidro') ? 'selected' : '' ?>>DR. M. Isidro</option>
                                    <option value="R. Garcia" <?= ($savedBy == 'R. Garcia') ? 'selected' : '' ?>>R. Garcia</option>
                                    <option value="E. Acosta" <?= ($savedBy == 'E. Acosta') ? 'selected' : '' ?>>E. Acosta</option>
                                    <option value="M. R. Suarez" <?= ($savedBy == 'M. R. Suarez') ? 'selected' : '' ?>>M. R. Suarez</option>
                                    <option value="S. Hilario" <?= ($savedBy == 'S. Hilario') ? 'selected' : '' ?>>S. Hilario</option>
                                    <option value="E. Ege" <?= ($savedBy == 'E. Ege') ? 'selected' : '' ?>>E. Ege</option>
                                    <option value="J. Magpayo" <?= ($savedBy == 'J. Magpayo') ? 'selected' : '' ?>>J. Magpayo</option>
                                    <option value="A. Miña" <?= ($savedBy == 'A. Miña') ? 'selected' : '' ?>>A. Miña</option>
                                </select>
                            </div>

                            <button type="submit" name="save_consultation" class="btn btn-primary btn-block">
                                <span class="glyphicon glyphicon-save"></span> Save Consultation
                            </button>
                        </form>
                    </div>
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


    <script src="assets/js/jquery-3.5.1.min.js"></script>
    <script src="assets/js/popper.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script src="assets/js/jsQR.min.js"></script>
    <script>
        // DOM Element References for QR Scanner
        const elements = {
            qrVideo: document.getElementById('qr-video'),
            startScannerBtn: document.getElementById('start-scanner'),
            stopScannerBtn: document.getElementById('stop-scanner'),
            qrImageUpload: document.getElementById('qr-image-upload'),
            qrCanvas: document.getElementById('qr-canvas'),
            studentInfoDiv: document.getElementById('student-info'),
            scanResultDiv: document.getElementById('scan-result'),
            loadingCameraDiv: document.getElementById('loading-camera'),
            loadingUploadDiv: document.getElementById('loading-upload'),
            mainContent: document.getElementById('mainContent'),
            openConsultationBtn: document.getElementById('open-consultation-btn'),
            cameraSelect: document.getElementById('camera-select'), // New camera select element

            // Form fields from frontconsult.php to be populated
            patientIdInput: document.getElementById('patient_id'),
            patientNameDisplay: document.getElementById('patient_name_display'),
            studentNumDisplay: document.getElementById('student_num_display'),
            programDisplay: document.getElementById('program_display'),
            dateField: document.getElementById('date'),
            timeInField: document.getElementById('time_in'),
            timeOutField: document.getElementById('time_out'),
            subjectiveField: document.getElementById('subjective'),
            objectiveField: document.getElementById('objective'),
            assessmentField: document.getElementById('assessment'),
            planField: document.getElementById('plan'),
            planDateField: document.getElementById('plan_date')
        };

        const ctx = elements.qrCanvas.getContext('2d');
        let videoStream = null;
        let animationFrameId = null;
        let scannedStudentData = null;

        // Helper function to update element visibility
        function updateVisibility(element, show) {
            element.classList.toggle('hidden', !show);
        }

        // Function to display messages in scanResultDiv
        function displayScanMessage(message, isError = false) {
            elements.scanResultDiv.textContent = message;
            elements.scanResultDiv.classList.toggle('error', isError);
        }

        // Function to fetch student data from the server
        async function fetchStudentData(qrData) {
            elements.studentInfoDiv.innerHTML = '<p>Searching for student...</p>';
            displayScanMessage('');
            updateVisibility(elements.openConsultationBtn, false);

            try {
                const response = await fetch('staffFrontconsult.php', { // AJAX request to the same page
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'qr_data=' + encodeURIComponent(qrData),
                });
                const data = await response.json();

                if (data.success) {
                    scannedStudentData = data.student;
                    elements.studentInfoDiv.innerHTML = `
                        <p><strong>Name:</strong> ${scannedStudentData.FirstName} ${scannedStudentData.MiddleInitial ? scannedStudentData.MiddleInitial + ' ' : ''}${scannedStudentData.LastName}</p>
                        <p><strong>Student Number:</strong> ${scannedStudentData.Student_Num}</p>
                        <p><strong>Program:</strong> ${scannedStudentData.Program}</p>
                        <p><strong>Patient ID:</strong> ${scannedStudentData.PatientID}</p>
                    `;
                    updateVisibility(elements.openConsultationBtn, true);
                } else {
                    elements.studentInfoDiv.innerHTML = `<p class="error">${data.message}</p>`;
                    updateVisibility(elements.openConsultationBtn, false);
                }
            } catch (error) {
                console.error('Error fetching student data:', error);
                elements.studentInfoDiv.innerHTML = `<p class="error">An error occurred while fetching student data.</p>`;
                updateVisibility(elements.openConsultationBtn, false);
            }
        }

        // --- Camera Scanner Logic ---

        // Function to populate camera dropdown
        async function populateCameraDropdown() {
            elements.cameraSelect.innerHTML = ''; // Clear existing options
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                const videoDevices = devices.filter(device => device.kind === 'videoinput');
                let defaultDeviceId = localStorage.getItem('selectedCameraId'); // Get saved ID

                if (videoDevices.length === 0) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'No camera found';
                    elements.cameraSelect.appendChild(option);
                    elements.startScannerBtn.disabled = true; // Disable start button if no camera
                } else {
                    videoDevices.forEach(device => {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.textContent = device.label || `Camera ${elements.cameraSelect.options.length + 1}`;
                        elements.cameraSelect.appendChild(option);
                        if (device.deviceId === defaultDeviceId) { // Select default if found
                            option.selected = true;
                        }
                    });
                    // If no default was found or the default is no longer available, try to select the first one
                    if (!defaultDeviceId || !videoDevices.some(device => device.deviceId === defaultDeviceId)) {
                        if (videoDevices.length > 0) {
                            elements.cameraSelect.value = videoDevices[0].deviceId;
                            localStorage.setItem('selectedCameraId', videoDevices[0].deviceId); // Save the new default
                        }
                    }
                    elements.startScannerBtn.disabled = false; // Enable start button
                }
            } catch (err) {
                console.error("Error enumerating devices: ", err);
                displayScanMessage('Error accessing camera devices. Please ensure camera permissions are granted.', true);
                elements.startScannerBtn.disabled = true;
            }
        }

        async function startScanner() {
            stopScanner(); // Stop any existing stream before starting a new one
            updateVisibility(elements.loadingCameraDiv, true);
            const selectedDeviceId = elements.cameraSelect.value; // Get selected camera ID

            if (!selectedDeviceId) {
                displayScanMessage('Please select a camera.', true);
                updateVisibility(elements.loadingCameraDiv, false);
                return;
            }

            // Save the newly selected camera as default
            localStorage.setItem('selectedCameraId', selectedDeviceId);

            try {
                videoStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        deviceId: { exact: selectedDeviceId } // Use exact device ID
                    }
                });
                elements.qrVideo.srcObject = videoStream;
                elements.qrVideo.play();
                updateVisibility(elements.startScannerBtn, false);
                updateVisibility(elements.stopScannerBtn, true);
                updateVisibility(elements.loadingCameraDiv, false);
                requestAnimationFrame(tick);
            } catch (err) {
                console.error("Error accessing camera: ", err);
                displayScanMessage('Error accessing camera. Please ensure camera permissions are granted and no other application is using the camera.', true);
                updateVisibility(elements.loadingCameraDiv, false);
            }
        }

        function stopScanner() {
            if (videoStream) {
                videoStream.getTracks().forEach(track => track.stop());
                elements.qrVideo.srcObject = null;
                cancelAnimationFrame(animationFrameId);
                animationFrameId = null;
                updateVisibility(elements.startScannerBtn, true);
                updateVisibility(elements.stopScannerBtn, false);
                elements.studentInfoDiv.innerHTML = '<p>Scan a QR code or upload an image to see student details.</p>';
                displayScanMessage('');
                updateVisibility(elements.openConsultationBtn, false);
                scannedStudentData = null;
            }
        }

        function tick() {
            if (elements.qrVideo.readyState === elements.qrVideo.HAVE_ENOUGH_DATA) {
                elements.qrCanvas.width = elements.qrVideo.videoWidth;
                elements.qrCanvas.height = elements.qrVideo.videoHeight;
                ctx.drawImage(elements.qrVideo, 0, 0, elements.qrCanvas.width, elements.qrCanvas.height);

                const imageData = ctx.getImageData(0, 0, elements.qrCanvas.width, elements.qrCanvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: "dontInvert" });

                if (code) {
                    console.log("QR Code detected:", code.data);
                    stopScanner();
                    fetchStudentData(code.data);
                }
            }
            if (videoStream && videoStream.active) {
                animationFrameId = requestAnimationFrame(tick);
            }
        }

        elements.startScannerBtn.addEventListener('click', startScanner);
        elements.stopScannerBtn.addEventListener('click', stopScanner);
        elements.cameraSelect.addEventListener('change', function() { // Add event listener for camera selection
            localStorage.setItem('selectedCameraId', this.value); // Save selected ID
            stopScanner(); // Stop current stream if camera changes
        });

        // --- Upload QR Code Image Logic ---
        elements.qrImageUpload.addEventListener('change', async (event) => {
            const file = event.target.files[0];
            if (!file) return;

            updateVisibility(elements.loadingUploadDiv, true);
            displayScanMessage('');
            elements.studentInfoDiv.innerHTML = '<p>Processing uploaded image...</p>';
            updateVisibility(elements.openConsultationBtn, false);

            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    elements.qrCanvas.width = img.width;
                    elements.qrCanvas.height = img.height;
                    ctx.drawImage(img, 0, 0, elements.qrCanvas.width, elements.qrCanvas.height);

                    const imageData = ctx.getImageData(0, 0, elements.qrCanvas.width, elements.qrCanvas.height);
                    const code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: "dontInvert" });

                    updateVisibility(elements.loadingUploadDiv, false);

                    if (code) {
                        console.log("Uploaded QR Code detected:", code.data);
                        fetchStudentData(code.data);
                    } else {
                        displayScanMessage('No QR code detected in the uploaded image. Please try another image.', true);
                        elements.studentInfoDiv.innerHTML = '<p>No QR code found in the image.</p>';
                        updateVisibility(elements.openConsultation-Btn, false);
                    }
                };
                img.onerror = () => {
                    updateVisibility(elements.loadingUploadDiv, false);
                    displayScanMessage('Error loading image. Please ensure it\'s a valid image file.', true);
                    elements.studentInfoDiv.innerHTML = '<p class="error">Failed to load image.</p>';
                    updateVisibility(elements.openConsultationBtn, false);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });

        // Initialize display state for QR scanner elements
        updateVisibility(elements.stopScannerBtn, false);
        updateVisibility(elements.startScannerBtn, true);
        updateVisibility(elements.openConsultationBtn, false);


        // JavaScript for sidebar toggle
        function toggleSidebar() {
            document.getElementById("mySidebar").classList.toggle("active");
            document.getElementById("mainContent").classList.toggle("sidebar-active");
        }

        // Adjust main content margin on initial load and resize
        function adjustMainContentLayout() {
            const sidebar = document.getElementById("mySidebar");
            const mainContent = document.getElementById("mainContent");
            if (window.innerWidth >= 992) {
                mainContent.style.marginLeft = '250px';
                mainContent.style.width = 'calc(100% - 250px)';
                sidebar.classList.remove("active"); // Ensure sidebar is open on large screens
                mainContent.classList.remove("sidebar-active"); // Ensure main content is correct
            } else {
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
            }
        }
        window.addEventListener('DOMContentLoaded', adjustMainContentLayout);
        window.addEventListener('resize', adjustMainContentLayout);


        // Function to populate the main consultation form fields
        function populateConsultationForm() {
            if (!scannedStudentData) return;

            const fullName = `${scannedStudentData.FirstName} ${scannedStudentData.MiddleInitial ? scannedStudentData.MiddleInitial + ' ' : ''}${scannedStudentData.LastName}`;
            
            // This date formatting in JS will use the client's local time, but we primarily rely on PHP for consistency.
            // Still good to have it here for immediate display.
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const formattedDate = `${year}-${month}-${day}`;

            // Get current time in Manila timezone using Intl.DateTimeFormat
            const now = new Date();
            const options = {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false, // Use 24-hour format
                timeZone: 'Asia/Manila'
            };
            const manilaTime = new Intl.DateTimeFormat('en-US', options).format(now);


            elements.patientIdInput.value = scannedStudentData.PatientID;
            elements.patientNameDisplay.value = fullName;
            elements.studentNumDisplay.value = scannedStudentData.Student_Num;
            elements.programDisplay.value = scannedStudentData.Program;

            // Only set date/time if fields are currently empty or not explicitly set by user
            if (!elements.dateField.value) elements.dateField.value = formattedDate;
            if (!elements.timeInField.value) {
                elements.timeInField.value = manilaTime;
            }
            // elements.timeOutField.value = ''; // Clear time out by default, user can fill
            // elements.subjectiveField.value = ''; // Clear existing consultation fields if desired
            // elements.objectiveField.value = '';
            // elements.assessmentField.value = '';
            // elements.planField.value = '';
            // elements.planDateField.value = '';
            
            // Scroll to the consultation form
            document.querySelector('.card:last-of-type').scrollIntoView({ behavior: 'smooth' });
        }

        elements.openConsultationBtn.addEventListener('click', populateConsultationForm);


        // Existing JS for medicine quantity hint and input state
        document.addEventListener('DOMContentLoaded', function() {
            const medicineSelect = document.getElementById('medicine_given');
            const quantityInput = document.getElementById('quantity_given');
            const quantityHint = document.getElementById('quantityHint');
            
            function updateQuantityHintAndInputState() {
                const selectedOption = medicineSelect.options[medicineSelect.selectedIndex];
                const availableQuantity = selectedOption.dataset.quantity;
                const unit = selectedOption.dataset.unit;

                if (medicineSelect.value === "") { // "No Medicine" selected
                    quantityInput.disabled = true;
                    quantityInput.value = 0;
                    quantityInput.removeAttribute('max');
                    quantityHint.textContent = 'Select a medicine to see available quantity.';
                } else if (availableQuantity !== undefined) {
                    quantityHint.textContent = `Available: ${availableQuantity} ${unit}`;
                    quantityInput.disabled = false;
                    quantityInput.setAttribute('max', availableQuantity);
                    if (parseInt(availableQuantity) === 0) {
                        quantityInput.value = 0;
                        quantityInput.disabled = true;
                        quantityHint.textContent = `Medicine out of stock.`;
                    } else if (parseInt(quantityInput.value) > parseInt(availableQuantity)) {
                        quantityInput.value = availableQuantity; // Adjust if current value exceeds new max
                    }
                } else { // Fallback if data-quantity is somehow missing
                    quantityInput.disabled = true;
                    quantityInput.value = 0;
                    quantityInput.removeAttribute('max');
                }
            }

            medicineSelect.addEventListener('change', updateQuantityHintAndInputState);

            // Set initial hint and input state on page load based on any pre-selected value
            updateQuantityHintAndInputState();

            // Initialize date and time fields to current values if they are empty on initial load
            const dateField = document.getElementById('date');
            const timeInField = document.getElementById('time_in');
            
            // Check if values are not already set by PHP (e.g., after a form submission error)
            if (!dateField.value) {
                // PHP has already set the date based on Asia/Manila, so this client-side setting
                // is mostly for robustness or if the PHP value isn't received for some reason.
                // It's crucial the PHP `date('Y-m-d')` is timezone-correct.
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
                const day = String(today.getDate()).padStart(2, '0');
                dateField.value = `${year}-${month}-${day}`;
            }

            if (!timeInField.value) { // Set default time in to Asia/Manila if empty
                const now = new Date();
                const options = {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false, // Use 24-hour format
                    timeZone: 'Asia/Manila'
                };
                const manilaTime = new Intl.DateTimeFormat('en-US', options).format(now);
                timeInField.value = manilaTime;
            }

            // Populate form when a patient row from the search table is clicked
            const patientTable = document.querySelector('.table.table-bordered.table-striped tbody');
            if (patientTable) {
                patientTable.addEventListener('click', function(event) {
                    let targetRow = event.target.closest('tr');
                    if (targetRow && targetRow.dataset.patientId) {
                        elements.patientIdInput.value = targetRow.dataset.patientId;
                        elements.patientNameDisplay.value = `${targetRow.dataset.firstName} ${targetRow.dataset.middleInitial ? targetRow.dataset.middleInitial + ' ' : ''}${targetRow.dataset.lastName}`;
                        elements.studentNumDisplay.value = targetRow.dataset.studentNum;
                        elements.programDisplay.value = targetRow.dataset.program;
                        // Scroll to the consultation form
                        document.querySelector('.card:last-of-type').scrollIntoView({ behavior: 'smooth' });
                    }
                });
            }

            // Call populateCameraDropdown on page load
            populateCameraDropdown();
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
                const response = await fetch('admin/chatbot_handler.php', {
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
<?php
// Close the database connection at the end of the script
$connection->close();
?>