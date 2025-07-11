<?php
session_start();

// Set the default timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Check if the user is logged in
if (!isset($_SESSION['staff_loggedin']) || $_SESSION['staff_loggedin'] !== true) {
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

// Handle deletion logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $connection->real_escape_string($_POST['delete_id']);
    $currentDate = $connection->real_escape_string($_POST['current_date']); // Get the current date to redirect back to it

    $deleteQuery = "DELETE FROM consultations WHERE ConsultationID = '$delete_id'";
    if ($connection->query($deleteQuery) === TRUE) {
        echo "<script>alert('Consultation record deleted successfully'); window.location.href='medilog.php?date=" . urlencode($currentDate) . "';</script>";
        exit();
    } else {
        echo "<script>alert('Error deleting record: " . $connection->error . "'); window.location.href='medilog.php?date=" . urlencode($currentDate) . "';</script>";
        exit();
    }
}

// Get the date from the GET parameter, or use today's date
$currentDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Fetch consultation logs for the current date
$consultationLogs = [];
$query = "
    SELECT c.*, p.FirstName, p.MiddleInitial, p.LastName, p.Student_Num 
    FROM consultations c
    JOIN patients p ON c.PatientID = p.PatientID
    WHERE DATE(c.Date) = '$currentDate'
    ORDER BY c.TimeIn DESC
";
$result = $connection->query($query);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $consultationLogs[] = $row;
    }
}

$connection->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Logs</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/flatpickr.min.css">
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
            margin-top: 80px;
            width: calc(100% - 250px);
            box-sizing: border-box;
            min-height: calc(100vh - 80px);
            transition: margin-left 0.3s ease-in-out, width 0.3s ease-in-out; /* Added for smooth toggle */
        }

        .logs-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .table-responsive {
            margin-top: 20px;
        }

        /* Date Picker styling */
        .flatpickr-input {
            width: 100%;
            max-width: 250px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1rem;
            margin-bottom: 20px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
        }
        .flatpickr-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        /* Media queries for responsiveness */
        /* Adjusted for smaller screens (phones) */
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
                padding-left: 70px; /* Adjust for toggle button */
            }
            .header .logo {
                width: 40px;
            }
            .header h1 { /* Smaller font for header on small screens */
                font-size: 1.5rem;
            }
            .sidebar.active {
                width: 250px;
            }
            .main-content.sidebar-active { /* New class for main-content when sidebar is active */
                margin-left: 250px;
            }
            .logs-container {
                padding: 15px; /* Reduce padding for smaller screens */
            }
            /* Make table columns stack or reduce font size on very small screens */
            table th, table td {
                font-size: 0.85rem; /* Smaller font for table content */
                white-space: nowrap; /* Prevent text wrapping in table cells */
            }
            .table-responsive {
                overflow-x: auto; /* Enable horizontal scrolling for tables */
                -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
            }
        }

        @media (max-width: 576px) { /* Even smaller screens (e.g., portrait phone) */
            .header {
                padding: 0 10px 0 60px; /* Further adjust header padding */
            }
            .header h1 {
                font-size: 1.3rem;
            }
            .logs-container {
                padding: 10px;
            }
            table th, table td {
                font-size: 0.75rem; /* Even smaller font for table */
                padding: 0.5rem; /* Smaller padding in table cells */
            }
            .flatpickr-input {
                max-width: 100%; /* Allow date picker to take full width */
                font-size: 0.9rem;
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
        <a href="staffgenerate_qr_codes.php">Generate QR Codes</a>
        <a href="staffmedinventory.php">MedInventory</a>
        <a href="staffmedilog.php" class="active">Medical Logs</a>
        <a href="stafflogout.php">Logout</a>
        <div class="sidebar-footer text-center">
            <p>UDM Clinic Cares System</p>
        </div>
    </div>

    <button class="sidebar-toggle-button" onclick="toggleSidebar()">☰</button>

    <div class="header d-flex align-items-center">
        <img src="images/UDMCLINIC_LOGO.png" alt="Logo" class="logo">
        <h1>UDM Clinic Cares Staff</h1>
    </div>

    <div class="main-content" id="mainContent">
        <div class="table-container">
            <div class="table-header">
                <h2>Medical Logs for <?php echo htmlspecialchars($currentDate); ?></h2>
                <div class="date-picker-container">
                    <label for="date-picker">Select Date:</label>
                    <input type="text" id="date-picker" value="<?php echo htmlspecialchars($currentDate); ?>">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Student No.</th>
                            <th>Patient Name</th>
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
                        <?php if (!empty($consultationLogs)): ?>
                            <?php foreach ($consultationLogs as $log): ?>
                                <tr>
                                    <td>
                                        <a href="view_details.php?id=<?php echo htmlspecialchars($log['Student_Num']); ?>">
                                            <?php echo htmlspecialchars($log['Student_Num']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['FirstName'] . ' ' . $log['MiddleInitial'] . ' ' . $log['LastName']); ?></td>
                                    <td><?php echo htmlspecialchars($log['TimeIn']); ?></td>
                                    <td><?php echo htmlspecialchars($log['TimeOut']); ?></td>
                                    <td><?php echo htmlspecialchars($log['Subjective']); ?></td>
                                    <td><?php echo htmlspecialchars($log['Objective']); ?></td>
                                    <td><?php echo htmlspecialchars($log['Assessment']); ?></td>
                                    <td><?php echo htmlspecialchars($log['MedicineGiven']); ?></td>
                                    <td><?php echo htmlspecialchars($log['QuantityGiven']); ?></td>
                                    <td><?php echo htmlspecialchars($log['Plan']); ?></td>
                                    <td><?php echo htmlspecialchars($log['PlanDate']); ?></td>
                                    <td><?php echo htmlspecialchars($log['SavedBy']); ?></td>
                                
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center">No medical logs found for this date.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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

    <script src="assets/js/jquery-3.5.1.slim.min.js"></script>
    <script src="assets/js/popper.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script src="assets/js/flatpickr.js"></script>
    <script>
        function toggleSidebar() {
            var sidebar = document.getElementById("mySidebar");
            var mainContent = document.getElementById("mainContent");
            sidebar.classList.toggle("active");
            mainContent.classList.toggle("sidebar-active");
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
            var mainContent = document.getElementById("mainContent");
            if (window.innerWidth >= 992) {
                mainContent.style.marginLeft = '250px';
                mainContent.style.width = 'calc(100% - 250px)';
            } else {
                mainContent.style.marginLeft = '0';
                mainContent.style.width = '100%';
            }
        });

        // Initialize Flatpickr
        flatpickr("#date-picker", {
            dateFormat: "Y-m-d",
            onChange: function(selectedDates, dateStr, instance) {
                // When a date is selected, redirect to the same page with the new date
                window.location.href = 'medilog.php?date=' + dateStr;
            }
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