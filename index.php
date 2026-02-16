<?php
ob_start();
/**
 * VetCare Pro v3.1 - Main Entry Point (Framework Architecture)
 * Security-enhanced: IP whitelist, access control, booking separation
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/PluginManager.php';

// Framework classes
require_once __DIR__ . '/includes/Framework/Template.php';
require_once __DIR__ . '/includes/Framework/Router.php';
require_once __DIR__ . '/includes/Framework/App.php';

// Database connection
$db = Database::getInstance();

// Plugin system init
$pluginManager = PluginManager::getInstance();
$pluginManager->loadEnabledPlugins();

// Initialize framework
$app = App::getInstance();
$app->register('db', $db);
$template = Template::getInstance();
$router = Router::getInstance();

$currentPage = $_GET['page'] ?? '';

// Public pages that don't require installation or login
$publicPages = ['login', 'install', 'public_booking', 'booking_confirm', 'reception_display', 'accounting_display', 'api_booking'];

// Installation check (skip for public pages)
if (!in_array($currentPage, $publicPages)) {
    $isInstalled = false;
    try {
        $userCount = $db->query("SELECT COUNT(*) FROM staff")->fetchColumn();
        if ($userCount > 0) {
            $isInstalled = true;
        }
    } catch (Exception $e) {
        $isInstalled = false;
    }

    if (!$isInstalled) {
        header('Location: index.php?page=install');
        exit;
    }
}

// Auto backup (daily)
try {
    require_once __DIR__ . '/includes/Backup.php';
    Backup::run();
} catch (Exception $e) { /* silent */ }

$auth = new Auth();
$app->register('auth', $auth);

// Page routing
$page = $_GET['page'] ?? ($_SESSION['user_id'] ?? false ? 'dashboard' : 'login');

// ========== SECURITY: Access Control ==========

// For the login page: check IP whitelist
if ($page === 'login') {
    Security::checkLoginAccess();
}

// For non-public pages: enforce IP whitelist and access policy
if (!in_array($page, $publicPages)) {
    // Check IP whitelist for EMR pages
    if (!Security::isIPAllowed()) {
        Security::checkLoginAccess();
    }
    
    // Check if EMR is at root and enforce separate access
    Security::enforceAccessPolicy();
    
    // Require login
    $auth->requireLogin();
}

// Set security headers for all responses
Security::setSecurityHeaders();

// ========== END SECURITY ==========

// API requests
if (strpos($page, 'api/') === 0 || strpos($page, 'api_') === 0) {
    $apiName = str_replace(['api/', 'api_'], '', $page);
    $apiFile = __DIR__ . '/api/' . $apiName . '.php';
    if (file_exists($apiFile)) {
        require_once $apiFile;
    } else {
        jsonResponse(['error' => 'API not found'], 404);
    }
    exit;
}

// Feature flags - check if insurance is enabled
$insuranceEnabled = getSetting('feature_insurance', '1') === '1';

