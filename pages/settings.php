<?php
/** 施設設定画面 */
if (!$auth->hasRole(ROLE_ADMIN)) { redirect('?page=dashboard'); }

// 設定を取得・保存するヘルパー関数
function getSetting($db, $key, $default = '') {
    $row = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = ?", [$key]);
    return $row ? $row['setting_value'] : $default;
}

function saveSetting($db, $key, $value) {
    $exists = $db->fetch("SELECT setting_key FROM hospital_settings WHERE setting_key = ?", [$key]);
    if ($exists) {
        $db->update('hospital_settings', ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')], 'setting_key = ?', [$key]);
    } else {
        $db->insert('hospital_settings', ['setting_key' => $key, 'setting_value' => $value]);
    }
}

// POST時の保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. テキスト設定の保存
    $keys = ['hospital_name', 'hospital_address', 'hospital_phone', 'tax_rate', 'ward_list'];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            saveSetting($db, $key, trim($_POST[$key]));
        }
    }

    // 2. 画像アップロード処理関数
    $handleUpload = function($inputName, $settingKey) use ($db) {
        if (!empty($_FILES[$inputName]['name'])) {
            $file = $_FILES[$inputName];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'])) {
                $filename = $settingKey . '_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
                    // 古いファイルを削除（任意実装）
                    $old = getSetting($db, $settingKey);
                    if ($old && file_exists(UPLOAD_DIR . $old)) { unlink(UPLOAD_DIR . $old); }
                    
                    saveSetting($db, $settingKey, $filename);
                }
            }
        }
    };

    // 印鑑とロゴをそれぞれ処理
    $handleUpload('stamp_image', 'stamp_image');
    $handleUpload('hospital_logo', 'hospital_logo'); // 新規追加: ロゴ
    
    // 削除フラグの処理
    if (isset($_POST['delete_stamp'])) saveSetting($db, 'stamp_image', '');
    if (isset($_POST['delete_logo'])) saveSetting($db, 'hospital_logo', '');

    $msg = "設定を保存しました。";
}

// 現在の設定値を読み込み
$s_name = getSetting($db, 'hospital_name', 'VetCare動物病院');
$s_addr = getSetting($db, 'hospital_address', '東京都...');
$s_phone = getSetting($db, 'hospital_phone', '03-0000-0000');
$s_tax  = getSetting($db, 'tax_rate', '10');
$s_wards = getSetting($db, 'ward_list', "第1犬舎\n第2猫舎\nICU\n隔離室");
$s_stamp = getSetting($db, 'stamp_image', '');
$s_logo  = getSetting($db, 'hospital_logo', ''); 
?>

<div class="fade-in">
    <h4 class="fw-bold mb-4"><i class="bi bi-gear-fill me-2"></i>施設設定</h4>

    <?php if (isset($msg)): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="row g-4">
        <?= csrf_field() ?>
        
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-bold">病院基本情報</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">病院名</label>
                        <input type="text" name="hospital_name" class="form-control" value="<?= h($s_name) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">住所</label>
                        <input type="text" name="hospital_address" class="form-control" value="<?= h($s_addr) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">電話番号</label>
                        <input type="text" name="hospital_phone" class="form-control" value="<?= h($s_phone) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-bold">画像・印字設定</div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label fw-bold">病院ロゴ画像</label>
                        <input type="file" name="hospital_logo" class="form-control" accept="image/*">
                        <div class="form-text">薬袋や領収書のヘッダーに使用します。</div>
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
                        <div class="form-text">書類の署名欄等に使用する印鑑画像（背景透過推奨）。</div>
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

        <div class="col-12">
            <div class="card">
                <div class="card-header fw-bold">その他の設定</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">消費税率 (%)</label>
                            <input type="number" name="tax_rate" class="form-control" value="<?= h($s_tax) ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">病棟・部屋リスト</label>
                            <textarea name="ward_list" class="form-control" rows="3"><?= h($s_wards) ?></textarea>
                            <div class="form-text">改行で区切って入力してください。</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary px-5 py-2 fw-bold">
                <i class="bi bi-save me-2"></i>設定を保存
            </button>
        </div>
    </form>
</div>