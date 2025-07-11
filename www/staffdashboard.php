<?php

session_start();

// Check if the user is logged in
if (!isset($_SESSION['staff_loggedin']) || $_SESSION['staff_loggedin'] !== true) {
    // Redirect to the login page with an error message
    header("Location: stafflogin.php?error=" . urlencode("Please log in to access this page."));
    exit();
}

// Database connection code remains the same
$servername = "localhost";
$username = "root";
$password = "";
$database = "clinic_data";

$connection = new mysqli($servername, $username, $password, $database);

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

// Existing queries remain the same
$query = "SELECT specialCases, COUNT(*) as tally FROM patients GROUP BY specialCases";
$result = $connection->query($query);

$colorMapping = [
    "Hepa B" => "#FFD700",
    "PWD" => "#FF6347",
    "Pregnant" => "#32CD32",
    "APL > N" => "#1E90FF",
    "PTB - Non Compliant" => "#FF4500",
    "PTB - Complied" => "#00CED1",
    "For APL" => "#8A2BE2"
];

$assessmentQuery = "
    SELECT c.Assessment, COUNT(DISTINCT CONCAT(p.FirstName, p.MiddleInitial, p.LastName, p.Student_Num)) AS tally
    FROM consultations c
    JOIN patients p ON c.PatientID = p.PatientID
    GROUP BY c.Assessment
";
$assessmentResult = $connection->query($assessmentQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDM Clinic Dashboard</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f0f8ff;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            /* Ensure the body itself doesn't cause overflow */
            overflow-x: hidden;
        }

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

        /* Main content container styles for responsiveness */
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

        .mission-vision-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .mission-vision-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #20B2AA;
        }

        .core-values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .core-value-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #20B2AA;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .section-title {
            color: #20B2AA;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #20B2AA;
        }

        .value-letter {
            font-weight: bold;
            color: #20B2AA;
            margin-right: 5px;
        }

        .panel {
            border-radius: 10px;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .panel-heading {
            background-color: #20B2AA;
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
        }

        /* Special case colors */
        .special-case {
            font-weight: bold;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
        }

        /* Add spacing for better readability */
        .table {
            margin-bottom: 0;
        }

        .panel-body {
            padding: 20px;
        }

        /* Make sure content doesn't overlap with fixed header */
        .main-content {
            padding-top: 20px;
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
        <a href="staffdashboard.php"  class="active">Dashboard</a>
        <a href="staffaddpatient.php">Add Patient</a>
        <a href="staffviewpatient.php">View Patients</a>
        <a href="stafffrontconsult.php">Consultation</a>
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

    <div class="header d-flex align-items-center">
        <img src="images/UDMCLINIC_LOGO.png" alt="Logo" class="logo">
        <h1>UDM Clinic Cares</h1>
    </div>

    <div class="container-fluid">
        <div class="main-content">
            <div class="mission-vision-section">
                <h2 class="section-title">About UDM Clinic</h2>

                <div class="mission-vision-card">
                    <h3 class="text-primary">Mission</h3>
                    <p>To dismantle barriers to healthcare for underserved students and personnel of Universidad de Manila and provide an accessible clinic facility that offers exceptional primary healthcare treatment and preventive medicine through comprehensive education and counseling.</p>
                </div>

                <div class="mission-vision-card">
                    <h3 class="text-primary">Vision</h3>
                    <p>In a world where the wonders of modern medicine can seem beyond reach, the belief is in transforming lives by health awareness and advocating for early prevention through patient education and counseling, the aim is to elevate health and, in turn, enhance lives through healthcare provision where individuals will not only be more productive in the workforce but will also make lasting contributions to their communities.</p>
                </div>

                <div class="core-values-grid">
                    <div class="core-value-item">
                        <span class="value-letter">H</span> - Holistic Care
                        <p class="small text-muted">We prioritize a holistic approach to healthcare, addressing physical, mental, and emotional well-being of our students and staff.</p>
                    </div>
                    <div class="core-value-item">
                        <span class="value-letter">E</span> - Empathy
                        <p class="small text-muted">We strive to understand and connect with every individual, showing compassion and empathy in all interactions.</p>
                    </div>
                    <div class="core-value-item">
                        <span class="value-letter">A</span> - Access
                        <p class="small text-muted">We are committed to providing accessible and inclusive health services for all members of our university community.</p>
                    </div>
                    <div class="core-value-item">
                        <span class="value-letter">L</span> - Leadership
                        <p class="small text-muted">We lead by example, fostering a culture of excellence and innovation in healthcare.</p>
                    </div>
                    <div class="core-value-item">
                        <span class="value-letter">T</span> - Teamwork
                        <p class="small text-muted">We value collaboration and teamwork among our staff and with other departments to enhance the health and well-being of our community.</p>
                    </div>
                    <div class="core-value-item">
                        <span class="value-letter">H</span> - Health Promotion
                        <p class="small text-muted">We actively promote and educate on healthy lifestyles, disease prevention, and wellness.</p>
                    </p>
                </div>
            </div>

            <div class="panel">
                <div class="panel-heading">
                    <h4>Special Case Tally Dashboard</h4>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Special Case</th>
                                    <th>Tally</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $specialCase = $row['specialCases'];
                                        $color = $colorMapping[$specialCase] ?? "#808080";
                                        echo "<tr>
                                            <td><span class='special-case' style='background-color: $color;'>" . htmlspecialchars($specialCase) . "</span></td>
                                            <td>" . htmlspecialchars($row['tally']) . "</td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='2' class='no-data'>No special cases found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-heading">
                    <h4>Assessment Tally</h4>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Assessment</th>
                                    <th>Tally</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($assessmentResult->num_rows > 0) {
                                    while ($row = $assessmentResult->fetch_assoc()) {
                                        echo "<tr>
                                            <td><a href='assessment_details.php?assessment=" . urlencode($row['Assessment']) . "'>" . htmlspecialchars($row['Assessment']) . "</a></td>
                                            <td>" . htmlspecialchars($row['tally']) . "</td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='2'>No assessments found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <a href="addpatient.php" class="btn btn-info btn-block mb-4">Add New Patient</a>
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
    <script>
        function toggleSidebar() {
            var sidebar = document.getElementById("mySidebar");
            sidebar.classList.toggle("active");
        }

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

<?php $connection->close(); ?>