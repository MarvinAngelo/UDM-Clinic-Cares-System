<?php

// chatbot_handler.php - Cora (Clinic Operations Response Assistant)
// This file handles the AJAX requests from the dashboard for the chatbot.

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = ""; // Assuming no password based on dashboard.php
$database = "clinic_data";

// Create database connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    error_log("Database Connection failed: " . $conn->connect_error);
    echo "Sorry, I'm having trouble connecting to the database right now. Please try again later.";
    exit();
}

/**
 * Function to perform fuzzy matching against a list of triggers.
 *
 * @param string $userMessage The user's input message.
 * @param array $triggers An array of predefined trigger phrases.
 * @param int $threshold The Levenshtein distance threshold. A lower number means a stricter match.
 * @return string|null The best matching trigger phrase if found within the threshold, otherwise null.
 */
function getFuzzyMatch($userMessage, $triggers, $threshold = 3) {
    $userMessage = strtolower(trim($userMessage));
    $bestMatch = null;
    $lowestDistance = -1;

    foreach ($triggers as $trigger) {
        $triggerLower = strtolower($trigger);
        // Calculate Levenshtein distance between user message and trigger
        $distance = levenshtein($userMessage, $triggerLower);

        // Check if the distance is within the threshold and if it's the closest match so far
        if ($distance <= $threshold && ($lowestDistance == -1 || $distance < $lowestDistance)) {
            $lowestDistance = $distance;
            $bestMatch = $trigger; // Return the original trigger for context if needed
        }
    }
    return $bestMatch;
}


// Function to get Cora's response
function getCoraResponse($userMessage, $conn) {
    // Convert to lowercase and trim whitespace
    $userMessage = strtolower(trim($userMessage));
    // Remove trailing question mark for robust matching
    $userMessage = rtrim($userMessage, '?');

    // Helper function to format patient details for a single patient
    function formatPatientDetails($patient) {
        $response = "Here are the details for " . htmlspecialchars($patient['FirstName']) . " " . htmlspecialchars($patient['LastName']) . ":\n";
        $response .= "• Patient ID: " . (isset($patient['PatientID']) ? htmlspecialchars($patient['PatientID']) : 'N/A') . "\n";
        $response .= "• Student Number: " . (isset($patient['Student_Num']) ? htmlspecialchars($patient['Student_Num']) : 'N/A') . "\n";
        $response .= "• Program: " . (isset($patient['Program']) ? htmlspecialchars($patient['Program']) : 'N/A') . "\n";
        $response .= "• Year Level: " . (isset($patient['yearLevel']) ? htmlspecialchars($patient['yearLevel']) : 'N/A') . "\n";
        $response .= "• Sex: " . (isset($patient['Sex']) ? htmlspecialchars($patient['Sex']) : 'N/A') . "\n";
        $response .= "• Age: " . (isset($patient['age']) ? htmlspecialchars($patient['age']) : 'N/A') . "\n";
        $response .= "• Address: " . (isset($patient['Address']) ? htmlspecialchars($patient['Address']) : 'N/A') . "\n";
        $response .= "• Phone: " . (isset($patient['ContactNumber']) ? htmlspecialchars($patient['ContactNumber']) : 'N/A') . "\n";
        $response .= "• Civil Status: " . (isset($patient['civil_status']) ? htmlspecialchars($patient['civil_status']) : 'N/A') . "\n";


        $course = isset($patient['Course']) ? htmlspecialchars($patient['Course']) : '';
        $year = isset($patient['Year']) ? htmlspecialchars($patient['Year']) : '';
        if (!empty($course) && !empty($year)) {
            $response .= "• Course & Year: " . $course . ", " . $year . "\n";
        } elseif (!empty($course)) {
            $response .= "• Course: " . $course . "\n";
        } elseif (!empty($year)) {
            $response .= "• Year: " . $year . "\n";
        }

        if (isset($patient['SpecialCases']) && !empty($patient['SpecialCases'])) {
             $response .= "• Special Cases: " . htmlspecialchars($patient['SpecialCases']) . "\n";
        }
        return $response;
    }

    // --- Dynamic Queries (Patient Details, Medicine Inventory, Medical Logs Today) ---
    // These are handled first as they involve database lookups based on specific query patterns.

// 1. Search by Patient ID
$patient_id_triggers = [
    "patient id", "id of patient", "patient number", "patient no", "id", "patient by id"
];
foreach ($patient_id_triggers as $trigger) {
    // Modify to use fuzzy matching for the trigger, and then check if the actual ID follows
    $matchedTrigger = getFuzzyMatch($userMessage, [$trigger], 2); // Small threshold for precise triggers
    if ($matchedTrigger !== null && strpos(strtolower($userMessage), strtolower($matchedTrigger)) === 0) {
        $id_query = trim(substr($userMessage, strlen($matchedTrigger)));
        if (is_numeric($id_query)) {
            $stmt = $conn->prepare("SELECT * FROM patients WHERE PatientID = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id_query);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $stmt->close();
                    return formatPatientDetails($result->fetch_assoc());
                } else {
                    $stmt->close();
                    return "I couldn't find a patient with Patient ID: " . htmlspecialchars($id_query) . ". Please check the ID and try again.";
                }
            } else {
                error_log("Failed to prepare statement for PatientID: " . $conn->error);
                return "I'm sorry, I encountered an error while trying to retrieve patient details by ID. Please try again.";
            }
        }
    }
}

