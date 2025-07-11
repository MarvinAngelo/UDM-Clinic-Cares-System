<?php
session_start();
// Include the backup logic here to enable automatic backups
require_once 'backup.php'; // Adjust path if backup.php is in a different directory

// Check if the user is logged in (you might want to add an admin-specific check here)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Redirect to the login page with an error message
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit();
}
$servername = "localhost";
$username_db = "root"; // Using a different variable name to avoid conflict with $_POST['username']
$password_db = "";
$database = "clinic_data"; // Assuming staffaccount table is in clinic_data

$connection = new mysqli($servername, $username_db, $password_db, $database);

if ($connection->connect_error) {
    die("Connection Failed: " . $connection->connect_error);
}

$message = ""; // To store success or error messages

// Handle staff creation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_staff'])) {
    $new_username = trim($_POST['new_username']);
    $new_password = $_POST['new_password']; // Password will not be hashed
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_username) || empty($new_password) || empty($confirm_password)) {
        $message = "<div class='alert alert-danger'>All fields are required.</div>";
    } elseif ($new_password !== $confirm_password) {
        $message = "<div class='alert alert-danger'>Passwords do not match.</div>";
    } else {
        // Password will NOT be hashed here
        // $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $plain_password = $new_password; // Store as plain text as requested

        // Check if username already exists
        $check_query = "SELECT id FROM staffaccount WHERE username = ?";
        $stmt_check = $connection->prepare($check_query);
        $stmt_check->bind_param("s", $new_username);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $message = "<div class='alert alert-warning'>Username already exists. Please choose a different username.</div>";
        } else {
            // Insert new staff account with plain text password
            $insert_query = "INSERT INTO staffaccount (username, password) VALUES (?, ?)";
            $stmt_insert = $connection->prepare($insert_query);
            $stmt_insert->bind_param("ss", $new_username, $plain_password); // Use plain_password

            if ($stmt_insert->execute()) {
                $message = "<div class='alert alert-success'>Staff account created successfully!</div>";
                // Clear form fields after successful creation
                $_POST['new_username'] = '';
                $_POST['new_password'] = '';
                $_POST['confirm_password'] = '';
            } else {
                $message = "<div class='alert alert-danger'>Error creating staff account: " . $stmt_insert->error . "</div>";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}

// Handle staff update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    $edit_id = $_POST['edit_id'];
    $edit_username = trim($_POST['edit_username']);
    $edit_password = $_POST['edit_password']; // Password will not be hashed
    $edit_confirm_password = $_POST['edit_confirm_password'];

    if (empty($edit_username)) {
        $message = "<div class='alert alert-danger'>Username cannot be empty.</div>";
    } elseif (!empty($edit_password) && $edit_password !== $edit_confirm_password) {
        $message = "<div class='alert alert-danger'>New passwords do not match.</div>";
    } else {
        // Check if the new username already exists for another user
        $check_username_query = "SELECT id FROM staffaccount WHERE username = ? AND id != ?";
        $stmt_check_username = $connection->prepare($check_username_query);
        $stmt_check_username->bind_param("si", $edit_username, $edit_id);
        $stmt_check_username->execute();
        $stmt_check_username->store_result();

        if ($stmt_check_username->num_rows > 0) {
            $message = "<div class='alert alert-warning'>Username already exists for another staff member. Please choose a different username.</div>";
        } else {
            $update_query = "UPDATE staffaccount SET username = ?";
            $param_types = "s";
            $params = [$edit_username];

            if (!empty($edit_password)) {
                // Password will NOT be hashed here
                // $hashed_new_password = password_hash($edit_password, PASSWORD_DEFAULT);
                $plain_new_password = $edit_password; // Store as plain text as requested
                $update_query .= ", password = ?";
                $param_types .= "s";
                $params[] = $plain_new_password; // Use plain_new_password
            }

            $update_query .= " WHERE id = ?";
            $param_types .= "i";
            $params[] = $edit_id;

            $stmt_update = $connection->prepare($update_query);
            $stmt_update->bind_param($param_types, ...$params);

            if ($stmt_update->execute()) {
                $message = "<div class='alert alert-success'>Staff account updated successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error updating staff account: " . $stmt_update->error . "</div>";
            }
            $stmt_update->close();
        }
        $stmt_check_username->close();
    }
}


// Fetch existing staff accounts for display
$staff_accounts = [];
$fetch_staff_query = "SELECT id, username FROM staffaccount ORDER BY username ASC";
$result_staff = $connection->query($fetch_staff_query);
if ($result_staff->num_rows > 0) {
    while ($row = $result_staff->fetch_assoc()) {
        $staff_accounts[] = $row;
    }
}

