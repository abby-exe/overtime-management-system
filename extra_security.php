<?php
session_start();
include('config.php'); // Include the config file with the hashed password

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input_password = $_POST['security_password'];

    // Verify the input password against the stored hash
    if (password_verify($input_password, $admin_security_password_hash)) {
        $_SESSION['security_verified'] = true; // Set session variable to indicate successful verification
        header("Location: admin.php"); // Redirect to admin page
        exit;
    } else {
        $error = "Incorrect security password. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="images/favicon.ico">
    <title>Admin Security Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
        }

        .login-box {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .login-box h1 {
            margin-bottom: 20px;
            font-size: 1.8em;
            color: #003b5c;
            border-bottom: 2px solid #29ABE2;
            padding-bottom: 10px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #003b5c;
            text-align: left;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 1em;
            box-sizing: border-box;
        }

        input[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #29ABE2;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        input[type="submit"]:hover {
            background-color: #1d89b2;
        }

        p.error {
            color: red;
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 20px;
        }

        /* Responsive styling */
        @media only screen and (max-width: 600px) {
            .login-box {
                padding: 20px;
                width: 90%; /* Ensure it fits within smaller screens */
            }

            .login-box h1 {
                font-size: 1.5em;
                padding-bottom: 8px;
            }

            label {
                font-size: 0.9em;
            }

            input[type="password"], input[type="submit"] {
                padding: 10px;
                font-size: 0.9em;
            }

            p.error {
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Admin Security Verification</h1>
        <form action="" method="post">
            <label for="security_password">Security Password:</label>
            <input type="password" id="security_password" name="security_password" required>

            <input type="submit" value="Verify">
        </form>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
    </div>
</body>
</html>