// 2. Search by Student Number
$student_num_triggers = [
    "student number", "student num", "id number", "s.n.", "sn", "id no", "student"
];
foreach ($student_num_triggers as $trigger) {
    $matchedTrigger = getFuzzyMatch($userMessage, [$trigger], 2); // Small threshold
    if ($matchedTrigger !== null && strpos(strtolower($userMessage), strtolower($matchedTrigger)) === 0) {
        $snum_query = trim(substr($userMessage, strlen($matchedTrigger)));
        $snum_query = str_replace('-', '', $snum_query); // Remove dashes from student number if present

        if (!empty($snum_query)) {
            $stmt = $conn->prepare("SELECT * FROM patients WHERE REPLACE(Student_Num, '-', '') = ?");
            if ($stmt) {
                $stmt->bind_param("s", $snum_query);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $stmt->close();
                    return formatPatientDetails($result->fetch_assoc());
                } else {
                    $stmt->close();
                    return "I couldn't find a patient with Student Number: " . htmlspecialchars($snum_query) . ". Please check the number and try again.";
                }
            } else {
                error_log("Failed to prepare statement for Student_Num: " . $conn->error);
                return "I'm sorry, I encountered an error while trying to retrieve patient details by student number. Please try again.";
            }
        }
    }
}

// 3. Search by First Name or Last Name (This section already uses LIKE, which is a form of fuzzy matching in SQL,
//    but we can improve the trigger recognition with fuzzy matching)
$patient_name_triggers = [
    "give details for", "details for", "give me the details of", "give me details of", "show details of", "show me the details of",
    "fetch details for", "get details for", "get patient info for", "get patient information for", "show info for", "show patient info for",
    "show me patient details for", "who's", "whos", "who is", "who was", "can i see the record of", "may i see the record of",
    "do you have info about", "do you have the record of", "what's the info of", "what is the info of", "what are the details of",
    "patient info for", "patient info of", "patient details for", "details about", "info about", "info of", "record of", "check record for",
    "find patient info for", "lookup patient info for", "lookup info for", "can you find info for", "can you check the record of",
    "can i get the details of", "can you tell me the info of", "would you show me patient info for", "can i access the record of",
    "i want patient info for", "what are the patient's details", "what is their medical info", "please show info for", "give patient record for",
    "access info for", "i want to see the details of", "do you have details for", "retrieve record of", "can i know more about",
    "tell me about", "tell me the record of", "can you provide info on", "what does the record say for", "does the system have info for",

    "meron bang record ni", "meron ka bang record ni", "pakita ang info ni", "anong detalye ni", "record ni", "info ni", "patingin ng record ni", "patingin nga ng record ni",
    "gusto ko makita ang info ni", "pwede ko ba makita ang details ni", "may impormasyon ba tungkol kay", "anong impormasyon kay",
    "gusto ko makita yung info ni", "pwede ba makita ang details ni", "patingin ng details ni", "check mo nga yung info ni",
    "ano na balita kay", "ano diagnosis ni", "ano condition ni", "may record ba si", "may info ba kay",
    "pakita mo sakin yung info ni", "tingnan natin yung record ni", "tingnan ko lang yung details ni",
    "pacheck nga ng info ni", "icheck mo yung record ni", "patingin naman ng file ni", "ano sakit ni",
    "may history ba si", "tingnan natin kung anong condition ni", "record kay", "ano meron kay",
    "ano ang info ni", "ano laman ng record ni",
];

