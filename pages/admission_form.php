<?php
/** 入院登録・編集フォーム */
$id = (int)($_GET['id'] ?? 0);
$admission = $id ? $db->fetch("SELECT * FROM admissions WHERE id = ?", [$id]) : null;
$patients_list = $db->fetchAll("SELECT p.id, p.name, p.patient_code, p.species FROM patients p WHERE p.is_active = 1 ORDER BY p.name");

// ▼ 施設設定から病棟リストを取得
$ward_setting = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'ward_list'");
$ward_list = $ward_setting ? explode("\n", str_replace("\r", "", $ward_setting['setting_value'])) : ['入院室'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['discharge']) && $id) {
        $db->update('admissions', ['status'=>'discharged','discharge_date'=>date('Y-m-d H:i:s'),'discharge_summary'=>trim($_POST['discharge_summary']??''),'discharged_by'=>$auth->currentUserId(),'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$id]);
        redirect("?page=admissions");
    }
    $data = [
        'patient_id' => (int)$_POST['patient_id'],
        'admitted_by' => $auth->currentUserId(),
        'admission_date' => $_POST['admission_date'],
        'status' => 'admitted',
        'ward' => trim($_POST['ward'] ?? ''),
        'cage_number' => trim($_POST['cage_number'] ?? ''),
        'reason' => trim($_POST['reason'] ?? ''),
        'diet_instructions' => trim($_POST['diet_instructions'] ?? ''),
        'exercise_instructions' => trim($_POST['exercise_instructions'] ?? ''),
        'special_notes' => trim($_POST['special_notes'] ?? ''),
        'estimated_discharge' => $_POST['estimated_discharge'] ?: null,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    if ($id) { $db->update('admissions', $data, 'id=?', [$id]); }
    else { $data['created_at'] = date('Y-m-d H:i:s'); $id = $db->insert('admissions', $data); }
    redirect("?page=temperature_chart&admission_id={$id}");
}
$a = $admission ?: ['admission_date'=>date('Y-m-d H:i:s')];
$pid = (int)($_GET['patient_id'] ?? ($a['patient_id'] ?? 0));
?>
<div class="fade-in">
    <a href="?page=admissions" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>入院管理</a>
    <h4 class="fw-bold mt-1 mb-3"><?= $id ? '入院情報編集' : '新規入院登録' ?></h4>
    <form method="POST" class="card">
        <div class="card-body"><div class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-6"><label class="form-label required">患畜</label>
                <select name="patient_id" class="form-select" required>
                    <option value="">選択</option>
                    <?php foreach ($patients_list as $pt): ?>
                    <option value="<?= $pt['id'] ?>" <?= $pid==$pt['id']?'selected':'' ?>><?= h($pt['name']) ?> (<?= h($pt['patient_code']) ?>)</option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-6"><label class="form-label required">入院日時</label>
                <input type="text" name="admission_date" class="form-control datetimepicker" value="<?= h($a['admission_date']) ?>" required></div>
            <div class="col-md-4"><label class="form-label">病棟</label>
                <select name="ward" class="form-select">
                    <option value="">選択</option>
                    <?php foreach ($ward_list as $w): $w = trim($w); if($w==='') continue; ?>
                    <option value="<?= h($w) ?>" <?= ($a['ward']??'')===$w?'selected':'' ?>><?= h($w) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-4"><label class="form-label">ケージ番号</label>
                <input type="text" name="cage_number" class="form-control" value="<?= h($a['cage_number'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">退院予定日</label>
                <input type="text" name="estimated_discharge" class="form-control datepicker" value="<?= h($a['estimated_discharge'] ?? '') ?>"></div>
            <div class="col-12"><label class="form-label">入院理由</label>
                <textarea name="reason" class="form-control" rows="2"><?= h($a['reason'] ?? '') ?></textarea></div>
            <div class="col-md-6"><label class="form-label">食事指示</label>
                <textarea name="diet_instructions" class="form-control" rows="2"><?= h($a['diet_instructions'] ?? '') ?></textarea></div>
            <div class="col-md-6"><label class="form-label">運動指示</label>
                <textarea name="exercise_instructions" class="form-control" rows="2"><?= h($a['exercise_instructions'] ?? '') ?></textarea></div>
            <div class="col-12"><label class="form-label">特記事項・注意事項</label>
                <textarea name="special_notes" class="form-control" rows="2"><?= h($a['special_notes'] ?? '') ?></textarea></div>
        </div></div>
        <div class="card-footer d-flex justify-content-between">
            <div>
                <?php if ($id && ($admission['status']??'')==='admitted'): ?>
                <button type="submit" name="discharge" value="1" class="btn btn-danger" data-confirm="退院処理を行いますか？">
                    <i class="bi bi-box-arrow-right me-1"></i>退院処理
                </button>
                <?php endif; ?>
            </div>
            <div>
                <a href="?page=admissions" class="btn btn-secondary me-2">キャンセル</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $id ? '更新' : '入院登録' ?></button>
            </div>
        </div>
    </form>
</div>