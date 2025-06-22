<?php
session_start();
ob_start(); // Start output buffering

// Ensure the user is logged in and has an HR role
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'HR') {
    header('Location: ../hrlogin.php'); // Redirect to login if not logged in as HR
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

    // Fetch the logged-in HR's details using a prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT name, ic_number, email FROM users WHERE email = :email AND role = 'HR'");
    $stmt->bindParam(':email', $_SESSION['email'], PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $hrName = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
        $hrIC = htmlspecialchars($user['ic_number'], ENT_QUOTES, 'UTF-8');
        $hrEmail = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
    } else {
        echo "<p>Error: User details not found.</p>";
        exit();
    }

    // Count total OT submissions
    $countStmt = $conn->prepare("SELECT COUNT(*) as total_ot_submissions FROM ot_submissions WHERE status = 'Approved'");
    $countStmt->execute();
    $totalOtSubmissions = $countStmt->fetch(PDO::FETCH_ASSOC)['total_ot_submissions'];

} catch(PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    exit();
}

// Fetch OT submissions filtered by site, department, and date
$selectedDate = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$selectedSite = isset($_GET['site']) ? $_GET['site'] : '';
$selectedDepartment = isset($_GET['department']) ? $_GET['department'] : '';

$otStmt = $conn->prepare("SELECT ot.*, u.name, u.department 
                          FROM ot_submissions ot 
                          JOIN users u ON ot.user_id = u.id 
                          WHERE ot.status = 'Approved' 
                          " . ($selectedSite ? "AND ot.site = :site " : "") . 
                          ($selectedDepartment ? "AND u.department = :department " : "") . 
                          ($selectedDate ? "AND ot.date = :date" : ""));

if ($selectedSite) {
    $otStmt->bindParam(':site', $selectedSite, PDO::PARAM_STR);
}
if ($selectedDepartment) {
    $otStmt->bindParam(':department', $selectedDepartment, PDO::PARAM_STR);
}
if ($selectedDate) {
    $otStmt->bindParam(':date', $selectedDate);
}
$otStmt->execute();
$otSubmissions = $otStmt->fetchAll(PDO::FETCH_ASSOC);

// Function to calculate total working hours
function calculateWorkingHours($timeIn, $timeOut) {
    $start = new DateTime($timeIn);
    $end = new DateTime($timeOut);
    $interval = $start->diff($end);
    return $interval->format('%H:%I');
}

