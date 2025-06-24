<?php
// classes/User.class.php
require_once 'MailSender.class.php'; // Include MailSender class

class User {
    private $pdo;
    private $mailSender; // Declare MailSender property

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->mailSender = new MailSender(); // Initialize MailSender
    }

    public function register($email, $password, $firstName, $lastName, $userTypeId) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $this->pdo->beginTransaction();
        try {
            // Insert user
            $stmt = $this->pdo->prepare("INSERT INTO users 
                                        (user_type_id, email, password_hash, first_name, last_name, is_active) 
                                        VALUES (?, ?, ?, ?, ?, 0)"); // is_active = 0 initially
            $stmt->execute([$userTypeId, $email, $hashedPassword, $firstName, $lastName]);
            $userId = $this->pdo->lastInsertId();

            // Create profile
            $this->initUserProfile($userId);

            // --- NEW: Send verification email ---
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours')); // Verification link valid for 24 hours
            $stmt_token = $this->pdo->prepare("INSERT INTO email_verifications (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt_token->execute([$userId, $token, $expires]);

            $verification_link = BASE_URL . 'public/verify_email.php?token=' . $token;
            $subject = "Verify your email address for EventCraftAI";
            $bodyHtml = "<p>Hi " . htmlspecialchars($firstName . ' ' . $lastName) . ",</p><p>Please click the following link to verify your email address:</p><p><a href='" . $verification_link . "'>" . $verification_link . "</a></p><p>This link will expire in 24 hours.</p><p>Thank you,</p><p>EventCraftAI Team</p>";
            $bodyText = "Hi " . htmlspecialchars($firstName . ' ' . $lastName) . ",\nPlease visit the following link to verify your email address: " . $verification_link . "\nThis link will expire in 24 hours.\n\nThank you,\nEventCraftAI Team";
            $email_sent = $this->mailSender->sendEmail($email, htmlspecialchars($firstName . ' ' . $lastName), $subject, $bodyHtml, $bodyText);

            if (!$email_sent) {
                error_log("User.class.php register: Email verification failed to send to " . $email . ". User registration might have succeeded, but email not sent.");
                // You might want to handle this more gracefully, perhaps by storing a flag in the session.
            }
            // --- END NEW EMAIL VERIFICATION ---

            $this->pdo->commit();
            return $userId;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new Exception("Registration failed: " . $e->getMessage());
        }
    }

    public function login($email, $password) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, password_hash, user_type_id, first_name, email, email_verified FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if (!$user['email_verified']) {
                    // Check if email is verified
                    throw new Exception("Your email address is not verified. Please check your inbox.");
                }
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
        } catch (PDOException | Exception $e) { // Catch both PDOException and general Exception
            error_log("Profile init error: " . $e->getMessage());
        }
    }

    public function getProfile($userId) {
        // Modified to select profile_image from user_profiles table
        $stmt = $this->pdo->prepare("SELECT u.*, up.* FROM users u
                                    LEFT JOIN user_profiles up ON u.id = up.user_id
                                    WHERE u.id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProfile($userId, $data) {
        try {
            $this->pdo->beginTransaction();

            // Update users table (for first/last name)
            $stmt = $this->pdo->prepare("UPDATE users SET 
                                        first_name = ?, last_name = ?
                                        WHERE id = ?");
            $stmt->execute([$data['first_name'], $data['last_name'], $userId]);

            // Update user_profiles table (for address, city, state, country, postal_code)
            // FIX: Added country and postal_code to SQL query and parameter list
            $stmt = $this->pdo->prepare("UPDATE user_profiles SET
                                        address = ?, city = ?, state = ?, country = ?, postal_code = ?
                                        WHERE user_id = ?");
            $stmt->execute([
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['state'] ?? null,
                $data['country'] ?? null,    // Added
                $data['postal_code'] ?? null, // Added
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
            $stmt = $this->pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ? AND is_active = 1"); // Added first_name, last_name
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch as assoc array

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
                // Use INSERT ... ON DUPLICATE KEY UPDATE if you want to reuse existing reset tokens
                $stmt_insert = $this->pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt_insert->execute([$user['id'], $token, $expires]);

                // --- NEW: Send password reset email ---
                $reset_link = BASE_URL . "public/reset_password.php?token=$token";
                $subject = "Password Reset Request for EventCraftAI";
                $bodyHtml = "<p>Hi " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ",</p><p>You have requested to reset your password for your EventCraftAI account.</p><p>Please click on the following link to reset your password:</p><p><a href=\"" . $reset_link . "\">Reset My Password</a></p><p>This link will expire in 1 hour.</p><p>If you did not request a password reset, please ignore this email.</p><p>Thank you,</p><p>EventCraftAI Team</p>";
                $bodyText = "Hi " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ",\nYou have requested to reset your password for your EventCraftAI account.\n\nPlease visit the following link to reset your password: " . $reset_link . "\nThis link will expire in 1 hour.\nIf you did not request a password reset, please ignore this email.\n\nThank you,\nEventCraftAI Team";
                
                $email_sent = $this->mailSender->sendEmail($email, htmlspecialchars($user['first_name'] . ' ' . $user['last_name']), $subject, $bodyHtml, $bodyText);
                if (!$email_sent) {
                    error_log("User.class.php initiatePasswordReset: Email notification failed to send to " . $email . ". Check Mailer Error logs.");
                }
                // --- END NEW EMAIL NOTIFICATION ---

                return true;
            }
            return false;
        } catch (PDOException | Exception $e) { // Catch both PDOException and general Exception
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
        } catch (PDOException | Exception $e) { // Catch both PDOException and general Exception
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
