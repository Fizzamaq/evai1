<?php
require_once __DIR__ . '/vendor/autoload.php'; // Google API Client

class CalendarManager {
    private $client;
    private $pdo;

    public function __construct($pdo) {
        $this->client = new Google_Client();
        $this->client->setClientId(GOOGLE_CLIENT_ID);
        $this->client->setClientSecret(GOOGLE_CLIENT_SECRET);
        $this->client->setRedirectUri(GOOGLE_REDIRECT_URI);
        $this->client->addScope(Google_Service_Calendar::CALENDAR);
        $this->pdo = $pdo;
    }

    public function getAuthUrl() {
        return $this->client->createAuthUrl();
    }

    public function handleCallback($code) {
        try {
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            $this->storeToken($_SESSION['user_id'], $token);
            return true;
        } catch (Exception $e) {
            error_log("Calendar Auth Error: " . $e->getMessage());
            return false;
        }
    }

    public function createCalendarEvent($userId, $eventData) {
        try {
            $fullToken = $this->getToken($userId); // Get the full token array
            if (!$fullToken) {
                throw new Exception("Failed to retrieve or refresh access token for user $userId.");
            }
            // The token is already set on $this->client within getToken()

            $service = new Google_Service_Calendar($this->client);

            $googleEvent = new Google_Service_Calendar_Event([
                'summary' => $eventData['title'],
                'description' => $eventData['description'],
                'start' => ['dateTime' => $eventData['start'], 'timeZone' => 'UTC'], // Added timezone
                'end' => ['dateTime' => $eventData['end'], 'timeZone' => 'UTC'],     // Added timezone
                'attendees' => array_map(function($email) {
                    return ['email' => $email];
                }, $eventData['attendees'] ?? []) // Handle case with no attendees
            ]);

            return $service->events->insert('primary', $googleEvent);
        } catch (Exception $e) {
            error_log("Calendar Event Error: " . $e->getMessage());
            return false;
        }
    }

    private function storeToken($userId, $token) {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_calendar_tokens
            (user_id, access_token, refresh_token, expires_at, created_at_timestamp)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                expires_at = VALUES(expires_at),
                created_at_timestamp = VALUES(created_at_timestamp)
        ");
        $stmt->execute([
            $userId,
            $token['access_token'],
            $token['refresh_token'] ?? null,
            date('Y-m-d H:i:s', $token['created'] + $token['expires_in']),
            $token['created'] // Store the creation timestamp
        ]);
    }

    private function getToken($userId) {
        $stmt = $this->pdo->prepare("
            SELECT access_token, refresh_token, expires_at, created_at_timestamp
            FROM user_calendar_tokens
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $storedToken = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$storedToken) {
            return false; // No token found for this user
        }

        // Reconstruct the token array as the Google API client expects it
        $tokenArray = [
            'access_token' => $storedToken['access_token'],
            'refresh_token' => $storedToken['refresh_token'],
            'expires_in' => strtotime($storedToken['expires_at']) - time(),
            'created' => (int)$storedToken['created_at_timestamp']
        ];

        $this->client->setAccessToken($tokenArray);

        // If the token is expired, refresh it
        if ($this->client->isAccessTokenExpired()) {
            if (empty($storedToken['refresh_token'])) {
                error_log("Calendar Token Refresh Error for user $userId: No refresh token available.");
                return false;
            }
            try {
                $this->client->fetchAccessTokenWithRefreshToken($storedToken['refresh_token']);
                $newToken = $this->client->getAccessToken(); // This will be the full new token array
                $this->storeToken($userId, $newToken); // Store the new token
                return $newToken; // Return the new full token array
            } catch (Exception $e) {
                error_log("Calendar Token Refresh Error for user $userId: " . $e->getMessage());
                return false;
            }
        }

        return $tokenArray; // Return the current (or freshly set) full token array
    }

    // Method to check if a user has a token (useful for UI)
    public function hasToken($userId) {
        $stmt = $this->pdo->prepare("SELECT 1 FROM user_calendar_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    }

    // NEW METHOD: Delete a user's calendar token
    public function deleteToken($userId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_calendar_tokens WHERE user_id = ?");
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Failed to delete calendar token for user $userId: " . $e->getMessage());
            return false;
        }
    }
}
