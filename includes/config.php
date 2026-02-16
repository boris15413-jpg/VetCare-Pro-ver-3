<?php
/**
 * VetCare Pro - Advanced Veterinary EMR System
 * Configuration File
 */

// Error handling
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Asia/Tokyo');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
if (!session_id()) {
    session_start();
}

// Application settings
define('APP_NAME', 'VetCare Pro');
define('APP_VERSION', '3.1.0');
define('APP_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', __DIR__);

// Database settings
define('DB_DRIVER', 'sqlite');
define('DB_SQLITE_PATH', BASE_PATH . '/data/vetcare.db');
define('DB_MYSQL_HOST', 'localhost');
define('DB_MYSQL_NAME', 'vetcare_db');
define('DB_MYSQL_USER', 'root');
define('DB_MYSQL_PASS', '');
define('DB_MYSQL_CHARSET', 'utf8mb4');

// Upload settings
define('UPLOAD_DIR', BASE_PATH . '/uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_CSV_TYPES', ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel']);

// Security
define('PASSWORD_MIN_LENGTH', 8);
define('SESSION_LIFETIME', 28800);
define('CSRF_TOKEN_LENGTH', 32);
define('API_RATE_LIMIT', 60); // requests per minute

// Plugin system
define('PLUGINS_DIR', BASE_PATH . '/plugins/');
define('PLUGINS_ENABLED_FILE', BASE_PATH . '/data/plugins.json');

// LINE Integration
define('LINE_CHANNEL_ACCESS_TOKEN', '');
define('LINE_CHANNEL_SECRET', '');
define('LINE_LOGIN_CHANNEL_ID', '');
define('LINE_LOGIN_CHANNEL_SECRET', '');

// Species master
define('SPECIES_LIST', [
    'dog' => "\xe7\x8a\xac",
    'cat' => "\xe7\x8c\xab",
    'rabbit' => "\xe3\x82\xa6\xe3\x82\xb5\xe3\x82\xae",
    'hamster' => "\xe3\x83\x8f\xe3\x83\xa0\xe3\x82\xb9\xe3\x82\xbf\xe3\x83\xbc",
    'bird' => "\xe9\xb3\xa5",
    'ferret' => "\xe3\x83\x95\xe3\x82\xa7\xe3\x83\xac\xe3\x83\x83\xe3\x83\x88",
    'turtle' => "\xe3\x82\xab\xe3\x83\xa1",
    'guinea_pig' => "\xe3\x83\xa2\xe3\x83\xab\xe3\x83\xa2\xe3\x83\x83\xe3\x83\x88",
    'chinchilla' => "\xe3\x83\x81\xe3\x83\xb3\xe3\x83\x81\xe3\x83\xa9",
    'hedgehog' => "\xe3\x83\x8f\xe3\x83\xaa\xe3\x83\x8d\xe3\x82\xba\xe3\x83\x9f",
    'snake' => "\xe3\x83\x98\xe3\x83\x93",
    'lizard' => "\xe3\x83\x88\xe3\x82\xab\xe3\x82\xb2",
    'fish' => "\xe9\xad\x9a",
    'other' => "\xe3\x81\x9d\xe3\x81\xae\xe4\xbb\x96"
]);

// Sex master
define('SEX_LIST', [
    'male' => "\xe3\x82\xaa\xe3\x82\xb9",
    'female' => "\xe3\x83\xa1\xe3\x82\xb9",
    'male_neutered' => "\xe3\x82\xaa\xe3\x82\xb9\xef\xbc\x88\xe5\x8e\xbb\xe5\x8b\xa2\xe6\xb8\x88\xef\xbc\x89",
    'female_spayed' => "\xe3\x83\xa1\xe3\x82\xb9\xef\xbc\x88\xe9\x81\xbf\xe5\xa6\x8a\xe6\xb8\x88\xef\xbc\x89",
    'unknown' => "\xe4\xb8\x8d\xe6\x98\x8e"
]);

// Blood types
define('BLOOD_TYPE_DOG', ['DEA1.1+', 'DEA1.1-', 'DEA1.2+', 'DEA1.2-', "\xe4\xb8\x8d\xe6\x98\x8e"]);
define('BLOOD_TYPE_CAT', ["A\xe5\x9e\x8b", "B\xe5\x9e\x8b", "AB\xe5\x9e\x8b", "\xe4\xb8\x8d\xe6\x98\x8e"]);

// Staff roles
define('ROLE_ADMIN', 'admin');
define('ROLE_VET', 'veterinarian');
define('ROLE_NURSE', 'nurse');
define('ROLE_RECEPTION', 'reception');
define('ROLE_LAB', 'lab_tech');

define('ROLE_NAMES', [
    'admin' => "\xe3\x82\xb7\xe3\x82\xb9\xe3\x83\x86\xe3\x83\xa0\xe7\xae\xa1\xe7\x90\x86\xe8\x80\x85",
    'veterinarian' => "\xe7\x8d\xa3\xe5\x8c\xbb\xe5\xb8\xab",
    'nurse' => "\xe5\x8b\x95\xe7\x89\xa9\xe7\x9c\x8b\xe8\xad\xb7\xe5\xb8\xab",
    'reception' => "\xe5\x8f\x97\xe4\xbb\x98",
    'lab_tech' => "\xe6\xa4\x9c\xe6\x9f\xbb\xe6\x8a\x80\xe5\xb8\xab"
]);

// Order status
define('ORDER_STATUS', [
    'pending' => "\xe6\x9c\xaa\xe5\xae\x9f\xe6\x96\xbd",
    'in_progress' => "\xe5\xae\x9f\xe6\x96\xbd\xe4\xb8\xad",
    'completed' => "\xe5\xae\x8c\xe4\xba\x86",
    'cancelled' => "\xe3\x82\xad\xe3\x83\xa3\xe3\x83\xb3\xe3\x82\xbb\xe3\x83\xab"
]);

// Admission status
define('ADMISSION_STATUS', [
    'admitted' => "\xe5\x85\xa5\xe9\x99\xa2\xe4\xb8\xad",
    'discharged' => "\xe9\x80\x80\xe9\x99\xa2",
    'transferred' => "\xe8\xbb\xa2\xe9\x99\xa2",
    'deceased' => "\xe6\xad\xbb\xe4\xba\xa1"
]);

// Appointment types
define('APPOINTMENT_TYPES', [
    'general' => "\xe4\xb8\x80\xe8\x88\xac\xe8\xa8\xba\xe5\xaf\x9f",
    'follow_up' => "\xe5\x86\x8d\xe8\xa8\xba",
    'vaccination' => "\xe3\x83\xaf\xe3\x82\xaf\xe3\x83\x81\xe3\x83\xb3",
    'checkup' => "\xe5\x81\xa5\xe5\xba\xb7\xe8\xa8\xba\xe6\x96\xad",
    'surgery' => "\xe6\x89\x8b\xe8\xa1\x93",
    'grooming' => "\xe3\x83\x88\xe3\x83\xaa\xe3\x83\x9f\xe3\x83\xb3\xe3\x82\xb0",
    'emergency' => "\xe7\xb7\x8a\xe6\x80\xa5"
]);

// Appointment status
define('APPOINTMENT_STATUS', [
    'scheduled' => "\xe4\xba\x88\xe7\xb4\x84\xe6\xb8\x88",
    'checked_in' => "\xe5\x8f\x97\xe4\xbb\x98\xe6\xb8\x88",
    'in_progress' => "\xe8\xa8\xba\xe5\xaf\x9f\xe4\xb8\xad",
    'completed' => "\xe5\xae\x8c\xe4\xba\x86",
    'cancelled' => "\xe3\x82\xad\xe3\x83\xa3\xe3\x83\xb3\xe3\x82\xbb\xe3\x83\xab",
    'no_show' => "\xe7\x84\xa1\xe6\x96\xad\xe3\x82\xad\xe3\x83\xa3\xe3\x83\xb3\xe3\x82\xbb\xe3\x83\xab"
]);
