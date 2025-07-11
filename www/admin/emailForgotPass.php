<?php
    //Import PHPMailer classes into the global namespace
    //These must be at the top of your script, not inside a function
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
 
    //Load Composer's autoloader
    require 'vendor/autoload.php';
 
    if (isset($_POST["register"]))
    {
        $name = $_POST["name"];
        $email = $_POST["email"];
        $password = $_POST["password"];
 
        //Instantiation and passing `true` enables exceptions
        $mail = new PHPMailer(true);
 
        try {
            //Enable verbose debug output
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
 
            //Send using SMTP
            $mail->isSMTP();
 
            //Set the SMTP server to send through
            $mail->Host = 'smtp.gmail.com';
 
            //Enable SMTP authentication
            $mail->SMTPAuth = true;
 
            //SMTP username
            $mail->Username = 'tryndayasou50@gmail.com ';
 
            //SMTP password
            $mail->Password = 'eyflojwgrxlysfxj';
 
            //Enable TLS encryption;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
 
            //TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
            $mail->Port = 465;
 
            //Recipients
            $mail->setFrom('tryndayasou50@gmail.com', 'clinic_UDM.com');
 
            //Add a recipient
            $mail->addAddress($email, $name);
 
            //Set email format to HTML
            $mail->isHTML(true);
 
            $verification_code = substr(number_format(time() * rand(), 0, '', ''), 0, 6);
 
            $mail->Subject = 'Email verification';
            $mail->Body    = '<p>Your verification code is: <b style="font-size: 30px;">' . $verification_code . '</b></p>';
 
            $mail->send();
            // echo 'Message has been sent';
 
            $encrypted_password = password_hash($password, PASSWORD_DEFAULT);
 
            // connect with database
            $conn = mysqli_connect("localhost", "root", "", "clinic_data");
 
            // insert in users table
            $sql = "INSERT INTO account(name, email, password, verification_code, email_verified_at) VALUES ('" . $name . "', '" . $email . "', '" . $encrypted_password . "', '" . $verification_code . "', NULL)";
            mysqli_query($conn, $sql);
 
            header("Location: otpForgotPass.php?email=" . $email);
            exit();
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/v2/all.min.css">
    <style>
form {
  display: flex; /* Arrange elements horizontally */
  flex-direction: column; /* Stack elements vertically */
  margin: 20px auto; /* Add some margin for spacing */
  width: 300px; /* Set form width */
  padding: 20px; /* Add padding for better spacing */
  border: 1px solid #ccc; /* Add a thin border */
  border-radius: 5px; /* Rounded corners for a smoother look */
}

input[type="text"],
input[type="submit"] {
  padding: 10px; /* Add padding to input fields and submit button */
  border: 1px solid #ccc; /* Border for input fields and button */
  border-radius: 3px; /* Rounded corners for input fields and button */
  margin-bottom: 10px; /* Add some space between elements */
}

input[type="submit"] {
  background-color: #4CAF50; /* Green color for submit button */
  color: white; /* White text for submit button */
  cursor: pointer; /* Change cursor to pointer on hover */
}   
</style>
</head>
<body>
        <div class="content-container">   
            <div class="container my-5">
                <div class="container">
                    <h2 class="text-center">Verify Your Email</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" name="email" id="email" class="form-control" required>
                        </div>
                        <button type="submit" name="register" class="btn btn-primary btn-block">Verify Email</button>
                    </form>
                    </form>
                </div>
            </div>
        </div>
</body>
</html>