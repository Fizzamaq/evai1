<?php
// public/admin/reports.php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../classes/ReportGenerator.class.php';
require_once __DIR__ . '/../../classes/User.class.php';

$reportGenerator = new ReportGenerator($pdo);

// Ensure user is an admin
$user = new User($pdo);
if (!$user->isAdmin($_SESSION['user_id'] ?? null)) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Set default dates if not provided
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

$financialData = [];
$vendorReport = [];
$userActivityReport = [];

// Generate financial report (for admin)
$financialData = $reportGenerator->generateFinancialReport($startDate, $endDate);

// Generate vendor performance report (admin can view all or specific vendor)
$vendorReport = $reportGenerator->generateVendorPerformanceReport(
    null, // Pass null to get aggregate for admin, or specific vendor ID if applicable
    $startDate,
    $endDate
);

// Generate user activity report (admin can view all or specific user)
$userActivityReport = $reportGenerator->generateUserActivityReport(
    null, // Pass null to get aggregate for admin, or specific user ID if applicable
    $startDate,
    $endDate
);

// Export functionality
if (isset($_GET['export']) && !empty($financialData)) {
    $reportGenerator->exportToCSV($financialData, 'financial-report.csv');
    exit();
}

include '../../includes/admin_header.php'; // Corrected path
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Reports - EventCraftAI</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css">
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/admin.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <div class="admin-container">
        <h1>Admin Reports</h1>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <div class="admin-section">
            <h2>Filter Reports by Date</h2>
            <div class="date-filter-form">
                <form method="GET" action="<?= BASE_URL ?>public/admin/reports.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </form>
            </div>
        </div>

        <div class="admin-section">
            <h2>Financial Report</h2>
            <?php if (!empty($financialData)): ?>
                <div class="table-responsive">
                    <table class="admin-table">
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
                </div>
                <p style="margin-top: 20px; text-align: right;">
                    <a href="<?= BASE_URL ?>public/admin/reports.php?export=csv&start_date=<?= htmlspecialchars($startDate) ?>&end_date=<?= htmlspecialchars($endDate) ?>" class="btn btn-secondary">Export to CSV</a>
                </p>
            <?php else: ?>
                <div class="empty-state">No financial data available for the selected period.</div>
            <?php endif; ?>
        </div>

        <div class="admin-section">
            <h2>Vendor Performance Report (All Vendors)</h2>
            <?php if (!empty($vendorReport)): ?>
                <div class="table-responsive">
                    <table class="admin-table">
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
                                    <td><?= date('F', mktime(0, 0, 0, $row['month'], 1)) ?></td>
                                    <td><?= htmlspecialchars($row['total_bookings']) ?></td>
                                    <td><?= number_format($row['average_rating'], 1) ?></td>
                                    <td>$<?= number_format($row['total_earnings'], 2) ?></td>
                                    <td><?= number_format($row['avg_lead_time'], 0) ?></td>
                                    <td><?= htmlspecialchars($row['total_reviews']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">No vendor performance data available for the selected period.</div>
            <?php endif; ?>
        </div>

        <div class="admin-section">
            <h2>User Activity Report (All Users)</h2>
            <?php if (!empty($userActivityReport)): ?>
                <div class="table-responsive">
                    <table class="admin-table">
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
                </div>
            <?php else: ?>
                <div class="empty-state">No user activity data available.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../../includes/admin_footer.php'; ?>
</body>
</html>
