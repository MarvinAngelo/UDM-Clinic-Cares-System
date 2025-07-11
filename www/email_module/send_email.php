<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; 

$conn = new mysqli('localhost', 'root', '', 'clinic_data');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $recipient = $data['email'];
    $subject = $data['subject'];
    $message = $data['message'];

    $mail = new PHPMailer(true);
    $response = array();

    try {
        // Server settings
        $mail->SMTPDebug = 0;                      // Disable debug output
        $mail->isSMTP();                           
        $mail->Host       = 'smtp.gmail.com';      
        $mail->SMTPAuth   = true;                  
        $mail->Username   = 'udmclinic2022@gmail.com'; 
        $mail->Password   = 'hbmiymyvpuojeszc';    
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;                   

        // Recipients
        $mail->setFrom('udmclinic2022@gmail.com', 'UDM Clinic');
        $mail->addAddress($recipient);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();
        $status = 'success';
        $response = array(
            'status' => 'success',
            'message' => 'Email sent successfully'
        );
        
    } catch (Exception $e) {
        $status = 'failed';
        $response = array(
            'status' => 'error',
            'message' => 'Email could not be sent. Error: ' . $mail->ErrorInfo
        );
    }

    // Log the email attempt
    try {
        $stmt = $conn->prepare("INSERT INTO email_logs (recipient_email, subject, message, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $recipient, $subject, $message, $status);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Log database errors but don't show to user
        error_log("Database error: " . $e->getMessage());
    }

    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

$conn->close();
?>