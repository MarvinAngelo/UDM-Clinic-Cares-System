<?php
session_start();

require_once 'backup.php'; // Adjust path if backup.php is in a different directory
// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Redirect to the login page with an error message
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit();
}

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$database = "clinic_data";

// Create connection
$connection = new mysqli($servername, $username, $password, $database);

// Check connection
if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Initialize a variable to hold messages
$notification_message = "";
$notification_type = ""; // success or danger

// Helper function to check for MySQL "zero date"
function isZeroDate($date_string) {
    // Check for empty string, '0000-00-00 00:00:00', or '0000-00-00'
    return (empty($date_string) || $date_string === '0000-00-00 00:00:00' || $date_string === '0000-00-00');
}

// Handle adding new medicine
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_medicine'])) {
    $medicine_name = $connection->real_escape_string($_POST['medicine_name']);
    $quantity = (int)$_POST['medicine_quantity'];
    $unit = $connection->real_escape_string($_POST['medicine_unit']);
    $expiration_date = !empty($_POST['expiration_date']) ? $connection->real_escape_string($_POST['expiration_date']) : null;

    // Use medicine_name column and include date_added
    $stmt = $connection->prepare("INSERT INTO medicine_inventory (medicine_name, quantity, unit, expiration_date, date_added) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("siss", $medicine_name, $quantity, $unit, $expiration_date);
        if ($stmt->execute()) {
            $notification_message = "Medicine added successfully!";
            $notification_type = "success";
        } else {
            $notification_message = "Error adding medicine: " . $stmt->error;
            $notification_type = "danger";
        }
        $stmt->close();
    } else {
        $notification_message = "Error preparing statement: " . $connection->error;
        $notification_type = "danger";
    }
}

// Handle dispensing medicine (decreasing quantity)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['dispense_medicine'])) {
    $medicine_id = (int)$_POST['dispense_medicine_id'];
    $dispense_quantity = (int)$_POST['dispense_quantity'];

    // First, get current quantity using medicine_id
    $current_quantity = 0;
    $stmt_check = $connection->prepare("SELECT quantity FROM medicine_inventory WHERE medicine_id = ?");
    if ($stmt_check) {
        $stmt_check->bind_param("i", $medicine_id);
        $stmt_check->execute();
        $res = $stmt_check->get_result();
        if ($row = $res->fetch_assoc()) {
            $current_quantity = $row['quantity'];
        }
        $stmt_check->close();
    }

    if ($dispense_quantity > 0 && $current_quantity >= $dispense_quantity) {
        // Decrease quantity using medicine_id
        $stmt_update = $connection->prepare("UPDATE medicine_inventory SET quantity = quantity - ? WHERE medicine_id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("ii", $dispense_quantity, $medicine_id);
            if ($stmt_update->execute()) {
                $notification_message = "Medicine dispensed successfully!";
                $notification_type = "success";
            } else {
                $notification_message = "Error dispensing medicine: " . $stmt_update->error;
                $notification_type = "danger";
            }
            $stmt_update->close();
        } else {
            $notification_message = "Error preparing update statement: " . $connection->error;
            $notification_type = "danger";
        }
    } elseif ($dispense_quantity <= 0) {
        $notification_message = "Dispense quantity must be greater than zero.";
        $notification_type = "danger";
    } else {
        $notification_message = "Insufficient stock to dispense " . $dispense_quantity . " units.";
        $notification_type = "danger";
    }
}

// Handle deleting medicine
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_medicine'])) {
    $medicine_id = (int)$_POST['delete_medicine_id'];

    $stmt = $connection->prepare("DELETE FROM medicine_inventory WHERE medicine_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $medicine_id);
        if ($stmt->execute()) {
            $notification_message = "Medicine deleted successfully!";
            $notification_type = "success";
        } else {
            $notification_message = "Error deleting medicine: " . $stmt->error;
            $notification_type = "danger";
        }
        $stmt->close();
    } else {
        $notification_message = "Error preparing statement: " . $connection->error;
        $notification_type = "danger";
    }
}

// Handle editing medicine
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_medicine'])) {
    $medicine_id = (int)$_POST['edit_medicine_id'];
    $medicine_name = $connection->real_escape_string($_POST['edit_medicine_name']);
    $quantity = (int)$_POST['edit_medicine_quantity'];
    $unit = $connection->real_escape_string($_POST['edit_medicine_unit']);
    $expiration_date = !empty($_POST['edit_expiration_date']) ? $connection->real_escape_string($_POST['edit_expiration_date']) : null;
    
    $stmt = $connection->prepare("UPDATE medicine_inventory SET medicine_name = ?, quantity = ?, unit = ?, expiration_date = ? WHERE medicine_id = ?");
    if ($stmt) {
        $stmt->bind_param("sissi", $medicine_name, $quantity, $unit, $expiration_date, $medicine_id);
        if ($stmt->execute()) {
            $notification_message = "Medicine updated successfully!";
            $notification_type = "success";
        } else {
            $notification_message = "Error updating medicine: " . $stmt->error;
            $notification_type = "danger";
        }
        $stmt->close();
    } else {
        $notification_message = "Error preparing statement: " . $connection->error;
        $notification_type = "danger";
    }
}


