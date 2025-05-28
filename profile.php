<?php
require_once '../includes/config.php';
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../classes/User.class.php';
$user = new User($pdo);
$profile = $user->getProfile($_SESSION['user_id']);
?>
<div class="profile-container">
    <h1>Your Profile</h1>
    
    <div class="profile-section">
        <div class="profile-pic">
            <?php if (!empty($profile['profile_image'])): ?>
        <img src="<?= ASSETS_PATH ?>uploads/users/<?= htmlspecialchars($profile['profile_image']) ?>" alt="Profile Picture">
    <?php else: ?>
        <div class="initials" style="background-image: url('<?= ASSETS_PATH ?>images/default-avatar.jpg'); background-size: cover; background-position: center;">
            <?= substr($profile['first_name'], 0, 1) . substr($profile['last_name'], 0, 1) ?>
        </div>
    <?php endif; ?>
        </div>
        
        <div class="profile-info">
            <h2><?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?></h2>
            <p><strong>Email:</strong> <?= htmlspecialchars($profile['email']) ?></p>
            <p><strong>Member Since:</strong> <?= date('F Y', strtotime($profile['created_at'])) ?></p>
            
            <a href="edit_profile.php" class="btn">Edit Profile</a>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>