// Handle export to CSV or Excel
if (isset($_POST['export_type'])) {
    $exportType = $_POST['export_type'];
    $filename = 'ot_submissions_' . date('Y-m-d');

    if ($exportType == 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Set column headers
        fputcsv($output, ['Employee Name', 'Date', 'Time In', 'Time Out', 'Site', 'Department', 'Total Working Hours', 'Status']);
        
        // Add rows
        foreach ($otSubmissions as $submission) {
            $dateFormatted = date('Y-m-d', strtotime($submission['date'])); // Format the date
            $totalHours = calculateWorkingHours($submission['time_in'], $submission['time_out']); // Calculate total working hours
            fputcsv($output, [
                $submission['name'], 
                $dateFormatted, 
                $submission['time_in'], 
                $submission['time_out'], 
                $submission['site'], 
                $submission['department'], 
                $totalHours, 
                $submission['status']
            ]);
        }
        
        fclose($output);
        exit();
    } elseif ($exportType == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '.xls"');

        echo '<table border="1">';
        echo '<tr>';
        echo '<th>Employee Name</th>';
        echo '<th>Date</th>';
        echo '<th>Time In</th>';
        echo '<th>Time Out</th>';
        echo '<th>Site</th>';
        echo '<th>Department</th>';
        echo '<th>Total Working Hours</th>';
        echo '<th>Status</th>';
        echo '</tr>';

        foreach ($otSubmissions as $submission) {
            $dateFormatted = date('Y-m-d', strtotime($submission['date']));
            $totalHours = calculateWorkingHours($submission['time_in'], $submission['time_out']);
            echo '<tr>';
            echo '<td>' . htmlspecialchars($submission['name']) . '</td>';
            echo '<td>' . htmlspecialchars($dateFormatted) . '</td>';
            echo '<td>' . htmlspecialchars($submission['time_in']) . '</td>';
            echo '<td>' . htmlspecialchars($submission['time_out']) . '</td>';
            echo '<td>' . htmlspecialchars($submission['site']) . '</td>';
            echo '<td>' . htmlspecialchars($submission['department']) . '</td>';
            echo '<td>' . htmlspecialchars($totalHours) . '</td>';
            echo '<td>' . htmlspecialchars($submission['status']) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="includes/images/favicon.ico">
    <title>HR Dashboard</title>
    <link rel="stylesheet" href="includes/styles.css">
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

        /* Date, Site, and Department Filters */
        .filter-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .filter-container form {
            display: flex;
            align-items: center;
        }

        .filter-container select,
        .filter-container input[type="date"] {
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
            margin-right: 10px;
        }

        .filter-container button {
            background-color: #29ABE2;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px; /* Added margin to separate buttons */
        }

        .filter-container button:hover {
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

    <!-- HR Information Slider -->
    <div id="employeeInfoSlider" class="employee-info-slider">
        <button class="back-button" onclick="closeSlider()">&#8678;</button> <!-- Back arrow to close the slider -->
        <div class="employee-info-content">
            <h2>HR's Dashboard</h2>
            <p><strong>Name:</strong> <?php echo $hrName; ?></p>
            <p><strong>IC Number:</strong> <?php echo $hrIC; ?></p>
            <p><strong>Email:</strong> <?php echo $hrEmail; ?></p>
            <button class="logout-button" onclick="logout()">Logout</button> <!-- Logout button -->
        </div>
    </div>

    <!-- Main Content -->
    <main class="dashboard-main">
        <h2>Welcome, <?php echo $hrName; ?></h2>
        <p>Here you can view employees' work hours, filter by site, department, or date, and export the data as an Excel or CSV file.</p>

        <!-- Display Total OT Submissions -->
        <h3>Total OT Submissions: <?php echo $totalOtSubmissions; ?></h3>

        <!-- Filters -->
        <div class="filter-container">
            <form method="get" action="">
                <select name="site">
                    <option value="">All Sites</option>
                    <option value="Solara" <?php echo $selectedSite === 'Solara' ? 'selected' : ''; ?>>Solara</option>
                    <option value="VSMaju" <?php echo $selectedSite === 'VSMaju' ? 'selected' : ''; ?>>VSMaju</option>
                    <option value="Fibre Team" <?php echo $selectedSite === 'Fibre Team' ? 'selected' : ''; ?>>Fibre Team</option>
                </select>

                <select name="department">
                    <option value="">All Departments</option>
                    <option value="HR" <?php echo $selectedDepartment === 'HR' ? 'selected' : ''; ?>>HR</option>
                    <option value="IT" <?php echo $selectedDepartment === 'IT' ? 'selected' : ''; ?>>IT</option>
                    <option value="Finance" <?php echo $selectedDepartment === 'Finance' ? 'selected' : ''; ?>>Finance</option>
                    <option value="Cybersecurity" <?php echo $selectedDepartment === 'Cybersecurity' ? 'selected' : ''; ?>>Cybersecurity</option>
                    <option value="Management" <?php echo $selectedDepartment === 'Management' ? 'selected' : ''; ?>>Management</option>
                </select>

                <input type="date" name="filter_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
                <button type="submit">Filter</button>
                <?php if ($selectedDate || $selectedSite || $selectedDepartment): ?>
                    <button type="button" onclick="window.location.href='hr_dashboard.php';">Remove Filter</button>
                <?php endif; ?>
            </form>

            <form method="post" action="">
                <select name="export_type">
                    <option value="csv">Export as CSV</option>
                    <option value="excel">Export as Excel</option>
                </select>
                <button type="submit">Export</button>
            </form>
        </div>

        <!-- Display OT Submissions -->
        <h3>OT Submissions</h3>
        <table class="ot-submissions-table">
            <tr>
                <th>Employee Name</th>
                <th>Date</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Site</th>
                <th>Department</th>
                <th>Total Working Hours</th>
                <th>Status</th>
            </tr>
            <?php if (!empty($otSubmissions)): ?>
                <?php foreach ($otSubmissions as $submission): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($submission['name']); ?></td>
                        <td><?php echo htmlspecialchars($submission['date']); ?></td>
                        <td><?php echo htmlspecialchars($submission['time_in']); ?></td>
                        <td><?php echo htmlspecialchars($submission['time_out']); ?></td>
                        <td><?php echo htmlspecialchars($submission['site']); ?></td>
                        <td><?php echo htmlspecialchars($submission['department']); ?></td>
                        <td><?php echo calculateWorkingHours($submission['time_in'], $submission['time_out']); ?></td>
                        <td><?php echo htmlspecialchars($submission['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No OT submissions found.</td>
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

        // Logout function
        function logout() {
            window.location.href = '../index.html'; // Redirect to the index.html page
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