// Handle staff deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_staff_id_confirmed'])) { // Changed name to avoid direct submission
    $delete_id = $_POST['delete_staff_id_confirmed'];

    // Prevent deleting the currently logged-in user if you implement user roles
    // For now, it just deletes the ID.
    // Consider adding a check to prevent deleting the *last* admin account.

    $delete_staff_query = "DELETE FROM staffaccount WHERE id = ?";
    $stmt_delete_staff = $connection->prepare($delete_staff_query);
    $stmt_delete_staff->bind_param("i", $delete_id);
    if ($stmt_delete_staff->execute()) {
        $message = "<div class='alert alert-success'>Staff account deleted successfully.</div>";
        // Redirect to refresh the page and remove the deleted user from the list
        header("Location: staff_creation.php");
        exit();
    } else {
        $message = "<div class='alert alert-danger'>Error deleting staff account: " . $stmt_delete_staff->error . "</div>";
    }
    $stmt_delete_staff->close();
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff</title>
    <link rel="stylesheet" href="../assets/css/v2/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/v2/all.min.css">
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
            transition: width 0.3s ease-in-out; /* Smooth transition */
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
        .container-fluid {
            margin-left: 250px; /* Space for sidebar */
            padding: 20px;
            margin-top: 80px; /* Height of header */
            width: calc(100% - 250px); /* Full width minus sidebar width */
            max-width: none; /* Override Bootstrap's max-width for .container-fluid */
            box-sizing: border-box; /* Include padding in the element's total width and height */
            overflow-y: auto; /* Enable vertical scrolling for main content */
            min-height: calc(100vh - 80px); /* Ensure content takes at least remaining viewport height */
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; /* Added for smooth toggle */
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

        /* Form Specific Styles */
        .form-container {
            background-color: #ffffff; /* White background */
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Subtle shadow */
            margin-bottom: 30px;
            border: 1px solid #e0e0e0; /* Light border */
        }

        .form-container .form-group {
            margin-bottom: 15px; /* Spacing between form groups */
        }

        .form-container label {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px; /* Space between label and input */
        }

        .form-container .form-control {
            border-radius: 5px; /* Slightly rounded inputs */
            border: 1px solid #ced4da;
            padding: 10px 12px;
            height: auto; /* Allow height to adjust */
        }

        .form-container .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .form-container .btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s ease, border-color 0.3s ease;
            display: flex; /* Use flexbox for button alignment */
            align-items: center; /* Center icon and text */
            justify-content: center; /* Center icon and text */
        }

        .form-container .btn i {
            margin-right: 8px; /* Space between icon and text */
        }

        .form-container .btn-primary {
            background-color: #28a745; /* Green for create */
            border-color: #28a745;
        }

        .form-container .btn-primary:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        /* Table Specific Styles (for staff list) */
        .staff-table-container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
            padding: 20px;
            margin-bottom: 30px;
            max-height: 500px;
            overflow-y: auto;
        }

        .staff-table-container .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
            vertical-align: middle;
            white-space: nowrap;
            padding: 12px 15px;
        }
        .table tbody tr {
            background-color: #fff;
        }
        .table tbody tr:nth-of-type(odd) {
            background-color: #f8f9fa;
        }
        .table tbody tr:hover {
            background-color: #e2f0ff;
        }
        .table td {
            vertical-align: middle;
            padding: 10px 15px;
            border-top: 1px solid #dee2e6;
            word-wrap: break-word;
            white-space: normal;
        }
        .table .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
        }

        /* Message alerts */
        .alert {
            margin-top: 20px;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-color: #ffeeba;
        }

        /* NEW STYLES FOR DATABASE BACKUP SECTION */
        .backup-card {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }

        .backup-card .card-header {
            background-color: #007bff; /* Primary blue for header */
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            padding: 15px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .backup-card .card-body {
            padding: 25px;
        }

        .backup-card .btn-group-lg > .btn,
        .backup-card .btn-lg {
            padding: 12px 25px;
            font-size: 1.1rem;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .backup-card .btn-group-lg > .btn i,
        .backup-card .btn-lg i {
            margin-right: 10px;
        }

        .backup-card .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }

        .backup-card .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }

        .backup-card .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }

        .backup-card .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }

        /* Specific styles for the file input in the import section */
        .backup-card .custom-file-label {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            padding-right: 70px; /* Make space for "Choose file" text */
        }
        .backup-card .custom-file-input:lang(en) ~ .custom-file-label::after {
            content: "Browse"; /* Change default "Choose file" to "Browse" */
        }


        /* Backup History Table */
        .backup-history-card {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }

        .backup-history-card .card-header {
            background-color: #6c757d; /* Darker gray for history header */
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            padding: 15px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .backup-history-card .table thead th {
            background-color: #6c757d; /* Match header color */
            border-color: #6c757d;
        }
        .backup-history-card .table tbody td .btn {
            white-space: nowrap; /* Prevent button text from wrapping */
        }

        /* Current Backup Status */
        .current-status-box {
            background-color: #e9f5ff; /* Very light blue */
            border: 1px solid #cce5ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            font-size: 1.1rem;
            color: #004085; /* Dark blue text */
        }
        .current-status-box i {
            font-size: 2rem;
            margin-right: 15px;
            color: #007bff;
        }
        .status-ok {
            color: #28a745; /* Green */
        }
        .status-warning {
            color: #ffc107; /* Yellow */
        }
        .status-danger {
            color: #dc3545; /* Red */
        }

        /* Media queries for responsiveness */
        @media (max-width: 992px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }

            .sidebar-toggle-button {
                display: block;
            }

            .container-fluid {
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

            .sidebar.active {
                width: 250px;
            }
            .container-fluid.sidebar-active {
                margin-left: 250px;
                width: calc(100% - 250px);
            }

            .form-container {
                padding: 15px;
            }
            .form-container .form-group {
                margin-bottom: 10px;
            }
            .form-container .form-control {
                padding: 8px 10px;
            }

            /* Adjustments for backup section on small screens */
            .backup-card .card-body .row > div {
                margin-bottom: 15px; /* Add space between columns when stacked */
            }
            .backup-card .card-body .row > div:last-child {
                margin-bottom: 0;
            }
            .backup-card .btn-group-lg > .btn,
            .backup-card .btn-lg {
                font-size: 1rem;
                padding: 10px 20px;
                width: 100%; /* Full width buttons */
            }
            .backup-card .input-group-append .btn {
                width: auto; /* Allow import button to size naturally */
            }

            .staff-table-container, .backup-history-card .table-responsive {
                padding: 10px;
            }
            .table {
                border: 0;
            }
            .table thead {
                display: none;
            }
            .table, .table tbody, .table tr, .table td {
                display: block;
                width: 100%;
            }
            .table tr {
                margin-bottom: 15px;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                padding: 10px;
                background-color: #fff;
            }
            .table td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: right;
                min-height: 35px;
                display: flex;
                align-items: center;
                justify-content: flex-end;
                padding-top: 5px;
                padding-bottom: 5px;
            }
            .table td::before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                width: calc(50% - 20px);
                padding-right: 10px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                font-weight: bold;
                color: #555;
                text-align: left;
            }
            .table td[data-label="ID"],
            .table td[data-label="Username"],
            .table td[data-label="Date/Time"],
            .table td[data-label="Type"],
            .table td[data-label="Status"] {
                text-align: left;
                padding-left: 10px;
                justify-content: flex-start;
                font-size: 1.1em;
                color: #007bff; /* Adjust color for history entries if needed */
            }
            .table td[data-label="ID"]::before,
            .table td[data-label="Username"]::before,
            .table td[data-label="Date/Time"]::before,
            .table td[data-label="Type"]::before,
            .table td[data-label="Status"]::before {
                display: none;
            }
            .table td[data-label="Actions"] {
                text-align: center;
                padding-left: 10px;
                justify-content: center;
            }
            .table td[data-label="Actions"]::before {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.5rem;
            }
            .form-container .form-group {
                margin-bottom: 10px;
            }
            .form-container .form-control {
                padding: 8px 10px;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar" id="mySidebar">
        <a href="dashboard.php">Dashboard</a>
        <a href="addpatient.php">Add Patient</a>
        <a href="viewpatient.php">View Patients</a>
        <a href="frontconsult.php">Consultation</a>
        <a href="recordTable.php">Patient Records</a>
        <a href="generate_qr_codes.php">Generate QR Codes</a>
        <a href="medinventory.php">MedInventory</a>
        <a href="medilog.php">Medical Logs</a>
        <a href="staff_creation.php" class="active">Manage Staff</a>
        <a href="logout.php">Logout</a>
        <div class="sidebar-footer text-center">
            <p>UDM Clinic Cares System</p>
        </div>
    </div>

    <button class="sidebar-toggle-button" onclick="toggleSidebar()">☰</button>

    <div class="header">
        <img src="images/UDMCLINIC_LOGO.png" alt="Logo" class="logo">
        <h1>UDM Clinic</h1>
    </div>

    <div class="container-fluid" id="mainContent">
        <h2 class="mb-4 text-primary">Staff Account Management</h2>

        <?php echo $message; // Display messages ?>

        <div class="form-container mb-4">
            <h3 class="mb-3">Create New Staff Account</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="new_username">Username:</label>
                    <input type="text" name="new_username" id="new_username" class="form-control"
                           value="<?= htmlspecialchars(isset($_POST['new_username']) ? $_POST['new_username'] : '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Password:</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                </div>
                <button type="submit" name="create_staff" class="btn btn-primary btn-block">
                    <i class="fas fa-user-plus"></i> Create Staff Account
                </button>
            </form>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                Existing Staff Accounts
            </div>
            <div class="staff-table-container">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($staff_accounts)): ?>
                            <?php foreach ($staff_accounts as $account): ?>
                                <tr>
                                    <td data-label="ID"><?= htmlspecialchars($account['id']) ?></td>
                                    <td data-label="Username"><?= htmlspecialchars($account['username']) ?></td>
                                    <td data-label="Actions">
                                        <button type="button" class="btn btn-info btn-sm mr-1" title="Edit Staff"
                                                data-toggle="modal" data-target="#editStaffModal"
                                                data-id="<?= htmlspecialchars($account['id']) ?>"
                                                data-username="<?= htmlspecialchars($account['username']) ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" title="Delete Staff"
                                                data-toggle="modal" data-target="#deleteConfirmModal"
                                                data-id="<?= htmlspecialchars($account['id']) ?>"
                                                data-username="<?= htmlspecialchars($account['username']) ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">No staff accounts found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="backup-card mt-4">
            <div class="card-header">
                <i class="fas fa-database"></i> Database Backup & Restore
            </div>
            <div class="card-body">
                <div class="current-status-box">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Manual Save Database:</strong> Click to manually save the database <br>
                        <strong>Import:</strong> Click to manually import the database
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <form action="backup.php" method="POST" class="h-100">
                            <button type="submit" name="save_backup" class="btn btn-success btn-lg btn-block h-100">
                                <i class="fas fa-download"></i> Manual Save Database
                            </button>
                        </form>
                    </div>
                    
                    <div class="col-md-6">
                        <form action="import.php" method="POST" enctype="multipart/form-data" class="h-100">
                            <div class="input-group input-group-lg h-100">
                                <div class="custom-file flex-grow-1">
                                    <input type="file" class="custom-file-input" id="import_file" name="import_file" accept=".sql" required>
                                    <label class="custom-file-label" for="import_file">Choose .sql file to import</label>
                                </div>
                                <div class="input-group-append">
                                    <button type="submit" name="import_backup" class="btn btn-info h-100">
                                        <i class="fas fa-upload"></i> Import
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="backup-history-card mt-4">
            <div class="card-header">
                <i class="fas fa-history"></i> Backup History
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>File Name</th>
                              
                            </tr>
                        </thead>
                        <tbody id="backupHistoryTableBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted">Loading backup history...</td>
                            </tr>
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div> 
    
    <div class="modal fade" id="editStaffModal" tabindex="-1" role="dialog" aria-labelledby="editStaffModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editStaffModalLabel">Edit Staff Account</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="form-group">
                            <label for="edit_username">Username:</label>
                            <input type="text" name="edit_username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_password">New Password (leave blank to keep current):</label>
                            <input type="password" name="edit_password" id="edit_password" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="edit_confirm_password">Confirm New Password:</label>
                            <input type="password" name="edit_confirm_password" id="edit_confirm_password" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="update_staff" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" id="deleteStaffForm">
                    <div class="modal-body">
                        <input type="hidden" name="delete_staff_id_confirmed" id="delete_staff_id_confirmed">
                        Are you sure you want to delete the staff account: <strong id="delete_username_display"></strong>? This action cannot be undone.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/jquery-3.5.1.slim.min.js"></script>
    <script src="../assets/js/popper.min.js"></script>
    <script src="../assets/js/bootstrap.min.js"></script>
    <script>
        // JavaScript for sidebar toggle
        function toggleSidebar() {
            var sidebar = document.getElementById("mySidebar");
            var mainContent = document.getElementById("mainContent");
            sidebar.classList.toggle("active"); // "active" class will handle the width change
            mainContent.classList.toggle("sidebar-active"); // Toggle main content class for margin adjustment
        }

        // Adjust main content margin on initial load based on sidebar state (for desktop)
        window.addEventListener('DOMContentLoaded', (event) => {
            var sidebar = document.getElementById("mySidebar");
            var mainContent = document.getElementById("mainContent");
            if (window.innerWidth >= 992) { // Desktop view
                mainContent.style.marginLeft = '250px';
                mainContent.style.width = 'calc(100% - 250px)';
                sidebar.classList.remove("active"); // Ensure sidebar is open on large screens
                mainContent.classList.remove("sidebar-active"); // Ensure main content is correct
            }
            fetchBackupHistory(); // Call function to load backup history on page load
        });

        // Add a listener to resize to handle orientation changes or window resizing
        window.addEventListener('resize', (event) => {
            var sidebar = document.getElementById("mySidebar");
            var mainContent = document.getElementById("mainContent");
            if (window.innerWidth >= 992) {
                mainContent.style.marginLeft = '250px';
                mainContent.style.width = 'calc(100% - 250px)';
                sidebar.classList.remove("active");
                mainContent.classList.remove("sidebar-active");
            } else {
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
            }
        });

        // Populate the edit modal with staff data when the edit button is clicked
        $('#editStaffModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Button that triggered the modal
            var id = button.data('id'); // Extract info from data-* attributes
            var username = button.data('username');

            var modal = $(this);
            modal.find('.modal-body #edit_id').val(id);
            modal.find('.modal-body #edit_username').val(username);
            modal.find('.modal-body #edit_password').val(''); // Clear password fields on open
            modal.find('.modal-body #edit_confirm_password').val(''); // Clear password fields on open
        });

        // Populate the delete confirmation modal
        $('#deleteConfirmModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Button that triggered the modal
            var id = button.data('id'); // Extract info from data-* attributes
            var username = button.data('username'); // Extract username

            var modal = $(this);
            modal.find('.modal-body #delete_staff_id_confirmed').val(id);
            modal.find('.modal-body #delete_username_display').text(username);
        });


        // For the custom file input to show the file name
        document.querySelector('.custom-file-input').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            var nextSibling = e.target.nextElementSibling
            nextSibling.innerText = fileName
        });

        // Function to fetch and display backup history (Requires a backend endpoint)
        async function fetchBackupHistory() {
            const tableBody = document.getElementById('backupHistoryTableBody');
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="sr-only">Loading...</span></div> Fetching history...</td></tr>';

            try {
                // IMPORTANT: You need to create a new PHP file (e.g., 'get_backup_history.php')
                // that queries your backup logs (if you have them) or scans a backup directory
                // and returns a JSON array of backup records.
                // For demonstration, let's assume it returns:
                // [{dateTime: '...', type: '...', status: '...', fileName: '...'}]
                const response = await fetch('get_backup_history.php'); // Replace with your actual endpoint
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const history = await response.json();

                if (history.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No backup history found.</td></tr>';
                    return;
                }

                tableBody.innerHTML = ''; // Clear loading message

                history.forEach(record => {
                    let statusBadgeClass = '';
                    if (record.status === 'Success') {
                        statusBadgeClass = 'badge-success';
                    } else if (record.status === 'Failed') {
                        statusBadgeClass = 'badge-danger';
                    } else {
                        statusBadgeClass = 'badge-secondary'; // For 'Pending', 'In Progress' etc.
                    }

                    const row = `
                        <tr>
                            <td data-label="Date/Time">${record.dateTime}</td>
                            <td data-label="Type">${record.type}</td>
                            <td data-label="Status"><span class="badge ${statusBadgeClass}">${record.status}</span></td>
                            <td data-label="File Name">${record.fileName}</td>
                        
                        </tr>
                    `;
                    tableBody.insertAdjacentHTML('beforeend', row);
                });

            } catch (error) {
                console.error('Error fetching backup history:', error);
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Error loading history: ${error.message}. Please try again.</td></tr>`;
            }
        }

        // Placeholder for delete function (requires backend implementation)
        async function deleteBackup(fileName) {
            if (confirm(`Are you sure you want to delete the backup file: ${fileName}?`)) {
                try {
                    // IMPORTANT: You need to create a new PHP file (e.g., 'delete_backup.php')
                    // that safely deletes the specified backup file and corresponding log entry.
                    const response = await fetch('delete_backup.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `fileName=${encodeURIComponent(fileName)}`
                    });

                    const result = await response.json();
                    if (result.success) {
                        alert('Backup deleted successfully!');
                        fetchBackupHistory(); // Refresh the list
                    } else {
                        alert('Error deleting backup: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error deleting backup:', error);
                    alert('An error occurred while trying to delete the backup.');
                }
            }
        }

    </script>
</body>

</html>