$matchedNameTrigger = getFuzzyMatch($userMessage, $patient_name_triggers, 4); // A bit more leeway for name triggers
if ($matchedNameTrigger !== null) {
    // Ensure the matched trigger is actually at the beginning of the user message
    if (strpos(strtolower($userMessage), strtolower($matchedNameTrigger)) === 0) {
        $name_query = trim(substr($userMessage, strlen($matchedNameTrigger)));
        $name_parts = explode(" ", $name_query);

        $stmt = null;
        $result = null;

        if (count($name_parts) >= 2) {
            $firstName = ucfirst($name_parts[0]);
            $lastName = ucfirst(end($name_parts));

            $stmt = $conn->prepare("SELECT * FROM patients WHERE FirstName = ? AND LastName = ?");
            if ($stmt) {
                $stmt->bind_param("ss", $firstName, $lastName);
            }
        } elseif (count($name_parts) == 1 && !empty($name_parts[0])) {
            $searchName = ucfirst($name_parts[0]);
            $likeSearchName = "%" . $searchName . "%";

            $stmt = $conn->prepare("SELECT * FROM patients WHERE FirstName LIKE ? OR LastName LIKE ?");
            if ($stmt) {
                $stmt->bind_param("ss", $likeSearchName, $likeSearchName);
            }
        }

        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $patient = $result->fetch_assoc();
                $stmt->close();
                return formatPatientDetails($patient);
            } elseif ($result->num_rows > 1) {
                $response = "I found multiple patients matching that name:\n";
                while ($patient = $result->fetch_assoc()) {
                    $response .= "• " . htmlspecialchars($patient['FirstName']) . " " . htmlspecialchars($patient['LastName']);
                    if (isset($patient['Student_Num']) && !empty($patient['Student_Num'])) {
                        $response .= " (Student No: " . htmlspecialchars($patient['Student_Num']) . ")";
                    }
                    $response .= "\n";
                }
                $response .= "Please provide a more specific name, student number, or patient ID for exact details.";
                $stmt->close();
                return $response;
            } else {
                $stmt->close();
                return "I couldn't find any patient matching '" . htmlspecialchars($name_query) . "'. Please check the name and try again.";
            }
        } else {
            error_log("Failed to prepare statement for Name search: " . $conn->error);
            return "I'm sorry, I encountered an error while trying to retrieve patient details by name. Please try again.";
        }
    }
}

// 4. Medicine Inventory Search
$medicine_availability_triggers = [
    "is medicine available", "check medicine", "medicine availability", "do you have", "stock of",
    "quantity of", "medicine stock", "medication stock", "how much", "is it available",
    "do you have medicine", "is this available", "do you still have", "available ba", "available pa ba",
    "may stock ba", "may gamot ba", "may availability ba", "do you carry", "carry this medicine",
    "how many stocks", "ilan ang stock nito", "ilan pa ang meron", "meron ba kayo ng", "meron pa ba kayo ng",
    "stock availability", "is it in stock", "is it on hand", "still have", "still in stock",
    "on hand stock", "may natitira pa ba", "supply of", "check if available", "nakastock ba",
    "may laman pa ba", "check if may gamot", "stock check for", "tingnan kung may gamot", "meron ba nito",
    "available ba ito", "available ba ang", "stocked pa ba", "availability ng gamot", "med availability",
    "check kung meron", "inventory ng", "supply ba meron", "gamot meron ba"
];

$matchedMedicineTrigger = getFuzzyMatch($userMessage, $medicine_availability_triggers, 5);
if ($matchedMedicineTrigger !== null) {
    // Extract the medicine name only if the matched trigger is at the beginning
    if (strpos(strtolower($userMessage), strtolower($matchedMedicineTrigger)) === 0) {
        $medicine_name_query = trim(substr($userMessage, strlen($matchedMedicineTrigger)));

        if (!empty($medicine_name_query)) {
            $stmt = $conn->prepare("SELECT medicine_name, quantity, unit, expiration_date FROM medicine_inventory WHERE medicine_name LIKE ?");
            if ($stmt) {
                $search_term = "%" . $medicine_name_query . "%";
                $stmt->bind_param("s", $search_term);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $medicine_found = $result->fetch_assoc();
                    $stmt->close();
                    $response = "Yes, " . htmlspecialchars($medicine_found['medicine_name']) . " is available.\n";
                    $response .= "• Quantity: " . htmlspecialchars($medicine_found['quantity']) . " " . htmlspecialchars($medicine_found['unit']) . "\n";
                    $response .= "• Expiration Date: " . htmlspecialchars($medicine_found['expiration_date']) . "\n";
                    return $response;
                } else {
                    $stmt->close();
                    return "I couldn't find '" . htmlspecialchars($medicine_name_query) . "' in the medicine inventory. Please check the name and try again.";
                }
            } else {
                error_log("Failed to prepare statement for medicine inventory search: " . $conn->error);
                return "I'm sorry, I encountered an error while trying to retrieve medicine information. Please try again.";
            }
        }
    }
}

