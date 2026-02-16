<?php
/** ヘッダーテンプレート (Update: v4対応) */
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/helpers.php'; // 念のため
$auth = new Auth();
$user = $auth->currentUser();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center">
                <i class="bi bi-heart-pulse-fill text-danger me-2 fs-4"></i>
                <div>
                    <div class="fw-bold"><?= h(APP_NAME) ?></div>
                    <small class="text-white-50">動物病院電子カルテ</small>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">メイン</div>
            <a href="?page=dashboard" class="nav-link <?= ($page ?? '') === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> ダッシュボード
            </a>
            <a href="?page=appointments" class="nav-link <?= ($page ?? '') === 'appointments' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i> 予約・受付
            </a>

            <div class="nav-section">患者管理</div>
            <a href="?page=patients" class="nav-link <?= ($page ?? '') === 'patients' ? 'active' : '' ?>">
                <i class="bi bi-clipboard2-pulse"></i> 患畜一覧
            </a>
            <a href="?page=owners" class="nav-link <?= ($page ?? '') === 'owners' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> 飼い主一覧
            </a>

            <div class="nav-section">診療・検査</div>
            <a href="?page=orders" class="nav-link <?= ($page ?? '') === 'orders' ? 'active' : '' ?>">
                <i class="bi bi-list-check"></i> オーダー管理
            </a>
            <a href="?page=lab_results" class="nav-link <?= ($page ?? '') === 'lab_results' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i> 検査結果
            </a>
            <a href="?page=pathology" class="nav-link <?= ($page ?? '') === 'pathology' ? 'active' : '' ?>">
                <i class="bi bi-microscope"></i> 病理検査
            </a>
            <a href="?page=pharmacy_print" class="nav-link <?= ($page ?? '') === 'pharmacy_print' ? 'active' : '' ?>">
                <i class="bi bi-capsule"></i> 薬袋・ラベル発行
            </a>
            <a href="?page=vaccinations" class="nav-link <?= ($page ?? '') === 'vaccinations' ? 'active' : '' ?>">
                <i class="bi bi-shield-plus"></i> ワクチン記録
            </a>

            <div class="nav-section">入院</div>
            <a href="?page=admissions" class="nav-link <?= ($page ?? '') === 'admissions' ? 'active' : '' ?>">
                <i class="bi bi-hospital"></i> 入院管理
            </a>
            <a href="?page=temperature_chart&view=list" class="nav-link <?= ($page ?? '') === 'temperature_chart' ? 'active' : '' ?>">
                <i class="bi bi-thermometer-half"></i> 温度板
            </a>

            <?php if ($auth->hasRole([ROLE_ADMIN, ROLE_VET, ROLE_NURSE])): ?>
            <div class="nav-section">看護</div>
            <a href="?page=nursing" class="nav-link <?= ($page ?? '') === 'nursing' ? 'active' : '' ?>">
                <i class="bi bi-journal-medical"></i> 看護記録
            </a>
            <a href="?page=nursing_tasks" class="nav-link <?= ($page ?? '') === 'nursing_tasks' ? 'active' : '' ?>">
                <i class="bi bi-check2-square"></i> タスク管理
            </a>
            <?php endif; ?>

            <div class="nav-section">書類・会計</div>
            <a href="?page=documents" class="nav-link <?= ($page ?? '') === 'documents' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i> 書類作成
            </a>
            <a href="?page=invoices" class="nav-link <?= ($page ?? '') === 'invoices' ? 'active' : '' ?>">
                <i class="bi bi-receipt"></i> 会計管理
            </a>

            <?php if ($auth->hasRole([ROLE_ADMIN, ROLE_VET])): ?>
            <div class="nav-section">統計</div>
            <a href="?page=statistics" class="nav-link <?= ($page ?? '') === 'statistics' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line"></i> 統計・分析
            </a>
            <?php endif; ?>

            <?php if ($auth->hasRole([ROLE_ADMIN])): ?>
            <div class="nav-section">管理</div>
            <a href="?page=settings" class="nav-link <?= ($page ?? '') === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> 施設設定
            </a>
            <a href="?page=accounts" class="nav-link <?= ($page ?? '') === 'accounts' ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i> アカウント管理
            </a>
            <a href="?page=master_roles" class="nav-link <?= ($page ?? '') === 'master_roles' ? 'active' : '' ?>">
                <i class="bi bi-shield-lock"></i> 職種・権限管理
            </a>
            <a href="?page=admin_security" class="nav-link <?= ($page ?? '') === 'admin_security' ? 'active' : '' ?>">
               <i class="bi bi-shield-check"></i> セキュリティ
             </a>
            <a href="?page=master_drugs" class="nav-link <?= ($page ?? '') === 'master_drugs' ? 'active' : '' ?>">
                <i class="bi bi-capsule"></i> 薬品マスタ
            </a>
            <a href="?page=inventory_alert" class="nav-link <?= ($page ?? '') === 'inventory_alert' ? 'active' : '' ?>">
                <i class="bi bi-exclamation-triangle"></i> 在庫アラート
            </a>
            <a href="?page=master_tests" class="nav-link <?= ($page ?? '') === 'master_tests' ? 'active' : '' ?>">
                <i class="bi bi-eyedropper"></i> 検査マスタ
            </a>
            <a href="?page=master_procedures" class="nav-link <?= ($page ?? '') === 'master_procedures' ? 'active' : '' ?>">
                <i class="bi bi-tools"></i> 処置マスタ
            </a>
            <a href="?page=notices" class="nav-link <?= ($page ?? '') === 'notices' ? 'active' : '' ?>">
                <i class="bi bi-megaphone"></i> お知らせ
            </a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="main-content" id="mainContent">
        <nav class="topbar">
            <div class="d-flex align-items-center">
                <button class="btn btn-link text-dark p-0 me-3 d-lg-none" onclick="toggleSidebar()">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div class="d-none d-md-block">
                    <small class="text-muted"><?= date('Y年m月d日（') . ['日','月','火','水','木','金','土'][date('w')] . '）' ?></small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <?php if(isset($_SESSION['stock_alerts']) && !empty($_SESSION['stock_alerts'])): ?>
                <a href="?page=inventory_alert" class="btn btn-link text-warning position-relative p-0" title="在庫不足あり">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;">!</span>
                </a>
                <?php endif; ?>

                <a href="?page=notices" class="btn btn-link text-dark position-relative p-0" title="お知らせ">
                    <i class="bi bi-bell fs-5"></i>
                    <?php
                    $unreadCount = 0;
                    try {
                        $unreadCount = (int)$db->fetch("
                            SELECT COUNT(*) as cnt 
                            FROM notices n 
                            WHERE n.is_active = 1 
                            AND (n.target_role = ? OR n.target_role = '')
                            AND NOT EXISTS (
                                SELECT 1 FROM notice_reads nr 
                                WHERE nr.notice_id = n.id AND nr.user_id = ?
                            )
                        ", [$auth->currentUserRole(), $auth->currentUserId()])['cnt'];
                    } catch (Exception $e) { /* テーブル未作成時用 */ }
                    
                    if ($unreadCount > 0):
                    ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;">
                        <?= $unreadCount ?>
                    </span>
                    <?php endif; ?>
                </a>
                <div class="dropdown">
                    <button class="btn btn-link text-dark text-decoration-none dropdown-toggle p-0" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <span class="d-none d-md-inline"><?= h($auth->currentUserName()) ?></span>
                        <span class="badge bg-info ms-1"><?= h(getRoleName($auth->currentUserRole())) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="?page=profile"><i class="bi bi-person me-2"></i>プロフィール</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="?page=login&action=logout"><i class="bi bi-box-arrow-right me-2"></i>ログアウト</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="page-content">