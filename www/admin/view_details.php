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

$patientID_from_get = ""; // This is Student_Num from GET request
$patient = null;
$consultations = [];
$imagePath = "uploads/default.png"; // Default image path
$foundImage = false; // Flag to check if a specific image exists
$qrCodeImagePath = ""; // Initialize QR code image path

// No need to include generate_qr_code_script.php here,
// as your existing generate_qr_codes.php handles the generation.

if (isset($_GET['id'])) {
    $patientID_from_get = $connection->real_escape_string($_GET['id']);

    // Fetch patient details using Student_Num, including qr_code_id and Address
    $patientQuery = "SELECT *, qr_code_id, Address FROM patients WHERE Student_Num = '$patientID_from_get'";
    $patientResult = $connection->query($patientQuery);

    if ($patientResult->num_rows > 0) {
        $patient = $patientResult->fetch_assoc();
        $p_id_actual = $patient['PatientID']; // Get the actual PatientID from the fetched patient for consultations

        // Determine image path
        foreach (['png', 'jpg', 'jpeg'] as $ext) {
            $tempPath = "uploads/patient_" . htmlspecialchars($p_id_actual) . "." . $ext;
            if (file_exists($tempPath)) {
                $imagePath = $tempPath;
                $foundImage = true;
                break;
            }
        }
        // If no specific image is found, $imagePath remains "uploads/default.png"

        // Determine QR code image path based on your generate_qr_codes.php's naming convention
        // Use qr_code_id first, fallback to Student_Num for the filename
        $qrCodeIdentifier = $patient['qr_code_id'] ?? $patient['Student_Num'];
        $qrCodeFileName = "patient_" . htmlspecialchars($qrCodeIdentifier) . ".png";
        $qrCodeFilePath = "qrcodes/" . $qrCodeFileName;

        // Check if the QR code file actually exists.
        // If not, it means your generate_qr_codes.php has not created it yet for this patient,
        // or there was an issue. We'll use a default placeholder.
        if (file_exists($qrCodeFilePath)) {
            $qrCodeImagePath = $qrCodeFilePath;
        } else {
            // Fallback if the QR code file does not exist
            $qrCodeImagePath = "qrcodes/patient_"; // Make sure you have this file!
        }


        // Fetch consultations for this patient using actual PatientID
        $consultationQuery = "SELECT * FROM consultations WHERE PatientID = '$p_id_actual' ORDER BY Date DESC, TimeIn DESC";
        $consultationResult = $connection->query($consultationQuery);

        if ($consultationResult->num_rows > 0) {
            while ($row = $consultationResult->fetch_assoc()) {
                $consultations[] = $row;
            }
        }
    } else {
        // Patient not found, redirect or show error (handle gracefully)
        echo "<script>alert('Patient not found.'); window.location.href='viewpatient.php';</script>";
        exit();
    }
} else {
    // No Student Number provided, redirect or show error
    echo "<script>alert('No Student Number provided.'); window.location.href='viewpatient.php';</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f0f8ff; /* Light blue background */
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

        .details-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-wrap: wrap; /* Allow wrapping for smaller screens */
            gap: 20px;
        }

        .patient-photo-container {
            flex: 0 0 200px; /* Fixed width for photo and buttons */
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px; /* Space between photo and buttons */
        }

        .patient-photo {
            width: 200px;
            height: 200px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f9f9f9;
        }

        .patient-photo img {
            max-width: 100%;
            max-height: 100%;
            display: block;
        }

        .patient-info {
            flex: 1; /* Takes remaining space */
            /* Ensure text alignment is left within the info section */
            text-align: left; /* Added for consistent alignment */
        }

        .patient-info h2 {
            color: #0066cc;
            margin-bottom: 20px;
        }

        .patient-info p {
            margin-bottom: 10px;
        }

        /* Added for column layout of patient details */
        .patient-details-column {
            padding-right: 15px; /* Adjust as needed for spacing between columns */
        }
        .patient-details-column:last-child {
            padding-right: 0;
            padding-left: 15px; /* Adjust as needed for spacing between columns */
        }

        .section-title {
            color: #0066cc;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .no-print {
            margin-top: 20px;
            text-align: center;
        }

        /* Print styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .details-container, .details-container * {
                visibility: visible;
            }
            .details-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                border: none;
                padding: 0;
                margin-top: 0;
            }
            .no-print {
                display: none;
            }
            .header, .sidebar, .sidebar-toggle-button {
                display: none;
            }
            /* Ensure images are printed */
            .patient-photo img {
                display: block;
                max-width: 150px; /* Adjust size for print if needed */
                height: auto;
                margin-bottom: 10px;
            }
        }

        /* Media queries for responsiveness */
        @media (max-width: 992px) {
            .sidebar {
                width: 0; /* Collapse sidebar on smaller screens */
                overflow: hidden; /* Hide content that overflows */
                transition: width 0.3s ease-in-out; /* Smooth transition */
            }

            .sidebar-toggle-button {
                display: block; /* Show the button */
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

            .main-content {
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

            .sidebar.active {
                width: 250px; /* Expand sidebar */
            }
            /* Stack patient info columns on smaller screens */
            .patient-details-column {
                padding-right: 15px;
                padding-left: 15px;
            }
            .patient-details-column:last-child {
                padding-left: 15px;
            }
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.5rem;
            }
            .details-container {
                flex-direction: column; /* Stack elements vertically */
                align-items: center; /* Center items when stacked */
                padding: 20px;
            }
            .patient-photo-container {
                width: 150px; /* Adjust width for smaller screens */
            }
            .patient-photo {
                width: 150px;
                height: 150px;
            }
            .patient-info {
                text-align: center;
            }
            table {
                font-size: 0.9em;
            }
            th, td {
                padding: 6px;
            }
        }

        /* Styles for Camera Modal */
        #cameraModal .modal-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        #cameraModal video {
            width: 100%;
            max-width: 400px; /* Limit video width */
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #000; /* Black background for video feed */
        }
        #cameraModal canvas {
            display: none; /* Hide canvas by default */
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        #cameraModal .shutter-button {
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 50%; /* Make it circular */
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: background-color 0.2s ease;
        }
        #cameraModal .shutter-button:hover {
            background-color: #0056b3;
        }

        /* Styles for Magnify Modal */
        #magnifyImageModal .modal-body {
            text-align: center;
        }
        #magnifyImageModal img {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        /* Styles for ID Card Modal */
        #idCardModal .modal-dialog {
            max-width: fit-content; /* Allow modal to shrink to content size */
        }
        #idCardModal .modal-content {
            border: none;
            background-color: transparent;
            box-shadow: none;
        }
        #idCardModal .modal-body {
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Flipping Card Container */
        .id-card-modal-content {
            width: 3.375in; /* Standard ID card width */
            height: 2.125in; /* Standard ID card height */
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 8px;
            overflow: hidden; /* Important for containing children */
            background-color: transparent; /* Changed to transparent for flip effect */
            position: relative; /* For z-index and perspective */
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            perspective: 1000px; /* For 3D effect */
        }

        .id-card-modal-inner {
            position: relative;
            width: 100%;
            height: 100%;
            text-align: center;
            transition: transform 0.6s;
            transform-style: preserve-3d; /* Key for 3D flip */
        }

        .id-card-modal-inner.flipped {
            transform: rotateY(180deg);
        }

        .id-card-front, .id-card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            -webkit-backface-visibility: hidden; /* Hide back of the element when flipped */
            backface-visibility: hidden;
            border-radius: 8px;
            background-color: #f7f7f7; /* Background for each side */
            padding: 5px; /* Padding for content inside each side */
            display: flex; /* For content layout */
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }

        .id-card-back {
            transform: rotateY(180deg); /* Start rotated for the back side */
            flex-direction: column; /* Stack QR code and text */
        }

        /* Front Side Elements */
        .id-card-front .header-section {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 1cm; /* 1cm height */
            background-color: #20B2AA; /* Teal background */
            color: white; /* White font */
            display: flex;
            align-items: center; /* Vertically center */
            justify-content: flex-start; /* Align to the start (left) */
            padding: 0 5px;
            box-sizing: border-box;
            font-size: 0.6em; /* Adjust font size */
            font-weight: bold;
        }

        .id-card-front .header-section img {
            height: 0.8cm; /* Adjust logo size */
            margin-right: 5px; /* Space between logo and text */
        }

        .id-card-front .header-text {
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Align text to the left */
            justify-content: center;
            line-height: 1.1;
        }
        .id-card-front .header-text span:first-child {
            font-size: 1.1em; /* "UDM Clinic Cares" slightly larger */
        }
        .id-card-front .header-text span:last-child {
            font-size: 0.9em; /* "Medical ID Card" slightly smaller */
        }


        .id-card-front .photo-container {
            flex-shrink: 0; /* Don't allow shrinking */
            width: 1in; /* Photo ID width */
            height: 1in; /* Photo ID height */
            border: 1px solid #eee;
            border-radius: 5px;
            overflow: hidden;
            margin-right: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #fff;
            position: absolute; /* Position relative to .id-card-front */
            top: calc(1cm + 0.5cm); /* Below header with 0.5cm margin */
            left: 5px;
        }
        .id-card-front .photo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .id-card-front .details-mid {
            flex-grow: 1; /* Take up available space */
            font-size: 0.7em;
            line-height: 1.2;
            color: #333;
            text-align: left; /* Ensure details are left-aligned */
            position: absolute; /* Position relative to .id-card-front */
            top: calc(1cm + 0.4cm); /* Adjusted to move slightly up */
            left: calc(5px + 1in + 8px); /* Right of photo container with margin */
            right: 5px;
            bottom: 5px; /* Allow vertical growth */
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }
        .id-card-front .details-mid p {
            margin: 0;
            word-wrap: break-word; /* Ensure text wraps */
        }
        .id-card-front .details-mid strong {
            color: #004b99;
        }

        /* Back Side Elements */
        .id-card-back .qr-container {
            width: 1.5in; /* QR Code width for back side */
            height: 1.5in; /* QR Code height for back side */
            border: 1px solid #eee;
            padding: 2px;
            background-color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 10px; /* Space between QR and back text */
        }
        .id-card-back .qr-container img {
            width: 100%;
            height: 100%;
            object-fit: contain; /* Ensure QR code fits without cropping */
        }

        .id-card-back .back-text {
            font-size: 0.6em;
            text-align: center;
            color: #555;
            line-height: 1.3;
        }
        .id-card-back .back-text p {
            margin: 0;
        }

        /* General footer for both sides if needed, otherwise specific to front */
        .id-card-modal-content .footer-section {
            position: absolute;
            bottom: 5px;
            width: 100%;
            text-align: center;
            font-size: 0.5em;
            color: #666;
        }

        /* Print styles for both sides */
        @media print {
            body * {
                visibility: hidden;
            }
            #idCardPrintArea, #idCardPrintArea * {
                visibility: visible;
            }
            #idCardPrintArea {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                border: none;
                padding: 0;
                margin-top: 0;
                display: flex; /* Arrange front and back for printing */
                flex-direction: row; /* Place them side by side */
                justify-content: center; /* Center horizontally */
                align-items: center; /* Center vertically */
                height: 100vh; /* Take full viewport height for centering */
                gap: 20px; /* Space between front and back */
            }

            .id-card-modal-content {
                box-shadow: none;
                border: 1px solid #ccc; /* Add border back for print */
                background-color: transparent; /* Reset background for print */
                perspective: none; /* Disable 3D for print */
                width: 3.375in; /* Standard ID card width */
                height: 2.125in; /* Standard ID card height */
                margin: 0; /* Remove margin for side-by-side */
            }

            .id-card-modal-inner {
                transform: none !important; /* Prevent flipping during print */
                transform-style: flat;
            }

            .id-card-front, .id-card-back {
                position: static; /* Reset positioning for print flow */
                backface-visibility: visible; /* Ensure both sides are visible */
                border-radius: 8px; /* Maintain border radius */
                background-color: #f7f7f7; /* Explicit background for print */
                display: flex;
                flex-direction: row; /* Keep row for front layout */
                align-items: center;
                justify-content: center;
                padding: 5px;
                box-sizing: border-box;
            }

            .id-card-back {
                transform: none; /* Reset rotation for print */
                flex-direction: column; /* Keep column for back layout */
            }

            /* Ensure header and details positioning within the print context */
            .id-card-front .header-section {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 1cm;
                padding: 0 5px;
                box-sizing: border-box;
                justify-content: flex-start; /* Align to the start (left) for print */
            }
            .id-card-front .photo-container {
                position: absolute;
                top: calc(1cm + 0.5cm); /* Consistent with screen */
                left: 5px;
            }
            .id-card-front .details-mid {
                position: absolute;
                top: calc(1cm + 0.4cm); /* Consistent with screen and adjusted */
                left: calc(5px + 1in + 8px);
                right: 5px;
                bottom: 5px;
            }

            .id-card-front .photo-container,
            .id-card-back .qr-container {
                border: 1px solid #eee; /* Ensure borders are visible in print */
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
        <a href="recordTable.php">Patient Records</a>
        <a href="generate_qr_codes.php" >Generate QR Codes</a>
        <a href="medinventory.php">MedInventory</a>
        <a href="medilog.php">Medical Logs</a>
        <a href="staff_creation.php">Manage Staff</a>
        <a href="logout.php">Logout</a>
        <div class="sidebar-footer text-center">
            <p>UDM Clinic Cares System</p>
        </div>
    </div>

    <button class="sidebar-toggle-button" onclick="toggleSidebar()">&#9776;</button>

    <div class="header d-flex align-items-center">
        <img src="images/UDMCLINIC_LOGO.png" alt="Logo" class="logo">
        <h1>UDM Clinic Cares</h1>
    </div>

    <div class="main-content">
        <div class="details-container">
            <div class="patient-photo-container">
                <div class="patient-photo">
                    <?php
                    // The $imagePath variable is now determined above in the PHP section
                    echo "<img id='patientPhoto' src='" . htmlspecialchars($imagePath) . "' alt='Patient Photo'>";
                    ?>
                </div>
                <button type="button" class="btn btn-dark mt-2" data-toggle="modal" data-target="#magnifyImageModal" onclick="loadMagnifiedImage()">
                    &#128269; Enlarge Photo
                </button>
                <label for="imageInput" class="btn btn-secondary mt-3">Upload/Change Photo</label>
                <form id="uploadForm" enctype="multipart/form-data" style="display: none;">
                    <input type="file" id="imageInput" name="image" accept="image/png, image/jpeg" onchange="uploadImage('<?php echo $patientID_from_get; ?>')">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($patientID_from_get); ?>">
                </form>
                <button type="button" class="btn btn-info mt-3" data-toggle="modal" data-target="#cameraModal">Take a Picture</button>
                <?php if ($foundImage) : // Only show delete button if an image exists ?>
                    <button type="button" class="btn btn-danger mt-3" onclick="deleteImage('<?php echo $patientID_from_get; ?>')">Delete Photo</button>
                <?php endif; ?>
            </div>
            <div class="patient-info">
                <h2>Patient Details</h2>
                <div class="row">
                    <div class="col-md-6 patient-details-column">
                        <p><strong>Full Name:</strong> <?php echo htmlspecialchars($patient['FirstName']) . " " . htmlspecialchars($patient['MiddleInitial']) . " " . htmlspecialchars($patient['LastName']); ?></p>
                        <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient['PatientID']); ?></p>
                        <p><strong>Student Number:</strong> <?php echo htmlspecialchars($patientID_from_get); ?></p>
                        <p><strong>Program:</strong> <?php echo htmlspecialchars($patient['Program']); ?></p>
                        <p><strong>Year Level:</strong> <?php echo htmlspecialchars($patient['yearLevel']); ?></p>
                        <p><strong>Sex:</strong> <?php echo htmlspecialchars($patient['Sex']); ?></p>
                        <p><strong>Age:</strong> <?php echo htmlspecialchars($patient['age']); ?></p>
                    </div>
                    <div class="col-md-6 patient-details-column">
                        <p><strong>Civil Status:</strong> <?php echo htmlspecialchars($patient['civil_status']); ?></p>
                        <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($patient['ContactNumber']); ?></p>
                        <p><strong>Emergency Contact Number:</strong> <?php echo htmlspecialchars($patient['emergency_number']); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($patient['Address']); ?></p>
                        <p><strong>Height:</strong> <?php echo htmlspecialchars($patient['height']); ?></p>
                        <p><strong>Weight:</strong> <?php echo htmlspecialchars($patient['weight']); ?></p>
                        <p><strong>Special Cases:</strong> <?php echo htmlspecialchars($patient['specialCases']); ?></p>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <button type="button" class="btn btn-success" onclick="showIdCardModal()">See ID Card</button>
                </div>
            </div>
        </div>

        <h3 class="section-title">Consultation History</h3>
        <?php if (!empty($consultations)) : ?>
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
                            <th>Medicine Given</th>
                            <th>Quantity Given</th>
                            <th>Plan</th>
                            <th>Follow Up Date</th>
                            <th>Saved By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultations as $consultation) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($consultation['Date']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['TimeIn']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['TimeOut']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['Subjective']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['Objective']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['MedicineGiven']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['QuantityGiven']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['Plan']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['PlanDate']); ?></td>
                                <td><?php htmlspecialchars($consultation['SavedBy']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <p>No consultation history found for this patient.</p>
        <?php endif; ?>

        <div class="no-print mt-4 text-center">
            <button class="btn btn-primary" onclick="window.print()">Print Patient Details</button>
            <a href="viewpatient.php" class="btn btn-secondary">Back to Patients</a>
        </div>
    </div>

    <div class="modal fade" id="cameraModal" tabindex="-1" role="dialog" aria-labelledby="cameraModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cameraModalLabel">Take Patient Photo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="stopCamera()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <video id="video" width="640" height="480" autoplay></video>
                    <canvas id="canvas" width="640" height="480"></canvas>
                    <button id="snap" class="shutter-button">&#128247;</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="stopCamera()">Close</button>
                    <button type="button" class="btn btn-primary" id="savePhoto">Save Photo</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="magnifyImageModal" tabindex="-1" role="dialog" aria-labelledby="magnifyImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="magnifyImageModalLabel">Patient Photo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <img id="magnifiedImage" src="" alt="Enlarged Patient Photo">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="idCardModal" tabindex="-1" role="dialog" aria-labelledby="idCardModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="idCardModalLabel">Patient Medical ID Card</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="id-card-modal-content">
                        <div class="id-card-modal-inner" id="idCardFlipper">
                            <div class="id-card-front">
                                </div>
                            <div class="id-card-back">
                                </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="flipIdCard()">Flip ID Card</button>
                    <button type="button" class="btn btn-primary" id="printIdCard">Print ID Card</button>
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

    <script src="../assets/js/jquery-3.5.1.min.js"></script>
    <script src="../assets/js/popper.min.js"></script>
    <script src="../assets/js/bootstrap.min.js"></script>
    <script>
        // Pass patient data from PHP to JavaScript
        const patientData = {
            FirstName: <?php echo json_encode($patient['FirstName']); ?>,
            MiddleInitial: <?php echo json_encode($patient['MiddleInitial']); ?>,
            LastName: <?php echo json_encode($patient['LastName']); ?>,
            PatientID: <?php echo json_encode($patient['PatientID']); ?>,
            Student_Num: <?php echo json_encode($patient['Student_Num']); ?>,
            Program: <?php echo json_encode($patient['Program']); ?>,
            yearLevel: <?php echo json_encode($patient['yearLevel']); ?>,
            Sex: <?php echo json_encode($patient['Sex']); ?>,
            age: <?php echo json_encode($patient['age']); ?>,
            ContactNumber: <?php echo json_encode($patient['ContactNumber']); ?>,
            emergency_number: <?php echo json_encode($patient['emergency_number']); ?>,
            specialCases: <?php echo json_encode($patient['specialCases']); ?>,
            Address: <?php echo json_encode($patient['Address']); ?>,
            qr_code_id: <?php echo json_encode($patient['qr_code_id']); ?>
        };
        const patientImagePath = <?php echo json_encode($imagePath); ?>;
        // Re-added qrCodeImagePath to JavaScript from PHP
        const qrCodeImagePath = <?php echo json_encode($qrCodeImagePath); ?>;


        // JavaScript for sidebar toggle
        function toggleSidebar() {
            var sidebar = document.getElementById("mySidebar");
            sidebar.classList.toggle("active");
        }

        // --- Image Upload Functionality ---
        function uploadImage(patientStudentNum) {
            const input = document.getElementById('imageInput');
            if (input.files.length === 0) {
                alert('Please select an image to upload.');
                return;
            }

            const formData = new FormData();
            formData.append('image', input.files[0]);
            formData.append('id', patientStudentNum); // Pass Student_Num

            fetch('upload_image.php', {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') {
                    document.getElementById('patientPhoto').src = data.imagePath + '?t=' + new Date().getTime(); // Cache-busting
                    // Re-render the delete button to ensure it's there or refreshed
                    const patientPhotoContainer = document.querySelector('.patient-photo-container');
                    let deleteBtn = patientPhotoContainer.querySelector('.btn-danger[onclick^="deleteImage"]');
                    if (!deleteBtn) {
                        deleteBtn = document.createElement('button');
                        deleteBtn.type = 'button';
                        deleteBtn.className = 'btn btn-danger mt-3';
                        deleteBtn.textContent = 'Delete Photo';
                        deleteBtn.setAttribute('onclick', `deleteImage('${patientStudentNum}')`);
                        patientPhotoContainer.appendChild(deleteBtn);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while uploading the image.');
            });
        }

        // --- Camera Functionality ---
        let stream; // To hold the media stream
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const context = canvas.getContext('2d');
        const snapButton = document.getElementById('snap');
        const savePhotoButton = document.getElementById('savePhoto');
        const patientStudentNum = '<?php echo $patientID_from_get; ?>'; // Get patient's Student_Num from PHP

        // Function to start camera
        async function startCamera() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                video.play();
                canvas.style.display = 'none'; // Hide canvas when video is playing
                video.style.display = 'block'; // Show video
                savePhotoButton.style.display = 'none'; // Hide save button initially
            } catch (err) {
                console.error("Error accessing camera: ", err);
                alert("Could not access camera. Please ensure you have a webcam and granted permissions.");
            }
        }

        // Function to stop camera
        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                video.srcObject = null;
            }
        }

        // When the modal is shown, start the camera
        $('#cameraModal').on('shown.bs.modal', function () {
            startCamera();
        });

        // When the modal is hidden, stop the camera
        $('#cameraModal').on('hidden.bs.modal', function () {
            stopCamera();
        });

        // Event listener for shutter button
        snapButton.addEventListener('click', () => {
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            video.style.display = 'none'; // Hide video after capture
            canvas.style.display = 'block'; // Show captured image on canvas
            savePhotoButton.style.display = 'block'; // Show save button
        });

        // Event listener for save photo button
        savePhotoButton.addEventListener('click', () => {
            const imageData = canvas.toDataURL('image/png'); // Get image data as base64 PNG

            const formData = new FormData();
            formData.append('imageData', imageData);
            formData.append('id', patientStudentNum); // Pass Student_Num

            fetch('upload_image.php', {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') {
                    document.getElementById('patientPhoto').src = data.imagePath + '?t=' + new Date().getTime(); // Update photo
                    $('#cameraModal').modal('hide'); // Close modal
                    stopCamera(); // Stop camera stream
                    // Re-render the delete button after a successful capture
                    const patientPhotoContainer = document.querySelector('.patient-photo-container');
                    let deleteBtn = patientPhotoContainer.querySelector('.btn-danger[onclick^="deleteImage"]');
                    if (!deleteBtn) {
                        deleteBtn = document.createElement('button');
                        deleteBtn.type = 'button';
                        deleteBtn.className = 'btn btn-danger mt-3';
                        deleteBtn.textContent = 'Delete Photo';
                        deleteBtn.setAttribute('onclick', `deleteImage('${patientStudentNum}')`);
                        patientPhotoContainer.appendChild(deleteBtn);
                    }
                }
            })
            .catch(error => {
                console.error('Error saving photo:', error);
                alert('An error occurred while saving the photo.');
            });
        });

        // --- Delete Image Functionality ---
        function deleteImage(patientStudentNum) {
            if (confirm('Are you sure you want to delete this patient\'s photo?')) {
                const formData = new FormData();
                formData.append('id', patientStudentNum);
                formData.append('action', 'delete_image'); // Indicate delete action

                fetch('upload_image.php', {
                    method: 'POST',
                    body: formData,
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === 'success') {
                        document.getElementById('patientPhoto').src = 'uploads/default.png?t=' + new Date().getTime(); // Set to default image
                        // Optionally remove the delete button if no image exists
                        const deleteBtn = document.querySelector('.patient-photo-container .btn-danger[onclick^="deleteImage"]');
                        if (deleteBtn) {
                            deleteBtn.remove();
                        }
                    }
                })
            .catch(error => {
                console.error('Error deleting image:', error);
                alert('An error occurred while deleting the image.');
            });
            }
        }

        // --- Magnify Image Functionality ---
        function loadMagnifiedImage() {
            const patientPhotoSrc = document.getElementById('patientPhoto').src;
            document.getElementById('magnifiedImage').src = patientPhotoSrc;
        }

        // --- ID Card Modal Functionality ---
        function showIdCardModal() {
            const idCardFrontDiv = $('#idCardModal .id-card-front');
            const idCardBackDiv = $('#idCardModal .id-card-back');
            idCardFrontDiv.empty(); // Clear previous content
            idCardBackDiv.empty(); // Clear previous content

            const photoSrc = patientImagePath;
            // The QR code image path is now directly passed from PHP
            const qrImgSrc = qrCodeImagePath + '?t=' + new Date().getTime(); // Add cache-busting for updateability

            // Build the FRONT ID card HTML structure
            const idCardFrontHtml = `
                <div class="header-section">
                    <img src="images/UDMCLINIC_LOGO.png" alt="Logo">
                    <div class="header-text">
                        <span>UDM Clinic Cares</span>
                        <span>Medical ID Card</span>
                    </div>
                </div>
                <div class="photo-container">
                    <img src="${photoSrc}" alt="Patient Photo">
                </div>
                <div class="details-mid">
                    <p><strong>Name:</strong> ${patientData.FirstName} ${patientData.MiddleInitial ? patientData.MiddleInitial + ' ' : ''}${patientData.LastName}</p>
                    <p><strong>Student No:</strong> ${patientData.Student_Num}</p>
                    <p><strong>Program:</strong> ${patientData.Program}</p>
                    <p><strong>Year Level:</strong> ${patientData.yearLevel}</p>
                    <p><strong>Contact Number:</strong> ${patientData.ContactNumber}</p>
                    <p><strong>Emergency Number:</strong> ${patientData.emergency_number}</p>
                    <p><strong>Address:</strong> ${patientData.Address}</p>
                </div>
            `;
            idCardFrontDiv.html(idCardFrontHtml);

            // Build the BACK ID card HTML structure
            const idCardBackHtml = `
                <div class="qr-container">
                    <img src="${qrImgSrc}" alt="QR Code">
                </div>
                <div class="back-text">
                    <p>This ID is for medical purposes only.</p>
                    <p>In case of emergency, please contact the provided emergency number.</p>
                    <p>UDM Clinic Contact: [Clinic Contact Number here]</p>
                </div>
            `;
            idCardBackDiv.html(idCardBackHtml);

            // Reset flip state to show front initially
            $('#idCardFlipper').removeClass('flipped');
            $('#idCardModal').modal('show');
        }

        function flipIdCard() {
            $('#idCardFlipper').toggleClass('flipped');
        }

        // Print functionality for the modal content
        $('#printIdCard').on('click', function() {
            const idCardFrontContent = $('#idCardModal .id-card-front').html();
            const idCardBackContent = $('#idCardModal .id-card-back').html();

            const printContent = `
                <div id="idCardPrintArea">
                    <div class="id-card-modal-content print-front">
                        ${idCardFrontContent}
                    </div>
                    <div class="id-card-modal-content print-back">
                        ${idCardBackContent}
                    </div>
                </div>
            `;

            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Print ID Card</title>');
            // Copy relevant styles for the ID card to the print window
            printWindow.document.write(`
                <style>
                    body {
                        margin: 0;
                        padding: 0;
                        font-family: Arial, sans-serif;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh; /* Ensure it takes full height for centering */
                    }
                    #idCardPrintArea {
                        display: flex;
                        flex-direction: row; /* Arrange side by side */
                        gap: 20px; /* Space between the two cards */
                        justify-content: center;
                        align-items: center;
                        width: auto;
                    }
                    .id-card-modal-content {
                        width: 3.375in;
                        height: 2.125in;
                        box-sizing: border-box;
                        border: 1px solid #ccc;
                        border-radius: 8px;
                        overflow: hidden;
                        background-color: #f7f7f7;
                        position: relative;
                        box-shadow: none;
                        flex-shrink: 0; /* Prevent shrinking when side-by-side */
                    }
                    .id-card-modal-content.print-front,
                    .id-card-modal-content.print-back {
                        /* Ensure these specifically apply when printing */
                        display: flex; /* Activate flex for internal layout */
                        flex-direction: row; /* Default for front side */
                        align-items: center;
                        justify-content: flex-start; /* Align header content to start */
                        padding: 5px;
                        box-sizing: border-box;
                    }

                    /* Specific styles for elements within the printed ID card */
                    .id-card-modal-content.print-front .header-section {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 1cm;
                        background-color: #20B2AA !important; /* Added !important */
                        color: white !important; /* Added !important */
                        display: flex;
                        align-items: center;
                        justify-content: flex-start;
                        padding: 0 5px;
                        box-sizing: border-box;
                        font-size: 0.6em;
                        font-weight: bold;
                        z-index: 10;
                    }
                    .id-card-modal-content.print-front .header-section img {
                        height: 0.8cm !important; /* Added !important */
                        margin-right: 5px;
                    }
                    .id-card-modal-content.print-front .header-text {
                        display: flex;
                        flex-direction: column;
                        align-items: flex-start;
                        justify-content: center;
                        line-height: 1.1;
                    }
                    .id-card-modal-content.print-front .header-text span:first-child { font-size: 1.1em; }
                    .id-card-modal-content.print-front .header-text span:last-child { font-size: 0.9em; }

                    .id-card-modal-content.print-front .photo-container {
                        position: absolute;
                        top: calc(1cm + 0.5cm);
                        left: 5px;
                        width: 1in;
                        height: 1in;
                        border: 1px solid #eee;
                        border-radius: 5px;
                        overflow: hidden;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        background-color: #fff;
                    }
                    .id-card-modal-content.print-front .photo-container img {
                        width: 100%;
                        height: 100%;
                        object-fit: cover;
                    }

                    .id-card-modal-content.print-front .details-mid {
                        position: absolute;
                        top: calc(1cm + 0.4cm);
                        left: calc(5px + 1in + 8px);
                        right: 5px;
                        bottom: 5px;
                        font-size: 0.7em;
                        line-height: 1.2;
                        color: #333;
                        text-align: left;
                        display: flex;
                        flex-direction: column;
                        justify-content: flex-start;
                    }
                    .id-card-modal-content.print-front .details-mid p { margin: 0; word-wrap: break-word; }
                    .id-card-modal-content.print-front .details-mid strong { color: #004b99; }


                    .id-card-modal-content.print-back {
                        flex-direction: column;
                    }
                    .id-card-modal-content.print-back .qr-container {
                        width: 1.5in;
                        height: 1.5in;
                        border: 1px solid #eee;
                        padding: 2px;
                        background-color: #fff;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        margin-bottom: 10px;
                    }
                    .id-card-modal-content.print-back .qr-container img {
                        width: 100%;
                        height: 100%;
                        object-fit: contain;
                    }
                    .id-card-modal-content.print-back .back-text {
                        font-size: 0.6em;
                        text-align: center;
                        color: #555;
                        line-height: 1.3;
                    }
                    .id-card-modal-content.print-back .back-text p { margin: 0; }
                </style>
            `);
            printWindow.document.write('</head><body>');
            printWindow.document.write(printContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            // printWindow.close(); // Keep open for debugging if needed, otherwise uncomment
        });

        // JavaScript for chatbot button and popover// Chatbot Popover Functions
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