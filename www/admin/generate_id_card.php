<?php
session_start();

// Check if the user is logged in (optional, but good practice for any page accessing patient data)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit();
}

// Include the TCPDF library
// Adjust this path if your tcpdf.php is located elsewhere
require_once('tcpdf/tcpdf.php'); // Assuming TCPDF is in a folder named 'tcpdf' in your project root

$servername = "localhost";
$username = "root";
$password = "";
$database = "clinic_data";

$connection = new mysqli($servername, $username, $password, $database);

if ($connection->connect_error) {
    die("Connection Failed: " . $connection->connect_error);
}

$patientID_from_get = "";
$patient = null;
$imagePath = __DIR__ . "/uploads/default.png"; // Default image path for the ID card

if (isset($_GET['id'])) {
    $patientID_from_get = $connection->real_escape_string($_GET['id']);

    // Fetch patient details using Student_Num
    $patientQuery = "SELECT * FROM patients WHERE Student_Num = '$patientID_from_get'";
    $patientResult = $connection->query($patientQuery);

    if ($patientResult->num_rows > 0) {
        $patient = $patientResult->fetch_assoc();
        $p_id_actual = $patient['PatientID']; // Get the actual PatientID for photo

        // Determine image path for the patient's photo
        foreach (['png', 'jpg', 'jpeg'] as $ext) {
            $tempPath = __DIR__ . "/uploads/patient_" . $p_id_actual . "." . $ext;
            if (file_exists($tempPath)) {
                $imagePath = $tempPath;
                break;
            }
        }

    } else {
        echo "Patient not found.";
        exit();
    }
} else {
    echo "No Patient ID provided.";
    exit();
}

// Create new PDF document
// Custom Unit: 1 inch = 72 points
// Page format: [width, height] in points.
// For a standard ID card size (3.375 x 2.125 inches), this is [243, 153] points.
// Let's make it a bit larger for clarity if needed, or stick to actual size.
// Using 3.5in x 2.25in for front and back, so [252, 162] points
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, array(252, 162), true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('UDM Clinic Cares System');
$pdf->SetTitle('Medical ID Card');
$pdf->SetSubject('Patient Medical ID Card');
$pdf->SetKeywords('ID Card, Medical, Patient, UDM Clinic');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins (left, top, right)
$pdf->SetMargins(5, 5, 5); // Small margins for ID card

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 5); // 5mm bottom margin

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
    require_once(dirname(__FILE__).'/lang/eng.php');
    $pdf->setLanguageArray($l);
}

// ---------------------------------------------------------

// Add a page for the Front of the ID Card
$pdf->AddPage();

// --- Front of the ID Card Layout ---

// Border for the card (optional, can be done with a rectangle)
$pdf->SetDrawColor(150, 150, 150);
$pdf->RoundedRect(0.5, 0.5, 251, 161, 3, '1111', 'D'); // Outer border

// Header Section
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(0, 102, 204); // Dark blue for clinic name
$pdf->SetY(8);
$pdf->Cell(0, 0, 'UDM Clinic Cares', 0, 1, 'C', 0, '', 0, false, 'T', 'M');

$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(0, 102, 204); // Same blue
$pdf->Cell(0, 0, 'Medical ID Card', 0, 1, 'C', 0, '', 0, false, 'T', 'M');

// Content Section: Photo and Info
$pdf->SetY(25); // Adjust Y position for photo/info block

// Patient Photo (Left side)
// TCPDF's Image function: Image(file, x, y, w, h, type, link, align, resize, dpi, palign, ismask, imgmask, border, fitbox, hidden, fitonpage, pgroup, jpegquality)
$photoWidth = 40; // in mm
$photoHeight = 40; // in mm
$photoX = 10; // X position from left margin
$photoY = 30; // Y position from top margin
$pdf->Image($imagePath, $photoX, $photoY, $photoWidth, $photoHeight, '', '', 'T', false, 300, '', false, false, 1, false, false, false, true);


// Patient Info (Right of photo)
$infoX = $photoX + $photoWidth + 5; // Start X position after photo + some padding
$infoY = $photoY;
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(51, 51, 51); // Dark grey for text

