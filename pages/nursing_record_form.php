<?php
/** 看護記録フォーム */
$patients_list = $db->fetchAll("SELECT p.id AS id, p.name AS name, p.patient_code AS patient_code FROM patients p JOIN admissions a ON a.patient_id=p.id WHERE a.status='admitted' UNION SELECT p.id AS id, p.name AS name, p.patient_code AS patient_code FROM patients p WHERE p.is_active=1 ORDER BY name");$pid = (int)($_GET['patient_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admId = $db->fetch("SELECT id FROM admissions WHERE patient_id=? AND status='admitted' LIMIT 1", [(int)$_POST['patient_id']]);
    $db->insert('nursing_records', [
        'patient_id' => (int)$_POST['patient_id'],
        'admission_id' => $admId ? $admId['id'] : null,
        'nurse_id' => $auth->currentUserId(),
        'record_type' => $_POST['record_type'],
        'content' => trim($_POST['content']),
        'priority' => $_POST['priority'] ?? 'normal',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // 【修正箇所】 入力した患畜のページへ戻るように変更
    redirect("?page=nursing&patient_id=" . $_POST['patient_id']);
}
?>
<div class="fade-in">
    <a href="?page=nursing<?= $pid ? '&patient_id='.$pid : '' ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>看護記録一覧</a>
    
    <h4 class="fw-bold mt-1 mb-3">看護記録の追加</h4>
    <form method="POST" class="card">
        <div class="card-body"><div class="row g-3"><?= csrf_field() ?>
            <div class="col-md-6"><label class="form-label required">患畜</label>
                <select name="patient_id" class="form-select" required>
                    <option value="">選択</option>
                    <?php foreach ($patients_list as $pt): ?>
                    <option value="<?= $pt['id'] ?>" <?= $pid==$pt['id']?'selected':'' ?>><?= h($pt['name']) ?> (<?= h($pt['patient_code']) ?>)</option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label">記録種別</label>
                <select name="record_type" class="form-select">
                    <option value="observation">観察</option><option value="care">ケア</option><option value="report">報告</option>
                </select></div>
            <div class="col-md-3"><label class="form-label">重要度</label>
                <select name="priority" class="form-select">
                    <option value="normal">通常</option><option value="high">重要</option><option value="low">低</option>
                </select></div>
            <div class="col-12"><label class="form-label required">記録内容</label>
                <textarea name="content" class="form-control" rows="5" required placeholder="観察事項、実施したケア、報告内容等"></textarea></div>
        </div></div>
        <div class="card-footer text-end">
            <a href="?page=nursing<?= $pid ? '&patient_id='.$pid : '' ?>" class="btn btn-secondary me-2">キャンセル</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>保存</button>
        </div>
    </form>
</div>