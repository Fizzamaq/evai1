<?php
// api/get_booking_details.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Booking.class.php';
require_once __DIR__ . '/../classes/User.class.php';
require_once __DIR__ . '/../classes/Vendor.class.php'; // Include Vendor class
require_once __DIR__ . '/../includes/auth.php'; // For authentication/session checks

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Check user authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit();
}

$bookingId = $_GET['booking_id'] ?? null;

if (!is_numeric($bookingId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID.']);
    exit();
}

$bookingSystem = new Booking($pdo);
$user = new User($pdo);
$vendor = new Vendor($pdo); // Instantiate Vendor class

try {
    $booking = $bookingSystem->getBooking((int)$bookingId);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Booking not found.']);
        exit();
    }

    // --- Access Control Check ---
    // A user can view their own booking (if they are the customer)
    // OR a vendor can view a booking if it belongs to their vendor profile.
    
    // Check if the logged-in user is the customer for this booking
    $is_customer = (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 1 && $booking['user_id'] == $_SESSION['user_id']);
    
    // Check if the logged-in user is the vendor for this booking
    $is_vendor = false;
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 2) { // Assuming 2 is vendor type
        $vendor_profile_for_session_user = $vendor->getVendorByUserId($_SESSION['user_id']); // Get the vendor profile ID for the logged-in vendor user
        if ($vendor_profile_for_session_user && $booking['vendor_id'] == $vendor_profile_for_session_user['id']) {
            $is_vendor = true;
        }
    }

    if (!$is_customer && !$is_vendor) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied. This booking does not belong to your account.']);
        error_log("Access denied. User ID: " . ($_SESSION['user_id'] ?? 'N/A') . ", User Type: " . ($_SESSION['user_type'] ?? 'N/A') . ", Booking User ID: {$booking['user_id']}, Booking Vendor ID: {$booking['vendor_id']}, Session Vendor Profile ID: " . ($vendor_profile_for_session_user['id'] ?? 'N/A'));
        exit();
    }
    // --- End Access Control Check ---


    // Fetch related details
    $event_details = null;
    if ($booking['event_id']) {
        $event_details_stmt = $pdo->prepare("SELECT title FROM events WHERE id = ?");
        $event_details_stmt->execute([$booking['event_id']]);
        $event_details = $event_details_stmt->fetch(PDO::FETCH_ASSOC);
        $booking['event_title'] = isset($event_details['title']) ? $event_details['title'] : 'N/A';
    }

    // Fetch client details (the user who made the booking)
    $client_details = $user->getUserById($booking['user_id']);
    // Filter client data to only send necessary fields for security
    $client_safe_data = [
        'id' => $client_details['id'],
        'first_name' => $client_details['first_name'],
        'last_name' => $client_details['last_name'],
        'email' => $client_details['email'],
        'phone' => $client_details['phone'] ?? null, // Include phone for admin/vendor if needed
    ];

    // Fetch vendor business name (from vendor_profiles)
    $vendor_profile_details = $vendor->getVendorProfileById($booking['vendor_id']); // Use getVendorProfileById as booking.vendor_id is vendor_profiles.id
    $booking['vendor_business_name'] = isset($vendor_profile_details['business_name']) ? $vendor_profile_details['business_name'] : 'N/A';


    // Fetch service name using service_id from bookings and vendor_services table
    // $booking['service_id'] here refers to vendor_services.id
    $service_name = 'N/A';
    if ($booking['service_id']) {
        $stmt_service = $pdo->prepare("SELECT service_name FROM vendor_services WHERE id = ?");
        $stmt_service->execute([$booking['service_id']]);
        $service_info = $stmt_service->fetch(PDO::FETCH_ASSOC);
        $service_name = isset($service_info['service_name']) ? $service_info['service_name'] : 'N/A';
    }
    $booking['service_name'] = $service_name;


    echo json_encode([
        'success' => true,
        'booking' => $booking,
        'client' => $client_safe_data,
        'vendor_business_name' => $booking['vendor_business_name']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
    error_log("Error in get_booking_details.php: " . $e->getMessage() . " on line " . $e->getLine() . " in file " . $e->getFile());
}
?>