// Core page mapping
$pageFiles = [
    'login' => 'pages/login.php',
    'dashboard' => 'pages/dashboard.php',
    'patients' => 'pages/patients.php',
    'patient_detail' => 'pages/patient_detail.php',
    'patient_form' => 'pages/patient_form.php',
    'owners' => 'pages/owners.php',
    'owner_form' => 'pages/owner_form.php',
    'medical_record' => 'pages/medical_record.php',
    'record_form' => 'pages/record_form.php',
    'temperature_chart' => 'pages/temperature_chart.php',
    'temperature_form' => 'pages/temperature_form.php',
    'admissions' => 'pages/admissions.php',
    'admission_form' => 'pages/admission_form.php',
    'orders' => 'pages/orders.php',
    'order_form' => 'pages/order_form.php',
    'pathology' => 'pages/pathology.php',
    'pathology_form' => 'pages/pathology_form.php',
    'lab_results' => 'pages/lab_results.php',
    'lab_import' => 'pages/lab_import.php',
    'nursing' => 'pages/nursing.php',
    'nursing_tasks' => 'pages/nursing_tasks.php',
    'nursing_record_form' => 'pages/nursing_record_form.php',
    'documents' => 'pages/documents.php',
    'document_print' => 'pages/document_print.php',
    'pharmacy_print' => 'pages/pharmacy_print.php',
    'invoices' => 'pages/invoices.php',
    'invoice_form' => 'pages/invoice_form.php',
    'invoice_print' => 'pages/invoice_print.php',
    'inventory_alert' => 'pages/inventory_alert.php',
    'appointments' => 'pages/appointments.php',
    'appointment_form' => 'pages/appointment_form.php',
    'reception' => 'pages/reception.php',
    'vaccinations' => 'pages/vaccinations.php',
    'accounts' => 'pages/accounts.php',
    'account_form' => 'pages/account_form.php',
    'master_roles' => 'pages/master_roles.php',
    'profile' => 'pages/profile.php',
    'master_drugs' => 'pages/master_drugs.php',
    'master_tests' => 'pages/master_tests.php',
    'master_procedures' => 'pages/master_procedures.php',
    'notices' => 'pages/notices.php',
    'notice_detail' => 'pages/notice_detail.php',
    'statistics' => 'pages/statistics.php',
    'install' => 'pages/install.php',
    'settings' => 'pages/settings.php',
    'admin_security' => 'pages/admin_security.php',
    'plugin_manager' => 'pages/plugin_manager.php',
    'line_settings' => 'pages/line_settings.php',
    // Insurance & Recept
    'insurance_master' => 'pages/insurance_master.php',
    'insurance_claims' => 'pages/insurance_claims.php',
    'insurance_claim_form' => 'pages/insurance_claim_form.php',
    'recept_print' => 'pages/recept_print.php',
    'estimates' => 'pages/estimates.php',
    'estimate_form' => 'pages/estimate_form.php',
    'diagnosis_master' => 'pages/diagnosis_master.php',
    // Clinical features
    'weight_history' => 'pages/weight_history.php',
    'referral_form' => 'pages/referral_form.php',
    'discharge_summary' => 'pages/discharge_summary.php',
    'consent_form' => 'pages/consent_form.php',
    'insurance_export' => 'pages/insurance_export.php',
    'update_db' => 'pages/update_db.php',
    // New features
    'staff_schedule' => 'pages/staff_schedule.php',
    'patient_images' => 'pages/patient_images.php',
    'closed_days' => 'pages/closed_days.php',
    'clinical_templates' => 'pages/clinical_templates.php',
    // Public pages
    'public_booking' => 'pages/public_booking.php',
    'booking_confirm' => 'pages/booking_confirm.php',
    'reception_display' => 'pages/reception_display.php',
    'accounting_display' => 'pages/accounting_display.php',
];

// Merge plugin routes
$pluginRoutes = $pluginManager->getPluginRoutes();
$pageFiles = array_merge($pageFiles, $pluginRoutes);

// Allow plugins to modify routes
$pageFiles = apply_filter('page_routes', $pageFiles);

$pageFile = $pageFiles[$page] ?? null;

if (!$pageFile || !file_exists(__DIR__ . '/' . $pageFile)) {
    $page = 'dashboard';
    $pageFile = 'pages/dashboard.php';
}

// Set template globals
$template->setGlobals([
    'app' => $app,
    'db' => $db,
    'auth' => $auth,
    'page' => $page,
    'insuranceEnabled' => $insuranceEnabled,
]);

// No-layout pages (print, public, etc.)
$nolayoutPages = ['document_print', 'invoice_print', 'pharmacy_print', 'recept_print', 'insurance_export', 'public_booking', 'booking_confirm', 'reception_display', 'accounting_display'];
if (in_array($page, $nolayoutPages)) {
    require_once __DIR__ . '/' . $pageFile;
    exit;
}

// Login & Install pages (own layout)
if ($page === 'login' || $page === 'install') {
    require_once __DIR__ . '/' . $pageFile;
    exit;
}

// Hook: before page render
do_action('before_page_render', $page);

// Standard layout
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/' . $pageFile;
require_once __DIR__ . '/templates/footer.php';

// Hook: after page render
do_action('after_page_render', $page);
