<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php?error=" . urlencode("Please log in to access this page."));
    exit();
}

require_once('tcpdf/tcpdf.php');
require_once 'phpqrcode/qrlib.php';

$connection = new mysqli("localhost", "root", "", "clinic_data");

if ($connection->connect_error) {
    die("Connection Failed: " . $connection->connect_error);
}

$sql = "SELECT PatientID, Student_Num, FirstName, MiddleInitial, LastName, Program FROM patients ORDER BY Program ASC, LastName ASC, FirstName ASC";
$result = $connection->query($sql);

$patients_by_program = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $program = $row['Program'];
        $patients_by_program[$program][] = $row;
    }
}

$custom_page_format = array(215.9, 330.2); // 8.5x13 in mm

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, $custom_page_format, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('UDM Clinic Cares System');
$pdf->SetTitle('Patient QR Codes');
$pdf->SetPrintHeader(false);
$pdf->SetPrintFooter(false);
$pdf->SetAutoPageBreak(TRUE, 10); // margin bottom

// Layout constants
$cols_per_page = 5;
$rows_per_page = 6;
$qr_image_size = 30;
$text_line_height = 5;
$text_lines = 2;
$text_block_height = $text_line_height * $text_lines;
$cell_padding_x = 2;
$cell_padding_y = 2;

$qr_cell_width = $qr_image_size + ($cell_padding_x * 2);
$qr_cell_height = $qr_image_size + $text_block_height + ($cell_padding_y * 3);
$page_margin_x = 10;
$page_margin_y = 10;
$title_area_height = 25;

$available_width = $pdf->getPageWidth() - (2 * $page_margin_x);
$available_height = $pdf->getPageHeight() - (2 * $page_margin_y) - $title_area_height;

$horizontal_spacing = max(2, ($available_width - ($cols_per_page * $qr_cell_width)) / ($cols_per_page - 1));
$vertical_spacing = max(2, ($available_height - ($rows_per_page * $qr_cell_height)) / ($rows_per_page - 1));

foreach ($patients_by_program as $program_name => $patients_in_program) {
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 15, 'QR Codes for ' . htmlspecialchars($program_name) . ' Program', 0, 1, 'C');
    $start_content_y = $pdf->GetY() + 5;
    $pdf->SetFont('helvetica', '', 8);

    $current_col = 0;
    $current_row = 0;

    foreach ($patients_in_program as $index => $patient) {
        // Force page break if about to exceed row limit
        if ($current_row >= $rows_per_page) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 15, 'QR Codes for ' . htmlspecialchars($program_name) . ' Program (Cont.)', 0, 1, 'C');
            $start_content_y = $pdf->GetY() + 5;
            $pdf->SetFont('helvetica', '', 8);
            $current_row = 0;
            $current_col = 0;
        }

        $cell_x = $page_margin_x + ($current_col * ($qr_cell_width + $horizontal_spacing));
        $cell_y = $start_content_y + ($current_row * ($qr_cell_height + $vertical_spacing));

        $pdf->Rect($cell_x, $cell_y, $qr_cell_width, $qr_cell_height, 'D');

        $qr_x = $cell_x + $cell_padding_x + (($qr_cell_width - (2 * $cell_padding_x) - $qr_image_size) / 2);
        $qr_y = $cell_y + $cell_padding_y;

        $qrData = "STUDENT-" . $patient['PatientID'];
        $qrFileName = 'qrcodes/patient_' . $patient['PatientID'] . '.png';

        if (!file_exists('qrcodes')) {
            mkdir('qrcodes', 0777, true);
        }
        if (!file_exists($qrFileName)) {
            QRcode::png($qrData, $qrFileName, QR_ECLEVEL_L, 4, 2);
        }

        $pdf->Image($qrFileName, $qr_x, $qr_y, $qr_image_size, $qr_image_size, 'PNG', '', '', false, 300, '', false, false, 1);

        $fullName = htmlspecialchars($patient['FirstName'] . " " . (!empty($patient['MiddleInitial']) ? $patient['MiddleInitial'] . " " : "") . $patient['LastName']);
        $studentNum = htmlspecialchars($patient['Student_Num']);
        $text_block_x = $cell_x + $cell_padding_x;
        $text_block_y = $qr_y + $qr_image_size + $cell_padding_y;

        $pdf->SetXY($text_block_x, $text_block_y);
        $pdf->MultiCell($qr_cell_width - (2 * $cell_padding_x), $text_block_height, $fullName . "\n" . $studentNum, 1, 'C', 0, 1, '', '', true);

        $current_col++;
        if ($current_col >= $cols_per_page) {
            $current_col = 0;
            $current_row++;
        }
    }
}

$pdf->Output('patient_qr_codes.pdf', 'I');
$connection->close();
?>
