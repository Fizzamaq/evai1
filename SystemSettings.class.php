<?php
// classes/SystemSettings.class.php
class SystemSettings {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getSetting($key) {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['setting_value'] ?? null;
        } catch (PDOException $e) {
            error_log("Get setting error: " . $e->getMessage());
            return null;
        }
    }

    // Modified updateSetting to include description and data_type for initial population
    // It uses ON DUPLICATE KEY UPDATE assuming setting_key is UNIQUE or PRIMARY
    public function updateSetting($key, $value, $description = null, $dataType = null) {
        try {
            $sql = "INSERT INTO system_settings (setting_key, setting_value, description, data_type) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        setting_value = VALUES(setting_value), 
                        description = VALUES(description), 
                        data_type = VALUES(data_type),
                        updated_at = NOW()"; // Update timestamp on change
            $params = [$key, $value, $description, $dataType];

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Update setting error: " . $e->getMessage());
            return false;
        }
    }

    public function getAllSettings() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM system_settings ORDER BY setting_key ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get all settings error: " . $e->getMessage());
            return [];
        }
    }

    // Method to ensure default settings are present, useful on first run
    public function ensureDefaultSettings() {
        $defaultSettings = [
            'site_name' => ['value' => 'EventCraftAI', 'description' => 'Website Name', 'data_type' => 'string'],
            'contact_email' => ['value' => 'info@eventcraftai.com', 'description' => 'Contact Email Address', 'data_type' => 'string'],
            'maintenance_mode' => ['value' => '0', 'description' => 'Enable maintenance mode', 'data_type' => 'boolean'],
            'reviews_enabled' => ['value' => '1', 'description' => 'Allow users to leave reviews', 'data_type' => 'boolean'],
            'stripe_publishable_key' => ['value' => '', 'description' => 'Stripe Publishable Key (for frontend)', 'data_type' => 'string'],
            'openai_api_key' => ['value' => '', 'description' => 'OpenAI API Key (for AI Assistant)', 'data_type' => 'string'],
            // Add more settings as needed
        ];

        foreach ($defaultSettings as $key => $details) {
            // Check if setting exists to avoid re-inserting, but update will handle if it's already there
            $this->updateSetting($key, $details['value'], $details['description'], $details['data_type']);
        }
    }
}