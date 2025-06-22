<?php
ob_start(); // Start output buffering
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="images/favicon.ico">
    <title>Team Lead Login</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            position: relative;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            overflow-x: hidden; /* Prevent horizontal scroll */
        }

        /* Background image with reduced opacity */
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('images/loginbg.jpg'); /* Path to your background image */
            background-size: cover; /* Ensure the image covers the entire background */
            background-position: center; /* Center the background image */
            background-repeat: no-repeat; /* Prevent the image from repeating */
            opacity: 0.3; /* Reduce opacity of the background image */
            z-index: -1; /* Place the background behind everything else */
        }

        .login-box {
            background-color: rgba(255, 255, 255, 0.9); /* White background with transparency */
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
            margin-top: 30px; /* Added space between the header and login box */
        }

        header {
            background-color: rgba(41, 171, 226, 0.9); /* Slightly transparent blue background */
            width: 100%;
            padding: 15px;
            text-align: center;
            box-sizing: border-box;
            font-size: 1.5em;
            font-weight: bold;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
        }

        h1 {
            margin-top: 80px; /* Added space to avoid overlap with the header */
            color: #003b5c;
        }

        label {
            color: #003b5c;
            font-weight: bold;
            margin-top: 10px;
            display: block;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px 0;
            border: 1px solid #003b5c;
            border-radius: 5px;
            box-sizing: border-box;
        }

        input[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: #003b5c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #002a47;
        }

        .back-button {
            position: absolute;
            bottom: 40px;
            left: 20px;
            padding: 10px 20px;
            background-color: #003b5c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .back-button:hover {
            background-color: #002d45;
        }

        footer {
            background-color: rgba(41, 171, 226, 0.9);
            padding: 10px;
            color: #ffffff;
            text-align: center;
            width: 100%;
            position: fixed;
            bottom: 0;
            left: 0;
        }

        /* Media Queries for responsiveness */
        @media (max-width: 768px) {
            h1 {
                margin-top: 60px; /* Adjust the margin for smaller screens */
                font-size: 1.2em;
            }

            header {
                padding: 10px;
                font-size: 1.2em;
            }

            .login-box {
                max-width: 90%; /* Allow more space on smaller screens */
                padding: 15px;
            }

            input[type="text"],
            input[type="password"] {
                padding: 8px;
                font-size: 14px;
            }

            input[type="submit"] {
                padding: 8px;
                font-size: 14px;
            }

            .back-button {
                padding: 8px 16px;
                font-size: 14px;
            }

            footer {
                font-size: 12px;
                padding: 8px;
            }
        }

        @media (max-width: 480px) {
            h1 {
                margin-top: 50px; /* Adjust the margin further for very small screens */
                font-size: 1em;
            }

            header {
                padding: 8px;
                font-size: 1em;
            }

            .login-box {
                max-width: 90%; /* Same as the Employee login box */
                padding: 15px; /* Ensure padding is consistent */
            }

            input[type="text"],
            input[type="password"] {
                padding: 6px;
                font-size: 12px;
            }

            input[type="submit"] {
                padding: 6px;
                font-size: 12px;
            }

            .back-button {
                padding: 6px 12px;
                font-size: 12px;
            }

            footer {
                font-size: 10px;
                padding: 6px;
            }
        }
    </style>
    <script>
        // Disable right-click
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });

        // Disable common keyboard shortcuts
        document.onkeydown = function(e) {
            if (e.key === "F12" || 
                (e.ctrlKey && e.shiftKey && e.key === "I") || 
                (e.ctrlKey && e.shiftKey && e.key === "J") || 
                (e.ctrlKey && e.key === "U") || 
                (e.ctrlKey && e.key === "S")) {
                return false;
            }
        };

        // Toggle Password Visibility
        function togglePassword() {
            var passwordField = document.getElementById("password");
            if (passwordField.type === "password") {
                passwordField.type = "text";
            } else {
                passwordField.type = "password";
            }
        }
    </script>
</head>
<body>
    <!-- Header -->
    <header>
        Solara Overtime Management System
    </header>

    <h1>Team Lead Login</h1>
    <div class="login-box">
        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Database connection details
            $servername = "localhost";
            $username = "root";
            $password = "";
            $dbname = "ottest";

            try {
                // Create connection using PDO
                $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
                // Set the PDO error mode to exception
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Prepare the SQL statement to fetch the hashed password and role
                $stmt = $conn->prepare("SELECT password, role FROM users WHERE email = :email");
                $stmt->bindParam(':email', $_POST['email']);
                $stmt->execute();

                // Check if the user exists
                if ($stmt->rowCount() > 0) {
                    // Fetch the stored hashed password and role
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $hashed_password = $row['password'];
                    $role = $row['role'];

                    // Verify the entered password against the hashed password
                    if (password_verify($_POST['password'], $hashed_password)) {
                        if ($role === 'Team Lead') {
                            // Set session variables and redirect to the team lead dashboard
                            session_start();
                            $_SESSION['email'] = $_POST['email'];
                            $_SESSION['role'] = $role;
                            header("Location: dashboard/teamlead_dashboard.php");
                            exit;
                        } else {
                            echo "<p style='color:red;'>Access denied. Only Team Leads are allowed to log in.</p>";
                        }
                    } else {
                        echo "<p style='color:red;'>Invalid email or password. Please try again.</p>";
                    }
                } else {
                    echo "<p style='color:red;'>Invalid email or password. Please try again.</p>";
                }
            } catch(PDOException $e) {
                echo "Error: " . $e->getMessage();
            }

            // Close connection
            $conn = null;
        }
        ?>

        <form action="" method="post">
            <label for="email">Email:</label><br>
            <input type="text" id="email" name="email" required><br><br>

            <label for="password">Password:</label><br>
            <input type="password" id="password" name="password" required><br><br>

            <!-- Container for Checkbox and Label to align them horizontally -->
            <div class="checkbox-container">
                <input type="checkbox" id="show-password" onclick="togglePassword()"> 
                <label for="show-password">Show Password</label>
            </div><br>
            
            <input type="submit" value="Login">
        </form>
    </div>
    <button class="back-button" onclick="window.location.href='index.html'">← Back</button>

    <!-- Footer -->
    <footer>
        &copy; 2024 Solara Systems (M) Sdn.Bhd
    </footer>
</body>
</html>

<?php
ob_end_flush(); // Flush the output buffer and send output
?>