// 5. List all medicine in inventory
$list_all_medicine_triggers = [
    "list all medicine", "show all medicine", "what medicine do you have", "medicine list", "inventory list", "all medicine",
    "show me all the medicine", "can I see the medicine list", "list of all available medicine", "what medicines are available",
    "full list of medicine", "all available medicines", "complete list of medications", "what do you have in stock",
    "show your medicine inventory", "display medicine list", "medicine inventory", "available medications", "list all drugs",
    "what meds are in stock", "what's in your inventory", "list of medicines", "do you have a medicine list",
    "complete medicine stock", "show medication list", "ano mga gamot meron kayo", "ipakita lahat ng gamot",
    "pakita ang medicine list", "listahan ng gamot", "ano gamot meron", "pakita lahat ng medicine",
    "ano mga gamot na available", "may listahan ba ng gamot", "lahat ng gamot", "mga gamot na meron",
    "patingin ng inventory ng gamot", "inventory ng lahat ng gamot", "ano nasa inventory", "pakita ang list ng gamot",
];

if (getFuzzyMatch($userMessage, $list_all_medicine_triggers, 4) !== null) {
    $stmt = $conn->prepare("SELECT medicine_name, quantity, unit FROM medicine_inventory ORDER BY medicine_name ASC");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response = "Here's the current list of medicine in the inventory:\n";
            while ($medicine = $result->fetch_assoc()) {
                $response .= "• " . htmlspecialchars($medicine['medicine_name']) . ": " . htmlspecialchars($medicine['quantity']) . " " . htmlspecialchars($medicine['unit']) . "\n";
            }
            $stmt->close();
            return $response;
        } else {
            $stmt->close();
            return "The medicine inventory is currently empty.";
        }
    } else {
        error_log("Failed to prepare statement for listing all medicine: " . $conn->error);
        return "I'm sorry, I encountered an error while trying to retrieve the medicine list. Please try again.";
    }
}

// 6. Medical Logs for Today (Asia/Manila time)
$medical_logs_today_triggers = [
    "medical logs today", "consultations today", "who logged today", "daily medical logs", "today's consultations",
    "show logs for today", "anong logs ngayong araw", "consultations ngayong araw", "sino naka-log ngayon",
    "logs for today", "logs today", "any medical logs today", "consultation records today", "today's logs",
    "who had consultations today", "did anyone log today", "did someone log today", "consultation logs today",
    "view today's logs", "view today's consultations", "patients seen today", "show me today's medical logs",
    "who visited today", "today's patient list", "who had checkup today", "who was seen today",
    "who were the patients today", "check logs today", "list of consultations today", "check log",
    "may nagpa-checkup ba today", "may nagpatingin ba ngayon", "sino nagpatingin ngayong araw",
    "sino mga pasyente ngayon", "sino mga nagpacheck up today", "ilabas ang logs ngayon",
    "tingnan ang consultations ngayon", "pakita ang medical logs ngayong araw", "consultation ngayong araw",
    "logs ngayong araw", "medical records ngayong araw", "record ngayong araw", "kanino ang logs ngayong araw",
    "sino mga naka-record ngayon", "pasok ngayong araw", "logs ngayon"
];

