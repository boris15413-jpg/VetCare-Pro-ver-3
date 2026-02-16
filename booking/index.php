<?php
/**
 * VetCare Pro - Public Booking Gateway
 * Separate entry point for public-facing online booking.
 * Deploy at /booking/ - isolated from the EMR system.
 */

// Load core config and database (minimal includes)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/helpers.php';

// Do NOT load Auth or Security modules - this is public-facing

$db = Database::getInstance();

// Handle API calls through the booking gateway
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$action = $_GET['page'] ?? $_GET['action'] ?? '';

// Route API requests
if ($action === 'api' || strpos($requestUri, '/booking/api') !== false || 
    in_array($action, ['get_slots', 'get_doctors', 'get_month_availability', 'submit_booking'])) {
    // Forward to booking API
    if (in_array($action, ['get_slots', 'get_doctors', 'get_month_availability', 'submit_booking'])) {
        $_GET['action'] = $action;
    }
    require_once __DIR__ . '/../api/booking.php';
    exit;
}

// Only allow public booking pages
$page = $action ?: 'booking';
$allowedPages = ['booking', 'confirm'];

if (!in_array($page, $allowedPages)) {
    $page = 'booking';
}

// Set flag so booking page knows it's running from /booking/ gateway
define('BOOKING_GATEWAY', true);

if ($page === 'booking') {
    require_once __DIR__ . '/../pages/public_booking.php';
} elseif ($page === 'confirm') {
    require_once __DIR__ . '/../pages/booking_confirm.php';
}
