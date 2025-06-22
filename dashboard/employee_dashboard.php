<?php
session_start();
ob_start(); // Start output buffering

// Ensure the user is logged in
if (!isset($_SESSION['email'])) {
    header('Location: employee_login.php'); // Redirect to login if not logged in
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
    $stmt = $conn->prepare("SELECT id, name, ic_number, email, department FROM users WHERE email = :email");
    $stmt->bindParam(':email', $_SESSION['email'], PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $user['id'];  // Get the user ID from the users table
        $employeeName = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
        $employeeIC = htmlspecialchars($user['ic_number'], ENT_QUOTES, 'UTF-8');
        $employeeEmail = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
        $employeeDepartment = htmlspecialchars($user['department'], ENT_QUOTES, 'UTF-8');
    } else {
        echo "<p>Error: User details not found. Please contact support.</p>";
        exit();
    }

} catch(PDOException $e) {
    echo "<p style='color:red;'>Error: Unable to connect to the database. Please try again later.</p>";
    exit();
}

// Handle OT submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ot'])) {
    $timeIn = $_POST['time_in'];
    $timeOut = $_POST['time_out'];
    $site = $_POST['site'];
    $date = $_POST['date'];

    // Check if the same time for the same date already exists for this user
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM ot_submissions WHERE user_id = :user_id AND date = :date AND time_in = :time_in AND time_out = :time_out");
    $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $checkStmt->bindParam(':date', $date);
    $checkStmt->bindParam(':time_in', $timeIn);
    $checkStmt->bindParam(':time_out', $timeOut);
    $checkStmt->execute();
    $exists = $checkStmt->fetchColumn();

    if ($exists) {
        echo "<p style='color:red;'>Warning: This time entry already exists for the selected date.</p>";
    } else {
        $stmt = $conn->prepare("INSERT INTO ot_submissions (user_id, time_in, time_out, site, date) VALUES (:user_id, :time_in, :time_out, :site, :date)");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);  // Bind the user ID
        $stmt->bindParam(':time_in', $timeIn);
        $stmt->bindParam(':time_out', $timeOut);
        $stmt->bindParam(':site', $site);
        $stmt->bindParam(':date', $date);
        $stmt->execute();

        // Redirect to avoid form resubmission issues
        header('Location: employee_dashboard.php');
        exit();
    }
}

