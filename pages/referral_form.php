<?php
/** 紹介状作成 */
$patientId = (int)($_GET['patient_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
$patient = $patientId ? $db->fetch("SELECT p.*, o.name as owner_name FROM patients p JOIN owners o ON p.owner_id = o.id WHERE p.id = ?", [$patientId]) : null;

if (!$patient && $id) {
    $ref = $db->fetch("SELECT * FROM referrals WHERE id = ?", [$id]);
    if ($ref) {
        $patientId = $ref['patient_id'];
        $patient = $db->fetch("SELECT p.*, o.name as owner_name FROM patients p JOIN owners o ON p.owner_id = o.id WHERE p.id = ?", [$patientId]);
    }
}

if (!$patient) { redirect('?page=patients'); }

$referral = $id ? $db->fetch("SELECT * FROM referrals WHERE id = ?", [$id]) : null;

// Get recent records for pre-fill
$recentRecords = $db->fetchAll("SELECT mr.*, s.name as vet_name FROM medical_records mr JOIN staff s ON mr.staff_id = s.id WHERE mr.patient_id = ? ORDER BY mr.visit_date DESC LIMIT 5", [$patientId]);
$recentLabs = $db->fetchAll("SELECT * FROM lab_results WHERE patient_id = ? ORDER BY tested_at DESC LIMIT 10", [$patientId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $data = [
        'patient_id' => $patientId,
        'referring_hospital' => getSetting('hospital_name', ''),
        'referring_vet' => trim($_POST['referring_vet']),
        'destination_hospital' => trim($_POST['destination_hospital']),
        'destination_vet' => trim($_POST['destination_vet'] ?? ''),
        'destination_address' => trim($_POST['destination_address'] ?? ''),
        'destination_phone' => trim($_POST['destination_phone'] ?? ''),
        'reason' => trim($_POST['reason']),
        'clinical_history' => trim($_POST['clinical_history']),
        'current_diagnosis' => trim($_POST['current_diagnosis']),
        'current_treatment' => trim($_POST['current_treatment']),
        'test_results_summary' => trim($_POST['test_results_summary'] ?? ''),
        'request_items' => trim($_POST['request_items'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'urgency' => $_POST['urgency'] ?? 'normal',
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    
    if ($referral) {
        $db->update('referrals', $data, 'id = ?', [$id]);
    } else {
        $data['referral_number'] = 'REF' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $data['created_by'] = $auth->currentUserId();
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = $db->insert('referrals', $data);
    }
    setFlash('success', '紹介状を保存しました');
    redirect('?page=referral_form&id=' . $id . '&patient_id=' . $patientId);
}

$hospitalName = getSetting('hospital_name', '');
$currentVet = $auth->currentUserName();
?>

<div class="fade-in">
    <a href="?page=patient_detail&id=<?= $patientId ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= h($patient['name']) ?>の詳細</a>
    <h4 class="fw-bold mt-1 mb-3"><i class="bi bi-envelope-paper me-2"></i>紹介状 - <?= h($patient['name']) ?></h4>

    <?php renderFlash(); ?>

    <?php if ($referral): ?>
    <div class="alert alert-info py-2">
        <i class="bi bi-info-circle me-1"></i>紹介状番号: <strong><?= h($referral['referral_number']) ?></strong>
        <a href="?page=referral_form&id=<?= $id ?>&patient_id=<?= $patientId ?>&print=1" class="btn btn-sm btn-outline-primary ms-3" target="_blank"><i class="bi bi-printer me-1"></i>印刷用</a>
    </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-hospital me-2"></i>紹介先情報</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">紹介先病院名 <span class="text-danger">*</span></label>
                                <input type="text" name="destination_hospital" class="form-control" value="<?= h($referral['destination_hospital'] ?? '') ?>" required placeholder="○○動物医療センター">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">紹介先獣医師名</label>
                                <input type="text" name="destination_vet" class="form-control" value="<?= h($referral['destination_vet'] ?? '') ?>" placeholder="○○先生">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">紹介先住所</label>
                                <input type="text" name="destination_address" class="form-control" value="<?= h($referral['destination_address'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">紹介先電話</label>
                                <input type="text" name="destination_phone" class="form-control" value="<?= h($referral['destination_phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-file-medical me-2"></i>診療情報</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">紹介理由 <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="2" required placeholder="精密検査のため / 手術適応の判断のため 等"><?= h($referral['reason'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">臨床経過 <span class="text-danger">*</span></label>
                            <textarea name="clinical_history" class="form-control" rows="4" required placeholder="初診日、主訴、経過..."><?= h($referral['clinical_history'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">現在の診断名</label>
                            <input type="text" name="current_diagnosis" class="form-control" value="<?= h($referral['current_diagnosis'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">現在の治療内容</label>
                            <textarea name="current_treatment" class="form-control" rows="3" placeholder="投薬内容、処置内容..."><?= h($referral['current_treatment'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">検査結果概要</label>
                            <textarea name="test_results_summary" class="form-control" rows="3" placeholder="血液検査結果、画像検査所見..."><?= h($referral['test_results_summary'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">依頼事項</label>
                            <textarea name="request_items" class="form-control" rows="2" placeholder="CT検査、MRI検査、外科手術の適応判断..."><?= h($referral['request_items'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">備考</label>
                            <textarea name="notes" class="form-control" rows="2"><?= h($referral['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3" style="position:sticky; top:80px;">
                    <div class="card-header"><i class="bi bi-gear me-2"></i>設定</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">紹介元獣医師名</label>
                            <input type="text" name="referring_vet" class="form-control" value="<?= h($referral['referring_vet'] ?? $currentVet) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">緊急度</label>
                            <select name="urgency" class="form-select">
                                <option value="normal" <?= ($referral['urgency'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>通常</option>
                                <option value="urgent" <?= ($referral['urgency'] ?? '') === 'urgent' ? 'selected' : '' ?>>急ぎ</option>
                                <option value="emergency" <?= ($referral['urgency'] ?? '') === 'emergency' ? 'selected' : '' ?>>緊急</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i><?= $referral ? '更新' : '保存' ?></button>
                    </div>
                </div>

                <!-- Recent records for reference -->
                <?php if (!empty($recentRecords)): ?>
                <div class="card">
                    <div class="card-header"><i class="bi bi-journal me-2"></i>最近の診察（参考）</div>
                    <div class="card-body p-0">
                        <?php foreach ($recentRecords as $rec): ?>
                        <div class="p-2 border-bottom small">
                            <strong><?= formatDate($rec['visit_date']) ?></strong> <?= h($rec['vet_name']) ?><br>
                            <?php if ($rec['diagnosis_name']): ?>
                            <span class="badge bg-dark"><?= h($rec['diagnosis_name']) ?></span>
                            <?php endif; ?>
                            <div class="text-muted"><?= h(mb_substr($rec['assessment'], 0, 80)) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
