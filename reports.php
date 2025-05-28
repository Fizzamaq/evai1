<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/ReportGenerator.class.php';
require_once '../classes/User.class.php'; // Required for isAdmin and isVendor check

$reportGenerator = new ReportGenerator($pdo); // Pass PDO

// Ensure user is an admin or vendor for specific reports
$user = new User($pdo);
$is_admin = $user->isAdmin($_SESSION['user_id'] ?? null);
$is_vendor = $user->isVendor($_SESSION['user_id'] ?? null); // Assuming isVendor method exists

// Set default dates if not provided
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

$financialData = [];
$vendorReport = [];
$userActivityReport = [];

// Generate financial report (typically for admin)
if ($is_admin) {
    $financialData = $reportGenerator->generateFinancialReport($startDate, $endDate);
}

// Generate vendor performance report
if ($is_vendor && isset($_SESSION['vendor_id'])) {
    $vendorReport = $reportGenerator->generateVendorPerformanceReport(
        $_SESSION['vendor_id'],
        $startDate,
        $endDate
    );
} else if ($is_admin && isset($_GET['vendor_id'])) { // Admin can view specific vendor reports
     $vendorReport = $reportGenerator->generateVendorPerformanceReport(
        (int)$_GET['vendor_id'],
        $startDate,
        $endDate
    );
}

// Generate user activity report (for user or admin)
if (isset($_SESSION['user_id'])) {
    $userActivityReport = $reportGenerator->generateUserActivityReport(
        $_SESSION['user_id'],
        $startDate,
        $endDate
    );
}

// Export functionality
if (isset($_GET['export']) && !empty($financialData)) { // Only export if data is present
    $reportGenerator->exportToCSV($financialData, 'financial-report.csv');
    exit(); // Terminate script after export
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .reports-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .report-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .report-section h2 {
            margin-top: 0;
            color: #2d3436;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .report-table th, .report-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .report-table th {
            background-color: #f2f2f2;
            font-weight: 600;
        }
        .date-filter-form {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: flex-end;
        }
        .date-filter-form label {
            font-weight: 600;
        }
        .date-filter-form input[type="date"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .btn-export {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        .btn-export:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
    <?php
    if ($is_admin) {
        include '../includes/admin_header.php';
    } else {
        include 'header.php'; // Or vendor_header.php if user is a vendor
    }
    ?>

    <div class="reports-container">
        <h1>System Reports</h1>

        <div class="date-filter-form">
            <form method="GET" action="reports.php" style="display: flex; gap: 15px; align-items: flex-end;">
                <div>
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div>
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <button type="submit" class="btn btn-primary">Apply Filter</button>
            </form>
        </div>

        <?php if ($is_admin): ?>
            <div class="report-section">
                <h2>Financial Report</h2>
                <?php if (!empty($financialData)): ?>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Total Bookings</th>
                                <th>Total Revenue</th>
                                <th>Average Booking Value</th>
                                <th>Collected Amount</th>
                                <th>Pending Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($financialData as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['date']) ?></td>
                                    <td><?= htmlspecialchars($row['total_bookings']) ?></td>
                                    <td>$<?= number_format($row['total_revenue'], 2) ?></td>
                                    <td>$<?= number_format($row['average_booking_value'], 2) ?></td>
                                    <td>$<?= number_format($row['collected_amount'], 2) ?></td>
                                    <td>$<?= number_format($row['pending_amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top: 20px;">
                        <a href="reports.php?export=csv&start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>" class="btn-export">Export to CSV</a>
                    </p>
                <?php else: ?>
                    <p>No financial data available for the selected period.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($is_vendor || $is_admin): ?>
            <div class="report-section">
                <h2>Vendor Performance Report</h2>
                <?php if (!empty($vendorReport)): ?>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Total Bookings</th>
                                <th>Average Rating</th>
                                <th>Total Earnings</th>
                                <th>Avg Lead Time (Days)</th>
                                <th>Total Reviews</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendorReport as $row): ?>
                                <tr>
                                    <td><?= date('F', mktime(0, 0, 0, $row['month'], 1)) ?></td> <td><?= htmlspecialchars($row['total_bookings']) ?></td>
                                    <td><?= number_format($row['average_rating'], 1) ?></td>
                                    <td>$<?= number_format($row['total_earnings'], 2) ?></td>
                                    <td><?= number_format($row['avg_lead_time'], 0) ?></td>
                                    <td><?= htmlspecialchars($row['total_reviews']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No vendor performance data available for the selected period.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="report-section">
                <h2>User Activity Report</h2>
                <?php if (!empty($userActivityReport)): ?>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Event Type</th>
                                <th>Total Events</th>
                                <th>Avg Budget ($)</th>
                                <th>Completed Events</th>
                                <th>Cancelled Events</th>
                                <th>Total Messages</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($userActivityReport as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars(ucfirst($row['event_type'])) ?></td>
                                    <td><?= htmlspecialchars($row['total_events']) ?></td>
                                    <td>$<?= number_format($row['avg_budget'], 2) ?></td>
                                    <td><?= htmlspecialchars($row['completed_events']) ?></td>
                                    <td><?= htmlspecialchars($row['cancelled_events']) ?></td>
                                    <td><?= htmlspecialchars($row['total_messages']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No user activity data available.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>