<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['staff_loggedin']) || $_SESSION['staff_loggedin'] !== true) {
    // Redirect to the login page with an error message
    header("Location: stafflogin.php?error=" . urlencode("Please log in to access this page."));
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "clinic_data";

$connection = new mysqli($servername, $username, $password, $database);

if ($connection->connect_error) {
    die("Connection Failed: " . $connection->connect_error);
}

// Handle multiple deletions if a POST request is sent
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_ids'])) {
    $deleteIDs = $_POST['delete_ids'];
    if (!empty($deleteIDs)) {
        // Ensure all IDs are integers to prevent SQL injection
        $deleteIDs = array_map('intval', $deleteIDs);
        $placeholders = implode(',', array_fill(0, count($deleteIDs), '?'));
        $types = str_repeat('i', count($deleteIDs));

        $stmt = $connection->prepare("DELETE FROM consultations WHERE ConsultationID IN ($placeholders)");
        if ($stmt === false) {
            echo "<div class='alert alert-danger'>Failed to prepare delete statement: " . $connection->error . "</div>";
        } else {
            $stmt->bind_param($types, ...$deleteIDs);

            if ($stmt->execute()) {
                // Redirect after successful deletion to refresh the page
                // Use $_GET['id'] to ensure we stay on the same patient's records
                header("Location: patient_records.php?id=" . (isset($_GET['id']) ? intval($_GET['id']) : ''));
                exit();  // Ensure no further code is executed after redirection
            } else {
                echo "<div class='alert alert-danger'>Error deleting records: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    }
}

$patient = null;
$consultationsResult = null;

if (isset($_GET['id'])) {
    $patientID = intval($_GET['id']);

    // Fetch the patient's information
    $patientQuery = "SELECT * FROM patients WHERE PatientID = ?";
    $stmt = $connection->prepare($patientQuery);
    if ($stmt === false) {
        die("Failed to prepare patient query: " . $connection->error);
    }
    $stmt->bind_param("i", $patientID);
    $stmt->execute();
    $patientResult = $stmt->get_result();
    $patient = $patientResult->fetch_assoc();
    $stmt->close();

    if (!$patient) {
        // If patient not found, set an error message and proceed to display
        $errorMessage = "Patient not found.";
    } else {
        // Fetch all consultations for this patient
        $consultationsQuery = "
            SELECT c.*,
                CONCAT(p.FirstName, ' ', IFNULL(p.MiddleInitial, ''), ' ', p.LastName) AS FullName,
                p.Student_Num, p.Program
            FROM consultations c
            JOIN patients p ON c.PatientID = p.PatientID
            WHERE c.PatientID = ?
            ORDER BY c.Date DESC
        "; // Changed c.Date to c.ConsultationDate based on recent consistency

        $stmt = $connection->prepare($consultationsQuery);
        if ($stmt === false) {
            die("Failed to prepare consultations query: " . $connection->error);
        }
        $stmt->bind_param("i", $patientID);
        $stmt->execute();
        $consultationsResult = $stmt->get_result();
        $stmt->close();
    }
} else {
    $errorMessage = "Patient ID is missing. Please select a patient to view their records.";
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f0f8ff; /* Light blue background */
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
            box-sizing: border-box; /* Include padding in width calculation */
        }

        .header .logo {
            width: 50px;
            height: auto;
            margin-right: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            color: white;
        }

        /* Sidebar styles */
        .sidebar {
            background-color: #aad8e6;
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            padding-top: 111px; /* Adjust based on header height */
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 999;
            box-sizing: border-box;
            overflow-y: auto;
            transition: width 0.3s ease-in-out; /* Added for smooth toggle */
        }

        .sidebar a {
            display: block;
            color: #0066cc;
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
            position: absolute;
            bottom: 0;
            width: 100%;
            background-color: #aad8e6;
            color: #0066cc;
            padding: 10px 0;
            font-size: 0.8rem;
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.05);
        }

        /* Sidebar toggle button */
        .sidebar-toggle-button {
            display: none; /* Hidden by default, shown on smaller screens */
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background-color: #20B2AA;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        /* Main Content container styles */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            margin-top: 80px; /* Space for fixed header */
            width: calc(100% - 250px);
            box-sizing: border-box;
            min-height: calc(100vh - 80px); /* Ensure it takes full height below header */
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; /* Added for smooth toggle */
        }

        .container {
            margin-top: 20px; /* Adjust margin for content inside main-content */
            padding: 0; /* Remove default container padding as main-content has padding */
        }

        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        /* Prevent text overflow */
        .table td, .table th {
            word-wrap: break-word;
            white-space: normal;
        }

        .table td {
            max-width: 150px; /* Limit cell width before ellipsis */
            text-overflow: ellipsis;
            overflow: hidden;
        }

        /* Make the table scrollable */
        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        /* Hide unnecessary elements in print */
        @media print {
            body * {
                visibility: hidden;
            }

            .printable-area, .printable-area * {
                visibility: visible;
            }

            .printable-area {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
            }

            .btn, .sidebar, .header, .sidebar-toggle-button {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
                padding-top: 0 !important;
            }

            .container {
                margin-top: 0 !important;
            }
        }

        /* Media queries for responsiveness */
        @media (max-width: 992px) {
            .sidebar {
                width: 0;
            }
            .sidebar-toggle-button {
                display: block;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .header {
                height: 60px;
                padding-left: 70px;
            }
            .header .logo {
                width: 40px;
            }
            .header h1 {
                font-size: 1.5rem;
            }
            .sidebar.active {
                width: 250px;
            }
            .main-content.sidebar-active { /* New class for main-content when sidebar is active */
                margin-left: 250px;
            }
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.3rem;
            }
            .card {
                padding: 10px;
            }
            table th, table td {
                font-size: 0.85rem;
            }
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }

        @media (max-width: 576px) {
            .header {
                padding: 0 10px 0 60px;
            }
            .header h1 {
                font-size: 1.1rem;
            }
            table th, table td {
                font-size: 0.75rem;
                padding: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar" id="mySidebar">
        <a href="staffdashboard.php">Dashboard</a>
        <a href="staffaddpatient.php">Add Patient</a>
        <a href="staffviewpatient.php">View Patients</a>
        <a href="stafffrontconsult.php">Consultation</a>
        <a href="staffrecordTable.php" class="active">Patient Records</a>
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
        <img src="images/UDMCLINIC_LOGO.png" alt="Logo" class="logo">
        <h1>UDM Clinic Cares Staff</h1>
    </div>

    <div class="main-content" id="mainContent">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Patient Records</h2>
            <button class="btn btn-secondary" onclick="window.print()">Print</button>
        </div>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
            <div class="container text-center mt-4">
                <a href="recordTable.php" class="btn btn-primary">Back to View Patients List</a>
            </div>
        <?php else: ?>
            <div class="card mb-4 printable-area">
                <div class="card-header bg-primary text-white">
                    Patient Records for: <?= htmlspecialchars($patient['FirstName'] . ' ' . $patient['LastName']) ?>
                    (Student No.: <?= htmlspecialchars($patient['Student_Num']) ?>, Program: <?= htmlspecialchars($patient['Program']) ?>)
                </div>
               

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                    
                                        <th>Date</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Subjective</th>
                                        <th>Objective</th>
                                        <th>Assessment</th>
                                        <th>Plan</th>
                                        <th>Follow Up Date</th>
                                        <th>Saved By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($consultationsResult && $consultationsResult->num_rows > 0) {
                                        while ($record = $consultationsResult->fetch_assoc()) {
                                            echo "<tr>";
                                            
                                            echo "<td>" . htmlspecialchars($record['Date']) . "</td>"; // Changed from Date
                                            echo "<td>" . htmlspecialchars($record['TimeIn']) . "</td>";
                                            echo "<td>" . htmlspecialchars($record['TimeOut']) . "</td>";

                                            echo "<td>" . htmlspecialchars($record['Subjective']) . "</td>";
                                            echo "<td>" . htmlspecialchars($record['Objective']) . "</td>";
                                            echo "<td>" . htmlspecialchars($record['Assessment']) . "</td>";
                                            echo "<td>" . htmlspecialchars($record['Plan']) . "</td>";
                                            echo "<td>" . htmlspecialchars($record['PlanDate']) . "</td>";
                                            echo "<td>" . htmlspecialchars($record['SavedBy']) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='11' class='text-center'>No consultation records found for this patient.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center">
                <a href="recordTable.php" class="btn btn-primary mt-3">Back to View Patients List</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="assets/js/jquery-3.5.1.slim.min.js"></script>
    <script src="assets/js/popper.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script>
        // JavaScript for sidebar toggle
        function toggleSidebar() {
            var sidebar = document.getElementById("mySidebar");
            var mainContent = document.getElementById("mainContent");
            sidebar.classList.toggle("active");
            mainContent.classList.toggle("sidebar-active");
        }

        // Adjust main content margin on initial load based on sidebar state (for desktop)
        window.addEventListener('DOMContentLoaded', (event) => {
            if (window.innerWidth >= 992) { // Desktop view
                mainContent.style.marginLeft = '250px';
                mainContent.style.width = 'calc(100% - 250px)';
            }
        });

        // Add a listener to resize to handle orientation changes or window resizing
        window.addEventListener('resize', (event) => {
            if (window.innerWidth >= 992) {
                mainContent.style.marginLeft = '250px';
                mainContent.style.width = 'calc(100% - 250px)';
            } else {
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
            }
        });

        // Select all checkboxes functionality
        document.getElementById('select-all').addEventListener('change', function () {
            const checkboxes = document.querySelectorAll('input[name="delete_ids[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });
    </script>
</body>
</html>