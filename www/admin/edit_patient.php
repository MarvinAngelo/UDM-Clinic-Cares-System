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

$connection = new mysqli($servername, $username, $password, $database);

if ($connection->connect_error) {
    die("Connection Failed: " . $connection->connect_error);
}

// Check if PatientID is passed in the URL
if (isset($_GET['id'])) {
    $patient_id = $connection->real_escape_string($_GET['id']);

    // Fetch patient details
    $query = "SELECT * FROM patients WHERE PatientID = '$patient_id'";
    $result = $connection->query($query);

    if ($result->num_rows > 0) {
        $patient = $result->fetch_assoc();
    } else {
        die("Patient not found.");
    }
} else {
    die("Invalid request.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_num = $connection->real_escape_string($_POST['student_num']);
    $first_name = $connection->real_escape_string($_POST['first_name']);
    $middle_initial = $connection->real_escape_string($_POST['middle_initial']);
    $last_name = $connection->real_escape_string($_POST['last_name']);
    $sex = $connection->real_escape_string($_POST['sex']);

    $update_query = "UPDATE patients 
                     SET Student_Num = '$student_num', 
                         FirstName = '$first_name', 
                         MiddleInitial = '$middle_initial', 
                         LastName = '$last_name', 
                         Sex = '$sex' 
                     WHERE PatientID = '$patient_id'";

    if ($connection->query($update_query) === TRUE) {
        echo "<script>alert('Patient details updated successfully'); window.location.href = 'viewpatient.php';</script>";
    } else {
        echo "<script>alert('Error updating record: " . $connection->error . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
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
        .main-content {
            margin-left: 250px; /* Space for sidebar */
            padding: 20px;
            margin-top: 80px; /* Height of header */
            width: calc(100% - 250px); /* Full width minus sidebar width */
            box-sizing: border-box; /* Include padding in the element's total width and height */
            min-height: calc(100vh - 80px); /* Ensure content takes at least remaining viewport height */
        }

        .form-container {
            max-width: 600px;
            margin: auto;
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background-color: #0066cc;
            border: none;
        }

        .btn-primary:hover {
            background-color: #004b99;
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

            .main-content {
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
            
            .form-container {
                padding: 15px; /* Reduce padding on smaller screens */
            }
        }
    </style>
</head>
<body>
<div class="sidebar" id="mySidebar">
        <a href="dashboard.php"  class="active">Dashboard</a>
        <a href="addpatient.php">Add Patient</a>
        <a href="viewpatient.php">View Patients</a>
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

    <div class="main-content">
        <div class="form-container">
            <h2>Edit Patient</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="student_num">Student Number</label>
                    <input type="text" id="student_num" name="student_num" class="form-control" value="<?php echo htmlspecialchars($patient['Student_Num']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($patient['FirstName']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="middle_initial">Middle Initial</label>
                    <input type="text" id="middle_initial" name="middle_initial" class="form-control" value="<?php echo htmlspecialchars($patient['MiddleInitial']); ?>">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($patient['LastName']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="sex">Sex</label>
                    <select id="sex" name="sex" class="form-control" required>
                        <option value="Male" <?php if ($patient['Sex'] === 'Male') echo 'selected'; ?>>Male</option>
                        <option value="Female" <?php if ($patient['Sex'] === 'Female') echo 'selected'; ?>>Female</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Update</button>
                <a href="viewpatient.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

    <script src="../assets/js/jquery-3.5.1.slim.min.js"></script>
    <script src="../assets/js/popper.min.js"></script>
    <script src="../assets/js/bootstrap.min.js"></script>
    <script>
        // JavaScript for sidebar toggle
        function toggleSidebar() {
            var sidebar = document.getElementById("mySidebar");
            sidebar.classList.toggle("active"); // "active" class will handle the width change
        }
    </script>
</body>
</html>