<?php
    // Verification process
    if (isset($_POST["verify_email"])) {
        $email = $_POST["email"];
        $verification_code = $_POST["verification_code"];

        // Connect with the database
        $conn = mysqli_connect("localhost", "root", "", "clinic_data");

        // Mark email as verified
        $sql = "UPDATE account SET email_verified_at = NOW() WHERE email = '" . $email . "' AND verification_code = '" . $verification_code . "'";
        $result = mysqli_query($conn, $sql);

        if (mysqli_affected_rows($conn) == 0) {
            die("Verification code failed.");
        } else {
            // Only delete the verification code (set it to NULL)
            $sql_delete = "UPDATE account SET verification_code = NULL WHERE email = '" . $email . "'";
            mysqli_query($conn, $sql_delete);
        }

        header("Location: forgotPassword.php");
        exit();
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
    <link rel="stylesheet" href="../assets/css/all.min.css">
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
              <form method="POST">
                <input type="hidden" name="email" value="<?php echo $_GET['email']; ?>" required>
                <input type="text" name="verification_code" placeholder="Enter verification code" required />
            
                <input type="submit" name="verify_email" value="Verify">
              </form>
            </div>
        </div>
</body>
</html>
