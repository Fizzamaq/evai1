<?php
// classes/Review.class.php
class Review {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function submitReview($data) {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO reviews (
                    booking_id, reviewer_id, reviewed_id, rating, review_title, review_content,
                    service_quality, communication, value_for_money, would_recommend, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");
            $stmt->execute([
                $data['booking_id'],
                $data['reviewer_id'],
                $data['reviewed_id'],
                $data['rating'],
                $data['review_title'],
                $data['review_content'],
                $data['service_quality'],
                $data['communication'],
                $data['value_for_money'],
                $data['would_recommend']
            ]);

            // Update average rating for the reviewed vendor (or user)
            $this->updateAverageRating($data['reviewed_id']);

            // Mark booking as reviewed
            $stmt = $this->pdo->prepare("UPDATE bookings SET is_reviewed = TRUE WHERE id = ?");
            $stmt->execute([$data['booking_id']]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Review submission error: " . $e->getMessage());
            return false;
        }
    }

    private function updateAverageRating($reviewedId) {
        try {
            // Calculate average rating for the reviewed entity (vendor or user)
            $stmt = $this->pdo->prepare("
                SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
                FROM reviews
                WHERE reviewed_id = ?
            ");
            $stmt->execute([$reviewedId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Assuming 'vendor_profiles' table has 'rating' and 'total_reviews' columns
            $updateStmt = $this->pdo->prepare("
                UPDATE vendor_profiles
                SET rating = ?, total_reviews = ?
                WHERE user_id = ?
            ");
            $updateStmt->execute([
                $result['avg_rating'] ?? 0,
                $result['total_reviews'] ?? 0,
                $reviewedId
            ]);
        } catch (PDOException $e) {
            error_log("Update average rating error: " . $e->getMessage());
        }
    }

    public function getReviewsForEntity($entityId, $entityType = 'vendor') {
        try {
            // Adjust query based on entityType (e.g., if reviews can be for users or vendors)
            $stmt = $this->pdo->prepare("
                SELECT r.*, u.first_name, u.last_name, up.profile_image
                FROM reviews r
                JOIN users u ON r.reviewer_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id
                WHERE r.reviewed_id = ?
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$entityId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get reviews for entity error: " . $e->getMessage());
            return [];
        }
    }
}
?>