// Fetch all medicines for display, including date_added and using correct column names
$medicines = [];
$sql_medicines = "SELECT medicine_id, medicine_name, quantity, unit, expiration_date, date_added FROM medicine_inventory ORDER BY medicine_name ASC";
$result_medicines = $connection->query($sql_medicines);

if ($result_medicines->num_rows > 0) {
    while ($row = $result_medicines->fetch_assoc()) {
        $medicines[] = $row;
    }
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Inventory</title>
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
            left: 0; /* Default for desktop */
            padding-top: 111px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 999;
            box-sizing: border-box;
            overflow-y: auto;
            transition: left 0.3s ease-in-out; /* Changed from width to left */
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
            margin-left: 250px; /* Default for desktop */
            padding: 20px;
            margin-top: 80px;
            width: calc(100% - 250px); /* Default for desktop */
            box-sizing: border-box;
            min-height: calc(100vh - 80px);
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out;
        }

        .inventory-card {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        /* Notification styles */
        .notification {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }

        .notification.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .notification.danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Media queries for responsiveness */
        @media (max-width: 992px) {
            .sidebar {
                left: -250px; /* Hide sidebar by default on smaller screens */
            }
            .sidebar.active {
                left: 0; /* Show sidebar when active */
            }
            .sidebar-toggle-button {
                display: block; /* Show toggle button on smaller screens */
            }
            .main-content {
                margin-left: 0; /* Initially no margin on small screens */
                width: 100%; /* Initially full width on small screens */
                padding-left: 15px;
                padding-right: 15px;
            }
            .main-content.sidebar-active { /* When sidebar is active, shift main content */
                margin-left: 250px;
                width: calc(100% - 250px);
            }
            .header {
                height: 60px;
                padding-left: 70px; /* Adjust header padding to make space for toggle button */
            }
            .header .logo {
                width: 40px;
            }
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.5rem;
            }
            .inventory-card {
                padding: 15px;
            }
        }

        @media (max-width: 576px) {
            .btn {
                width: 100%;
                margin-bottom: 10px;
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
        <a href="generate_qr_codes.php">Generate QR Codes</a>
        <a href="medinventory.php" class="active">MedInventory</a>
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

    <div class="main-content" id="mainContent">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>MedInventory</h1>
        </div>
        
        <div class="container mt-1">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="inventory-card">

                        <?php if (!empty($notification_message)): ?>
                            <div class="notification <?= $notification_type ?>">
                                <?= htmlspecialchars($notification_message) ?>
                            </div>
                        <?php endif; ?>

                        <h3 class="mb-3">Add New Medicine</h3>
                        <form action="medinventory.php" method="POST" class="mb-5">
                            <input type="hidden" name="add_medicine" value="1">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="medicine_name">Medicine Name</label>
                                    <input type="text" class="form-control" id="medicine_name" name="medicine_name" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="medicine_quantity">Quantity</label>
                                    <input type="number" class="form-control" id="medicine_quantity" name="medicine_quantity" min="0" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label for="medicine_unit">Unit (e.g., tablets, ml)</label>
                                    <input type="text" class="form-control" id="medicine_unit" name="medicine_unit" placeholder="e.g., tablets" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="expiration_date">Expiration Date (Optional)</label>
                                <input type="date" class="form-control" id="expiration_date" name="expiration_date">
                            </div>
                            <button type="submit" class="btn btn-success">Add Medicine</button>
                        </form>

                        <h3 class="mb-3">Current Stock</h3>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Medicine Name</th>
                                        <th>Quantity</th>
                                        <th>Unit</th>
                                        <th>Expiration Date</th>
                                        <th>Date Added</th>
                                        <th style="width: 200px;">Actions</th> </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($medicines)): ?>
                                        <?php foreach ($medicines as $medicine): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($medicine['medicine_name']) ?></td>
                                                <td id="quantity_<?= $medicine['medicine_id'] ?>"><?= htmlspecialchars($medicine['quantity']) ?></td>
                                                <td><?= htmlspecialchars($medicine['unit']) ?></td>
                                                <td><?= $medicine['expiration_date'] ? htmlspecialchars(date('M d, Y', strtotime($medicine['expiration_date']))) : 'N/A' ?></td>
                                                <td><?= !isZeroDate($medicine['date_added']) ? htmlspecialchars(date('M d, Y H:i:s', strtotime($medicine['date_added']))) : 'N/A' ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <form action="medinventory.php" method="POST" class="form-inline d-inline mr-2" onsubmit="return confirm('Are you sure you want to dispense this medicine?');">
                                                            <input type="hidden" name="dispense_medicine" value="1">
                                                            <input type="hidden" name="dispense_medicine_id" value="<?= $medicine['medicine_id'] ?>">
                                                            <input type="number" name="dispense_quantity" class="form-control form-control-sm mr-2" placeholder="Qty" min="1" required style="width: 80px;">
                                                            <button type="submit" class="btn btn-sm btn-info">Dispense</button>
                                                        </form>
                                                        <button type="button" class="btn btn-sm btn-warning mr-2 edit-btn"
                                                            data-id="<?= $medicine['medicine_id'] ?>"
                                                            data-name="<?= htmlspecialchars($medicine['medicine_name']) ?>"
                                                            data-quantity="<?= htmlspecialchars($medicine['quantity']) ?>"
                                                            data-unit="<?= htmlspecialchars($medicine['unit']) ?>"
                                                            data-expiration="<?= htmlspecialchars($medicine['expiration_date']) ?>"
                                                            data-toggle="modal" data-target="#editMedicineModal">
                                                            Edit
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger delete-modal-trigger"
                                                            data-id="<?= $medicine['medicine_id'] ?>"
                                                            data-name="<?= htmlspecialchars($medicine['medicine_name']) ?>"
                                                            data-toggle="modal" data-target="#confirmDeleteModal">
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6">No medicines in inventory.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editMedicineModal" tabindex="-1" role="dialog" aria-labelledby="editMedicineModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editMedicineModalLabel">Edit Medicine</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="medinventory.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_medicine" value="1">
                        <input type="hidden" id="edit_medicine_id" name="edit_medicine_id">
                        <div class="form-group">
                            <label for="edit_medicine_name">Medicine Name</label>
                            <input type="text" class="form-control" id="edit_medicine_name" name="edit_medicine_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_medicine_quantity">Quantity</label>
                            <input type="number" class="form-control" id="edit_medicine_quantity" name="edit_medicine_quantity" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_medicine_unit">Unit</label>
                            <input type="text" class="form-control" id="edit_medicine_unit" name="edit_medicine_unit" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_expiration_date">Expiration Date (Optional)</label>
                            <input type="date" class="form-control" id="edit_expiration_date" name="edit_expiration_date">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" role="dialog" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete <strong id="medicineNameToDelete"></strong>? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form action="medinventory.php" method="POST" class="d-inline">
                        <input type="hidden" name="delete_medicine" value="1">
                        <input type="hidden" id="confirm_delete_medicine_id" name="delete_medicine_id">
                        <button type="submit" class="btn btn-danger">Confirm Delete</button>
                    </form>
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


    <script src="../assets/js/jquery-3.5.1.slim.min.js"></script>
    <script src="../assets/js/popper.min.js"></script>
    <script src="../assets/js/bootstrap.min.js"></script>
    <script>
        // JavaScript for sidebar toggle
        function toggleSidebar() {
            var sidebar = document.getElementById("mySidebar");
            var mainContent = document.getElementById("mainContent");
            sidebar.classList.toggle("active"); // "active" class will handle the sidebar position
            mainContent.classList.toggle("sidebar-active"); // "sidebar-active" will shift main content
        }

        // Adjust main content margin on initial load based on sidebar state (for desktop)
        window.addEventListener('DOMContentLoaded', (event) => {
            var mainContent = document.getElementById("mainContent");
            if (window.innerWidth >= 992) { // Desktop view
                mainContent.style.marginLeft = '250px';
                mainContent.style.width = 'calc(100% - 250px)';
            }
        });

        // Add a listener to resize to handle orientation changes or window resizing
        window.addEventListener('resize', (event) => {
            var sidebar = document.getElementById("mySidebar");
            var mainContent = document.getElementById("mainContent");
            if (window.innerWidth >= 992) {
                sidebar.classList.remove('active'); // Ensure sidebar is not 'active' for desktop
                mainContent.classList.remove('sidebar-active'); // Remove shifting class
                mainContent.style.marginLeft = '250px';
                mainContent.style.width = 'calc(100% - 250px)';
            } else {
                // On smaller screens, allow sidebar to be hidden/shown by toggle
                // Do not force margin-left here, it's handled by toggleSidebar
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
            }
        });

        // JavaScript for Edit Medicine Modal
        $(document).ready(function() {
            $('#editMedicineModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget); // Button that triggered the modal
                var medicineId = button.data('id');
                var medicineName = button.data('name');
                var quantity = button.data('quantity');
                var unit = button.data('unit');
                var expirationDate = button.data('expiration');
                
                var modal = $(this);
                modal.find('#edit_medicine_id').val(medicineId);
                modal.find('#edit_medicine_name').val(medicineName);
                modal.find('#edit_medicine_quantity').val(quantity);
                modal.find('#edit_medicine_unit').val(unit);

                // For date input, format to YYYY-MM-DD
                if (expirationDate) {
                    modal.find('#edit_expiration_date').val(expirationDate);
                } else {
                    modal.find('#edit_expiration_date').val(''); // Clear if N/A
                }
            });

            // JavaScript for Confirm Delete Modal
            $('#confirmDeleteModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget); // Button that triggered the modal
                var medicineId = button.data('id'); // Extract info from data-* attributes
                var medicineName = button.data('name');

                var modal = $(this);
                modal.find('#medicineNameToDelete').text(medicineName); // Set medicine name in modal body
                modal.find('#confirm_delete_medicine_id').val(medicineId); // Set the hidden input for form submission
            });

            // Keep the dispense confirmation for now as it was explicitly requested in the previous turn.
            // If you want to remove this, you would remove the onsubmit from the dispense form.
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