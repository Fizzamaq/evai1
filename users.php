<?php
// public/admin/users.php
// TEMPORARY: Enable full error reporting for debugging.
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../classes/User.class.php';

$user_obj = new User($pdo);

// Admin authentication
if (!$user_obj->isAdmin($_SESSION['user_id'] ?? null)) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Handle actions (activate/deactivate/delete user)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id_target = $_POST['user_id'] ?? null;

    if ($user_id_target && is_numeric($user_id_target)) {
        try {
            // Prevent admin from deactivating/deleting themselves
            if ($user_id_target == $_SESSION['user_id'] && ($_SESSION['user_type'] ?? null) == 3 && ($action === 'deactivate' || $action === 'delete')) {
                $_SESSION['error_message'] = "Admins cannot deactivate or delete their own account from here.";
            } else {
                if ($action === 'activate') {
                    $user_obj->updateUserStatus($user_id_target, 1);
                    $_SESSION['success_message'] = "User activated successfully.";
                } elseif ($action === 'deactivate') {
                    $user_obj->updateUserStatus($user_id_target, 0);
                    $_SESSION['success_message'] = "User deactivated successfully.";
                } elseif ($action === 'delete') {
                    $user_obj->deleteUser($user_id_target);
                    $_SESSION['success_message'] = "User deleted successfully.";
                }
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Action failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Invalid user ID.";
    }
    header('Location: ' . BASE_URL . 'public/admin/users.php');
    exit();
}

// Fetch all users
$users = $user_obj->getAllUsers();

// Include the admin header
include '../../includes/admin_header.php';
?>

<div class="admin-container">
    <h1>Manage Users</h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <div class="admin-table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['user_type']); ?></td> <td>
                                <?php 
                                    if ($user['is_active']) {
                                        echo '<span class="status-badge status-active">Active</span>';
                                    } else {
                                        echo '<span class="status-badge status-inactive">Inactive</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>public/profile.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-sm btn-info" style="margin-right: 5px;">View Profile</a>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                    <?php if ($user['is_active']): ?>
                                        <button type="submit" name="action" value="deactivate" class="btn btn-sm btn-warning">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" name="action" value="activate" class="btn btn-sm btn-success">Activate</button>
                                    <?php endif; ?>
                                </form>
                                <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this user? This action is irreversible.');">
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'admin_footer.php'; ?>
