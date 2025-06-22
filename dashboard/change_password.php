<?php
session_start();
ob_start(); // Start output buffering

// Ensure the user is logged in
if (!isset($_SESSION['email'])) {
    header('Location: ../employee_login.php'); // Redirect to login if not logged in
    exit();
}

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ottest";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch the logged-in user's details using a prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = :email");
    $stmt->bindParam(':email', $_SESSION['email'], PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $user['id'];  // Get the user ID from the users table
        $currentPasswordHash = $user['password'];
    } else {
        echo "<p>Error: User details not found. Please contact support.</p>";
        exit();
    }

} catch(PDOException $e) {
    echo "<p style='color:red;'>Error: Unable to connect to the database. Please try again later.</p>";
    exit();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Verify current password
    if (password_verify($currentPassword, $currentPasswordHash)) {
        // Check if new passwords match
        if ($newPassword === $confirmPassword) {
            // Hash the new password
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update the password in the database
            $updateStmt = $conn->prepare("UPDATE users SET password = :new_password WHERE id = :user_id");
            $updateStmt->bindParam(':new_password', $newPasswordHash, PDO::PARAM_STR);
            $updateStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $updateStmt->execute();

            // Redirect to the login page after successful password change
            header('Location: ../employee_login.php');
            exit();
        } else {
            echo "<p style='color:red;'>Error: New passwords do not match.</p>";
        }
    } else {
        echo "<p style='color:red;'>Error: Current password is incorrect.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="includes/images/favicon.ico">
    <title>Change Password</title>
    <link rel="stylesheet" href="includes/styles.css">
    <style>
        body {
            background-color: #f4f4f4;
            font-family: Arial, sans-serif;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .bg-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
        }

        .change-password-container {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            color: #003b5c;
        }

        .change-password-container h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .input-container {
            position: relative;
            margin-bottom: 15px;
        }

        .input-container input[type="password"],
        .input-container input[type="text"] {
            width: 100%;
            padding: 10px;
            padding-right: 40px; /* Add extra padding to make space for the icon */
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
            box-sizing: border-box;
        }

        .password-visibility-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 18px;
            user-select: none;
        }

        .change-password-container input[type="submit"] {
            background-color: #29ABE2;
            color: white;
            border: none;
            cursor: pointer;
            padding: 10px;
            border-radius: 5px;
            font-size: 16px;
        }

        .change-password-container input[type="submit"]:hover {
            background-color: #1d89b2;
        }

        #password-strength {
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }

        #password-match-message {
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <img src="includes/images/passwordbg2.jpg" alt="Background Image" class="bg-image">
    <div class="change-password-container">
        <h2>Change Password</h2>
        <form action="" method="post">
            <div class="input-container">
                <label for="current_password">Current Password:</label>
                <input type="password" id="current_password" name="current_password" required>
                <span class="password-visibility-toggle" onclick="toggleVisibility('current_password');">👁️</span>
            </div>

            <div class="input-container">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required onkeyup="checkPasswordStrength();">
                <span class="password-visibility-toggle" onclick="toggleVisibility('new_password');">👁️</span>
            </div>
            <div id="password-strength"></div> <!-- Password Strength Indicator -->

            <div class="input-container">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required onkeyup="checkPasswordMatch();">
                <span class="password-visibility-toggle" onclick="toggleVisibility('confirm_password');">👁️</span>
            </div>
            <div id="password-match-message"></div> <!-- Password Match Message -->

            <input type="submit" name="change_password" value="Change Password">
        </form>
    </div>

    <script>
        function toggleVisibility(fieldId) {
            var field = document.getElementById(fieldId);
            if (field.type === "password") {
                field.type = "text";
            } else {
                field.type = "password";
            }
        }

        function checkPasswordStrength() {
            var strengthBar = document.getElementById('password-strength');
            var password = document.getElementById('new_password').value;
            var strength = 0;

            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[$@#&!]+/)) strength += 1;

            switch(strength) {
                case 0:
                    strengthBar.innerHTML = "";
                    break;
                case 1:
                    strengthBar.innerHTML = "Weak";
                    strengthBar.style.color = "red";
                    break;
                case 2:
                    strengthBar.innerHTML = "Better";
                    strengthBar.style.color = "orange";
                    break;
                case 3:
                    strengthBar.innerHTML = "Medium";
                    strengthBar.style.color = "yellow";
                    break;
                case 4:
                    strengthBar.innerHTML = "Strong";
                    strengthBar.style.color = "green";
                    break;
                case 5:
                    strengthBar.innerHTML = "Very Strong";
                    strengthBar.style.color = "darkgreen";
                    break;
            }
        }

        function checkPasswordMatch() {
            var newPassword = document.getElementById('new_password').value;
            var confirmPassword = document.getElementById('confirm_password').value;
            var matchMessage = document.getElementById('password-match-message');

            if (newPassword === confirmPassword) {
                matchMessage.style.color = "green";
                matchMessage.innerHTML = "Passwords match.";
            } else {
                matchMessage.style.color = "red";
                matchMessage.innerHTML = "Passwords do not match.";
            }
        }

        // Disable right-click and common inspect element shortcuts
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });

        document.onkeydown = function(e) {
            if (e.key === "F12" || 
                (e.ctrlKey && e.shiftKey && e.key === "I") || 
                (e.ctrlKey && e.shiftKey && e.key === "J") || 
                (e.ctrlKey && e.key === "U") || 
                (e.ctrlKey && e.key === "S")) {
                return false;
            }
        };
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
