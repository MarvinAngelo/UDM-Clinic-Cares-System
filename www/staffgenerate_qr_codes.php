<?php
session_start();

// Check if the staff is logged in
if (!isset($_SESSION['staff_loggedin']) || $_SESSION['staff_loggedin'] !== true) {
    // Redirect to the staff login page with an error message
    header("Location: staffLogin.php?error=" . urlencode("Please log in to access this page."));
    exit();
}

// Include the QR Code library
require_once 'admin/phpqrcode/qrlib.php';

$servername = "localhost";
$username = "root";
$password = "";
$database = "clinic_data";

$connection = new mysqli($servername, $username, $password, $database);

if ($connection->connect_error) {
    die("Connection Failed: " . $connection->connect_error);
}

// Fetch all patient data (or specific data you want in the QR code)
// It's still using 'patients' table, as per previous context
$sql = "SELECT PatientID, Student_Num, FirstName, MiddleInitial, LastName, Program FROM patients ORDER BY Program ASC, LastName ASC, FirstName ASC";
$result = $connection->query($sql);

$patients_by_program = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $program = $row['Program'];
        if (!isset($patients_by_program[$program])) {
            $patients_by_program[$program] = [];
        }
        $patients_by_program[$program][] = $row;
    }
}

// Prepare statement for updating qr_code_id (from previous modification)
$update_stmt = $connection->prepare("UPDATE patients SET qr_code_id = ? WHERE PatientID = ?");
if ($update_stmt === false) {
    die("Prepare failed: " . htmlspecialchars($connection->error));
}
// 'si' indicates the types of the parameters: 's' for string (qr_code_id), 'i' for integer (PatientID)
$update_stmt->bind_param("si", $qrDataToStore, $patientIdToUpdate);

