<?php
/** 予約フォーム */
$patients_list = $db->fetchAll("SELECT p.id, p.name, p.patient_code, o.id as oid, o.name as oname FROM patients p JOIN owners o ON p.owner_id=o.id WHERE p.is_active=1 ORDER BY p.name");
$staffList = $db->fetchAll("SELECT id, name, role FROM staff WHERE is_active=1 AND role IN ('admin','veterinarian') ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->insert('appointments', [
        'patient_id' => $_POST['patient_id'] ?: null,
        'owner_id' => $_POST['owner_id'] ?: null,
        'staff_id' => $_POST['staff_id'] ?: null,
        'appointment_date' => $_POST['appointment_date'],
        'appointment_time' => $_POST['appointment_time'],
        'duration' => (int)$_POST['duration'],
        'appointment_type' => $_POST['appointment_type'],
        'status' => 'scheduled',
        'reason' => trim($_POST['reason'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    redirect("?page=appointments&date=" . $_POST['appointment_date']);
}
?>
<div class="fade-in">
    <a href="?page=appointments" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>予約管理</a>
    <h4 class="fw-bold mt-1 mb-3">新規予約</h4>
    <form method="POST" class="card"><div class="card-body"><div class="row g-3"><?= csrf_field() ?>
        <div class="col-md-6"><label class="form-label">患畜 (既存)</label>
            <select name="patient_id" class="form-select" id="patientSel" onchange="updateOwner()">
                <option value="">新規患者</option>
                <?php foreach ($patients_list as $pt): ?>
                <option value="<?= $pt['id'] ?>" data-oid="<?= $pt['oid'] ?>"><?= h($pt['name']) ?> (<?= h($pt['patient_code']) ?>) - <?= h($pt['oname']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <input type="hidden" name="owner_id" id="ownerId">
        <div class="col-md-6"><label class="form-label">担当医</label>
            <select name="staff_id" class="form-select">
                <option value="">未定</option>
                <?php foreach ($staffList as $st): ?>
                <option value="<?= $st['id'] ?>"><?= h($st['name']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label required">日付</label>
            <input type="text" name="appointment_date" class="form-control datepicker" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-md-3"><label class="form-label required">時刻</label>
            <input type="text" name="appointment_time" class="form-control timepicker" value="09:00" required></div>
        <div class="col-md-3"><label class="form-label">所要時間(分)</label>
            <select name="duration" class="form-select">
                <option value="15">15分</option><option value="30" selected>30分</option><option value="60">60分</option><option value="90">90分</option><option value="120">120分</option>
            </select></div>
        <div class="col-md-3"><label class="form-label">種別</label>
            <select name="appointment_type" class="form-select">
                <option value="general">一般</option><option value="follow_up">再診</option><option value="checkup">健診</option><option value="vaccination">予防接種</option><option value="surgery">手術</option><option value="emergency">救急</option>
            </select></div>
        <div class="col-12"><label class="form-label">理由</label>
            <input type="text" name="reason" class="form-control"></div>
        <div class="col-12"><label class="form-label">備考</label>
            <textarea name="notes" class="form-control" rows="2"></textarea></div>
    </div></div>
    <div class="card-footer text-end">
        <a href="?page=appointments" class="btn btn-secondary me-2">キャンセル</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>予約登録</button>
    </div></form>
</div>
<script>function updateOwner(){const s=document.getElementById('patientSel');const o=s.options[s.selectedIndex];document.getElementById('ownerId').value=o.dataset.oid||'';}</script>
