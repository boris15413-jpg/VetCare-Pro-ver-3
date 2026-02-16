<?php
/** 患畜登録・編集フォーム  **/
$id = (int)($_GET['id'] ?? 0);
$patient = $id ? $db->fetch("SELECT * FROM patients WHERE id = ?", [$id]) : null;
$owners = $db->fetchAll("SELECT id, name, owner_code FROM owners WHERE is_active = 1 ORDER BY name");

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // カルテ番号の処理
    $pCode = trim($_POST['patient_code'] ?? '');
    
    // 重複チェック (新規登録時、または編集でコードが変更された場合)
    if ($pCode) {
        $existing = $db->fetch("SELECT id FROM patients WHERE patient_code = ? AND id != ?", [$pCode, $id]);
        if ($existing) {
            $error = "エラー: カルテNo '{$pCode}' は既に使用されています。";
        }
    } else {
        // 空の場合は自動生成 (新規のみ)
        if (!$id) {
            $pCode = 'PT-' . str_pad($db->count('patients') + 1, 4, '0', STR_PAD_LEFT);
        } else {
            // 編集で空にされた場合は元のコードを維持
            $pCode = $patient['patient_code'];
        }
    }

    if (!$error) {
        $data = [
            'patient_code' => $pCode,
            'owner_id' => (int)$_POST['owner_id'],
            'name' => trim($_POST['name']),
            'species' => $_POST['species'],
            'breed' => trim($_POST['breed'] ?? ''),
            'color' => trim($_POST['color'] ?? ''),
            'sex' => $_POST['sex'],
            'birthdate' => $_POST['birthdate'] ?: null,
            'weight' => $_POST['weight'] ?: null,
            'microchip_id' => trim($_POST['microchip_id'] ?? ''),
            'blood_type' => trim($_POST['blood_type'] ?? ''),
            'allergies' => trim($_POST['allergies'] ?? ''),
            'chronic_conditions' => trim($_POST['chronic_conditions'] ?? ''),
            'insurance_company' => trim($_POST['insurance_company'] ?? ''),
            'insurance_number' => trim($_POST['insurance_number'] ?? ''),
            'insurance_rate' => (int)($_POST['insurance_rate'] ?? 0),
            'notes' => trim($_POST['notes'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($id) {
            $db->update('patients', $data, 'id = ?', [$id]);
            $auth->logActivity($auth->currentUserId(), 'update_patient', "患畜更新: {$data['name']}", 'patient', $id);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = $db->insert('patients', $data);
            $auth->logActivity($auth->currentUserId(), 'create_patient', "患畜登録: {$data['name']}", 'patient', $id);
        }
        redirect("?page=patient_detail&id={$id}");
    }
}
$p = $patient ?: [];
$ownerIdFromGet = (int)($_GET['owner_id'] ?? ($p['owner_id'] ?? 0));
?>

<div class="fade-in">
    <a href="?page=patients" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>患畜一覧</a>
    <h4 class="fw-bold mt-1 mb-3"><?= $id ? '患畜情報編集' : '新規患畜登録' ?></h4>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="card">
        <div class="card-body">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12 bg-light p-2 rounded border mb-2">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <label class="form-label">カルテNo (任意)</label>
                            <input type="text" name="patient_code" class="form-control" value="<?= h($p['patient_code'] ?? '') ?>" placeholder="自動生成 (空欄可)">
                        </div>
                        <div class="col-md-9">
                            <small class="text-muted">※紙カルテからの移行などで番号を指定したい場合は入力してください。空欄の場合は自動採番されます。</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label required">飼い主</label>
                    <select name="owner_id" class="form-select" required>
                        <option value="">選択してください</option>
                        <?php foreach ($owners as $ow): ?>
                        <option value="<?= $ow['id'] ?>" <?= $ownerIdFromGet == $ow['id'] ? 'selected' : '' ?>><?= h($ow['name']) ?> (<?= h($ow['owner_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small><a href="?page=owner_form" target="_blank">新しい飼い主を登録</a></small>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">患畜名</label>
                    <input type="text" name="name" class="form-control" value="<?= h($p['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">種別</label>
                    <select name="species" class="form-select" required>
                        <?php foreach (SPECIES_LIST as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['species'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">品種</label>
                    <input type="text" name="breed" class="form-control" value="<?= h($p['breed'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">毛色</label>
                    <input type="text" name="color" class="form-control" value="<?= h($p['color'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label required">性別</label>
                    <select name="sex" class="form-select" required>
                        <?php foreach (SEX_LIST as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['sex'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">生年月日</label>
                    <input type="text" name="birthdate" class="form-control datepicker" value="<?= h($p['birthdate'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">体重 (kg)</label>
                    <input type="number" name="weight" class="form-control" step="0.01" value="<?= h($p['weight'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">血液型</label>
                    <input type="text" name="blood_type" class="form-control" value="<?= h($p['blood_type'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">マイクロチップID</label>
                    <input type="text" name="microchip_id" class="form-control" value="<?= h($p['microchip_id'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="bi bi-exclamation-triangle text-danger me-1"></i>アレルギー</label>
                    <input type="text" name="allergies" class="form-control" value="<?= h($p['allergies'] ?? '') ?>" placeholder="薬品アレルギー等">
                </div>
                <div class="col-12">
                    <label class="form-label">既往歴・持病</label>
                    <textarea name="chronic_conditions" class="form-control" rows="2"><?= h($p['chronic_conditions'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">保険会社</label>
                    <input type="text" name="insurance_company" class="form-control" value="<?= h($p['insurance_company'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">保険証番号</label>
                    <input type="text" name="insurance_number" class="form-control" value="<?= h($p['insurance_number'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">保険負担率 (%)</label>
                    <input type="number" name="insurance_rate" class="form-control" min="0" max="100" value="<?= h($p['insurance_rate'] ?? 0) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">備考</label>
                    <textarea name="notes" class="form-control" rows="2"><?= h($p['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="?page=patients" class="btn btn-secondary me-2">キャンセル</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $id ? '更新' : '登録' ?></button>
        </div>
    </form>
</div>