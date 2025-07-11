<?php
session_start();


// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "clinic_data";

// Database connection
$connection = new mysqli($servername, $username, $password, $database);
if ($connection->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $connection->connect_error]));
}

if (!isset($_POST['id']) && !isset($_POST['action'])) { // Add action check for delete
    echo json_encode(['status' => 'error', 'message' => 'Patient ID or action not provided.']);
    exit();
}

$patientID_from_form = $connection->real_escape_string($_POST['id']);
$uploadDir = 'uploads/'; // Directory to save images

// Create the uploads directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Determine the actual PatientID (assuming 'id' can be Student_Num or PatientID)
$actualPatientID = '';
$getPatientIDQuery = "SELECT PatientID FROM patients WHERE Student_Num = '$patientID_from_form'";
$result = $connection->query($getPatientIDQuery);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $actualPatientID = $row['PatientID'];
} else {
    // If not found by Student_Num, assume it's already PatientID
    $actualPatientID = $patientID_from_form;
}

// --- Handle Delete Image Action ---
if (isset($_POST['action']) && $_POST['action'] === 'delete_image') {
    $deleted = false;
    $allowedfileExtensions = ['jpg', 'png', 'jpeg'];
    foreach ($allowedfileExtensions as $ext) {
        $filePath = $uploadDir . 'patient_' . $actualPatientID . '.' . $ext;
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                $deleted = true;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete the image file.']);
                $connection->close();
                exit();
            }
        }
    }

    // Also update the database to clear the patient_pic path
    $updateQuery = "UPDATE patients SET patient_pic = NULL WHERE PatientID = '$actualPatientID'";
    $connection->query($updateQuery);

    if ($deleted) {
        echo json_encode(['status' => 'success', 'message' => 'Image deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No image found to delete for this patient.']);
    }
    $connection->close();
    exit();
}

// --- Handle file upload (from <input type="file">) ---
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['image']['tmp_name'];
    $fileName = $_FILES['image']['name'];
    $fileSize = $_FILES['image']['size'];
    $fileType = $_FILES['image']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    $allowedfileExtensions = ['jpg', 'png', 'jpeg'];
    if (!in_array($fileExtension, $allowedfileExtensions)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Only JPG, JPEG, and PNG are allowed.']);
        exit();
    }

    $newFileName = 'patient_' . $actualPatientID . '.' . $fileExtension;
    $destPath = $uploadDir . $newFileName;

    // Check if an old image exists for this patient and delete it
    foreach ($allowedfileExtensions as $ext) {
        $oldPath = $uploadDir . 'patient_' . $actualPatientID . '.' . $ext;
        if (file_exists($oldPath) && $oldPath !== $destPath) { // Don't delete if it's the same file being moved
            unlink($oldPath);
        }
    }

    if (move_uploaded_file($fileTmpPath, $destPath)) {
        // Update the database to store the path
        $updateQuery = "UPDATE patients SET patient_pic = '$destPath' WHERE PatientID = '$actualPatientID'";
        $connection->query($updateQuery);

        echo json_encode(['status' => 'success', 'message' => 'Image uploaded successfully.', 'imagePath' => $destPath]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error moving the uploaded file.']);
    }
    exit();
}

// --- Handle webcam image data (from JavaScript canvas) ---
if (isset($_POST['imageData'])) {
    $imageData = $_POST['imageData'];
    // Remove the "data:image/png;base64," part
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData));
    
    $newFileName = 'patient_' . $actualPatientID . '.png'; // Always save as PNG for webcam captures
    $destPath = $uploadDir . $newFileName;

    // Check if an old image exists for this patient and delete it
    $allowedfileExtensions = ['jpg', 'png', 'jpeg']; // Re-define for this block
    foreach ($allowedfileExtensions as $ext) {
        $oldPath = $uploadDir . 'patient_' . $actualPatientID . '.' . $ext;
        if (file_exists($oldPath) && $oldPath !== $destPath) {
            unlink($oldPath);
        }
    }

    if (file_put_contents($destPath, $data)) {
        // Update the database to store the path
        $updateQuery = "UPDATE patients SET patient_pic = '$destPath' WHERE PatientID = '$actualPatientID'";
        $connection->query($updateQuery);

        echo json_encode(['status' => 'success', 'message' => 'Photo taken and saved successfully.', 'imagePath' => $destPath]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error saving the captured image.']);
    }
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'No image data received or invalid request.']);

$connection->close();
?>