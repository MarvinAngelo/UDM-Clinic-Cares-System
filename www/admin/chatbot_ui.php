<?php
// chatbot_ui.php
// This file contains the HTML, CSS, and JavaScript for the UDM Cora chatbot popover.
?>

<style>
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
        flex-direction: column; /* Will be toggled to flex by JS */
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
        display: flex; /* Use flexbox to stack content vertically */
        flex-direction: column; /* Stack content vertically */
        align-items: flex-start; /* Align content to the start */
    }

    /* Styles for clickable student number */
    .bot-message a {
        color: #0066cc; /* Standard link blue */
        text-decoration: underline;
        cursor: pointer;
    }

    .bot-message a:hover {
        color: #004b99;
    }

    /* Styles for the "View Details" button */
    .view-details-button {
        background-color: #007bff; /* A prominent blue */
        color: white;
        border: none;
        border-radius: 5px;
        padding: 8px 12px;
        margin-top: 10px; /* Space above the button to separate it from text */
        cursor: pointer;
        font-size: 0.9rem;
        display: block; /* Make it a block element to ensure it's on a new line */
        width: fit-content; /* Adjust width to content */
        transition: background-color 0.3s ease;
    }

    .view-details-button:hover {
        background-color: #0056b3;
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

<script>
    // Chatbot Popover Functions
    function toggleChatbotPopover() {
        const chatbotPopover = document.getElementById("chatbotPopover");
        const isCurrentlyOpen = chatbotPopover.style.display === "flex";

        if (isCurrentlyOpen) {
            chatbotPopover.style.display = "none";
            localStorage.setItem('chatbotState', 'closed'); // Save state
        } else {
            chatbotPopover.style.display = "flex";
            localStorage.setItem('chatbotState', 'open'); // Save state
            document.getElementById("chatbotInput").focus(); // Focus on input when opened
            const messagesDiv = document.getElementById("chatbotMessages");
            messagesDiv.scrollTop = messagesDiv.scrollHeight; // Scroll to bottom
        }
    }

    // On page load, check localStorage for chatbot state
    document.addEventListener('DOMContentLoaded', (event) => {
        const chatbotPopover = document.getElementById("chatbotPopover");
        const savedChatbotState = localStorage.getItem('chatbotState');
        if (savedChatbotState === 'open') {
            chatbotPopover.style.display = "flex";
            document.getElementById("chatbotInput").focus(); // Focus on input if opened by default
            const messagesDiv = document.getElementById("chatbotMessages");
            messagesDiv.scrollTop = messagesDiv.scrollHeight; // Scroll to bottom
        } else {
            chatbotPopover.style.display = "none"; // Ensure it's hidden by default if no state or closed
        }
    });

    async function sendChatbotMessage() {
        const inputField = document.getElementById("chatbotInput");
        const message = inputField.value.trim();
        if (message === "") return;

        const messagesDiv = document.getElementById("chatbotMessages");

        // Display user message
        const userMessageDiv = document.createElement("div");
        userMessageDiv.classList.add("user-message");
        userMessageDiv.textContent = message;
        messagesDiv.appendChild(userMessageDiv);

        // Clear input
        inputField.value = "";
        messagesDiv.scrollTop = messagesDiv.scrollHeight; // Scroll to bottom

        // Add typing indicator
        const typingIndicatorDiv = document.createElement("div");
        typingIndicatorDiv.classList.add("bot-message", "typing-indicator");
        typingIndicatorDiv.innerHTML = '<span class="dot"></span><span class="dot"></span><span class="dot"></span>';
        messagesDiv.appendChild(typingIndicatorDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;

        try {
            // Send message to backend
            const response = await fetch('chatbot_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'query=' + encodeURIComponent(message)
            });
            let data = await response.text(); // Bot's response

            // Remove typing indicator
            if (messagesDiv.contains(typingIndicatorDiv)) {
                messagesDiv.removeChild(typingIndicatorDiv);
            }

            // Create the main bot message container
            const botMessageDiv = document.createElement("div");
            botMessageDiv.classList.add("bot-message");
            messagesDiv.appendChild(botMessageDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;

            const typingSpeed = 20; // milliseconds per character

            // --- MODIFICATION START ---
            // Define patterns for student numbers
            // Note: The original request stated to use student_num for the ID, not patient_id.
            // The previous regex was: /• Student Number: (\S+?)###(\d+)/
            // Where $1 is student_num and $2 is patient_id.
            // If the backend `chatbot_handler.php` always sends both, we will use $1 (student_num)
            // for the `id` parameter.
            // If the backend only sends (Student No: XXXXX), we'll also handle that.

            const studentNumAndIdPattern = /• Student Number: (\S+?)###(\d+)/; // Regex to find student_num and patient_id
            const studentNumOnlyPattern = /\(Student No:\s*(\S+)\)/; // Regex for "(Student No: XXXXX)"

            let displayText = ''; // The text that will be typed out
            let studentNumberForButton = ''; // To store the student number for the button's URL

            // Check for the combined pattern first
            let matchAndId = data.match(studentNumAndIdPattern);
            if (matchAndId) {
                const studentNum = matchAndId[1];
                // Replace the matched pattern with just the display text (no link here)
                displayText = data.replace(matchAndId[0], `• Student Number: ${studentNum}`);
                studentNumberForButton = studentNum; // Use student_num for the button
            } else {
                // If not found, check for the student number only pattern
                let matchOnlyNum = data.match(studentNumOnlyPattern);
                if (matchOnlyNum) {
                    const studentNum = matchOnlyNum[1];
                    // Replace the matched pattern with just the display text
                    displayText = data.replace(matchOnlyNum[0], `(Student No: ${studentNum})`);
                    studentNumberForButton = studentNum; // Use student_num for the button
                } else {
                    // No special pattern found, use the data as is
                    displayText = data;
                }
            }

            // Create a span element to hold the typed text
            const textContentSpan = document.createElement('span');
            botMessageDiv.appendChild(textContentSpan); // Add the span to the bot message div

            let charIndex = 0;
            function typeCharacter() {
                if (charIndex < displayText.length) {
                    textContentSpan.textContent += displayText.charAt(charIndex);
                    charIndex++;
                    messagesDiv.scrollTop = messagesDiv.scrollHeight; // Keep scrolling as text appears
                    setTimeout(typeCharacter, typingSpeed);
                } else {
                    // After all characters are typed, add the button if a student number was found
                    if (studentNumberForButton) {
                        const button = document.createElement('button');
                        button.classList.add('view-details-button');
                        button.textContent = 'View Details';
                        // Redirect using studentNum as the ID, encoding it for URL safety
                        button.onclick = () => window.open(`view_details.php?id=${encodeURIComponent(studentNumberForButton)}`, '_blank');
                        
                        // Append the button to the botMessageDiv, outside (below) the text span
                        botMessageDiv.appendChild(button);
                    }
                    messagesDiv.scrollTop = messagesDiv.scrollHeight;
                }
            }
            typeCharacter(); // Start typing animation
            // --- MODIFICATION END ---

        } catch (error) {
            console.error('Error:', error);
            // Remove typing indicator if an error occurred
            if (messagesDiv.contains(typingIndicatorDiv)) {
                messagesDiv.removeChild(typingIndicatorDiv);
            }
            const errorMessageDiv = document.createElement("div");
            errorMessageDiv.classList.add("bot-message");
            errorMessageDiv.textContent = "Sorry, something went wrong. Please try again.";
            messagesDiv.appendChild(errorMessageDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
    }

    // Allow sending message with Enter key
    document.getElementById("chatbotInput").addEventListener("keypress", function(event) {
        if (event.key === "Enter") {
            event.preventDefault(); // Prevent default form submission if input is in a form
            sendChatbotMessage();
        }
    });
</script>