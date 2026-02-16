<?php
/**
 * VetCare Pro v3.1 - Full Installation Wizard
 * Complete open-source installer: environment check, DB init, admin, facility, LINE
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$step = $_POST['step'] ?? $_GET['step'] ?? '1';
$msg = '';
$error = '';
$totalSteps = 6;

// Environment check for Step 1
function checkEnvironment() {
    $checks = [];
    // PHP version
    $checks['php_version'] = [
        'label' => 'PHP バージョン (' . phpversion() . ')',
        'ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'required' => '8.0以上'
    ];
    // PDO SQLite
    $checks['pdo_sqlite'] = [
        'label' => 'PDO SQLite 拡張',
        'ok' => extension_loaded('pdo_sqlite'),
        'required' => '必須'
    ];
    // Session
    $checks['session'] = [
        'label' => 'セッション',
        'ok' => session_status() === PHP_SESSION_ACTIVE || session_status() === PHP_SESSION_NONE,
        'required' => '必須'
    ];
    // JSON
    $checks['json'] = [
        'label' => 'JSON 拡張',
        'ok' => extension_loaded('json'),
        'required' => '必須'
    ];
    // mbstring
    $checks['mbstring'] = [
        'label' => 'mbstring 拡張',
        'ok' => extension_loaded('mbstring'),
        'required' => '推奨'
    ];
    // Data directory writable
    $dataDir = BASE_PATH . '/data';
    if (!is_dir($dataDir)) { @mkdir($dataDir, 0755, true); }
    $checks['data_dir'] = [
        'label' => 'data/ ディレクトリ (書き込み可)',
        'ok' => is_dir($dataDir) && is_writable($dataDir),
        'required' => '必須'
    ];
    // Uploads directory
    $uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : BASE_PATH . '/uploads/';
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
    $checks['upload_dir'] = [
        'label' => 'uploads/ ディレクトリ (書き込み可)',
        'ok' => is_dir($uploadDir) && is_writable($uploadDir),
        'required' => '必須'
    ];
    // Backups directory
    $backupDir = BASE_PATH . '/backups';
    if (!is_dir($backupDir)) { @mkdir($backupDir, 0755, true); }
    $checks['backup_dir'] = [
        'label' => 'backups/ ディレクトリ (書き込み可)',
        'ok' => is_dir($backupDir) && is_writable($backupDir),
        'required' => '推奨'
    ];
    return $checks;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    
    if ($step === '1') {
        // Step 1: Environment + Database migration
        $envChecks = checkEnvironment();
        $criticalFail = false;
        foreach ($envChecks as $k => $c) {
            if (!$c['ok'] && $c['required'] === '必須') {
                $criticalFail = true;
            }
        }
        if ($criticalFail) {
            $error = '必須の環境要件を満たしていません。上記の赤い項目を確認してください。';
            $step = '1';
        } else {
            // Create directories
            $dirs = [BASE_PATH . '/data', BASE_PATH . '/uploads', BASE_PATH . '/backups', BASE_PATH . '/plugins'];
            foreach ($dirs as $d) {
                if (!is_dir($d)) { @mkdir($d, 0755, true); }
            }
            
            // Run ALL migrations in order
            $migrations = [
                '001_create_tables.php',
                '003_insurance_recept.php',
                '004_v3_enhancements.php',
                '005_v3_framework.php',
                '006_usability_features.php',
            ];
            $migErrors = [];
            foreach ($migrations as $mig) {
                $migPath = BASE_PATH . '/migrations/' . $mig;
                if (file_exists($migPath)) {
                    try {
                        ob_start();
                        require_once $migPath;
                        ob_get_clean();
                    } catch (Exception $e) {
                        $migErrors[] = $mig . ': ' . $e->getMessage();
                    }
                }
            }
            
            // Enable all features by default on fresh install
            $defaultSettings = [
                'feature_insurance' => '1',
                'public_booking_enabled' => '1',
                'booking_new_patient_enabled' => '1',
                'priority_reservation' => '0',
                'accounting_display_mode' => 'name',
                'tax_rate' => '10',
                'appointment_interval' => '30',
                'appointment_start_time' => '09:00',
                'appointment_end_time' => '18:00',
                'max_appointments_per_slot' => '3',
                'backup_retention_days' => '30',
                'booking_lunch_start' => '12:00',
                'booking_lunch_end' => '13:00',
                'booking_days_ahead' => '60',
                'booking_welcome_message' => '',
                'booking_notice_message' => '',
                'business_hours_weekday' => '9:00〜12:00 / 16:00〜19:00',
                'business_hours_saturday' => '9:00〜12:00',
                'business_hours_holiday' => '休診',
                'closed_weekdays' => '0',
                'receipt_footer_message' => 'お大事になさってください。',
                'security_ip_whitelist_enabled' => '0',
                'security_enforce_separate_access' => '0',
            ];
            foreach ($defaultSettings as $k => $v) {
                try { setSetting($k, $v); } catch (Exception $e) {}
            }
            
            if (!empty($migErrors)) {
                $msg = 'データベース初期化完了（一部警告あり: ' . implode(', ', $migErrors) . '）';
            } else {
                $msg = 'データベーステーブルの作成が完了しました。全機能が有効化されています。';
            }
            $step = '2';
        }
    }
    elseif ($step === '2') {
        if (isset($_POST['load_sample'])) {
            try {
                ob_start();
                require_once BASE_PATH . '/migrations/002_seed_data.php';
                ob_get_clean();
                $msg = 'サンプルデータを投入しました。デモアカウント: admin/admin123';
            } catch (Exception $e) {
                $error = 'サンプルデータ投入エラー: ' . $e->getMessage();
                $step = '2';
            }
        }
        if (!$error) $step = '3';
    }
    elseif ($step === '3') {
        $auth = new Auth();
        $loginId = trim($_POST['login_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $name = trim($_POST['name'] ?? '');
        
        if (strlen($loginId) < 3) {
            $error = 'ログインIDは3文字以上で入力してください。';
            $step = '3';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'パスワードは' . PASSWORD_MIN_LENGTH . '文字以上で入力してください。';
            $step = '3';
        } elseif (empty($name)) {
            $error = '氏名を入力してください。';
            $step = '3';
        } else {
            $existing = $db->fetch("SELECT id FROM staff WHERE login_id = ?", [$loginId]);
            if ($existing) {
                $msg = '管理者アカウントは既に存在します。次のステップに進みます。';
            } else {
                $result = $auth->createAccount([
                    'login_id' => $loginId,
                    'password' => $password,
                    'name' => $name,
                    'name_kana' => trim($_POST['name_kana'] ?? ''),
                    'role' => 'admin',
                    'email' => trim($_POST['email'] ?? ''),
                ]);
                if (isset($result['error'])) {
                    $error = $result['error'];
                    $step = '3';
                } else {
                    $msg = '管理者アカウントを作成しました。';
                }
            }
            if (!$error) $step = '4';
        }
    }
    elseif ($step === '4') {
        $settings = [
            'hospital_name' => trim($_POST['hospital_name'] ?? ''),
            'hospital_phone' => trim($_POST['hospital_phone'] ?? ''),
            'hospital_address' => trim($_POST['hospital_address'] ?? ''),
            'hospital_email' => trim($_POST['hospital_email'] ?? ''),
            'hospital_fax' => trim($_POST['hospital_fax'] ?? ''),
            'hospital_director' => trim($_POST['hospital_director'] ?? ''),
            'business_hours_weekday' => trim($_POST['hospital_hours'] ?? '9:00〜12:00 / 16:00〜19:00'),
            'business_hours_saturday' => trim($_POST['hospital_hours_sat'] ?? '9:00〜12:00'),
            'business_hours_holiday' => trim($_POST['hospital_hours_hol'] ?? '休診'),
            'appointment_interval' => trim($_POST['appointment_interval'] ?? '30'),
            'appointment_start_time' => trim($_POST['appointment_start_time'] ?? '09:00'),
            'appointment_end_time' => trim($_POST['appointment_end_time'] ?? '18:00'),
            'max_appointments_per_slot' => trim($_POST['max_appointments_per_slot'] ?? '3'),
            'booking_lunch_start' => trim($_POST['booking_lunch_start'] ?? '12:00'),
            'booking_lunch_end' => trim($_POST['booking_lunch_end'] ?? '13:00'),
            'public_booking_enabled' => isset($_POST['public_booking_enabled']) ? '1' : '0',
            'booking_new_patient_enabled' => isset($_POST['booking_new_patient_enabled']) ? '1' : '0',
            'priority_reservation' => isset($_POST['priority_reservation']) ? '1' : '0',
            'tax_rate' => trim($_POST['tax_rate'] ?? '10'),
            'feature_insurance' => isset($_POST['feature_insurance']) ? '1' : '0',
        ];
        
        // Handle closed days
        if (isset($_POST['closed_weekdays']) && is_array($_POST['closed_weekdays'])) {
            $settings['closed_weekdays'] = implode(',', $_POST['closed_weekdays']);
        } else {
            $settings['closed_weekdays'] = '';
        }
        
        foreach ($settings as $key => $value) {
            setSetting($key, $value);
        }
        
        $msg = '施設情報を保存しました。';
        $step = '5';
    }
    elseif ($step === '5') {
        $lineSettings = [
            'line_channel_access_token' => trim($_POST['line_channel_access_token'] ?? ''),
            'line_channel_secret' => trim($_POST['line_channel_secret'] ?? ''),
            'line_notify_appointment' => isset($_POST['line_notify_appointment']) ? '1' : '0',
            'line_notify_reminder' => isset($_POST['line_notify_reminder']) ? '1' : '0',
            'line_notify_vaccination' => isset($_POST['line_notify_vaccination']) ? '1' : '0',
        ];
        
        foreach ($lineSettings as $key => $value) {
            setSetting($key, $value);
        }
        
        $msg = 'LINE設定を保存しました。';
        $step = '6';
    }
    elseif ($step === '6') {
        $step = 'done';
    }
}

$envChecks = ($step === '1') ? checkEnvironment() : [];
?>
<!DOCTYPE html>
<html lang="ja"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>初期セットアップ - <?= h(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Noto Sans JP', -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh; margin: 0;
        }
        .install-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .install-card {
            width: 100%; max-width: 680px;
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.18);
            overflow: hidden;
        }
        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff; padding: 32px; text-align: center;
            position: relative;
        }
        .install-header::after {
            content: ''; position: absolute; bottom: 0; left: 0; right: 0;
            height: 4px; background: linear-gradient(90deg, #f093fb, #667eea, #764ba2);
        }
        .install-body { padding: 32px; }
        @media (max-width: 576px) { .install-body { padding: 20px 16px; } }

        .step-progress { display: flex; justify-content: center; align-items: center; gap: 4px; margin-bottom: 28px; flex-wrap: wrap; }
        .step-dot {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; font-weight: 700;
            background: #e2e8f0; color: #94a3b8;
            transition: all 0.3s; flex-shrink: 0;
        }
        .step-dot.active {
            background: linear-gradient(135deg, #667eea, #764ba2); color: #fff;
            transform: scale(1.15); box-shadow: 0 4px 16px rgba(102,126,234,0.4);
        }
        .step-dot.done { background: #10b981; color: #fff; }
        .step-line { width: 20px; height: 2px; background: #e2e8f0; flex-shrink: 0; }
        .step-line.done { background: #10b981; }

        .btn-install {
            padding: 14px 24px; font-size: 1rem; font-weight: 700;
            border-radius: 14px; width: 100%; margin-top: 8px;
            border: none; cursor: pointer; transition: all 0.2s;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .btn-install:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(102,126,234,0.3); color: #fff; }
        .btn-skip {
            padding: 12px 24px; font-size: 0.9rem; border-radius: 14px; width: 100%;
            background: #fff; color: #64748b; border: 2px solid #e2e8f0;
            cursor: pointer; transition: all 0.2s; font-weight: 600;
        }
        .btn-skip:hover { border-color: #667eea; color: #667eea; }

        .form-label { font-weight: 600; font-size: 0.88rem; margin-bottom: 4px; }
        .form-label.required::after { content: ' *'; color: #ef4444; }
        .form-control, .form-select { border-radius: 10px; border-color: #e2e8f0; padding: 10px 14px; }
        .form-control:focus, .form-select:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.12); }
        .form-text { font-size: 0.75rem; color: #64748b; }

        .env-check { padding: 8px 12px; border-radius: 10px; margin-bottom: 6px; display: flex; align-items: center; gap: 10px; font-size: 0.88rem; }
        .env-check.ok { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .env-check.fail { background: #fef2f2; border: 1px solid #fecaca; }
        .env-check.warn { background: #fffbeb; border: 1px solid #fde68a; }

        .weekday-checks { display: flex; flex-wrap: wrap; gap: 8px; }
        .weekday-checks .form-check {
            padding: 8px 16px; border: 2px solid #e2e8f0; border-radius: 10px;
            cursor: pointer; transition: all 0.2s; margin: 0;
        }
        .weekday-checks .form-check:has(input:checked) { border-color: #667eea; background: #eff6ff; }
        .weekday-checks .form-check-input { margin-right: 4px; }

        .feature-toggle { padding: 12px; border: 2px solid #e2e8f0; border-radius: 12px; margin-bottom: 8px; transition: all 0.2s; }
        .feature-toggle:has(input:checked) { border-color: #667eea; background: #f8f7ff; }

        .complete-check { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 0.88rem; }
        .complete-check i { font-size: 1rem; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.4s ease; }
    </style>
</head>
<body>
<div class="install-container">
    <div class="install-card fade-in">
        <!-- Header -->
        <div class="install-header">
            <i class="bi bi-heart-pulse-fill" style="font-size:2.4rem;"></i>
            <h4 class="fw-bold mt-2 mb-1"><?= h(APP_NAME) ?></h4>
            <p class="mb-0 opacity-75 small">初期セットアップウィザード v<?= APP_VERSION ?></p>
            <p class="mb-0 opacity-50" style="font-size:0.72rem;margin-top:4px;">オープンソース動物病院電子カルテシステム</p>
        </div>

        <div class="install-body">
            <!-- Step Progress -->
            <?php if ($step !== 'done'): ?>
            <div class="step-progress">
                <?php
                $stepNum = is_numeric($step) ? (int)$step : 7;
                $stepLabels = ['環境確認', 'データ', 'アカウント', '施設設定', 'LINE', '完了'];
                for ($i = 1; $i <= $totalSteps; $i++):
                    $cls = $i < $stepNum ? 'done' : ($i == $stepNum ? 'active' : '');
                ?>
                    <?php if ($i > 1): ?><div class="step-line <?= $i <= $stepNum ? 'done' : '' ?>"></div><?php endif; ?>
                    <div class="step-dot <?= $cls ?>" title="<?= $stepLabels[$i-1] ?>">
                        <?php if ($i < $stepNum): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            <?= $i ?>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="text-center mb-3" style="font-size:0.72rem;color:#94a3b8;">
                Step <?= min($stepNum, $totalSteps) ?> / <?= $totalSteps ?>: <?= $stepLabels[min($stepNum, $totalSteps) - 1] ?>
            </div>
            <?php endif; ?>

            <!-- Messages -->
            <?php if ($msg): ?>
            <div class="alert alert-success py-2 d-flex align-items-center gap-2 fade-in" role="alert" style="border-radius:12px;">
                <i class="bi bi-check-circle-fill"></i> <?= h($msg) ?>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 d-flex align-items-center gap-2 fade-in" role="alert" style="border-radius:12px;">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= h($error) ?>
            </div>
            <?php endif; ?>

            <!-- Step 1: Environment + Database -->
            <?php if ($step === '1'): ?>
            <h5 class="fw-bold mb-3"><i class="bi bi-database me-2" style="color:#667eea;"></i>Step 1: 環境確認・データベース初期化</h5>
            <p class="text-muted small mb-3">サーバー環境をチェックし、データベーステーブルを作成します。</p>
            
            <div class="mb-3">
                <h6 class="fw-bold mb-2" style="font-size:0.85rem;"><i class="bi bi-gear me-1"></i>環境チェック</h6>
                <?php foreach ($envChecks as $key => $check): ?>
                <div class="env-check <?= $check['ok'] ? 'ok' : ($check['required'] === '必須' ? 'fail' : 'warn') ?>">
                    <i class="bi <?= $check['ok'] ? 'bi-check-circle-fill text-success' : ($check['required'] === '必須' ? 'bi-x-circle-fill text-danger' : 'bi-exclamation-circle-fill text-warning') ?>"></i>
                    <span class="flex-grow-1"><?= h($check['label']) ?></span>
                    <span class="badge <?= $check['ok'] ? 'bg-success' : ($check['required'] === '必須' ? 'bg-danger' : 'bg-warning') ?>"><?= $check['ok'] ? 'OK' : $check['required'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="p-3 rounded-3 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                <small class="text-muted">
                    <i class="bi bi-database me-1"></i>データベース: <strong><?= DB_DRIVER === 'mysql' ? 'MySQL' : 'SQLite' ?></strong>
                    <?php if (DB_DRIVER === 'sqlite'): ?>
                    | <code style="font-size:0.72rem;"><?= h(DB_SQLITE_PATH) ?></code>
                    <?php endif; ?>
                    <br><i class="bi bi-folder me-1"></i>インストール先: <code style="font-size:0.72rem;"><?= h(BASE_PATH) ?></code>
                </small>
            </div>
            
            <div class="p-3 rounded-3 mb-3" style="background:#eff6ff;border:1px solid #bfdbfe;">
                <small style="color:#1e40af;">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>作成されるテーブル:</strong> staff, patients, owners, medical_records, orders, appointments, invoices, lab_results, drug_master, test_master, insurance_companies, insurance_claims, staff_schedules, clinical_templates, closed_days 他（計30テーブル以上）
                </small>
            </div>

            <form method="POST">
                <input type="hidden" name="step" value="1">
                <button type="submit" class="btn-install">
                    <i class="bi bi-play-fill me-2"></i>環境チェック・データベース初期化を実行
                </button>
            </form>

            <!-- Step 2: Sample Data -->
            <?php elseif ($step === '2'): ?>
            <h5 class="fw-bold mb-3"><i class="bi bi-box me-2" style="color:#667eea;"></i>Step 2: サンプルデータ</h5>
            <p class="text-muted small">デモ用のサンプルデータを投入しますか？<br>実際の運用環境では「スキップ」を選択してください。</p>
            
            <div class="p-3 rounded-3 mb-3" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                <small style="color:#166534;">
                    <strong><i class="bi bi-box-arrow-in-down me-1"></i>サンプルデータの内容:</strong>
                    <ul class="mb-0 mt-1" style="padding-left:18px;">
                        <li>デモスタッフアカウント（admin, dr_suzuki, ns_sato, rc_kato）</li>
                        <li>サンプル飼い主・患畜データ</li>
                        <li>薬品・検査マスタデータ</li>
                        <li>サンプル診療記録・予約データ</li>
                    </ul>
                </small>
            </div>
            
            <form method="POST" class="d-grid gap-2">
                <input type="hidden" name="step" value="2">
                <button type="submit" name="load_sample" value="1" class="btn-install">
                    <i class="bi bi-box-arrow-in-down me-2"></i>サンプルデータを投入する
                </button>
                <button type="submit" class="btn-skip">
                    <i class="bi bi-skip-forward me-2"></i>スキップ（空のデータベースで開始）
                </button>
            </form>

            <!-- Step 3: Admin Account -->
            <?php elseif ($step === '3'): ?>
            <h5 class="fw-bold mb-3"><i class="bi bi-person-plus me-2" style="color:#667eea;"></i>Step 3: 管理者アカウント作成</h5>
            <p class="text-muted small mb-3">システム管理者のログインアカウントを作成します。</p>
            <form method="POST">
                <input type="hidden" name="step" value="3">
                <div class="mb-3">
                    <label class="form-label required">ログインID</label>
                    <input type="text" name="login_id" class="form-control" required minlength="3" placeholder="admin" value="<?= h($_POST['login_id'] ?? '') ?>">
                    <div class="form-text">3文字以上の英数字</div>
                </div>
                <div class="mb-3">
                    <label class="form-label required">パスワード</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>" placeholder="<?= PASSWORD_MIN_LENGTH ?>文字以上">
                        <button class="btn btn-outline-secondary" type="button" onclick="let p=document.getElementById('password');p.type=p.type==='password'?'text':'password';"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label required">氏名</label>
                        <input type="text" name="name" class="form-control" required placeholder="山田 太郎" value="<?= h($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">フリガナ</label>
                        <input type="text" name="name_kana" class="form-control" placeholder="ヤマダ タロウ" value="<?= h($_POST['name_kana'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">メールアドレス</label>
                    <input type="email" name="email" class="form-control" placeholder="admin@example.com" value="<?= h($_POST['email'] ?? '') ?>">
                </div>
                <button type="submit" class="btn-install">
                    <i class="bi bi-person-check me-2"></i>アカウントを作成して次へ
                </button>
            </form>

            <!-- Step 4: Hospital Settings -->
            <?php elseif ($step === '4'): ?>
            <h5 class="fw-bold mb-3"><i class="bi bi-building me-2" style="color:#667eea;"></i>Step 4: 施設・予約・機能設定</h5>
            <p class="text-muted small mb-3">病院の基本情報と機能設定を行います。後から設定画面で変更可能です。</p>
            <form method="POST">
                <input type="hidden" name="step" value="4">
                
                <!-- Basic Info -->
                <h6 class="fw-bold mb-2" style="font-size:0.85rem;"><i class="bi bi-hospital me-1"></i>基本情報</h6>
                <div class="mb-2">
                    <label class="form-label required">病院名</label>
                    <input type="text" name="hospital_name" class="form-control" required placeholder="○○動物病院">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label">電話番号</label>
                        <input type="text" name="hospital_phone" class="form-control" placeholder="03-1234-5678">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">メール</label>
                        <input type="email" name="hospital_email" class="form-control" placeholder="info@example.com">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">住所</label>
                    <input type="text" name="hospital_address" class="form-control" placeholder="〒000-0000 東京都○○区...">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label">FAX番号</label>
                        <input type="text" name="hospital_fax" class="form-control" placeholder="03-1234-5679">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">院長名</label>
                        <input type="text" name="hospital_director" class="form-control" placeholder="山田 太郎">
                    </div>
                </div>
                
                <!-- Business Hours -->
                <hr>
                <h6 class="fw-bold mb-2" style="font-size:0.85rem;"><i class="bi bi-clock me-1"></i>診療時間</h6>
                <div class="mb-2">
                    <label class="form-label">平日</label>
                    <input type="text" name="hospital_hours" class="form-control" value="9:00〜12:00 / 16:00〜19:00" placeholder="9:00〜12:00 / 16:00〜19:00">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label">土曜日</label>
                        <input type="text" name="hospital_hours_sat" class="form-control" value="9:00〜12:00">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">日祝</label>
                        <input type="text" name="hospital_hours_hol" class="form-control" value="休診">
                    </div>
                </div>
                
                <!-- Closed Days -->
                <div class="mb-3">
                    <label class="form-label">定休日（曜日を選択）</label>
                    <div class="weekday-checks">
                        <?php 
                        $weekdays = ['日曜', '月曜', '火曜', '水曜', '木曜', '金曜', '土曜'];
                        foreach ($weekdays as $i => $d): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="closed_weekdays[]" value="<?= $i ?>" id="wd<?= $i ?>"
                                <?= $i == 0 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="wd<?= $i ?>"><?= $d ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Appointment Settings -->
                <hr>
                <h6 class="fw-bold mb-2" style="font-size:0.85rem;"><i class="bi bi-calendar-check me-1"></i>予約設定</h6>
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <label class="form-label small">開始時間</label>
                        <input type="text" name="appointment_start_time" class="form-control form-control-sm" value="09:00">
                    </div>
                    <div class="col-4">
                        <label class="form-label small">終了時間</label>
                        <input type="text" name="appointment_end_time" class="form-control form-control-sm" value="18:00">
                    </div>
                    <div class="col-4">
                        <label class="form-label small">間隔(分)</label>
                        <select name="appointment_interval" class="form-select form-select-sm">
                            <option value="15">15分</option>
                            <option value="20">20分</option>
                            <option value="30" selected>30分</option>
                            <option value="60">60分</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <label class="form-label small">1枠最大予約数</label>
                        <input type="number" name="max_appointments_per_slot" class="form-control form-control-sm" value="3" min="1" max="20">
                    </div>
                    <div class="col-4">
                        <label class="form-label small">昼休み開始</label>
                        <input type="text" name="booking_lunch_start" class="form-control form-control-sm" value="12:00">
                    </div>
                    <div class="col-4">
                        <label class="form-label small">昼休み終了</label>
                        <input type="text" name="booking_lunch_end" class="form-control form-control-sm" value="13:00">
                    </div>
                </div>
                
                <!-- Feature toggles -->
                <hr>
                <h6 class="fw-bold mb-2" style="font-size:0.85rem;"><i class="bi bi-toggles me-1"></i>機能設定</h6>
                <div class="feature-toggle">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="public_booking_enabled" class="form-check-input" id="featBooking" checked>
                        <label class="form-check-label fw-bold" for="featBooking">オンライン予約システム</label>
                    </div>
                    <div class="form-text">飼い主がWebから24時間予約可能になります</div>
                </div>
                <div class="feature-toggle">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="booking_new_patient_enabled" class="form-check-input" id="featNewPat" checked>
                        <label class="form-check-label fw-bold" for="featNewPat">オンライン予約での新患登録</label>
                    </div>
                    <div class="form-text">初めての方がオンライン予約時に飼い主・ペット情報を登録できます</div>
                </div>
                <div class="feature-toggle">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="priority_reservation" class="form-check-input" id="featPriority">
                        <label class="form-check-label fw-bold" for="featPriority">予約優先モード</label>
                    </div>
                    <div class="form-text">予約者を優先的に案内します（予約ページに注意書きが表示されます）</div>
                </div>
                <div class="feature-toggle">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="feature_insurance" class="form-check-input" id="featIns" checked>
                        <label class="form-check-label fw-bold" for="featIns">保険会社関連機能</label>
                    </div>
                    <div class="form-text">レセプト・保険マスタ・保険請求機能を有効にします</div>
                </div>
                
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small">消費税率 (%)</label>
                        <input type="number" name="tax_rate" class="form-control form-control-sm" value="10" min="0" max="30">
                    </div>
                </div>

                <button type="submit" class="btn-install">
                    <i class="bi bi-save me-2"></i>施設情報を保存して次へ
                </button>
            </form>

            <!-- Step 5: LINE Settings -->
            <?php elseif ($step === '5'): ?>
            <h5 class="fw-bold mb-3"><i class="bi bi-chat-dots me-2" style="color:#667eea;"></i>Step 5: LINE連携設定</h5>
            <p class="text-muted small">LINE Messaging APIを設定すると、飼い主への予約通知やリマインダーを自動送信できます。<br>後から設定画面で変更できます。</p>
            
            <div class="p-3 rounded-3 mb-3" style="background:#eff6ff;border:1px solid #bfdbfe;">
                <small style="color:#1e40af;">
                    <i class="bi bi-info-circle me-1"></i>LINE連携はオプションです。<a href="https://developers.line.biz/" target="_blank" style="color:#667eea;">LINE Developers Console</a> でチャンネルを作成してAPIキーを取得してください。
                </small>
            </div>
            
            <form method="POST">
                <input type="hidden" name="step" value="5">
                <div class="mb-3">
                    <label class="form-label">Channel Access Token</label>
                    <textarea name="line_channel_access_token" class="form-control" rows="2" placeholder="LINE Developers Console で取得したトークン"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Channel Secret</label>
                    <input type="text" name="line_channel_secret" class="form-control" placeholder="LINE Developers Console で取得したシークレットキー">
                </div>
                <hr>
                <h6 class="fw-bold mb-2" style="font-size:0.85rem;">通知設定</h6>
                <div class="form-check mb-2">
                    <input type="checkbox" name="line_notify_appointment" class="form-check-input" id="lineAppt" checked>
                    <label class="form-check-label" for="lineAppt">予約確認メッセージを送信</label>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" name="line_notify_reminder" class="form-check-input" id="lineRemind" checked>
                    <label class="form-check-label" for="lineRemind">予約リマインダーを送信（前日）</label>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="line_notify_vaccination" class="form-check-input" id="lineVax" checked>
                    <label class="form-check-label" for="lineVax">ワクチン接種時期リマインダー</label>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn-install">
                        <i class="bi bi-save me-2"></i>LINE設定を保存して完了
                    </button>
                    <button type="submit" class="btn-skip">
                        <i class="bi bi-skip-forward me-2"></i>スキップ（後で設定）
                    </button>
                </div>
            </form>

            <!-- Step 6: Complete -->
            <?php elseif ($step === '6' || $step === 'done'): ?>
            <div class="text-center py-3 fade-in">
                <div style="width:88px;height:88px;margin:0 auto 20px;background:linear-gradient(135deg,#10b981 0%,#059669 100%);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(16,185,129,0.3);">
                    <i class="bi bi-check-lg text-white" style="font-size:2.8rem;"></i>
                </div>
                <h4 class="fw-bold mb-2">セットアップ完了!</h4>
                <p class="text-muted mb-4"><?= h(APP_NAME) ?> v<?= APP_VERSION ?> の準備が整いました。</p>
                
                <div class="p-3 rounded-3 mb-4 text-start" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                    <small class="fw-bold d-block mb-2"><i class="bi bi-check-circle me-1 text-success"></i>セットアップ済み項目</small>
                    <div class="d-flex flex-column gap-1">
                        <div class="complete-check"><i class="bi bi-check-circle-fill text-success"></i> データベース初期化（全テーブル作成済み）</div>
                        <div class="complete-check"><i class="bi bi-check-circle-fill text-success"></i> 管理者アカウント作成</div>
                        <div class="complete-check"><i class="bi bi-check-circle-fill text-success"></i> 施設情報・診療時間設定</div>
                        <div class="complete-check"><i class="bi bi-check-circle-fill text-success"></i> 予約システム設定</div>
                        <div class="complete-check"><i class="bi bi-check-circle-fill text-success"></i> LINE連携設定</div>
                        <div class="complete-check"><i class="bi bi-check-circle-fill text-success"></i> 全機能有効化</div>
                    </div>
                </div>
                
                <div class="p-3 rounded-3 mb-4 text-start" style="background:#eff6ff;border:1px solid #bfdbfe;">
                    <small style="color:#1e40af;">
                        <strong><i class="bi bi-lightbulb me-1"></i>次のステップ:</strong>
                        <ul class="mb-0 mt-1" style="padding-left:18px;">
                            <li>スタッフアカウントを追加（管理 > アカウント管理）</li>
                            <li>医師のシフトを登録（管理 > シフト管理）</li>
                            <li>薬品・検査マスタを登録（管理 > 各種マスタ）</li>
                            <li>Web予約ページを公開（設定 > 予約設定）</li>
                        </ul>
                    </small>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="step" value="6">
                    <a href="./index.php" class="btn-install d-inline-flex align-items-center justify-content-center gap-2" style="text-decoration:none;">
                        <i class="bi bi-box-arrow-in-right"></i>ログインページへ
                    </a>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
