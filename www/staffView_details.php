<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['staff_loggedin']) || $_SESSION['staff_loggedin'] !== true) {
    // Redirect to the login page with an error message
    header("Location: stafflogin.php?error=" . urlencode("Please log in to access this page."));
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
$imagePath = "admin/uploads/default.png"; // Default image path
$foundImage = false; // Flag to check if a specific image exists

if (isset($_GET['id'])) {
    $patientID_from_get = $connection->real_escape_string($_GET['id']);

    // Fetch patient details using Student_Num
    $patientQuery = "SELECT * FROM patients WHERE Student_Num = '$patientID_from_get'";
    $patientResult = $connection->query($patientQuery);

    if ($patientResult->num_rows > 0) {
        $patient = $patientResult->fetch_assoc();
        $p_id_actual = $patient['PatientID']; // Get the actual PatientID from the fetched patient for consultations

        // Determine image path
        foreach (['png', 'jpg', 'jpeg'] as $ext) {
            $tempPath = "admin/uploads/patient_" . htmlspecialchars($p_id_actual) . "." . $ext;
            if (file_exists($tempPath)) {
                $imagePath = $tempPath;
                $foundImage = true;
                break;
            }
        }
        // If no specific image is found, $imagePath remains "uploads/default.png"

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
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
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
    </style>
</head>
<body>
<div class="sidebar" id="mySidebar">
        <a href="staffdashboard.php">Dashboard</a>
        <a href="staffaddpatient.php">Add Patient</a>
        <a href="staffviewpatient.php">View Patients</a>
        <a href="stafffrontconsult.php">Consultation</a>
        <a href="staffrecordTable.php">Patient Records</a>
        <a href="staffgenerate_qr_codes.php" >Generate QR Codes</a>
        <a href="staffmedinventory.php">MedInventory</a>
        <a href="staffmedilog.php">Medical Logs</a>
        <a href="staffstaff_creation.php">Manage Staff</a>
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
                    🔍 Enlarge Photo
                </button>
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
                                <td><?php echo htmlspecialchars($consultation['Assessment']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['MedicineGiven']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['QuantityGiven']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['Plan']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['PlanDate']); ?></td>
                                <td><?php echo htmlspecialchars($consultation['SavedBy']); ?></td>
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
            <a href="staffviewpatient.php" class="btn btn-secondary">Back to Patients</a>
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
                    <button id="snap" class="shutter-button">📸</button>
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

    <script src="assets/js/jquery-3.5.1.min.js"></script>
    <script src="assets/js/popper.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script>
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
                        document.getElementById('patientPhoto').src = 'admin/uploads/default.png?t=' + new Date().getTime(); // Set to default image
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
    </script>
</body>
</html>