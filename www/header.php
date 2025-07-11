<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDM Clinic Header</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f0f8ff; /* Light blue background */
        }
        .header {
            position: fixed; /* Make the header fixed at the top */
            top: 0;
            left: 0;
            width: 100%; /* Ensure it spans the full width */
            background-color: #20B2AA; /* Sea green background */
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            z-index: 1000; /* Ensure it stays above other content */
        }
        .header img {
            width: 70px; /* Adjust logo size */
            height: auto;
            margin-right: 15px; /* Space between logo and text */
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        /* Add some margin for content below the fixed header */
        .content {
            margin-top: 100px; /* Adjust to match the height of the header */
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header">
        <img src="images/UDMCLINIC_LOGO.png" alt="UDM Logo">
        <h1>UDM CLINIC</h1>
    </div>
</body>
</html>
