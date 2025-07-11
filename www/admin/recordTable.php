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

// Added $studentNumSearch and $patientIDSearch
$startDate = $endDate = $programSearch = $nameSearch = $assessmentSearch = $studentNumSearch = $patientIDSearch = ""; 
$whereClauses = [];
$params = [];
$paramTypes = "";

// Handle search form submission or retrieve from session
if (isset($_POST['search_records'])) {
    // Store search parameters in session
    $_SESSION['search_params']['start_date'] = $_POST['start_date'];
    $_SESSION['search_params']['end_date'] = $_POST['end_date'];
    // Program now comes from a select dropdown
    $_SESSION['search_params']['program'] = isset($_POST['program']) ? $_POST['program'] : ''; 
    $_SESSION['search_params']['name'] = $_POST['name'];
    $_SESSION['search_params']['assessment'] = $_POST['assessment'];
    // Remove dashes from student number input before storing and using
    $_SESSION['search_params']['student_num'] = str_replace('-', '', $_POST['student_num']); 
    // Add Patient ID to session
    $_SESSION['search_params']['patient_id'] = $_POST['patient_id'];

    // Set variables from POST for immediate use
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $programSearch = isset($_POST['program']) ? $_POST['program'] : '';
    $nameSearch = $_POST['name'];
    $assessmentSearch = $_POST['assessment'];
    $studentNumSearch = str_replace('-', '', $_POST['student_num']); // Remove dashes for search
    $patientIDSearch = $_POST['patient_id']; // Set Patient ID from POST
} elseif (isset($_SESSION['search_params'])) {
    // Retrieve search parameters from session if no new search was submitted
    $startDate = $_SESSION['search_params']['start_date'];
    $endDate = $_SESSION['search_params']['end_date'];
    $programSearch = $_SESSION['search_params']['program'];
    $nameSearch = $_SESSION['search_params']['name'];
    $assessmentSearch = $_SESSION['search_params']['assessment'];
    $studentNumSearch = $_SESSION['search_params']['student_num']; // Retrieve pre-processed student number
    $patientIDSearch = $_SESSION['search_params']['patient_id']; // Retrieve Patient ID
}

// Build WHERE clauses based on current (POST or SESSION) search parameters
if (!empty($startDate) && !empty($endDate)) {
    $whereClauses[] = "c.Date BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $paramTypes .= "ss";
}

if (!empty($programSearch)) {
    // For dropdown, use exact match
    $whereClauses[] = "p.Program = ?";
    $params[] = $programSearch;
    $paramTypes .= "s";
}

if (!empty($nameSearch)) {
    $whereClauses[] = "CONCAT(p.FirstName, ' ', IFNULL(p.MiddleInitial, ''), ' ', p.LastName) LIKE ?";
    $params[] = "%" . $nameSearch . "%";
    $paramTypes .= "s";
}

if (!empty($assessmentSearch)) {
    $whereClauses[] = "c.Assessment LIKE ?";
    $params[] = "%" . $assessmentSearch . "%";
    $paramTypes .= "s";
}

// Add student number search clause
if (!empty($studentNumSearch)) {
    // When searching the database, also remove dashes from the database column value
    // This makes the search flexible whether the stored ID has dashes or not.
    // However, for efficiency, it's often better to standardize the stored format.
    // Given the `LIKE` operator, we'll strip dashes from the input and search for it.
    // If Student_Num in DB contains dashes, this approach might not work perfectly.
    // A more robust solution might involve storing student numbers without dashes in the DB.
    // For now, we'll assume a direct match after stripping dashes from input.
    $whereClauses[] = "REPLACE(p.Student_Num, '-', '') LIKE ?";
    $params[] = "%" . $studentNumSearch . "%";
    $paramTypes .= "s";
}

// Add Patient ID search clause
if (!empty($patientIDSearch)) {
    $whereClauses[] = "p.PatientID LIKE ?";
    $params[] = "%" . $patientIDSearch . "%";
    $paramTypes .= "s";
}