// Fetch OT submissions for the logged-in user
$selectedDate = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$otStmt = $conn->prepare("SELECT * FROM ot_submissions WHERE user_id = :user_id" . ($selectedDate ? " AND date = :date" : ""));
$otStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
if ($selectedDate) {
    $otStmt->bindParam(':date', $selectedDate);
}
$otStmt->execute();
$otSubmissions = $otStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="includes/images/favicon.ico">
    <title>Employee Dashboard</title>
    <link rel="stylesheet" href="includes/styles.css"> <!-- Link to your external CSS -->
    <style>
        body {
            background: url('includes/images/dashbg.jpg') no-repeat center center fixed; /* Inline background image */
            background-size: cover; /* Ensure it fits all screen sizes */
            color: #003b5c;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            text-align: center;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.7); /* White overlay with reduced opacity */
            z-index: -1; /* Ensure it is behind all content */
        }

        /* OT Submission Form Styles */
        .ot-form-container {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            color: #003b5c; /* Keep consistent with your theme */
        }

        .ot-form-container input[type="text"],
        .ot-form-container input[type="time"],
        .ot-form-container select,
        .ot-form-container input[type="date"] {
            width: 100%;
            padding: 12px; /* Increased padding for better spacing */
            margin-bottom: 15px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px; /* Ensure the font size is consistent */
            box-sizing: border-box; /* Make sure padding is included in the width */
            appearance: none; /* Ensure cross-browser consistency */
        }

        /* Time inputs easier to use on phones */
        @media (max-width: 600px) {
            .ot-form-container input[type="time"] {
                font-size: 18px; /* Larger font size on mobile */
                padding: 15px; /* More padding on mobile */
            }
        }

        .ot-form-container input[type="submit"],
        .ot-form-container button[type="button"] {
            background-color: #29ABE2;
            color: white;
            border: none;
            padding: 12px; /* Increased padding for consistency */
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px; /* Ensure font size is consistent */
            margin-bottom: 10px; /* Space between the buttons */
        }

        .ot-form-container input[type="submit"]:hover,
        .ot-form-container button[type="button"]:hover {
            background-color: #1d89b2;
        }

        /* OT Submissions Table */
        .ot-submissions-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
            color: #003b5c;
        }

        .ot-submissions-table th, .ot-submissions-table td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
        }

        .ot-submissions-table th {
            background-color: #29ABE2; /* Header with your theme color */
            color: #ffffff; /* White text */
        }

        .ot-submissions-table tr:nth-child(even) {
            background-color: #f2f2f2; /* Light grey for even rows */
        }

        .ot-submissions-table tr:hover {
            background-color: #d3eaf2; /* Light blue on hover */
        }

        /* Submission Button Styling */
        .dashboard-main button {
            background-color: #29ABE2;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 20px 0;
            font-size: 16px;
        }

        .dashboard-main button:hover {
            background-color: #1d89b2;
        }

        /* Style for the date filter input */
        .date-filter {
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-start; /* Align to the left */
            padding-left: 1px; /* Optional: Add padding if you want to align it further to the left */
        }

        .date-filter input[type="date"] {
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .date-filter button {
            background-color: #29ABE2;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
        }

        .date-filter button:hover {
            background-color: #1d89b2;
        }

        /* Sidebar Styling */
        .employee-info-slider {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 20px;
            height: 100vh;
            box-sizing: border-box;
        }

        .employee-info-content {
            flex-grow: 1;
        }

        .change-password-button {
            display: block;
            text-align: center;
            background-color: #29ABE2;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            margin-top: 20px;
            font-size: 16px;
        }

        .change-password-button:hover {
            background-color: #1d89b2;
        }

    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <button class="hamburger-button" onclick="openSlider()">&#9776;</button> <!-- Hamburger button -->
        <img src="includes/images/logo.png" alt="Company Logo" class="logo"> <!-- Add your logo image here -->
        <h1>Overtime Management System</h1>
    </header>
    
    <!-- Employee Information Slider -->
    <div id="employeeInfoSlider" class="employee-info-slider">
        <button class="back-button" onclick="closeSlider()">&#8678;</button> <!-- Back arrow to close the slider -->
        <div class="employee-info-content">
            <h2>Employee's Dashboard</h2>
            <p><strong>Name:</strong> <?php echo $employeeName; ?></p>
            <p><strong>IC Number:</strong> <?php echo $employeeIC; ?></p>
            <p><strong>Email:</strong> <?php echo $employeeEmail; ?></p>
            <p><strong>Department:</strong> <?php echo $employeeDepartment; ?></p>
            <a href="change_password.php" class="change-password-button">Change Password</a> <!-- Change Password button -->
        </div>
        <div class="logout-container">
            <a href="../index.html" class="logout-button">Logout</a> <!-- Logout button inside the slider -->
        </div>

    </div>

    <!-- Main Content -->
    <main class="dashboard-main">
        <h2>Welcome, <?php echo $employeeName; ?></h2>
        <p>Here you can view your work hours, submit overtime requests, and manage your profile.</p>

        <!-- OT Submission Form Button -->
        <button onclick="document.getElementById('otSubmissionForm').style.display='block'">Submit Overtime</button>

        <!-- OT Submission Form -->
        <div id="otSubmissionForm" class="ot-form-container" style="display:none;">
            <h3>OT Submission Form</h3>
            <form action="" method="post">
                <input type="hidden" name="user_id" value="<?php echo $userId; ?>"> <!-- Hidden user_id field -->

                <label for="time_in">Time In:</label>
                <input type="time" id="time_in" name="time_in" required>

                <label for="time_out">Time Out:</label>
                <input type="time" id="time_out" name="time_out" required>

                <label for="site">Site:</label>
                <select id="site" name="site" required>
                    <option value="Solara">Solara</option>
                    <option value="VSMaju">VSMaju</option>
                    <option value="Fibre Team">Fibre Team</option>
                </select>

                <label for="date">Date:</label>
                <input type="date" id="date" name="date" required>

                <input type="submit" name="submit_ot" value="Submit">
            </form>
            <button type="button" onclick="document.getElementById('otSubmissionForm').style.display='none'">Close</button> <!-- Close button -->
        </div>

        <!-- Date Filter -->
        <div class="date-filter">
            <form method="get" action="">
                <input type="date" name="filter_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                <button type="submit">Filter</button>
                <?php if ($selectedDate): ?>
                    <button type="button" onclick="window.location.href='employee_dashboard.php';">Remove Filter</button>
                <?php endif; ?>
            </form>
        </div>

        <!-- Display OT Submissions -->
        <h3>Your OT Submissions</h3>
        <table class="ot-submissions-table">
            <tr>
                <th>Date</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Site</th>
                <th>Status</th>
                <th>Rejection Reason</th>
            </tr>
            <?php if (!empty($otSubmissions)): ?>
                <?php foreach ($otSubmissions as $submission): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($submission['date']); ?></td>
                        <td><?php echo htmlspecialchars($submission['time_in']); ?></td>
                        <td><?php echo htmlspecialchars($submission['time_out']); ?></td>
                        <td><?php echo htmlspecialchars($submission['site']); ?></td>
                        <td><?php echo htmlspecialchars($submission['status']); ?></td>
                        <td>
                            <?php 
                            if ($submission['status'] == 'Rejected') {
                                echo htmlspecialchars($submission['rejection_reason']);
                            } else {
                                echo '-'; // Display a dash if there's no rejection reason
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No OT submissions found for the selected date.</td>
                </tr>
            <?php endif; ?>
        </table>
    </main>

    <!-- Footer -->
    <footer>
        &copy; 2024 Solara Systems (M) Sdn.Bhd
    </footer>

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

        // Toggle the Employee Info Slider
        function openSlider() {
            document.getElementById('employeeInfoSlider').classList.add('active');
        }

        function closeSlider() {
            document.getElementById('employeeInfoSlider').classList.remove('active');
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
