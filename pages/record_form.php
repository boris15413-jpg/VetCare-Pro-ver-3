<?php
/** カルテ記録フォーム */
require_once __DIR__ . '/../includes/Audit.php'; // 監査ログクラス読み込み

$id = (int)($_GET['id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);

// ▼ 画像削除処理（GETリクエストで削除）
if (isset($_GET['action']) && $_GET['action'] === 'delete_image' && isset($_GET['image_id'])) {
    $imgId = (int)$_GET['image_id'];
    $img = $db->fetch("SELECT * FROM record_images WHERE id = ?", [$imgId]);
    if ($img) {
        $filePath = UPLOAD_DIR . $img['file_path'];
        if (file_exists($filePath)) { unlink($filePath); }
        $db->query("DELETE FROM record_images WHERE id = ?", [$imgId]);
        
        // ログ記録
        Audit::log('record_images', $imgId, 'DELETE', $img, null);
        
        redirect("?page=record_form&id=" . $img['record_id']);
    }
}

$rec = $id ? $db->fetch("SELECT * FROM medical_records WHERE id = ?", [$id]) : null;
if ($rec) $patient_id = $rec['patient_id'];
$patient = $patient_id ? $db->fetch("SELECT p.*, o.name as owner_name FROM patients p JOIN owners o ON p.owner_id = o.id WHERE p.id = ?", [$patient_id]) : null;
$patients_list = $db->fetchAll("SELECT p.id, p.name, p.patient_code, p.species FROM patients p WHERE p.is_active = 1 ORDER BY p.name");

// ▼ 既存画像の取得
$existing_images = $id ? $db->fetchAll("SELECT * FROM record_images WHERE record_id = ?", [$id]) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'patient_id' => (int)$_POST['patient_id'],
        'staff_id' => $auth->currentUserId(),
        'visit_date' => $_POST['visit_date'],
        'visit_type' => $_POST['visit_type'],
        'chief_complaint' => trim($_POST['chief_complaint'] ?? ''),
        'subjective' => trim($_POST['subjective'] ?? ''),
        'objective' => trim($_POST['objective'] ?? ''),
        'assessment' => trim($_POST['assessment'] ?? ''),
        'plan' => trim($_POST['plan'] ?? ''),
        'diagnosis_name' => trim($_POST['diagnosis_name'] ?? ''),
        'body_weight' => $_POST['body_weight'] ?: null,
        'body_temperature' => $_POST['body_temperature'] ?: null,
        'heart_rate' => $_POST['heart_rate'] ?: null,
        'respiratory_rate' => $_POST['respiratory_rate'] ?: null,
        'blood_pressure_sys' => $_POST['blood_pressure_sys'] ?: null,
        'blood_pressure_dia' => $_POST['blood_pressure_dia'] ?: null,
        'bcs' => $_POST['bcs'] ?: null,
        'notes' => trim($_POST['notes'] ?? ''),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    if ($id) {
        // 更新前データを取得
        $oldData = $db->fetch("SELECT * FROM medical_records WHERE id = ?", [$id]);
        
        // 更新実行
        $db->update('medical_records', $data, 'id = ?', [$id]);
        
        // ★ 監査ログ記録 (UPDATE)
        Audit::log('medical_records', $id, 'UPDATE', $oldData, $data);
        
    } else {
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = $db->insert('medical_records', $data);
        
        // ★ 監査ログ記録 (INSERT)
        Audit::log('medical_records', $id, 'INSERT', null, $data);
    }

    // 体重の更新（患者テーブル）
    if ($_POST['body_weight'] && $data['patient_id']) {
        $oldPatient = $db->fetch("SELECT weight FROM patients WHERE id = ?", [$data['patient_id']]);
        $db->update('patients', ['weight' => $_POST['body_weight'], 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$data['patient_id']]);
        
        // 患者情報の変更ログも残す
        Audit::log('patients', $data['patient_id'], 'UPDATE', $oldPatient, ['weight' => $_POST['body_weight']]);
    }

    // ▼ 画像アップロード処理
    if (!empty($_FILES['images']['name'][0])) {
        // アップロードディレクトリの確認と作成
        if (!is_dir(UPLOAD_DIR)) { mkdir(UPLOAD_DIR, 0777, true); }

        $files = $_FILES['images'];
        $count = count($files['name']);
        
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $newFileName = 'rec_' . $id . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($files['tmp_name'][$i], UPLOAD_DIR . $newFileName)) {
                        $imgData = [
                            'record_id' => $id,
                            'file_path' => $newFileName,
                            'caption' => $files['name'][$i],
                            'image_type' => 'photo'
                        ];
                        $imgId = $db->insert('record_images', $imgData);
                        
                        // 画像追加ログ
                        Audit::log('record_images', $imgId, 'INSERT', null, $imgData);
                    }
                }
            }
        }
    }

    redirect("?page=medical_record&id={$id}");
}
$r = $rec ?: ['visit_date' => date('Y-m-d'), 'visit_type' => 'outpatient'];
?>
<div class="fade-in">
    <a href="<?= $patient ? '?page=patient_detail&id='.$patient_id : '?page=patients' ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>戻る</a>
    <h4 class="fw-bold mt-1 mb-3"><?= $id ? 'カルテ編集' : '新規カルテ記録' ?></h4>

    <?php if ($patient): ?>
    <div class="alert alert-info py-2 mb-3">
        <strong><?= h($patient['name']) ?></strong> (<?= h($patient['patient_code']) ?>) - <?= h(getSpeciesName($patient['species'])) ?> <?= h($patient['breed']) ?> | 飼い主: <?= h($patient['owner_name']) ?>
        <?php if ($patient['allergies']): ?><span class="allergy-tag ms-2"><i class="bi bi-exclamation-triangle"></i> <?= h($patient['allergies']) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="card" enctype="multipart/form-data">
        <div class="card-body">
            <?= csrf_field() ?>
            <div class="row g-3">
                <?php if (!$patient): ?>
                <div class="col-md-6"><label class="form-label required">患畜</label>
                    <select name="patient_id" class="form-select" required>
                        <option value="">選択</option>
                        <?php foreach ($patients_list as $pt): ?>
                        <option value="<?= $pt['id'] ?>"><?= h($pt['name']) ?> (<?= h($pt['patient_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="patient_id" value="<?= $patient_id ?>">
                <?php endif; ?>
                <div class="col-md-3"><label class="form-label required">診察日</label>
                    <input type="text" name="visit_date" class="form-control datepicker" value="<?= h($r['visit_date']) ?>" required></div>
                <div class="col-md-3"><label class="form-label required">診察種別</label>
                    <select name="visit_type" class="form-select">
                        <option value="outpatient" <?= $r['visit_type']==='outpatient'?'selected':'' ?>>外来</option>
                        <option value="admission" <?= $r['visit_type']==='admission'?'selected':'' ?>>入院</option>
                        <option value="emergency" <?= $r['visit_type']==='emergency'?'selected':'' ?>>救急</option>
                        <option value="follow_up" <?= $r['visit_type']==='follow_up'?'selected':'' ?>>再診</option>
                    </select>
                </div>

                <div class="col-12"><label class="form-label">主訴</label>
                    <input type="text" name="chief_complaint" class="form-control" value="<?= h($r['chief_complaint'] ?? '') ?>" placeholder="来院の理由"></div>
                
                <div class="col-12">
                    <label class="form-label"><i class="bi bi-images me-1"></i>画像添付 (写真・レントゲン・検査結果など)</label>
                    <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                    <div class="form-text">※複数選択可 (JPEG, PNG, GIF)</div>
                    
                    <?php if (!empty($existing_images)): ?>
                    <div class="mt-3 d-flex flex-wrap gap-3">
                        <?php foreach ($existing_images as $img): ?>
                        <div class="card" style="width: 150px;">
                            <a href="uploads/<?= h($img['file_path']) ?>" target="_blank">
                                <img src="uploads/<?= h($img['file_path']) ?>" class="card-img-top" style="height: 100px; object-fit: cover;">
                            </a>
                            <div class="card-body p-2 text-center">
                                <a href="?page=record_form&id=<?= $id ?>&action=delete_image&image_id=<?= $img['id'] ?>" 
                                   class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('この画像を削除しますか？\n削除操作は監査ログに記録されます。')">
                                   <i class="bi bi-trash"></i> 削除
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-12"><label class="form-label">診断名</label>
                    <input type="text" name="diagnosis_name" class="form-control" value="<?= h($r['diagnosis_name'] ?? '') ?>"></div>

                <div class="col-12"><hr><h6 class="fw-bold">バイタルサイン</h6></div>
                <div class="col-md-2"><label class="form-label">体重(kg)</label>
                    <input type="number" name="body_weight" class="form-control" step="0.01" value="<?= h($r['body_weight'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label">体温(℃)</label>
                    <input type="number" name="body_temperature" class="form-control" step="0.1" value="<?= h($r['body_temperature'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label">心拍(/分)</label>
                    <input type="number" name="heart_rate" class="form-control" value="<?= h($r['heart_rate'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label">呼吸(/分)</label>
                    <input type="number" name="respiratory_rate" class="form-control" value="<?= h($r['respiratory_rate'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label">血圧(収縮)</label>
                    <input type="number" name="blood_pressure_sys" class="form-control" value="<?= h($r['blood_pressure_sys'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label">血圧(拡張)</label>
                    <input type="number" name="blood_pressure_dia" class="form-control" value="<?= h($r['blood_pressure_dia'] ?? '') ?>"></div>

                <div class="col-12"><hr><h6 class="fw-bold">SOAP記録</h6></div>
                <div class="col-12"><label class="form-label"><span class="badge bg-primary me-1">S</span>主観的情報（飼い主からの情報）</label>
                    <textarea name="subjective" class="form-control" rows="3" placeholder="飼い主からの訴え、経過など"><?= h($r['subjective'] ?? '') ?></textarea></div>
                <div class="col-12"><label class="form-label"><span class="badge bg-success me-1">O</span>客観的情報（診察所見・検査結果）</label>
                    <textarea name="objective" class="form-control" rows="3" placeholder="身体検査所見、検査結果など"><?= h($r['objective'] ?? '') ?></textarea></div>
                <div class="col-12"><label class="form-label"><span class="badge bg-warning me-1">A</span>評価（診断）</label>
                    <textarea name="assessment" class="form-control" rows="3" placeholder="診断、鑑別診断など"><?= h($r['assessment'] ?? '') ?></textarea></div>
                <div class="col-12"><label class="form-label"><span class="badge bg-danger me-1">P</span>計画（治療方針）</label>
                    <textarea name="plan" class="form-control" rows="3" placeholder="治療計画、処方、次回予定など"><?= h($r['plan'] ?? '') ?></textarea></div>
                
                <div class="col-md-2"><label class="form-label">BCS</label>
                    <select name="bcs" class="form-select">
                        <option value="">-</option>
                        <?php for ($i=1; $i<=9; $i++): ?>
                        <option value="<?= $i ?>" <?= ($r['bcs'] ?? '') == $i ? 'selected' : '' ?>><?= $i ?>/9</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-10"><label class="form-label">備考</label>
                    <textarea name="notes" class="form-control" rows="2"><?= h($r['notes'] ?? '') ?></textarea></div>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="<?= $patient ? '?page=patient_detail&id='.$patient_id : '?page=patients' ?>" class="btn btn-secondary me-2">キャンセル</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $id ? '更新' : '保存' ?></button>
        </div>
    </form>
</div>