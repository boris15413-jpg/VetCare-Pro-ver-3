<?php
/** 施設設定画面 */
require_once __DIR__ . '/../includes/Security.php';
if (!$auth->hasRole(ROLE_ADMIN)) { redirect('?page=dashboard'); }

// POST時の保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    // 1. テキスト設定の保存
    $keys = [
        'hospital_name', 'hospital_address', 'hospital_phone', 'hospital_fax',
        'hospital_email', 'hospital_director', 'hospital_license',
        'tax_rate', 'ward_list',
        'business_hours_weekday', 'business_hours_saturday', 'business_hours_holiday',
        'emergency_phone',
        'receipt_footer_message', 'invoice_note',
        'backup_retention_days',
        'appointment_start_time', 'appointment_end_time', 'appointment_interval',
        'max_appointments_per_slot',
        // Booking settings
        'booking_welcome_message', 'booking_notice_message',
        'booking_lunch_start', 'booking_lunch_end',
        'booking_days_ahead',
    ];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            setSetting($key, trim($_POST[$key]));
        }
    }
    
    // Feature toggles
    $featureToggles = ['feature_insurance', 'public_booking_enabled', 'booking_new_patient_enabled', 'priority_reservation'];
    foreach ($featureToggles as $ft) {
        setSetting($ft, isset($_POST[$ft]) ? '1' : '0');
    }
    
    // Security settings
    $securityToggles = ['security_ip_whitelist_enabled', 'security_enforce_separate_access'];
    foreach ($securityToggles as $st) {
        setSetting($st, isset($_POST[$st]) ? '1' : '0');
    }
    if (isset($_POST['security_ip_whitelist'])) {
        setSetting('security_ip_whitelist', trim($_POST['security_ip_whitelist']));
    }
    if (isset($_POST['security_allowed_gateway'])) {
        setSetting('security_allowed_gateway', trim($_POST['security_allowed_gateway']));
    }
    
    // Display mode
    if (isset($_POST['accounting_display_mode'])) {
        setSetting('accounting_display_mode', $_POST['accounting_display_mode']);
    }

    // 2. 画像アップロード処理関数
    $handleUpload = function($inputName, $settingKey) {
        if (!empty($_FILES[$inputName]['name'])) {
            $file = $_FILES[$inputName];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                $filename = $settingKey . '_' . time() . '.' . $ext;
                $uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : BASE_PATH . '/uploads/';
                if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $old = getSetting($settingKey);
                    if ($old && file_exists($uploadDir . $old)) { @unlink($uploadDir . $old); }
                    setSetting($settingKey, $filename);
                }
            }
        }
    };

    // 印鑑とロゴをそれぞれ処理
    $handleUpload('stamp_image', 'stamp_image');
    $handleUpload('hospital_logo', 'hospital_logo');
    
    // 削除フラグの処理
    if (isset($_POST['delete_stamp'])) setSetting('stamp_image', '');
    if (isset($_POST['delete_logo'])) setSetting('hospital_logo', '');

    $msg = "設定を保存しました。";
}

// 現在の設定値を読み込み
$s_name    = getSetting('hospital_name', 'VetCare動物病院');
$s_addr    = getSetting('hospital_address', '');
$s_phone   = getSetting('hospital_phone', '');
$s_fax     = getSetting('hospital_fax', '');
$s_email   = getSetting('hospital_email', '');
$s_director = getSetting('hospital_director', '');
$s_license = getSetting('hospital_license', '');
$s_tax     = getSetting('tax_rate', '10');
$s_wards   = getSetting('ward_list', "第1犬舎\n第2猫舎\nICU\n隔離室");
$s_stamp   = getSetting('stamp_image', '');
$s_logo    = getSetting('hospital_logo', '');
$s_hours_w = getSetting('business_hours_weekday', '9:00〜12:00 / 16:00〜19:00');
$s_hours_s = getSetting('business_hours_saturday', '9:00〜12:00');
$s_hours_h = getSetting('business_hours_holiday', '休診');
$s_closed  = getSetting('closed_days', '日曜・祝日');
$s_emphone = getSetting('emergency_phone', '');
$s_receipt_footer = getSetting('receipt_footer_message', 'お大事になさってください。');
$s_invoice_note   = getSetting('invoice_note', '');
$s_backup_days    = getSetting('backup_retention_days', '30');
$s_feature_insurance = getSetting('feature_insurance', '1');
$s_public_booking = getSetting('public_booking_enabled', '0');
$s_booking_new_patient = getSetting('booking_new_patient_enabled', '1');
$s_priority_reservation = getSetting('priority_reservation', '0');
$s_display_mode = getSetting('accounting_display_mode', 'name');