$pdf->SetXY($infoX, $infoY);
$pdf->writeHTMLCell(0, 0, '', '', '<strong>Name:</strong> ' . htmlspecialchars($patient['FirstName']) . ' ' . htmlspecialchars($patient['MiddleInitial']) . ' ' . htmlspecialchars($patient['LastName']), 0, 1, 0, true, 'L', true);
$pdf->SetX($infoX);
$pdf->writeHTMLCell(0, 0, '', '', '<strong>Student No:</strong> ' . htmlspecialchars($patient['Student_Num']), 0, 1, 0, true, 'L', true);
$pdf->SetX($infoX);
$pdf->writeHTMLCell(0, 0, '', '', '<strong>Patient ID:</strong> ' . htmlspecialchars($patient['PatientID']), 0, 1, 0, true, 'L', true);
$pdf->SetX($infoX);
$pdf->writeHTMLCell(0, 0, '', '', '<strong>Program:</strong> ' . htmlspecialchars($patient['Program']), 0, 1, 0, true, 'L', true);
$pdf->SetX($infoX);
$pdf->writeHTMLCell(0, 0, '', '', '<strong>Year Level:</strong> ' . htmlspecialchars($patient['yearLevel']), 0, 1, 0, true, 'L', true);
$pdf->SetX($infoX);
$pdf->writeHTMLCell(0, 0, '', '', '<strong>Sex:</strong> ' . htmlspecialchars($patient['Sex']), 0, 1, 0, true, 'L', true);
$pdf->SetX($infoX);
$pdf->writeHTMLCell(0, 0, '', '', '<strong>Age:</strong> ' . htmlspecialchars($patient['age']), 0, 1, 0, true, 'L', true);


// Footer Section for front
$pdf->SetY($pdf->GetY() + 5); // Move below the info section
$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(102, 102, 102);
$pdf->Cell(0, 0, 'University of the City of Manila Clinic', 0, 1, 'C', 0, '', 0, false, 'T', 'M');
$pdf->Cell(0, 0, 'Contact: ' . htmlspecialchars($patient['ContactNumber']), 0, 1, 'C', 0, '', 0, false, 'T', 'M');


// Add a new page for the Back of the ID Card
$pdf->AddPage();

// Border for the card (optional, can be done with a rectangle)
$pdf->SetDrawColor(150, 150, 150);
$pdf->RoundedRect(0.5, 0.5, 251, 161, 3, '1111', 'D'); // Outer border

// --- Back of the ID Card Layout ---

// Header for back
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(0, 102, 204);
$pdf->SetY(8);
$pdf->Cell(0, 0, 'Emergency Contact', 0, 1, 'C', 0, '', 0, false, 'T', 'M');


// Emergency Info
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(85, 85, 85);
$pdf->SetY(25);
$pdf->writeHTMLCell(0, 0, '', '', '<p style="text-align: center;"><strong>Emergency No:</strong> ' . htmlspecialchars($patient['emergency_number']) . '</p>', 0, 1, 0, true, 'L', true);
$pdf->writeHTMLCell(0, 0, '', '', '<p style="text-align: center;"><strong>Special Cases:</strong> ' . htmlspecialchars($patient['specialCases']) . '</p>', 0, 1, 0, true, 'L', true);
$pdf->SetY($pdf->GetY() + 5); // Add some space
$pdf->writeHTMLCell(0, 0, '', '', '<p style="text-align: center;">For medical emergencies, please contact the UDM Clinic or the emergency contact listed above.</p>', 0, 1, 0, true, 'L', true);


// QR Code Generation
$qrData = "Patient ID: " . $p_id_actual . "\n" .
          "Name: " . $patient['FirstName'] . " " . $patient['LastName'] . "\n" .
          "Student No: " . $patient['Student_Num'] . "\n" .
          "Emergency No: " . $patient['emergency_number'];

// Set QR code style
$style = array(
    'border' => 1,
    'vpadding' => 'auto',
    'hpadding' => 'auto',
    'fgcolor' => array(0,0,0),
    'bgcolor' => array(255,255,255),
    'module_width' => 1, // width of a single module in points
    'module_height' => 1 // height of a single module in points
);

// Write 2D Barcode (QR CODE)
// write2DBarcode(code, type, x, y, w, h, style, align, dist=false)
$qrSize = 50; // in mm
$qrX = ($pdf->getPageWidth() - $qrSize) / 2; // Center horizontally
$qrY = $pdf->GetY() + 5; // Position below text
$pdf->write2DBarcode($qrData, 'QRCODE,H', $qrX, $qrY, $qrSize, $qrSize, $style, 'N');


$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(102, 102, 102);
$pdf->SetY($qrY + $qrSize + 5); // Position below QR code
$pdf->Cell(0, 0, 'Scan for Patient Quick Info', 0, 1, 'C', 0, '', 0, false, 'T', 'M');


// Footer Section for back
$pdf->SetY($pdf->GetY() + 5); // Move below the QR code info
$pdf->Cell(0, 0, 'UDM Clinic Cares System', 0, 1, 'C', 0, '', 0, false, 'T', 'M');
$pdf->Cell(0, 0, 'Developed for the University of the City of Manila', 0, 1, 'C', 0, '', 0, false, 'T', 'M');

// ---------------------------------------------------------

// Close and output PDF document
$pdf->Output("Medical_ID_Card_" . $patientID_from_get . ".pdf", 'I'); // 'I' for inline display in browser

$connection->close();
?>