<?php
// public/admin/vendors.php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../classes/User.class.php';
require_once __DIR__ . '/../../classes/Vendor.class.php';

$user_obj = new User($pdo);
$vendor_obj = new Vendor($pdo);

// Admin authentication
if (!$user_obj->isAdmin($_SESSION['user_id'] ?? null)) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Handle actions (verify/unverify/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $vendor_id_target = $_POST['vendor_id'] ?? null; // This is vendor_profiles.id

    if ($vendor_id_target && is_numeric($vendor_id_target)) {
        try {
            if ($action === 'verify') {
                $vendor_obj->updateVendorVerificationStatus($vendor_id_target, 1);
                $_SESSION['success_message'] = "Vendor verified successfully.";
            } elseif ($action === 'unverify') {
                $vendor_obj->updateVendorVerificationStatus($vendor_id_target, 0);
                $_SESSION['success_message'] = "Vendor unverified successfully.";
            } elseif ($action === 'delete') {
                // IMPORTANT: This will delete the vendor_profile and cascade delete
                // associated portfolio items, services, availability.
                // It does NOT delete the user account (users.id).
                $vendor_obj->deleteVendorProfile($vendor_id_target); // Renamed for clarity in Vendor.class.php
                $_SESSION['success_message'] = "Vendor profile deleted successfully.";
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Action failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Invalid vendor ID.";
    }
    header('Location: ' . BASE_URL . 'public/admin/vendors.php'); // Redirect back to this page
    exit();
}

// Fetch all vendor profiles
$vendors = $vendor_obj->getAllVendorProfiles();

include '../../includes/admin_header.php';
?>

<div class="admin-container">
    <h1>Manage Vendors</h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Business Name</th>
                    <th>Contact Email</th>
                    <th>City</th>
                    <th>Rating</th>
                    <th>Total Reviews</th>
                    <th>Verified</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($vendors)): ?>
                    <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vendor['id']); ?></td>
                            <td><?php echo htmlspecialchars($vendor['business_name']); ?></td>
                            <td><?php echo htmlspecialchars($vendor['email']); ?></td>
                            <td><?php echo htmlspecialchars($vendor['business_city']); ?></td>
                            <td><?php echo number_format($vendor['rating'], 1); ?></td>
                            <td><?php echo htmlspecialchars($vendor['total_reviews']); ?></td>
                            <td><?php echo $vendor['verified'] ? 'Yes' : 'No'; ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?php echo htmlspecialchars($vendor['id']); ?>" class="btn btn-sm btn-info">View Profile</a>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="vendor_id" value="<?php echo htmlspecialchars($vendor['id']); ?>">
                                    <?php if ($vendor['verified']): ?>
                                        <button type="submit" name="action" value="unverify" class="btn btn-sm btn-warning">Unverify</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="verify" class="btn btn-sm btn-success">Verify</button>
                                    <?php endif; ?>
                                </form>
                                <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this vendor profile? This action is irreversible and will remove all associated data.');">
                                    <input type="hidden" name="vendor_id" value="<?php echo htmlspecialchars($vendor['id']); ?>">
                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No vendor profiles found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'admin_footer.php'; ?>