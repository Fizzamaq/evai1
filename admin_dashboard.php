<?php
// public/admin/admin_dashboard.php
// TEMPORARY: Enable full error reporting for debugging.
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/config.php'; // Correct path to config.php
require_once __DIR__ . '/../../classes/User.class.php';
require_once __DIR__ . '/../../classes/ReportGenerator.class.php';

$user = new User($pdo);
if (!$user->isAdmin($_SESSION['user_id'] ?? null)) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Get system statistics
$report = new ReportGenerator($pdo);
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'active'")->fetchColumn(),
    'pending_vendors' => $pdo->query("SELECT COUNT(*) FROM vendor_profiles WHERE verified = 0")->fetchColumn(),
    'revenue' => $pdo->query("SELECT SUM(final_amount) FROM bookings WHERE status = 'completed'")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - EventCraftAI</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include '../../includes/admin_header.php'; ?> <div class="admin-container">
        <h1>System Overview</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['active_events']) ?></div>
                <div class="stat-label">Active Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['pending_vendors']) ?></div>
                <div class="stat-label">Pending Vendors</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?= number_format($stats['revenue'] ?? 0, 2) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <div class="admin-section">
            <h2>Quick Actions</h2>
            <div class="action-grid">
                <a href="users.php" class="action-card">
                    <i class="fas fa-users"></i>
                    Manage Users
                </a>
                <a href="events.php" class="action-card">
                    <i class="fas fa-calendar-alt"></i>
                    Monitor Events
                </a>
                <a href="vendors.php" class="action-card">
                    <i class="fas fa-store"></i>
                    Vendor Approvals
                </a>
            </div>
        </div>

        <div class="admin-section">
            <h2>Recent Activity</h2>
            <div class="activity-log">
                <?php foreach ($report->getSystemActivity() as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon"><i class="<?= htmlspecialchars($activity['icon_class']) ?>"></i></div>
                    <div class="activity-content">
                        <div class="activity-message"><?= htmlspecialchars($activity['message']) ?></div>
                        <div class="activity-time"><?= date('M j, Y g:i a', strtotime($activity['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php include 'admin_footer.php'; ?>
</body>
</html>