if (getFuzzyMatch($userMessage, $medical_logs_today_triggers, 4) !== null) {
    date_default_timezone_set('Asia/Manila');
    $today_date = date('Y-m-d');

    $stmt = $conn->prepare("
            SELECT
                c.TimeIn, c.TimeOut, c.Date, c.Subjective, c.Objective,
                c.Assessment, c.MedicineGiven, c.QuantityGiven, c.SavedBy,
                p.FirstName, p.MiddleInitial, p.LastName, p.program, p.student_num
            FROM
                consultations c
            JOIN
                patients p ON c.PatientID = p.PatientID
            WHERE
                c.Date = ?
            ORDER BY c.TimeIn ASC
        ");

    if ($stmt) {
        $stmt->bind_param("s", $today_date);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $response = "Here are the medical logs for today (" . htmlspecialchars($today_date) . "):\n";
            while ($log = $result->fetch_assoc()) {
                $response .= "\n--- Consultation ---\n";
                $response .= "• Patient: " . htmlspecialchars($log['FirstName']) . " " . htmlspecialchars($log['MiddleInitial']) . " " . htmlspecialchars($log['LastName']) . "\n";
                $response .= "• Student No: " . htmlspecialchars($log['student_num']) . "\n";
                $response .= "• Program: " . htmlspecialchars($log['program']) . "\n";
                $response .= "• Time In: " . htmlspecialchars($log['TimeIn']) . "\n";
                $response .= "• Time Out: " . htmlspecialchars($log['TimeOut']) . "\n";
                $response .= "• Subjective: " . htmlspecialchars($log['Subjective']) . "\n";
                $response .= "• Objective: " . htmlspecialchars($log['Objective']) . "\n";
                $response .= "• Assessment: " . htmlspecialchars($log['Assessment']) . "\n";
                $response .= "• Medicine Given: " . (empty($log['MedicineGiven']) ? 'N/A' : htmlspecialchars($log['MedicineGiven'])) . "\n";
                $response .= "• Quantity Given: " . (empty($log['QuantityGiven']) ? 'N/A' : htmlspecialchars($log['QuantityGiven'])) . "\n";
                $response .= "• Saved By: " . htmlspecialchars($log['SavedBy']) . "\n";
            }
            $stmt->close();
            return $response;
        } else {
            $stmt->close();
            return "There are no medical logs recorded for today (" . htmlspecialchars($today_date) . ").";
        }
    } else {
        error_log("Failed to prepare statement for medical logs: " . $conn->error);
        return "I'm sorry, I encountered an error while trying to retrieve today's medical logs. Please try again.";
    }
}

    // --- Static Knowledge Base for UDM Clinic Cares System ---
    // This uses a structured array similar to chatbot_response.php's knowledge base.
    $clinicCaresKnowledgeBase = [
        [
            'questions' => ["hello", "hi", "hey"],
            'answer' => "Hello there! I'm UDM Cora, your Clinic Operations Response Assistant. How can I assist you today?"
        ],
        [
            'questions' => ["teach me how to use the system", "how to use the system", "system guide", "guide to the system", "system tutorial"],
            'answer' => "Certainly! Here's a quick guide to the main parts of the UDM Clinic Cares System:\n\n" .
                        "• **Dashboard**: See the tally and home of UDM Cora.\n" .
                        "• **Add Patient**: Add a patient.\n" .
                        "• **View Patients**: View patient list and their profiles.\n" .
                        "• **Consultation**: Embedded with QR ScanLog, to scan the QR of each individual patient.\n" .
                        "• **Patient Records**: Consultation records of patients.\n" .
                        "• **Generate QR Codes**: This will generate the QR code.\n" .
                        "• **MedInventory**: This will show the Medicine inventory and the medicine names, types, and quantity.\n" .
                        "• **Medical Logs**: This will log the patients for today.\n" .
                        "• **Manage Staff**: This will manage the staff.\n" .
                        "• **Logout**: When you're done, simply click here to securely exit the system.\n\n" .
                        "Is there a specific section you'd like to know more about?"
        ],
        [
            'questions' => ["dashboard"],
            'answer' => "The Dashboard is your home screen where you can see a tally of important information and interact with me, UDM Cora."
        ],
        [
            'questions' => ["add patient"],
            'answer' => "To add a new patient, please navigate to the 'Add Patient' section from the sidebar. Here, you can input new patient details to register them into the system."
        ],
        [
            'questions' => ["view patients", "patient list"],
            'answer' => "In the 'View Patients' section, you can find a comprehensive list of all registered patients and view their individual profiles, including their personal and medical information."
        ],
        [
            'questions' => ["consultation"],
            'answer' => "The Consultation feature is embedded with QR ScanLog. This allows you to easily scan the QR codes of patients to quickly start a new consultation record for them."
        ],
        [
            'questions' => ["patient records"],
            'answer' => "The 'Patient Records' section provides access to the detailed consultation history and medical records of all patients within the system."
        ],
        [
            'questions' => ["generate qr codes", "qr code generation"],
            'answer' => "The 'Generate QR Codes' feature allows you to create unique QR codes for patient identification. These QR codes can then be used with the Consultation's QR ScanLog for quick patient lookup."
        ],
        [
            'questions' => ["medinventory", "medical inventory", "medicines"],
            'answer' => "The 'MedInventory' module displays the current medicine inventory. Here, you can see details such as medicine names, types, and available quantities."
        ],
        [
            'questions' => ["manage staff", "staff management"],
            'answer' => "The 'Manage Staff' section allows you to oversee and update information and roles for the clinic staff members."
        ],
        [
            'questions' => ["logout", "sign out"],
            'answer' => "If you wish to log out, you can click on the 'Logout' link in the sidebar."
        ],
        [
            'questions' => ["thank you", "thanks", "appreciate it"],
            'answer' => "You're most welcome! Is there anything else I can assist you with today?"
        ],
        [
            'questions' => ["bye", "goodbye", "see you"],
            'answer' => "Goodbye! Have a productive day!"
        ],
        [
            'questions' => [
                "sino gumawa sayo", "sino nag develop sayo", "what's your secret", "who coded you", "who made you",
                "who created you", "who built you", "who designed you", "sino nag program sayo", "who's your developer",
                "who's your creator", "who's your maker", "who is your maker", "who invented you", "who's behind you",
                "who created this chatbot", "who made this chatbot", "sino gumawa ng chatbot na ito", "sino nag program ng chatbot na ito",
                "sino gumawa ng bot na ito", "sino nasa likod mo", "sino gumawa sa'yo", "who is the person behind you",
                "who's responsible for you", "who is your author", "who's the mastermind behind you", "developer ng chatbot na ito",
                "sinong gumawa sa'yo", "sinong lumikha sayo", "who started you", "origin of this chatbot", "chatbot creator",
                "how were you made", "who gave you life", "who gave you purpose", "paano ka ginawa", "who launched you",
                "sino gumawa ng AI na ito", "sino gumawa ng UDM chatbot", "sino gumawa ng UDM Clinic Cares bot"
            ],
            'answer' => "I'm Cora, Marvin Angelo A. Dela Cruz created me as a chatbot to help you!"
        ],
        [
            'questions' => [
                "who created this whole system", "what's the history of this system", "history of system",
                "history of udm clinic cares", "history of clinic cares system", "who made this system",
                "sino gumawa ng system", "who developed this system", "who created clinic cares",
                "who built this system", "who created udm clinic cares", "origin of the system",
                "origin of clinic cares", "how did this system start", "how did clinic cares start",
                "background of this system", "background of clinic cares", "history behind this system",
                "what's the background of the system", "who is behind this system", "what is udm clinic cares history",
                "clinic cares story", "anong pinagmulan ng system", "anong pinagmulan ng clinic cares",
                "sino gumawa ng clinic cares", "who is the creator of this system", "what's the story of this system",
                "who started this project", "how this system came to be", "clinic cares development story",
            ],
            'answer' => "CO-41, SY 2024-2025 made this system lead by Engr. Jonathan De Leon, and it was maintained by Marvin Angelo A. Dela Cruz and Erik Josef M. Pallasigue, they added features like, Generate QR Codes, MedInventory, Medical Logs, Manage Staff, QR ScanLog they also fix the original issues of the past system, like duplication of patients, and other issues occured on the first prototype of the project"
        ]
    ];

    // Check static knowledge base
    foreach ($clinicCaresKnowledgeBase as $item) {
        $matchedTrigger = getFuzzyMatch($userMessage, $item['questions'], 3); // Adjust threshold as needed
        if ($matchedTrigger !== null) {
            return $item['answer'];
        }
    }

    // --- Fallback Response ---
    return "I'm sorry, I couldn't understand that. I am designed to assist with navigating dashboard features or retrieving patient details. You can ask for patient details by name (e.g., 'details for John Doe' or 'details for John'), student number (e.g., 'student number 2022-0001'), or patient ID (e.g., 'patient id 123'). You can also ask me to 'list all medicine' to see the inventory, or ask for 'medical logs today' to see today's consultations.";
}

// Check if it's a POST request and if a query is sent
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["query"])) {
    $userQuery = $_POST["query"];
    $response = getCoraResponse($userQuery, $conn);
    echo $response; // Send the response back as plain text
} else {
    // If accessed directly without a POST query
    echo "Hello! I'm Cora, your Clinic Operations Response Assistant. How can I help you today?";
}

// Close the database connection
$conn->close();

?>