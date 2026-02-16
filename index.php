<?php
ob_start();
/**
 * VetCare Pro - メインエントリーポイント 
 **/

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/helpers.php';

// データベース接続
$db = Database::getInstance();

$currentPage = $_GET['page'] ?? '';

if ($currentPage !== 'install') {
    $isInstalled = false;
    try {
        // staffテーブルが存在し、かつユーザーが1人以上登録されているか確認
        // (テーブルが存在しない場合はPDOExceptionが発生してcatchブロックへ移動します)
        $userCount = $db->query("SELECT COUNT(*) FROM staff")->fetchColumn();
        if ($userCount > 0) {
            $isInstalled = true;
        }
    } catch (Exception $e) {
        // テーブル未作成などのエラー -> 未インストールとみなす
        $isInstalled = false;
    }

    if (!$isInstalled) {
        // 未インストールならインストール画面へ強制リダイレクト
        header('Location: index.php?page=install');
        exit;
    }
}

$auth = new Auth();

// ページルーティング
// ログイン済みならダッシュボード、未ログインならログインページへ
$page = $_GET['page'] ?? ($_SESSION['user_id'] ?? false ? 'dashboard' : 'login');

// ログイン不要ページ
$publicPages = ['login', 'install'];

if (!in_array($page, $publicPages)) {
    $auth->requireLogin();
}

// APIリクエスト
if (strpos($page, 'api/') === 0) {
    $apiFile = __DIR__ . '/api/' . str_replace('api/', '', $page) . '.php';
    if (file_exists($apiFile)) {
        require_once $apiFile;
    } else {
        jsonResponse(['error' => 'API not found'], 404);
    }
    exit;
}

// ページマッピング
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
];

$pageFile = $pageFiles[$page] ?? null;

if (!$pageFile || !file_exists(__DIR__ . '/' . $pageFile)) {
    // ページが見つからない場合はダッシュボードへ (ログイン済みの場合)
    // 未ログインの場合は Auth::requireLogin() で弾かれる
    $page = 'dashboard';
    $pageFile = 'pages/dashboard.php';
}

// 印刷系ページはレイアウト(ヘッダー・フッター)不要
$nolayoutPages = ['document_print', 'invoice_print', 'pharmacy_print'];
if (in_array($page, $nolayoutPages)) {
    require_once __DIR__ . '/' . $pageFile;
    exit;
}

// ログインページ、インストールページもレイアウト不要
if ($page === 'login' || $page === 'install') {
    require_once __DIR__ . '/' . $pageFile;
    exit;
}

// レイアウト付きページ
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/' . $pageFile;
require_once __DIR__ . '/templates/footer.php';