// The database connection is NOT closed here because it's needed for the update loop below.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate QR Codes</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
            box-sizing: border-box;
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
            background-color: #aad8e6;
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            padding-top: 111px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 999;
            box-sizing: border-box;
            overflow-y: auto;
            transition: width 0.3s ease-in-out;
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
            margin-top: 80px;
            width: calc(100% - 250px);
            box-sizing: border-box;
            min-height: calc(100vh - 80px);
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
        }

        .program-container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 30px;
        }

        .program-container h3 {
            color: #0066cc;
            margin-bottom: 20px;
            border-bottom: 2px solid #e3f2fd;
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer; /* Indicate clickable */
        }

        .program-container .search-bar {
            margin-bottom: 15px;
        }

        .program-container .search-bar input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
        }

        .student-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .student-table th, .student-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #dee2e6;
            text-align: left;
            vertical-align: middle;
        }

        .student-table th {
            background-color: #e3f2fd;
            color: #0066cc;
            font-weight: bold;
            white-space: nowrap; /* Prevent headers from wrapping too much */
        }

        .student-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .student-table .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
            padding: 5px 10px;
            font-size: 0.875rem;
            border-radius: 4px;
            white-space: nowrap; /* Prevent button text from wrapping */
        }

        .student-table .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }

        /* Collapse arrow styling */
        .collapse-toggle .fas {
            transition: transform 0.3s ease-in-out;
        }
        .collapse-toggle[aria-expanded="true"] .fas {
            transform: rotate(180deg);
        }

        /* Modal for QR Code Display */
        .qr-modal .modal-content {
            border-radius: 8px;
        }
        .qr-modal .modal-header {
            background-color: #20B2AA;
            color: white;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .qr-modal .modal-title {
            font-size: 1.5rem;
        }
        .qr-modal .modal-body {
            text-align: center;
            padding: 30px;
        }
        .qr-modal .modal-body img {
            max-width: 250px;
            height: auto;
            border: 1px solid #ddd;
            padding: 10px;
            background-color: white;
        }
        .qr-modal .modal-body p {
            margin-top: 15px;
            font-size: 1.1rem;
            color: #555;
        }

        /* Media queries for responsiveness */
        /* For large desktops and tablets (992px and up) */
        @media (min-width: 992px) {
            .main-content {
                margin-left: 250px;
                width: calc(100% - 250px);
            }
            .sidebar {
                width: 250px;
            }
        }

        /* For tablets (768px to 991px) */
        @media (max-width: 991.98px) {
            .sidebar {
                width: 0; /* Hidden by default on smaller screens */
                padding-top: 80px; /* Adjust for header height */
            }
            .sidebar.active {
                width: 250px; /* Show when active */
            }
            .sidebar-toggle-button {
                display: block; /* Show toggle button */
            }
            .main-content {
                margin-left: 0; /* No margin when sidebar is hidden */
                width: 100%;
                padding-top: 80px; /* Adjust for header height */
            }
            .main-content.sidebar-active {
                margin-left: 250px; /* Push content when sidebar is active */
            }
            .header {
                height: 60px; /* Slightly smaller header */
                padding-left: 70px; /* Make space for toggle button */
            }
            .header .logo {
                width: 40px; /* Smaller logo */
            }
            .header h1 {
                font-size: 1.5rem; /* Smaller title */
            }
        }

        /* For phones (max-width: 767.98px) */
        @media (max-width: 767.98px) {
            .program-container {
                padding: 15px; /* Reduce padding */
            }
            .program-container h3 {
                font-size: 1.2rem; /* Smaller program title */
            }
            .program-container small {
                font-size: 0.8rem; /* Smaller student count */
            }
            .student-table th, .student-table td {
                padding: 8px 5px; /* Smaller table cell padding */
                font-size: 0.85rem; /* Smaller table text */
            }
            .student-table .btn-info {
                padding: 3px 6px; /* Smaller button padding */
                font-size: 0.75rem; /* Smaller button text */
            }
            .qr-modal .modal-title {
                font-size: 1.2rem; /* Smaller modal title */
            }
            .qr-modal .modal-body img {
                max-width: 200px; /* Smaller QR image in modal */
            }
            .qr-modal .modal-body p {
                font-size: 1rem; /* Smaller text in modal */
            }
            .d-flex.justify-content-between.align-items-center.mb-4 {
                flex-direction: column; /* Stack elements */
                align-items: flex-start !important; /* Align to start */
            }
            .d-flex.justify-content-between.align-items-center.mb-4 h2 {
                margin-bottom: 10px; /* Space between title and button */
            }
            .no-print {
                width: 100%; /* Make print button full width */
                margin-top: 10px; /* Add space */
            }
        }

        /* For very small phones (max-width: 575.98px) - Bootstrap's 'xs' breakpoint */
        @media (max-width: 575.98px) {
            .header {
                padding: 0 10px; /* Even less padding */
                height: 50px;
            }
            .header .logo {
                width: 35px;
            }
            .header h1 {
                font-size: 1.3rem;
            }
            .sidebar-toggle-button {
                top: 10px;
                left: 10px;
                padding: 8px 12px;
            }
            .main-content {
                padding: 10px; /* Even less main content padding */
                margin-top: 60px; /* Adjust for smaller header */
            }
            .program-container {
                padding: 10px;
            }
            .student-table th, .student-table td {
                padding: 6px 3px; /* Minimal table padding */
                font-size: 0.8rem; /* Smallest table text */
            }
            .student-table .btn-info {
                padding: 2px 4px; /* Minimal button padding */
                font-size: 0.7rem; /* Smallest button text */
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
        <a href="stafffrontconsult.php">Consultation</a>
        <a href="staffrecordTable.php">Patient Records</a>
        <a href="staffgenerate_qr_codes.php" class="active">Generate QR Codes</a>
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
            <h2>Generated QR Codes by Program</h2>
            <form action="staffgenerate_qr_pdf.php" method="post" target="_blank">
                <button type="submit" class="btn btn-primary no-print">Print All QR Codes to PDF</button>
            </form>
        </div>

        <?php if (!empty($patients_by_program)): ?>
            <?php foreach ($patients_by_program as $program => $patients): ?>
                <?php $program_id = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $program)); ?>
                <div class="program-container">
                    <h3 data-toggle="collapse" href="#collapse<?php echo htmlspecialchars($program_id); ?>" role="button" aria-expanded="false" aria-controls="collapse<?php echo htmlspecialchars($program_id); ?>" class="collapse-toggle">
                        <?php echo htmlspecialchars($program); ?>
                        <small class="text-muted mr-2">(<?php echo count($patients); ?> Students)</small>
                        <span class="collapse-arrow"><i class="fas fa-chevron-right"></i> <span class="collapse-text">Expand</span></span>
                    </h3>
                    <div class="collapse" id="collapse<?php echo htmlspecialchars($program_id); ?>">
                        <div class="search-bar">
                            <input type="text" class="form-control" onkeyup="filterStudents(this, '<?php echo md5($program); ?>')" placeholder="Search students in <?php echo htmlspecialchars($program); ?>...">
                        </div>
                        <div class="table-responsive">
                            <table class="table student-table" id="table-<?php echo md5($program); ?>">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student ID</th>
                                        <th>Full Name</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($patients as $patient): ?>
                                        <?php
                                        // Data to encode in the QR code (PatientID is recommended for lookup)
                                        $qrData = "STUDENT-" . $patient['PatientID'];

                                        $qrFileName = 'admin/qrcodes/patient_' . $patient['PatientID'] . '.png';

                                    


                                        // Generate QR code if it doesn't exist or if you want to regenerate every time
                                        if (!file_exists($qrFileName)) {
                                            QRcode::png($qrData, $qrFileName, QR_ECLEVEL_L, 4, 2);
                                        }

                                        // Store the generated qrData in the database
                                        $qrDataToStore = $qrData;
                                        $patientIdToUpdate = $patient['PatientID'];

                                        // Execute the prepared statement to update the qr_code_id column
                                        // Using `execute()` with parameters bound earlier
                                        $update_stmt->execute();
                                        // You might want to add error checking here for the update_stmt->execute()
                                        // For example: if (!$update_stmt->execute()) { error_log("Update failed for " . $patient['PatientID'] . ": " . $update_stmt->error); }
                                        ?>
                                        <tr data-full-name="<?php echo htmlspecialchars($patient['FirstName'] . ' ' . $patient['LastName']); ?>" data-student-num="<?php echo htmlspecialchars($patient['Student_Num']); ?>">
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($patient['Student_Num']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['FirstName'] . " " . (!empty($patient['MiddleInitial']) ? $patient['MiddleInitial'] . " " : "") . $patient['LastName']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#qrCodeModal"
                                                        data-qr-image="<?php echo htmlspecialchars($qrFileName); ?>"
                                                        data-student-name="<?php echo htmlspecialchars($patient['FirstName'] . " " . $patient['LastName']); ?>"
                                                        data-student-id="<?php echo htmlspecialchars($patient['Student_Num']); ?>">
                                                    See QR Code
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info" role="alert">
                    No patients found to generate QR codes.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade qr-modal" id="qrCodeModal" tabindex="-1" role="dialog" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrCodeModalLabel">QR Code for <span id="modalStudentName"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <img id="modalQrImage" src="" alt="QR Code">
                    <p>Student ID: <span id="modalStudentId"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Close the prepared statement and database connection after all operations are complete
    if (isset($update_stmt)) {
        $update_stmt->close();
    }
    if (isset($connection)) {
        $connection->close();
    }
    ?>

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

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
    <script>
        // JavaScript for sidebar toggle
        function toggleSidebar() {
            var sidebar = document.getElementById("mySidebar");
            var mainContent = document.getElementById("mainContent");
            sidebar.classList.toggle("active");
            mainContent.classList.toggle("sidebar-active"); // Toggle class for main content
        }

        // Adjust main content margin on initial load based on sidebar state (for desktop)
        window.addEventListener('DOMContentLoaded', (event) => {
            if (window.innerWidth >= 992) { // Desktop view
                document.getElementById('mainContent').style.marginLeft = '250px';
                document.getElementById('mainContent').style.width = 'calc(100% - 250px)';
            }
        });

        // Add a listener to resize to handle orientation changes or window resizing
        window.addEventListener('resize', (event) => {
            if (window.innerWidth >= 992) {
                document.getElementById('mainContent').style.marginLeft = '250px';
                document.getElementById('mainContent').style.width = 'calc(100% - 250px)';
            } else {
                document.getElementById('mainContent').style.marginLeft = '0';
                document.getElementById('mainContent').style.width = '100%';
            }
        });

        // JavaScript for filtering students
        function filterStudents(input, tableIdSuffix) {
            const filter = input.value.toLowerCase();
            const table = document.getElementById('table-' + tableIdSuffix);
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) { // Start from 1 to skip table headers
                const fullName = tr[i].getAttribute('data-full-name').toLowerCase();
                const studentNum = tr[i].getAttribute('data-student-num').toLowerCase();

                if (fullName.includes(filter) || studentNum.includes(filter)) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }

        // JavaScript for QR Code Modal
        $('#qrCodeModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Button that triggered the modal
            var qrImage = button.data('qr-image'); // Extract info from data-* attributes
            var studentName = button.data('student-name');
            var studentId = button.data('student-id');

            var modal = $(this);
            modal.find('.modal-title #modalStudentName').text(studentName);
            modal.find('#modalQrImage').attr('src', qrImage);
            modal.find('#modalStudentId').text(studentId);
        });

        // JavaScript for collapse text and arrow rotation
        $(document).ready(function() {
            $('.collapse-toggle').each(function() {
                var $this = $(this);
                var targetId = $this.attr('href');
                var $target = $(targetId);
                var $arrow = $this.find('.fas');
                var $text = $this.find('.collapse-text');

                // Initial state: collapsed, so arrow is right and text is Expand
                // This state is set in the HTML directly now: <i class="fas fa-chevron-right"></i> <span class="collapse-text">Expand</span>
                // No need to set it here initially as it's already in the PHP output.

                $target.on('show.bs.collapse', function () {
                    $arrow.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                    $text.text('Collapse');
                }).on('hide.bs.collapse', function () {
                    $arrow.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                    $text.text('Expand');
                });
            });
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