// Security settings
$s_ip_whitelist_enabled = getSetting('security_ip_whitelist_enabled', '0');
$s_ip_whitelist = getSetting('security_ip_whitelist', '');
$s_enforce_separate = getSetting('security_enforce_separate_access', '0');
$s_allowed_gateway = getSetting('security_allowed_gateway', '');
$s_apt_start = getSetting('appointment_start_time', '09:00');
$s_apt_end = getSetting('appointment_end_time', '18:00');
$s_apt_interval = getSetting('appointment_interval', '30');
$s_max_per_slot = getSetting('max_appointments_per_slot', '3');

// Booking settings
$s_booking_welcome = getSetting('booking_welcome_message', '');
$s_booking_notice = getSetting('booking_notice_message', '');
$s_booking_lunch_start = getSetting('booking_lunch_start', '12:00');
$s_booking_lunch_end = getSetting('booking_lunch_end', '13:00');
$s_booking_days_ahead = getSetting('booking_days_ahead', '60');
?>

<div class="fade-in">
    <h4 class="fw-bold mb-4"><i class="bi bi-gear-fill me-2"></i>施設設定</h4>

    <?php if (isset($msg)): ?><div class="alert alert-success alert-dismissible fade show"><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="row g-4">
        <?= csrf_field() ?>
        
        <!-- 病院基本情報 -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-bold"><i class="bi bi-hospital me-2"></i>病院基本情報</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">病院名 <span class="text-danger">*</span></label>
                        <input type="text" name="hospital_name" class="form-control" value="<?= h($s_name) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">住所</label>
                        <input type="text" name="hospital_address" class="form-control" value="<?= h($s_addr) ?>" placeholder="〒000-0000 東京都○○区...">
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">電話番号</label>
                            <input type="text" name="hospital_phone" class="form-control" value="<?= h($s_phone) ?>" placeholder="03-0000-0000">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">FAX番号</label>
                            <input type="text" name="hospital_fax" class="form-control" value="<?= h($s_fax) ?>" placeholder="03-0000-0001">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">メールアドレス</label>
                        <input type="email" name="hospital_email" class="form-control" value="<?= h($s_email) ?>" placeholder="info@example-vet.com">
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">院長名</label>
                            <input type="text" name="hospital_director" class="form-control" value="<?= h($s_director) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">開設許可番号</label>
                            <input type="text" name="hospital_license" class="form-control" value="<?= h($s_license) ?>" placeholder="動物取扱業登録番号">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 画像・印字設定 -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-bold"><i class="bi bi-image me-2"></i>画像・印字設定</div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold">病院ロゴ画像</label>
                        <input type="file" name="hospital_logo" class="form-control" accept="image/*">
                        <div class="form-text">薬袋・領収書・レセプトのヘッダーに使用します。</div>
                        <?php if ($s_logo): ?>
                            <div class="mt-2 p-2 border rounded bg-light d-flex align-items-center gap-3">
                                <img src="uploads/<?= h($s_logo) ?>" style="height: 50px; object-fit: contain;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="delete_logo" id="delLogo">
                                    <label class="form-check-label text-danger" for="delLogo">ロゴを削除</label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3 border-top pt-3">
                        <label class="form-label fw-bold">角印・印影画像</label>
                        <input type="file" name="stamp_image" class="form-control" accept="image/*">
                        <div class="form-text">書類・レセプトの署名欄に使用する印鑑画像（背景透過推奨）。</div>
                        <?php if ($s_stamp): ?>
                            <div class="mt-2 p-2 border rounded bg-light d-flex align-items-center gap-3">
                                <img src="uploads/<?= h($s_stamp) ?>" style="height: 50px; object-fit: contain;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="delete_stamp" id="delStamp">
                                    <label class="form-check-label text-danger" for="delStamp">印影を削除</label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 診療時間 -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-bold"><i class="bi bi-clock me-2"></i>診療時間・予約設定</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">平日</label>
                        <input type="text" name="business_hours_weekday" class="form-control" value="<?= h($s_hours_w) ?>" placeholder="9:00〜12:00 / 16:00〜19:00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">土曜日</label>
                        <input type="text" name="business_hours_saturday" class="form-control" value="<?= h($s_hours_s) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">日曜・祝日</label>
                        <input type="text" name="business_hours_holiday" class="form-control" value="<?= h($s_hours_h) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">休診日管理</label>
                        <a href="?page=closed_days" class="btn btn-outline-secondary btn-sm d-block">
                            <i class="bi bi-calendar-x me-1"></i>休診日設定画面へ
                        </a>
                        <small class="form-text">平日含む任意の曜日や臨時休診日を設定できます。</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">緊急連絡先</label>
                        <input type="text" name="emergency_phone" class="form-control" value="<?= h($s_emphone) ?>" placeholder="090-0000-0000">
                    </div>
                    <hr>
                    <h6 class="fw-bold mb-2">予約設定</h6>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small">開始時間</label>
                            <input type="text" name="appointment_start_time" class="form-control form-control-sm" value="<?= h($s_apt_start) ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">終了時間</label>
                            <input type="text" name="appointment_end_time" class="form-control form-control-sm" value="<?= h($s_apt_end) ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label small">間隔(分)</label>
                            <select name="appointment_interval" class="form-select form-select-sm">
                                <?php foreach ([15,20,30,60] as $iv): ?>
                                <option value="<?= $iv ?>" <?= $s_apt_interval == $iv ? 'selected' : '' ?>><?= $iv ?>分</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">1枠あたりの最大予約数</label>
                        <input type="number" name="max_appointments_per_slot" class="form-control form-control-sm" value="<?= h($s_max_per_slot) ?>" min="1" max="20">
                    </div>
                    <hr>
                    <h6 class="fw-bold mb-2">昼休み設定</h6>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">昼休み開始</label>
                            <input type="text" name="booking_lunch_start" class="form-control form-control-sm" value="<?= h($s_booking_lunch_start) ?>" placeholder="12:00">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">昼休み終了</label>
                            <input type="text" name="booking_lunch_end" class="form-control form-control-sm" value="<?= h($s_booking_lunch_end) ?>" placeholder="13:00">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">予約受付日数（何日先まで）</label>
                        <input type="number" name="booking_days_ahead" class="form-control form-control-sm" value="<?= h($s_booking_days_ahead) ?>" min="7" max="365">
                    </div>
                    <hr>
                    <h6 class="fw-bold mb-2">Web予約ページメッセージ</h6>
                    <div class="mb-2">
                        <label class="form-label small">ウェルカムメッセージ</label>
                        <textarea name="booking_welcome_message" class="form-control form-control-sm" rows="2" placeholder="ご予約ありがとうございます。"><?= h($s_booking_welcome) ?></textarea>
                        <small class="form-text">予約ページ上部に表示されます。</small>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">注意事項メッセージ</label>
                        <textarea name="booking_notice_message" class="form-control form-control-sm" rows="2" placeholder="予約時間の5分前にお越しください。"><?= h($s_booking_notice) ?></textarea>
                        <small class="form-text">時間選択の下に黄色い注意枠で表示されます。</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- その他の設定 -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-bold"><i class="bi bi-sliders me-2"></i>会計・システム設定</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">消費税率 (%)</label>
                        <input type="number" name="tax_rate" class="form-control" value="<?= h($s_tax) ?>" min="0" max="100" step="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">病棟・部屋リスト</label>
                        <textarea name="ward_list" class="form-control" rows="3"><?= h($s_wards) ?></textarea>
                        <div class="form-text">改行で区切って入力してください。</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">領収書フッターメッセージ</label>
                        <input type="text" name="receipt_footer_message" class="form-control" value="<?= h($s_receipt_footer) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">請求書備考（デフォルト）</label>
                        <textarea name="invoice_note" class="form-control" rows="2"><?= h($s_invoice_note) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">バックアップ保持日数</label>
                        <input type="number" name="backup_retention_days" class="form-control" value="<?= h($s_backup_days) ?>" min="1" max="365">
                    </div>
                    <hr>
                    <h6 class="fw-bold mb-2">機能の有効/無効</h6>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="feature_insurance" id="featInsurance" <?= $s_feature_insurance === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="featInsurance">保険会社関連機能（レセプト・保険マスタ・保険請求）</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="public_booking_enabled" id="featBooking" <?= $s_public_booking === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="featBooking">オンライン予約システム（外部公開）</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="booking_new_patient_enabled" id="featNewPatient" <?= $s_booking_new_patient === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="featNewPatient">オンライン予約での新患・飼い主登録</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="priority_reservation" id="featPriority" <?= $s_priority_reservation === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="featPriority">予約優先モード（予約者を優先案内・注意書き表示）</label>
                    </div>
                    <hr>
                    <h6 class="fw-bold mb-2">受付・会計表示設定</h6>
                    <div class="mb-2">
                        <label class="form-label small">待合・会計表示モード</label>
                        <select name="accounting_display_mode" class="form-select form-select-sm">
                            <option value="name" <?= $s_display_mode==='name'?'selected':'' ?>>名前表示</option>
                            <option value="number" <?= $s_display_mode==='number'?'selected':'' ?>>番号表示（受付時に自動発行）</option>
                        </select>
                        <small class="form-text">番号モードでは受付時に番号を自動発行し、待合・会計画面で番号のみ表示します。</small>
                    </div>
                    <hr>
                    <h6 class="fw-bold mb-2">テンプレート管理</h6>
                    <a href="?page=clinical_templates" class="btn btn-outline-primary btn-sm d-block">
                        <i class="bi bi-file-earmark-ruled me-1"></i>カルテテンプレート管理画面へ
                    </a>
                    <small class="form-text">SOAPテンプレートや処方セットを登録してカルテ入力を効率化します。</small>
                </div>
            </div>
        </div>

        <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary px-5 py-2 fw-bold">
                <i class="bi bi-save me-2"></i>設定を保存
            </button>
        </div>
    </form>

    <!-- セキュリティ設定 -->
    <div class="card mt-4">
        <div class="card-header fw-bold text-danger"><i class="bi bi-shield-lock me-2"></i>セキュリティ設定</div>
        <div class="card-body">
            <form method="POST">
                <?= csrf_field() ?>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3"><i class="bi bi-wifi me-1"></i>アクセス制限（IPホワイトリスト）</h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="security_ip_whitelist_enabled" id="ipWhitelist" <?= $s_ip_whitelist_enabled === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ipWhitelist">
                                <strong>IPアドレス制限を有効にする</strong>
                            </label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">許可するIPアドレス</label>
                            <textarea name="security_ip_whitelist" class="form-control font-monospace" rows="5" placeholder="192.168.1.0/24&#10;10.0.0.0/8&#10;203.0.113.50&#10;# コメント行"><?= h($s_ip_whitelist) ?></textarea>
                            <div class="form-text">
                                1行に1つずつIPアドレスを入力してください。<br>
                                CIDR表記（例: 192.168.1.0/24）やワイルドカード（例: 192.168.1.*）に対応しています。<br>
                                <code>#</code> で始まる行はコメントとして無視されます。<br>
                                <strong>ローカルネットワーク（192.168.x.x, 10.x.x.x, 172.16-31.x.x）からのアクセスは常に許可されます。</strong>
                            </div>
                        </div>
                        <div class="alert alert-info py-2 small">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>現在のIPアドレス:</strong> <code><?= h(Security::getClientIP()) ?></code>
                            <?php if (Security::isLocalAccess()): ?>
                            <span class="badge bg-success ms-1">ローカル</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3"><i class="bi bi-folder-symlink me-1"></i>外部アクセス制限</h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="security_enforce_separate_access" id="enforceSeparate" <?= $s_enforce_separate === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="enforceSeparate">
                                <strong>ルート配置時に外部アクセスを制限する</strong>
                            </label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">許可するURLパス（ゲートウェイ）</label>
                            <input type="text" name="security_allowed_gateway" class="form-control" value="<?= h($s_allowed_gateway) ?>" placeholder="/emr/">
                            <div class="form-text">
                                電子カルテをドキュメントルートに配置した場合、ここで指定したパス以外からのアクセスをブロックします。<br>
                                例: <code>/emr/</code> と設定すると、<code>https://example.com/emr/</code> からのみアクセス可能になります。<br>
                                <strong>ローカルサーバー（LAN内）では自動的に無効化されます。</strong>
                            </div>
                        </div>
                        
                        <hr>
                        <h6 class="fw-bold mb-3"><i class="bi bi-link-45deg me-1"></i>外部予約ページ</h6>
                        <div class="alert alert-warning py-2 small mb-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            外部予約ページは電子カルテとは別のURLで運用してください。
                        </div>
                        <p class="small text-muted">
                            外部予約ページは <code>/booking/</code> ディレクトリから独立してアクセスできます。<br>
                            セキュリティのため、予約ページでは電子カルテの内部機能にアクセスできません。
                        </p>
                        <?php
                        $bookingUrl = APP_URL . '/booking/';
                        ?>
                        <div class="input-group mb-2">
                            <span class="input-group-text"><i class="bi bi-globe"></i></span>
                            <input type="text" class="form-control" value="<?= h($bookingUrl) ?>" readonly id="bookingUrl">
                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('bookingUrl').value);this.innerHTML='<i class=\'bi bi-check\'></i>';">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                        <small class="text-muted">このURLを患者さんに公開してください。電子カルテのデータには直接アクセスできません。</small>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <button type="submit" class="btn btn-danger px-4 py-2 fw-bold">
                        <i class="bi bi-shield-check me-2"></i>セキュリティ設定を保存
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- システム情報 -->
    <div class="card mt-4">
        <div class="card-header fw-bold"><i class="bi bi-info-circle me-2"></i>システム情報</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><small class="text-muted d-block">アプリケーション</small><strong><?= APP_NAME ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">PHP バージョン</small><strong><?= phpversion() ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">データベース</small><strong><?= DB_DRIVER === 'mysql' ? 'MySQL' : 'SQLite' ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">データベースサイズ</small><strong><?php
                    if (DB_DRIVER === 'sqlite' && file_exists(DB_SQLITE_PATH)) {
                        echo round(filesize(DB_SQLITE_PATH) / 1024 / 1024, 2) . ' MB';
                    } else { echo '-'; }
                ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">タイムゾーン</small><strong><?= date_default_timezone_get() ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">アップロード上限</small><strong><?= ini_get('upload_max_filesize') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">サーバー</small><strong><?= php_uname('s') . ' ' . php_uname('r') ?></strong></div>
                <div class="col-md-3">
                    <small class="text-muted d-block">動作モード</small>
                    <strong><?= php_sapi_name() === 'cli-server' ? 'ビルトインサーバー' : (php_sapi_name() === 'apache2handler' ? 'Apache' : php_sapi_name()) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>
