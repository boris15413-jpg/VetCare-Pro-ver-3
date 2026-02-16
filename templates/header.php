<?php
/** VetCare Pro v3.0 - Premium Header Template */
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$auth = new Auth();
$user = $auth->currentUser();
$currentTheme = $_COOKIE['vc_theme'] ?? 'light';

// Unread notice count
$unreadCount = 0;
try {
    $unreadCount = (int)$db->fetch("
        SELECT COUNT(*) as cnt FROM notices n 
        WHERE n.is_active = 1 
        AND (n.target_role = ? OR n.target_role = '')
        AND NOT EXISTS (SELECT 1 FROM notice_reads nr WHERE nr.notice_id = n.id AND nr.user_id = ?)
    ", [$auth->currentUserRole(), $auth->currentUserId()])['cnt'];
} catch (Exception $e) {}

// Stock alerts
$stockAlertCount = 0;
try {
    $stockAlertCount = (int)$db->fetch("SELECT COUNT(*) as cnt FROM drug_master WHERE stock_quantity <= min_stock AND is_active = 1")['cnt'];
} catch (Exception $e) {}

// Today's appointment count
$todayAptCount = 0;
try {
    $todayAptCount = (int)$db->fetch("SELECT COUNT(*) as cnt FROM appointments WHERE appointment_date = ? AND status IN ('scheduled','checked_in')", [date('Y-m-d')])['cnt'];
} catch (Exception $e) {}

// Pending insurance claims
$insuranceEnabled = getSetting('feature_insurance', '1') === '1';
$pendingClaimsCount = 0;
if ($insuranceEnabled) {
    try {
        $pendingClaimsCount = (int)$db->fetch("SELECT COUNT(*) as cnt FROM insurance_claims WHERE claim_status IN ('draft','submitted')")['cnt'];
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="ja" data-theme="<?= h($currentTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?> - 動物病院電子カルテ</title>
    
    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <?php do_action('enqueue_styles'); ?>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="brand-logo">
                <div class="brand-icon"><i class="bi bi-heart-pulse-fill"></i></div>
                <div>
                    <div class="brand-name"><?= h(APP_NAME) ?></div>
                    <div class="brand-sub">Veterinary EMR</div>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <!-- Main -->
            <div class="nav-section">メイン</div>
            <a href="?page=dashboard" class="nav-link <?= ($page ?? '') === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> ダッシュボード
            </a>
            <a href="?page=reception" class="nav-link <?= ($page ?? '') === 'reception' ? 'active' : '' ?>">
                <i class="bi bi-display"></i> 受付・待合
                <?php if ($todayAptCount > 0): ?>
                <span class="nav-badge bg-primary text-white"><?= $todayAptCount ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=appointments" class="nav-link <?= ($page ?? '') === 'appointments' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i> 予約管理
            </a>

            <!-- Patient Management -->
            <div class="nav-section">患者管理</div>
            <a href="?page=patients" class="nav-link <?= ($page ?? '') === 'patients' ? 'active' : '' ?>">
                <i class="bi bi-clipboard2-pulse"></i> 患畜一覧
            </a>
            <a href="?page=owners" class="nav-link <?= ($page ?? '') === 'owners' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> 飼い主一覧
            </a>

            <!-- Clinical -->
            <div class="nav-section">診療・検査</div>
            <a href="?page=orders" class="nav-link <?= ($page ?? '') === 'orders' ? 'active' : '' ?>">
                <i class="bi bi-list-check"></i> オーダー管理
            </a>
            <a href="?page=lab_results" class="nav-link <?= ($page ?? '') === 'lab_results' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i> 検査結果
            </a>
            <a href="?page=lab_import" class="nav-link <?= ($page ?? '') === 'lab_import' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-arrow-up"></i> CSV取込
            </a>
            <a href="?page=pathology" class="nav-link <?= ($page ?? '') === 'pathology' ? 'active' : '' ?>">
                <i class="bi bi-microscope"></i> 病理検査
            </a>
            <a href="?page=vaccinations" class="nav-link <?= ($page ?? '') === 'vaccinations' ? 'active' : '' ?>">
                <i class="bi bi-shield-plus"></i> ワクチン記録
            </a>

            <!-- Inpatient -->
            <div class="nav-section">入院</div>
            <a href="?page=admissions" class="nav-link <?= ($page ?? '') === 'admissions' ? 'active' : '' ?>">
                <i class="bi bi-hospital"></i> 入院管理
            </a>
            <a href="?page=temperature_chart&view=list" class="nav-link <?= ($page ?? '') === 'temperature_chart' ? 'active' : '' ?>">
                <i class="bi bi-thermometer-half"></i> 温度板
            </a>

            <!-- Nursing -->
            <?php if ($auth->hasRole([ROLE_ADMIN, ROLE_VET, ROLE_NURSE])): ?>
            <div class="nav-section">看護</div>
            <a href="?page=nursing" class="nav-link <?= ($page ?? '') === 'nursing' ? 'active' : '' ?>">
                <i class="bi bi-journal-medical"></i> 看護記録
            </a>
            <a href="?page=nursing_tasks" class="nav-link <?= ($page ?? '') === 'nursing_tasks' ? 'active' : '' ?>">
                <i class="bi bi-check2-square"></i> タスク管理
            </a>
            <?php endif; ?>

            <!-- Documents & Billing -->
            <div class="nav-section">書類・会計</div>
            <a href="?page=documents" class="nav-link <?= ($page ?? '') === 'documents' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i> 書類作成
            </a>
            <a href="?page=consent_form&action=list" class="nav-link <?= ($page ?? '') === 'consent_form' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-check"></i> 同意書
            </a>
            <a href="?page=invoices" class="nav-link <?= ($page ?? '') === 'invoices' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i> 会計管理
            </a>
            <a href="?page=estimates" class="nav-link <?= ($page ?? '') === 'estimates' ? 'active' : '' ?>">
                <i class="bi bi-calculator"></i> 見積もり
            </a>

            <!-- Insurance & Recept (conditional) -->
            <?php if ($insuranceEnabled): ?>
            <div class="nav-section">保険・レセプト</div>
            <a href="?page=insurance_claims" class="nav-link <?= in_array($page ?? '', ['insurance_claims', 'insurance_claim_form']) ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-medical"></i> レセプト管理
                <?php if ($pendingClaimsCount > 0): ?>
                <span class="nav-badge bg-warning text-dark"><?= $pendingClaimsCount ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <!-- Statistics -->
            <?php if ($auth->hasRole([ROLE_ADMIN, ROLE_VET])): ?>
            <div class="nav-section">統計</div>
            <a href="?page=statistics" class="nav-link <?= ($page ?? '') === 'statistics' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line"></i> 統計・分析
            </a>
            <?php endif; ?>

            <!-- Admin -->
            <?php if ($auth->hasRole([ROLE_ADMIN])): ?>
            <div class="nav-section">管理</div>
            <a href="?page=settings" class="nav-link <?= ($page ?? '') === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> 施設設定
            </a>
            <a href="?page=accounts" class="nav-link <?= ($page ?? '') === 'accounts' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i> アカウント管理
            </a>
            <a href="?page=staff_schedule" class="nav-link <?= ($page ?? '') === 'staff_schedule' ? 'active' : '' ?>">
                <i class="bi bi-calendar2-week"></i> 勤務表管理
            </a>
            <a href="?page=closed_days" class="nav-link <?= ($page ?? '') === 'closed_days' ? 'active' : '' ?>">
                <i class="bi bi-calendar-x"></i> 休診日管理
            </a>
            <a href="?page=clinical_templates" class="nav-link <?= ($page ?? '') === 'clinical_templates' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-ruled"></i> テンプレート
            </a>
            <a href="?page=line_settings" class="nav-link <?= ($page ?? '') === 'line_settings' ? 'active' : '' ?>">
                <i class="bi bi-chat-dots"></i> LINE連携
            </a>
            <a href="?page=plugin_manager" class="nav-link <?= ($page ?? '') === 'plugin_manager' ? 'active' : '' ?>">
                <i class="bi bi-puzzle"></i> プラグイン
            </a>

            <div class="nav-section">マスタ管理</div>
            <a href="?page=master_drugs" class="nav-link <?= ($page ?? '') === 'master_drugs' ? 'active' : '' ?>">
                <i class="bi bi-capsule"></i> 薬品マスタ
            </a>
            <a href="?page=inventory_alert" class="nav-link <?= ($page ?? '') === 'inventory_alert' ? 'active' : '' ?>">
                <i class="bi bi-exclamation-triangle"></i> 在庫アラート
                <?php if ($stockAlertCount > 0): ?>
                <span class="nav-badge bg-danger text-white"><?= $stockAlertCount ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=master_tests" class="nav-link <?= ($page ?? '') === 'master_tests' ? 'active' : '' ?>">
                <i class="bi bi-eyedropper"></i> 検査マスタ
            </a>
            <a href="?page=master_procedures" class="nav-link <?= ($page ?? '') === 'master_procedures' ? 'active' : '' ?>">
                <i class="bi bi-tools"></i> 処置マスタ
            </a>
            <?php if ($insuranceEnabled): ?>
            <a href="?page=insurance_master" class="nav-link <?= ($page ?? '') === 'insurance_master' ? 'active' : '' ?>">
                <i class="bi bi-building"></i> 保険会社マスタ
            </a>
            <?php endif; ?>
            <a href="?page=diagnosis_master" class="nav-link <?= ($page ?? '') === 'diagnosis_master' ? 'active' : '' ?>">
                <i class="bi bi-journal-code"></i> 診断マスタ
            </a>
            <a href="?page=admin_security" class="nav-link <?= ($page ?? '') === 'admin_security' ? 'active' : '' ?>">
                <i class="bi bi-shield-check"></i> セキュリティ
            </a>
            <a href="?page=notices" class="nav-link <?= ($page ?? '') === 'notices' ? 'active' : '' ?>">
                <i class="bi bi-megaphone"></i> お知らせ
            </a>
            <?php endif; ?>

            <?php do_action('sidebar_menu', $page, $auth); ?>
        </nav>
    </div>

    <!-- Main content -->
    <div class="main-content" id="mainContent">
        <!-- Topbar -->
        <nav class="topbar">
            <div class="topbar-left">
                <button class="topbar-icon-btn d-lg-none" onclick="toggleSidebar()" aria-label="メニュー">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div class="d-none d-md-block">
                    <small class="text-muted fw-medium">
                        <?php
                        $dow = ['日','月','火','水','木','金','土'];
                        echo date('Y年m月d日') . '(' . $dow[date('w')] . ')';
                        ?>
                    </small>
                </div>
            </div>
            <div class="topbar-right">
                <!-- Theme toggle -->
                <button class="topbar-icon-btn" onclick="toggleTheme()" title="テーマ切替">
                    <i class="bi bi-<?= $currentTheme === 'dark' ? 'sun' : 'moon-stars' ?>"></i>
                </button>

                <!-- Stock alerts -->
                <?php if ($stockAlertCount > 0): ?>
                <a href="?page=inventory_alert" class="topbar-icon-btn" title="在庫不足: <?= $stockAlertCount ?>件">
                    <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                    <span class="notification-dot" style="background: var(--warning);"></span>
                </a>
                <?php endif; ?>

                <!-- Notifications -->
                <a href="?page=notices" class="topbar-icon-btn" title="お知らせ">
                    <i class="bi bi-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="notification-dot"></span>
                    <?php endif; ?>
                </a>

                <!-- User dropdown -->
                <div class="dropdown">
                    <button class="user-pill dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="true">
                        <div class="user-avatar">
                            <?= mb_substr($auth->currentUserName(), 0, 1) ?>
                        </div>
                        <div class="user-info d-none d-md-block">
                            <div class="user-name"><?= h($auth->currentUserName()) ?></div>
                            <div class="user-role"><?= h(getRoleName($auth->currentUserRole())) ?></div>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg" style="border-radius:12px; min-width:200px;">
                        <li class="px-3 py-2 border-bottom">
                            <small class="text-muted">ログイン中</small>
                            <div class="fw-bold"><?= h($auth->currentUserName()) ?></div>
                        </li>
                        <li><a class="dropdown-item py-2" href="?page=profile"><i class="bi bi-person me-2"></i>プロフィール</a></li>
                        <?php if ($auth->hasRole([ROLE_ADMIN])): ?>
                        <li><a class="dropdown-item py-2" href="?page=settings"><i class="bi bi-gear me-2"></i>設定</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="?page=login&action=logout"><i class="bi bi-box-arrow-right me-2"></i>ログアウト</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="page-content">
            <?php renderFlash(); ?>
