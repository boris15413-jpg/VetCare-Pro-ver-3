<?php
/** 病理登録フォーム */
$id = (int)($_GET['id'] ?? 0);
$path = $id ? $db->fetch("SELECT * FROM pathology WHERE id = ?", [$id]) : null;
$patients_list = $db->fetchAll("SELECT id, name, patient_code FROM patients WHERE is_active=1 ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'patient_id' => (int)$_POST['patient_id'],
        'specimen_type' => trim($_POST['specimen_type']),
        'collection_site' => trim($_POST['collection_site'] ?? ''),
        'collection_date' => $_POST['collection_date'],
        'collected_by' => $auth->currentUserId(),
        'fixation_method' => trim($_POST['fixation_method'] ?? ''),
        'gross_description' => trim($_POST['gross_description'] ?? ''),
        'microscopic_description' => trim($_POST['microscopic_description'] ?? ''),
        'diagnosis' => trim($_POST['diagnosis'] ?? ''),
        'pathologist' => trim($_POST['pathologist'] ?? ''),
        'status' => $_POST['status'],
        'report_date' => $_POST['report_date'] ?: null,
        'notes' => trim($_POST['notes'] ?? ''),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    if ($id) { $db->update('pathology', $data, 'id=?', [$id]); }
    else {
        $data['pathology_number'] = 'PATH-' . date('Y') . '-' . str_pad($db->count('pathology') + 1, 3, '0', STR_PAD_LEFT);
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = $db->insert('pathology', $data);
    }
    redirect("?page=pathology");
}
$p = $path ?: ['collection_date'=>date('Y-m-d'),'status'=>'pending'];
$pid = (int)($_GET['patient_id'] ?? ($p['patient_id'] ?? 0));
?>
<div class="fade-in">
    <a href="?page=pathology" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>病理検査一覧</a>
    <h4 class="fw-bold mt-1 mb-3"><?= $id ? '病理検査編集' : '新規病理検査登録' ?></h4>
    <?php if ($id && $path): ?>
    <div class="alert alert-info py-2"><strong>病理番号:</strong> <?= h($path['pathology_number']) ?></div>
    <?php endif; ?>
    <form method="POST" class="card">
        <div class="card-body"><div class="row g-3"><?= csrf_field() ?>
            <div class="col-md-6"><label class="form-label required">患畜</label>
                <select name="patient_id" class="form-select" required>
                    <option value="">選択</option>
                    <?php foreach ($patients_list as $pt): ?>
                    <option value="<?= $pt['id'] ?>" <?= $pid==$pt['id']?'selected':'' ?>><?= h($pt['name']) ?> (<?= h($pt['patient_code']) ?>)</option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label required">採取日</label>
                <input type="text" name="collection_date" class="form-control datepicker" value="<?= h($p['collection_date']) ?>" required></div>
            <div class="col-md-3"><label class="form-label">状態</label>
                <select name="status" class="form-select">
                    <option value="pending" <?= ($p['status']??'')==='pending'?'selected':'' ?>>未検査</option>
                    <option value="in_progress" <?= ($p['status']??'')==='in_progress'?'selected':'' ?>>検査中</option>
                    <option value="completed" <?= ($p['status']??'')==='completed'?'selected':'' ?>>報告済</option>
                </select></div>
            <div class="col-md-6"><label class="form-label required">検体種類</label>
                <input type="text" name="specimen_type" class="form-control" value="<?= h($p['specimen_type'] ?? '') ?>" required placeholder="例: リンパ節穿刺吸引細胞診"></div>
            <div class="col-md-6"><label class="form-label">採取部位</label>
                <input type="text" name="collection_site" class="form-control" value="<?= h($p['collection_site'] ?? '') ?>"></div>
            <div class="col-md-6"><label class="form-label">固定方法</label>
                <input type="text" name="fixation_method" class="form-control" value="<?= h($p['fixation_method'] ?? '') ?>" placeholder="例: ホルマリン固定"></div>
            <div class="col-md-6"><label class="form-label">病理医</label>
                <input type="text" name="pathologist" class="form-control" value="<?= h($p['pathologist'] ?? '') ?>"></div>
            <div class="col-12"><label class="form-label">肉眼所見</label>
                <textarea name="gross_description" class="form-control" rows="3"><?= h($p['gross_description'] ?? '') ?></textarea></div>
            <div class="col-12"><label class="form-label">鏡検所見</label>
                <textarea name="microscopic_description" class="form-control" rows="4"><?= h($p['microscopic_description'] ?? '') ?></textarea></div>
            <div class="col-12"><label class="form-label">病理診断</label>
                <textarea name="diagnosis" class="form-control" rows="3"><?= h($p['diagnosis'] ?? '') ?></textarea></div>
            <div class="col-md-6"><label class="form-label">報告日</label>
                <input type="text" name="report_date" class="form-control datepicker" value="<?= h($p['report_date'] ?? '') ?>"></div>
            <div class="col-12"><label class="form-label">備考</label>
                <textarea name="notes" class="form-control" rows="2"><?= h($p['notes'] ?? '') ?></textarea></div>
        </div></div>
        <div class="card-footer text-end">
            <a href="?page=pathology" class="btn btn-secondary me-2">キャンセル</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $id ? '更新' : '登録' ?></button>
        </div>
    </form>
</div>
