<?php
header('Content-Type: application/json');

// DB connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "clinic_data";

$connection = new mysqli($servername, $username, $password, $database);
if ($connection->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

// Sanitize input
$last_name = $connection->real_escape_string($_POST['LastName']);
$first_name = $connection->real_escape_string($_POST['FirstName']);
$middle_initial = $connection->real_escape_string($_POST['MiddleInitial']);
$sex = strtoupper($connection->real_escape_string($_POST['Sex']));
$age = intval($_POST['age']);
$email = $connection->real_escape_string($_POST['email']);
$Program = $connection->real_escape_string($_POST['Program']);
$StudentNo = $connection->real_escape_string($_POST['StudentNo']);
$civil_status = $connection->real_escape_string($_POST['civil_status']);
$address = $connection->real_escape_string($_POST['Address']);
$contact_number = $connection->real_escape_string($_POST['ContactNumber']);
$emergency_number = $connection->real_escape_string($_POST['emergency_number']);
$guardian = $connection->real_escape_string($_POST['guardian']);
$height = floatval($_POST['height']);
$weight = floatval($_POST['weight']);
$year_level = $connection->real_escape_string($_POST['yearLevel']);
$special_cases = $connection->real_escape_string($_POST['specialCases']);

// Normalize StudentNo by removing dashes for comparison
$normalizedStudentNo = str_replace('-', '', $StudentNo);

// Validation
$errors = [];
if (!in_array($sex, ['M', 'F'])) {
    $errors[] = "Invalid value for Sex. Use 'M' or 'F'.";
}
if (!preg_match('/^\d{10}$/', $contact_number)) {
    $errors[] = "Invalid Contact Number. Must be 10 digits.";
}
if (!empty($emergency_number) && !preg_match('/^\d{10}$/', $emergency_number)) {
    $errors[] = "Invalid Emergency Number. Must be 10 digits.";
}

// Check for duplicate student number (use normalized number for comparison)
$check_sql = "SELECT * FROM patients WHERE REPLACE(Student_Num, '-', '') = '$normalizedStudentNo'";
$result = $connection->query($check_sql);
if ($result->num_rows > 0) {
    $errors[] = "The Student Number '$StudentNo' (without dashes) is already taken.";
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Insert query (storing the StudentNum exactly as entered, with dashes)
$sql = "INSERT INTO patients (LastName, FirstName, MiddleInitial, sex, age, civil_status, Address, ContactNumber, emergency_number, guardian, height, weight, yearLevel, specialCases, Student_Num, Program, email)
        VALUES ('$last_name', '$first_name', '$middle_initial', '$sex', $age, '$civil_status', '$address', '$contact_number', '$emergency_number', '$guardian', $height, $weight, '$year_level', '$special_cases', '$StudentNo', '$Program', '$email')";

if ($connection->query($sql) === TRUE) {
    echo json_encode(['success' => true, 'message' => "New patient added successfully!"]);
} else {
    echo json_encode(['success' => false, 'message' => "Database error: " . $connection->error]);
}

$connection->close();
?>
