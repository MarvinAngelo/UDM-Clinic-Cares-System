<?php
session_start();

require_once 'backup.php'; // Adjust path if backup.php is in a different directory
// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Redirect to the login page with an error message
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Patient</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
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

        /* Popup Table Styles */
        .popup-table {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 700px;
            background-color: white;
            border: 1px solid #ddd;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            z-index: 1002;
            padding: 20px;
            border-radius: 5px;
        }

        .popup-table .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 20px;
            cursor: pointer;
            color: #888;
        }

        .popup-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .popup-table th, .popup-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .popup-table th {
            background-color: #f1f1f1;
        }

        .popup-table td {
            background-color: #fafafa;
        }

        .sms-btn-container {
            position: relative;
            bottom: -10px;
            right: 10px;
            width: 100%;
            text-align: right;
        }

        .sms-btn {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .sms-btn:hover {
            background-color: #218838;
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

        .required-field::after {
            content: " *";
            color: red;
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

            .mission-vision-section, .panel {
                padding: 15px; /* Reduce padding on smaller screens */
            }

            .core-values-grid {
                grid-template-columns: 1fr; /* Stack core values vertically on small screens */
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
            display: flex; /* Will be toggled to flex by JS */
            flex-direction: column;
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
        <a href="addpatient.php"  class="active">Add Patient</a>
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

    <div class="header">
        <img src="images/UDMCLINIC_LOGO.png" alt="Logo" class="logo">
        <h1>UDM Clinic Cares</h1>
    </div>

    <div class="container-fluid"> <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4><span class="glyphicon glyphicon-th"></span> Add New Patient</h4>
                    </div>
                    <div class="panel-body">
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
                        <?php endif; ?>

                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
                        <?php endif; ?>

                        <form method="post" action="add_patient_backend.php">
                            <h5 class="text-primary text-center fw-bold" style="font-size: 24px;">Personal Information</h5>

                            <div class="form-group">
                                <label class="required-field">Student Number or Visitor Number</label>
                                <input type="text" class="form-control" name="StudentNo" required pattern="[A-Za-z0-9\-]+" title="Only letters, numbers, and dashes are allowed.">
                                <label class="required-field">Last Name</label>
                                <input type="text" class="form-control" name="LastName" required>
                                <label class="required-field">First Name</label>
                                <input type="text" class="form-control" name="FirstName" required>
                                <label>Middle Name</label>
                                <input type="text" class="form-control" name="MiddleInitial">
                                <label class="required-field">Email</label>
                                <input type="text" class="form-control" name="email" required>
                                <label class="required-field">Sex (M/F)</label>
                                <input type="text" class="form-control" name="Sex" required>
                                <label class="required-field">Age</label>
                                <input type="number" class="form-control" name="age" required>
                                <label class="required-field">Civil Status</label>
                                <input type="text" class="form-control" name="civil_status" required>
                            </div>

                            <h5 class="text-primary text-center fw-bold" style="font-size: 24px;">Contact Information</h5>
                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" class="form-control" name="Address">
                                <label class="required-field">Cellphone Number</label>
                                <input type="tel" class="form-control" name="ContactNumber" pattern="\d{10}" title="Enter a valid 10-digit number" required>
                                <label>Emergency Number</label>
                                <input type="tel" class="form-control" name="emergency_number" pattern="\d{10}" title="Enter a valid 10-digit number">
                                <label>Parent/Guardian</label>
                                <input type="text" class="form-control" name="guardian">
                            </div>

                            <h5 class="text-primary text-center fw-bold" style="font-size: 24px;">Physical Attributes</h5>
                            <div class="form-group">
                                <label class="required-field">Height (cm)</label>
                                <input type="number" class="form-control" name="height" required>
                                <label>Weight (kg)</label>
                                <input type="number" class="form-control" name="weight">
                            </div>

                            <h5 class="text-primary text-center fw-bold" style="font-size: 24px;">Academic Information</h5>
                            <div class="form-group">
                                <label class="required-field">Year Level</label>
                                <select class="form-control" id="yearLevel" name="yearLevel" required>
                                    <option value="">Select Year Level</option>
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2st Year</option>
                                    <option value="3rd Year">3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                    <option value="5th Year">5th Year</option>
                                    <option value="N/A">N/A</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="required-field">Program</label>
                                <select class="form-control" id="Program" name="Program" required>
                                    <option value="">Select Program</option>
                                    <option value="CET">CET - College of Engineering and Technology</option>
                                    <option value="CHS">CHS - College of Health and Science</option>
                                    <option value="CBA">CBA - College of Business Administration</option>
                                    <option value="CAS">CAS - College of Arts and Sciences</option>
                                    <option value="CCJ">CCJ - College of Criminal Justice</option>
                                    <option value="CED">CED - College of Education</option>
                                    <option value="N/A">N/A</option>
                                </select>
                            </div>

                            <h5 class="text-primary text-center fw-bold" style="font-size: 24px;">Medical Information</h5>
                            <div class="form-group">
                                <label>Special Cases</label>
                                <select class="form-control" id="specialCases" name="specialCases">
                                    <option value="">Select Special Case</option>
                                    <option value="Check Up">Check Up</option>
                                    <option value="Hepa B">Hepa B</option>
                                    <option value="PWD">PWD</option>
                                    <option value="Pregnant">Pregnant</option>
                                    <option value="APL > N">APL > N</option>
                                    <option value="PTB - Non Compliant">PTB - Non Compliant</option>
                                    <option value="PTB - Complied">PTB - Complied</option>
                                    <option value="For APL">For APL</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary" id="addPatientBtn">Add Patient</button>
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
            <div class="bot-message">Hello! I'm UDM Cora. How can I help you today regarding clinic data?</div>
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
            sidebar.classList.toggle("active"); // "active" class will handle the width change
        }

        // JavaScript for form submission via Fetch API
        document.querySelector("form").addEventListener("submit", function(e) {
            e.preventDefault(); // prevent default form submission

            const form = e.target;
            const formData = new FormData(form);
            const submitButton = document.getElementById("addPatientBtn"); // Get the submit button

            submitButton.disabled = true; // Disable the button immediately upon submission

            fetch("add_patient_backend.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json()) // expecting JSON from backend
            .then(data => {
                const alertContainer = document.createElement("div");
                alertContainer.classList.add("alert");

                if (data.success) {
                    alertContainer.classList.add("alert-success");
                    alertContainer.innerText = data.message;
                    form.reset(); // clear form on success
                } else {
                    alertContainer.classList.add("alert-danger");
                    alertContainer.innerText = data.message;
                }

                const existingAlert = document.querySelector(".alert");
                if (existingAlert) existingAlert.remove();

                form.prepend(alertContainer);

                // Scroll to the top of the page
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth' // smooth scrolling effect
                });
                submitButton.disabled = false; // Re-enable the button after response
            })
            .catch(error => {
                console.error("Error:", error);
                const alertContainer = document.createElement("div");
                alertContainer.classList.add("alert", "alert-danger");
                alertContainer.innerText = "An error occurred during submission. Please try again.";

                const existingAlert = document.querySelector(".alert");
                if (existingAlert) existingAlert.remove();

                form.prepend(alertContainer);
                submitButton.disabled = false; // Re-enable the button on error
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