<?php
class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function register($email, $password, $firstName, $lastName, $userTypeId) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $this->pdo->beginTransaction();
        try {
            // Insert user
            $stmt = $this->pdo->prepare("INSERT INTO users 
                                        (user_type_id, email, password_hash, first_name, last_name, is_active) 
                                        VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$userTypeId, $email, $hashedPassword, $firstName, $lastName]);
            $userId = $this->pdo->lastInsertId();

            // Create profile
            $this->initUserProfile($userId);

            $this->pdo->commit();
            return $userId;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception("Registration failed: " . $e->getMessage());
        }
    }

    public function login($email, $password) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, password_hash, user_type_id, first_name 
                                        FROM users 
                                        WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_type'] = $user['user_type_id'];
                $_SESSION['user_name'] = $user['first_name'];
                // Ensure user_profiles is initialized for existing users too.
                $this->initUserProfile($user['id']);
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    private function initUserProfile($userId) {
        try {
            // Insert IGNORE ensures it only inserts if user_id does not exist
            $stmt = $this->pdo->prepare("INSERT IGNORE INTO user_profiles (user_id) VALUES (?)");
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Profile init error: " . $e->getMessage());
        }
    }

    public function getProfile($userId) {
        // Modified to select profile_image from user_profiles table
        $stmt = $this->pdo->prepare("SELECT u.*, up.* FROM users u
                                    LEFT JOIN user_profiles up ON u.id = up.user_id
                                    WHERE u.id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    public function updateProfile($userId, $data) {
        try {
            $this->pdo->beginTransaction();

            // Update users table (for first/last name)
            $stmt = $this->pdo->prepare("UPDATE users SET 
                                        first_name = ?, last_name = ?
                                        WHERE id = ?");
            $stmt->execute([$data['first_name'], $data['last_name'], $userId]);

            // Update user_profiles table (for address, city, state)
            $stmt = $this->pdo->prepare("UPDATE user_profiles SET
                                        address = ?, city = ?, state = ?
                                        WHERE user_id = ?");
            $stmt->execute([
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['state'] ?? null,
                $userId
            ]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception("Profile update failed: " . $e->getMessage());
        }
    }

    // Missing method: getUserById (referenced in chat.php, dashboard.php, vendor_portfolio.php)
    public function getUserById($userId) {
        $stmt = $this->pdo->prepare("SELECT u.*, up.* FROM users u LEFT JOIN user_profiles up ON u.id = up.user_id WHERE u.id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Missing method: initiatePasswordReset (referenced in forgot_password.php)
    public function initiatePasswordReset($email) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
                // Use INSERT ... ON DUPLICATE KEY UPDATE if you want to reuse existing reset tokens
                $stmt = $this->pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $token, $expires]);

                // In a real application, you'd send an email here
                // For now, log it:
                error_log("Password reset link for $email: " . BASE_URL . "public/reset_password.php?token=$token");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Initiate password reset error: " . $e->getMessage());
            return false;
        }
    }

    // Missing method: validateResetToken (referenced in reset_password.php)
    public function validateResetToken($token) {
        $stmt = $this->pdo->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Missing method: resetPassword (referenced in reset_password.php)
    public function resetPassword($token, $newPassword) {
        try {
            $this->pdo->beginTransaction();
            $tokenData = $this->validateResetToken($token);

            if (!$tokenData) {
                throw new Exception("Invalid or expired token.");
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $tokenData['user_id']]);

            // Invalidate the token
            $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Reset password error: " . $e->getMessage());
            return false;
        }
    }

    // Missing method: isAdmin (referenced in settings.php)
    public function isAdmin($userId) {
        if ($userId === null) return false;
        $stmt = $this->pdo->prepare("SELECT user_type_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['user_type_id'] == 3; // Assuming 3 is admin type
    }

    // Missing method: isVendor (referenced in Vendor.class.php verifyVendorAccess)
    public function isVendor($userId) {
        if ($userId === null) return false;
        $stmt = $this->pdo->prepare("SELECT user_type_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['user_type_id'] == 2; // Assuming 2 is vendor type
    }


    // Missing method: updateProfileImage (referenced in process_profile.php)
    public function updateProfileImage($userId, $filename) {
        // Modified to update profile_image in user_profiles table
        $stmt = $this->pdo->prepare("UPDATE user_profiles SET profile_image = ? WHERE user_id = ?");
        return $stmt->execute([$filename, $userId]);
    }
}