// Deletion logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $connection->real_escape_string($_POST['delete_id']);
    $deleteQuery = "DELETE FROM consultations WHERE ConsultationID = '$delete_id'";
    if ($connection->query($deleteQuery) === TRUE) {
        // Instead of a full page reload, a JavaScript alert followed by a reload
        // to the *current* page (which will re-apply session filters) is better.
        echo "<script>alert('Consultation record deleted successfully'); window.location.href='recordTable.php';</script>";
        exit(); // Crucial to prevent further script execution after redirect
    } else {
        echo "<script>alert('Error deleting consultation record: " . $connection->error . "');</script>";
    }
}

// Logic for clearing search (if you add a clear button)
if (isset($_POST['clear_search'])) {
    unset($_SESSION['search_params']);
    $startDate = $endDate = $programSearch = $nameSearch = $assessmentSearch = $studentNumSearch = $patientIDSearch = ""; // Clear current variables
    header("Location: recordTable.php"); // Redirect to refresh the page without search params
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/all.min.css"> <style>
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
        .container-fluid { /* Changed from .container to .container-fluid for full width use */
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

        /* Search Form Specific Styles */
        .search-form-container {
            background-color: #ffffff; /* White background for the search form */
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Subtle shadow */
            margin-bottom: 30px;
            border: 1px solid #e0e0e0; /* Light border */
        }

        .search-form-container .form-group {
            margin-bottom: 15px; /* Spacing between form groups */
        }

        .search-form-container label {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px; /* Space between label and input */
        }
        .required-field::after {
            content: " *";
            color: red;
        }

        .search-form-container .form-control {
            border-radius: 5px; /* Slightly rounded inputs */
            border: 1px solid #ced4da;
            padding: 10px 12px;
            height: auto; /* Allow height to adjust */
        }

        .search-form-container .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .search-form-container .btn {
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s ease, border-color 0.3s ease;
            display: flex; /* Use flexbox for button alignment */
            align-items: center; /* Center icon and text */
            justify-content: center; /* Center icon and text */
        }

        .search-form-container .btn i {
            margin-right: 8px; /* Space between icon and text */
        }

        .search-form-container .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }

        .search-form-container .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        .search-form-container .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .search-form-container .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }

        /* Patient Records List - Table Specific Styles */
        .patient-records-table-container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
            padding: 20px; /* Padding around the table */
            margin-bottom: 30px; /* Space below the container */
            max-height: 500px; /* Added max-height for vertical scrolling */
            overflow-y: auto; /* Enable vertical scrolling */
        }

        .patient-records-table-container .table {
            margin-bottom: 0; /* Remove default table bottom margin */
        }

        .table thead th {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
            vertical-align: middle;
            white-space: nowrap; /* Prevent headers from wrapping too much */
            padding: 12px 15px; /* More padding for headers */
        }
        .table tbody tr {
            background-color: #fff;
        }
        .table tbody tr:nth-of-type(odd) {
            background-color: #f8f9fa; /* Light gray for odd rows */
        }
        .table tbody tr:hover {
            background-color: #e2f0ff; /* Lighter blue on hover */
        }
        .table td {
            vertical-align: middle;
            padding: 10px 15px;
            border-top: 1px solid #dee2e6;
            word-wrap: break-word; /* Ensure long words break */
            white-space: normal; /* Allow text to wrap */
            min-width: 80px; /* Minimum width for most columns */
        }
        .table td:first-child { /* No. column */
            min-width: 40px;
            text-align: center;
        }
        .table td:nth-child(3) { /* Student No. */
            min-width: 100px;
        }
        .table td:nth-child(4) { /* Full Name */
            min-width: 150px;
            font-weight: bold;
        }
        .table td:nth-child(5) { /* Program */
            min-width: 100px;
        }
        .table td:nth-child(6), /* Date */
        .table td:nth-child(15) { /* Follow Up Date */
            min-width: 90px;
            white-space: nowrap; /* Keep dates on one line */
        }
        .table td:nth-child(7), /* Time In */
        .table td:nth-child(8) { /* Time Out */
            min-width: 70px;
            white-space: nowrap;
        }
        .table td:nth-child(9), /* Subjective */
        .table td:nth-child(10), /* Objective */
        .table td:nth-child(14) { /* Plan */
            max-width: 250px; /* Limit width of text-heavy columns */
        }
        .table td:nth-child(13) { /* Quantity Given */
            text-align: center;
            min-width: 50px;
        }
        .table .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
        }

        /* Dropdown within table for patient names */
        .table .dropdown-toggle {
            color: #007bff; /* Link color for dropdown toggle */
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }
        .table .dropdown-toggle:hover {
            text-decoration: underline;
        }
        .table .dropdown-menu {
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            padding: 5px 0;
        }
        .table .dropdown-item {
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        .table .dropdown-item:hover {
            background-color: #e9ecef;
            color: #007bff;
        }

        /* Assessment Summary styles */
        #assessment-summary {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
            padding: 20px; /* Padding inside the card body */
            margin-bottom: 30px; /* Space below the container */
        }
        #assessment-summary .card-header {
            background-color: #28a745; /* Green for summary header */
            color: white;
            border-radius: 8px 8px 0 0; /* Rounded top corners */
            padding: 15px 20px; /* More padding for header */
            font-size: 1.25rem; /* Larger font size */
            font-weight: bold;
            margin: -20px -20px 20px -20px; /* Adjust to cover padding area */
        }
        #assessment-summary .form-inline .form-group {
            margin-right: 15px; /* Spacing between form elements */
            margin-bottom: 15px; /* Add bottom margin for stacking on small screens */
        }
        #assessment-summary .form-inline label {
            margin-right: 8px;
            font-weight: bold;
            color: #333;
        }
        #assessment-summary .form-inline .form-control {
            border-radius: 5px;
            border: 1px solid #ced4da;
            padding: 8px 12px;
        }
        #assessment-summary .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #assessment-summary .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        #assessment-summary .btn-primary i {
            margin-right: 8px;
        }
        #assessment-summary .table thead th {
            background-color: #28a745; /* Green for summary table headers */
            border-color: #28a745;
        }
        #assessment-summary .table tbody tr:nth-of-type(odd) {
            background-color: #f0fdf4; /* Lighter green tint for odd rows */
        }
        #assessment-summary .table tbody tr:hover {
            background-color: #e6ffe6; /* Even lighter green on hover */
        }
        #assessment-summary .table .font-weight-bold.table-info {
            background-color: #d4edda !important; /* A distinct light green for total row */
            color: #155724; /* Darker green text for total row */
            font-size: 1.1em;
        }
        #assessment-summary .btn-info {
            background-color: #17a2b8; /* Info blue for print button */
            border-color: #17a2b8;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #assessment-summary .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }
        #assessment-summary .btn-info i {
            margin-right: 8px;
        }


        /* Media queries for responsiveness */
        @media (max-width: 992px) {
            .sidebar {
                width: 0; /* Collapse sidebar on smaller screens */
                overflow: hidden; /* Hide content that overflows */
            }

            .sidebar-toggle-button {
                display: block; /* Show the button */
            }

            .container-fluid {
                margin-left: 0; /* Main content takes full width */
                width: 100%; /* Take full width */
            }

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
            .container-fluid.sidebar-active { /* New class for main-content when sidebar is active */
                margin-left: 250px;
                width: calc(100% - 250px);
            }

            .search-form-container {
                padding: 15px; /* Reduce padding on smaller screens */
            }
            .search-form-container .form-group {
                margin-bottom: 10px;
            }
            .search-form-container .btn {
                width: 100%; /* Full width buttons */
                margin-top: 10px;
            }
            
            /* Patient Records List - Mobile Table Transformation */
            .patient-records-table-container {
                padding: 10px; /* Reduce padding */
            }
            .table {
                border: 0; /* Remove outer table border */
            }
            .table thead {
                display: none; /* Hide table headers on small screens */
            }
            .table, .table tbody, .table tr, .table td {
                display: block; /* Make table elements behave like blocks */
                width: 100%; /* Take full width */
            }
            .table tr {
                margin-bottom: 15px;
                border: 1px solid #dee2e6; /* Add a border to each "card" row */
                border-radius: 5px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                padding: 10px;
                background-color: #fff;
            }
            .table td {
                border: none; /* Remove individual cell borders */
                position: relative;
                padding-left: 50%; /* Space for the pseudo-element label */
                text-align: right;
                min-height: 35px; /* Ensure a minimum height for rows */
                display: flex; /* Use flexbox to align label and content */
                align-items: center;
                justify-content: flex-end; /* Align content to the right */
                padding-top: 5px;
                padding-bottom: 5px;
            }
            .table td::before {
                /* Display column headers as labels */
                content: attr(data-label); /* Get label from data-label attribute */
                position: absolute;
                left: 10px; /* Position the label on the left */
                width: calc(50% - 20px); /* Adjust width for padding */
                padding-right: 10px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                font-weight: bold;
                color: #555;
                text-align: left;
            }

            /* Specific adjustments for Patient ID, Student No., Full Name, Program, Actions */
            .table td:first-child { /* No. column */
                display: none; /* Hide 'No.' column on mobile */
            }
            .table td[data-label="Patient ID"],
            .table td[data-label="Student No."],
            .table td[data-label="Full Name"],
            .table td[data-label="Program"] {
                text-align: left; /* Align these key identifiers to the left */
                padding-left: 10px; /* Remove pseudo-element padding */
                justify-content: flex-start;
                font-size: 1.1em;
                color: #007bff;
            }
            .table td[data-label="Patient ID"]::before,
            .table td[data-label="Student No."]::before,
            .table td[data-label="Full Name"]::before,
            .table td[data-label="Program"]::before {
                display: none; /* Hide pseudo-elements for these columns */
            }
            .table td[data-label="Actions"] {
                text-align: center;
                padding-left: 10px; /* Remove pseudo-element padding */
                justify-content: center;
            }
            .table td[data-label="Actions"]::before {
                display: none;
            }

            /* Assessment Summary - Mobile Adjustments */
            #assessment-summary {
                padding: 15px;
            }
            #assessment-summary .card-header {
                margin: -15px -15px 15px -15px; /* Adjust to new padding */
            }
            #assessment-summary .form-inline {
                flex-direction: column; /* Stack form elements vertically */
                align-items: stretch; /* Stretch items to full width */
            }
            #assessment-summary .form-inline .form-group {
                margin-right: 0;
                margin-bottom: 10px; /* Adjust margin */
                width: 100%;
            }
            #assessment-summary .form-inline button {
                width: 100%; /* Full width button */
            }
        }

        @media (max-width: 768px) {
            /* Further adjustments for even smaller screens */
            .header h1 {
                font-size: 1.5rem; /* Adjust font size for header title */
            }
            
            .search-form-container .form-group {
                margin-bottom: 10px; /* Stack inputs more tightly */
            }
            .search-form-container .form-control {
                padding: 8px 10px;
            }
        }

        @media print {
            button,
            input,
            form {
                display: none !important;
            }
            /* Ensure the header and report are visible */
            .d-print-block {
                display: block !important;
            }

            /* Adjust table width for printing */
            table {
                width: 100% !important;
                border-collapse: collapse;
            }
            th, td {
                border: 1px solid #000;
                padding: 8px;
                text-align: left;
            }
            /* Reset mobile table styles for print */
            .table, .table tbody, .table tr, .table td {
                display: table;
                width: 100%;
            }
            .table tr {
                margin-bottom: 0;
                border: none;
                box-shadow: none;
                padding: 0;
                background-color: transparent;
            }
            .table thead {
                display: table-header-group;
            }
            .table td {
                border: 1px solid #dee2e6;
                padding-left: initial;
                text-align: left;
                min-height: initial;
                display: table-cell;
                justify-content: initial;
                padding-top: initial;
                padding-bottom: initial;
            }
            .table td::before {
                display: none;
            }
            .table td:first-child {
                display: table-cell;
                text-align: left;
            }
            .table td[data-label="Patient ID"]::before,
            .table td[data-label="Student No."]::before,
            .table td[data-label="Full Name"]::before,
            .table td[data-label="Program"]::before {
                display: table-cell;
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
        <a href="viewpatient.php">View Patients</a>
        <a href="frontconsult.php">Consultation</a>
        <a href="recordTable.php" class="active">Patient Records</a>
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

    <div class="header">
        <img src="images/UDMCLINIC_LOGO.png" alt="Logo" class="logo">
        <h1>UDM Clinic Cares</h1>
    </div>

    <div class="container-fluid" id="mainContent"> <h2 class="mb-4 text-primary">Patient Records Search</h2> <div class="search-form-container">
            <form method="POST"> 
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="start_date">Start Date:</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date:</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                        </div>

                        <div class="form-group">
                            <label class="field">Program</label>
                            <select class="form-control" id="Program" name="program"> 
                                <option value="">Select Program</option>
                                <option value="CET" <?= ($programSearch == 'CET') ? 'selected' : '' ?>>CET - College of Engineering and Technology</option>
                                <option value="CHS" <?= ($programSearch == 'CHS') ? 'selected' : '' ?>>CHS - College of Health and Science</option>
                                <option value="CBA" <?= ($programSearch == 'CBA') ? 'selected' : '' ?>>CBA - College of Business Administration</option>
                                <option value="CAS" <?= ($programSearch == 'CAS') ? 'selected' : '' ?>>CAS - College of Arts and Sciences</option>
                                <option value="CCJ" <?= ($programSearch == 'CCJ') ? 'selected' : '' ?>>CCJ - College of Criminal Justice</option>
                                <option value="CED" <?= ($programSearch == 'CED') ? 'selected' : '' ?>>CED - College of Education</option>
                                <option value="N/A" <?= ($programSearch == 'N/A') ? 'selected' : '' ?>>N/A</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="student_num">Student ID Number:</label>
                            <input type="text" name="student_num" id="student_num" class="form-control" value="<?= htmlspecialchars(str_replace('-', '', $studentNumSearch)) ?>" placeholder="Enter Student ID">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="patient_id">Patient ID:</label>
                            <input type="text" name="patient_id" id="patient_id" class="form-control" value="<?= htmlspecialchars($patientIDSearch) ?>" placeholder="Enter Patient ID">
                        </div>

                        <div class="form-group">
                            <label for="name">Patient Name:</label>
                            <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($nameSearch) ?>" placeholder="Enter Full Name">
                        </div>

                        <div class="form-group">
                            <label for="assessment">Assessment:</label>
                            <input type="text" name="assessment" id="assessment" class="form-control" value="<?= htmlspecialchars($assessmentSearch) ?>" placeholder="Enter Assessment">
                        </div>
                    </div>
                </div>

                <div class="form-row justify-content-end mt-3">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <button type="submit" name="search_records" class="btn btn-primary btn-block">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <button type="submit" name="clear_search" class="btn btn-secondary btn-block">
                            <i class="fas fa-redo"></i> Clear
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                Patient Records List
            </div>
            <div class="patient-records-table-container">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Patient ID</th>
                            <th>Student No.</th>
                            <th>Full Name</th>
                            <th>Program</th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Subjective</th>
                            <th>Objective</th>
                            <th>Assessment</th>
                            <th>Medicine Given</th>
                            <th>Quantity Given</th>
                            <th>Plan</th>
                            <th>Follow Up Date</th>
                            <th>Saved By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php
                        // Base query
                        $query = "
                            SELECT c.*, 
                                CONCAT(p.FirstName, ' ', IFNULL(p.MiddleInitial, ''), ' ', p.LastName) AS FullName,
                                p.Student_Num, p.Program
                            FROM consultations c
                            JOIN patients p ON c.PatientID = p.PatientID";

                        // Add WHERE conditions if any filters are applied
                        if (!empty($whereClauses)) {
                            $query .= " WHERE " . implode(" AND ", $whereClauses);
                        }

                        $query .= " ORDER BY c.Date DESC";

                        // Prepare the statement
                        $stmt = $connection->prepare($query);

                        if (!empty($params)) {
                            $stmt->bind_param($paramTypes, ...$params);
                        }

                        $stmt->execute();
                        $recordsResult = $stmt->get_result();

                        // Fetch and display results
                        if ($recordsResult->num_rows > 0) {
                            $patientConsultations = [];

                            while ($record = $recordsResult->fetch_assoc()) {
                                $patientConsultations[$record['PatientID']][] = $record;
                            }

                            $count = 1;

                            foreach ($patientConsultations as $patientID => $consultations) {
                                $isFirst = true;

                                foreach ($consultations as $consultation) {
                                    echo "<tr>";
                                    if ($isFirst) {
                                        echo "<td rowspan='" . count($consultations) . "' data-label='No.'>" . $count . "</td>";
                                        echo "<td rowspan='" . count($consultations) . "' data-label='Patient ID'>" . htmlspecialchars($consultation['PatientID']) . "</td>";
                                        echo "<td rowspan='" . count($consultations) . "' data-label='Student No.'>" . htmlspecialchars($consultation['Student_Num']) . "</td>";

                                       echo "<td rowspan='" . count($consultations) . "' data-label='Full Name'>";
echo "<a href='patient_records.php?id=" . $consultation['PatientID'] . "&consult=" . $consultation['ConsultationID'] . "'>" .
    htmlspecialchars($consultation['FullName']) . "</a>";

// Show dropdown if there are more than 10 consultations
if (count($consultations) > 10) {
    echo "<div class='dropdown mt-1'>
            <button class='btn btn-sm btn-secondary dropdown-toggle' type='button' data-toggle='dropdown' data-boundary='body'>
                View All Consultations
            </button>
            <div class='dropdown-menu'>";
    foreach ($consultations as $drop) {
        echo "<a class='dropdown-item' href='patient_records.php?id=" . $drop['PatientID'] . "&consult=" . $drop['ConsultationID'] . "'>" .
            htmlspecialchars($drop['Date']) . " - " . htmlspecialchars($drop['Assessment']) . "</a>";
    }
    echo "</div></div>";
}
echo "</td>";

                                        echo "<td rowspan='" . count($consultations) . "' data-label='Program'>" . htmlspecialchars($consultation['Program']) . "</td>";

                                        $isFirst = false;
                                        $count++;
                                    }

                                    echo "<td data-label='Date'>" . htmlspecialchars($consultation['Date']) . "</td>";
                                    echo "<td data-label='Time In'>" . htmlspecialchars($consultation['TimeIn']) . "</td>";
                                    echo "<td data-label='Time Out'>" . htmlspecialchars($consultation['TimeOut']) . "</td>";
                                    echo "<td data-label='Subjective'>" . htmlspecialchars($consultation['Subjective']) . "</td>";
                                    echo "<td data-label='Objective'>" . htmlspecialchars($consultation['Objective']) . "</td>";
                                    echo "<td data-label='Assessment'>" . htmlspecialchars($consultation['Assessment']) . "</td>";
                                  echo "<td data-label='Medicine Given'>" . htmlspecialchars($consultation['MedicineGiven'] ?? '') . "</td>";
echo "<td data-label='Quantity Given'>" . htmlspecialchars($consultation['QuantityGiven'] ?? '') . "</td>";

                                    echo "<td data-label='Plan'>" . htmlspecialchars($consultation['Plan']) . "</td>";
                                    echo "<td data-label='Follow Up Date'>" . htmlspecialchars($consultation['PlanDate']) . "</td>";
                                    echo "<td data-label='Saved By'>" . htmlspecialchars($consultation['SavedBy']) . "</td>";
                                    echo "<td data-label='Actions'>
                                            <form method='POST' onsubmit='return confirm(\"Are you sure you want to delete this record?\");'>
                                                <input type='hidden' name='delete_id' value='" . htmlspecialchars($consultation['ConsultationID']) . "'>
                                                <button type='submit' class='btn btn-danger btn-sm' title='Delete Record'><i class='fas fa-trash-alt'></i></button>
                                            </form>
                                        </td>";
                                    echo "</tr>";
                                }
                            }
                        } else {
                            echo "<tr><td colspan='17' class='text-center'>No records found</td></tr>"; // Adjusted colspan
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4 mt-4" id="assessment-summary">
            <div class="text-center mb-3 d-none d-print-block">
                <h2 class="font-weight-bold">Universidad de Manila Clinic Cares System</h2>
                <p class="font-italic">Assessment Summary Report</p>
                <hr>
            </div>

            <div class="card-header">
                Assessment Summary
            </div>
            <div class="card-body">
                <form method="POST" class="form-row align-items-end mb-3"> <div class="col-md-4 col-sm-6 mb-3">
                        <label for="summary_start_date">Start Date:</label>
                        <input type="date" name="summary_start_date" id="summary_start_date" class="form-control"
                            value="<?= isset($_POST['summary_start_date']) ? htmlspecialchars($_POST['summary_start_date']) : '' ?>">
                    </div>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <label for="summary_end_date">End Date:</label>
                        <input type="date" name="summary_end_date" id="summary_end_date" class="form-control"
                            value="<?= isset($_POST['summary_end_date']) ? htmlspecialchars($_POST['summary_end_date']) : '' ?>">
                    </div>
                    <div class="col-md-4 col-sm-12 mb-3 d-flex align-items-end">
                        <button type="submit" name="search_assessment_summary" class="btn btn-primary btn-block">
                            <i class="fas fa-chart-bar"></i> Generate Summary
                        </button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Assessment Category</th>
                                <th>Number of Patients</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $totalPatients = 0; // Initialize total count

                            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_assessment_summary'])) {
                                $summaryStartDate = $_POST['summary_start_date'];
                                $summaryEndDate = $_POST['summary_end_date'];

                                if (!empty($summaryStartDate) && !empty($summaryEndDate)) {
                                    $summaryQuery = "
                                        SELECT c.Assessment, COUNT(DISTINCT c.PatientID) as PatientCount
                                        FROM consultations c
                                        WHERE c.Date BETWEEN ? AND ?
                                        GROUP BY c.Assessment
                                        ORDER BY PatientCount DESC
                                    ";

                                    $summaryStmt = $connection->prepare($summaryQuery);
                                    $summaryStmt->bind_param("ss", $summaryStartDate, $summaryEndDate);
                                    $summaryStmt->execute();
                                    $summaryResult = $summaryStmt->get_result();

                                    if ($summaryResult->num_rows > 0) {
                                        while ($summary = $summaryResult->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($summary['Assessment']) . "</td>";
                                            echo "<td>" . htmlspecialchars($summary['PatientCount']) . "</td>";
                                            echo "</tr>";

                                            $totalPatients += $summary['PatientCount']; // Add to total
                                        }

                                        // Display total row
                                        echo "<tr class='font-weight-bold table-info'>";
                                        echo "<td>Total Number of Patients</td>";
                                        echo "<td>" . htmlspecialchars($totalPatients) . "</td>";
                                        echo "</tr>";

                                    } else {
                                        echo "<tr><td colspan='2' class='text-center'>No summary data found for selected date range</td></tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='2' class='text-center'>Please select both start and end dates</td></tr>";
                                }
                            } else {
                                echo "<tr><td colspan='2' class='text-center'>Select a date range to generate summary</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-info mt-3" onclick="printSummary()">
                    <i class="fas fa-print"></i> Print Summary
                </button>
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


        function printSummary() {
            var printArea = document.getElementById("assessment-summary");
            var printContents = printArea.innerHTML;
            var originalContents = document.body.innerHTML;

            // Create a new window or iframe for printing to isolate content
            var printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Assessment Summary</title>');
            // Include Bootstrap CSS for styling in print
            printWindow.document.write('<link rel="stylesheet" href="../assets/css/bootstrap.min.css">');
            printWindow.document.write('<style>');
            printWindow.document.write(`
                body { font-family: Arial, sans-serif; margin: 20px; }
                .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                .table th, .table td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
                .table thead th { background-color: #007bff; color: white; }
                .font-weight-bold { font-weight: bold; }
                .text-center { text-align: center; }
                .text-primary { color: #007bff !important; }
                .text-success { color: #28a745 !important; }
                .text-info { background-color: #d1ecf1 !important; } /* For total row */
                .d-none.d-print-block { display: block !important; }
                hr { border-top: 1px solid rgba(0,0,0,.1); margin-top: 1rem; margin-bottom: 1rem; }
                /* Hide buttons/forms in print view */
                button, input, form { display: none !important; }
            `);
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(printContents);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close(); // Close the print window after printing (optional)
        }

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