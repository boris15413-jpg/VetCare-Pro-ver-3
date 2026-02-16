<?php
/** 退院サマリー */
$admissionId = (int)($_GET['admission_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);

if ($id) {
    $summary = $db->fetch("SELECT * FROM discharge_summaries WHERE id = ?", [$id]);
    if ($summary) $admissionId = $summary['admission_id'];
}

$admission = $db->fetch("
    SELECT a.*, p.name as patient_name, p.patient_code, p.species, p.breed, p.sex, p.birthdate, p.weight,
           o.name as owner_name, o.phone as owner_phone,
           s.name as vet_name
    FROM admissions a
    JOIN patients p ON a.patient_id = p.id
    JOIN owners o ON p.owner_id = o.id
    JOIN staff s ON a.admitted_by = s.id
    WHERE a.id = ?
", [$admissionId]);

if (!$admission) { redirect('?page=admissions'); }

$summary = $summary ?? $db->fetch("SELECT * FROM discharge_summaries WHERE admission_id = ?", [$admissionId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $data = [
        'admission_id' => $admissionId,
        'patient_id' => $admission['patient_id'],
        'admission_date' => $admission['admission_date'],
        'discharge_date' => $_POST['discharge_date'] ?: date('Y-m-d'),
        'diagnosis_on_admission' => trim($_POST['diagnosis_on_admission']),
        'diagnosis_on_discharge' => trim($_POST['diagnosis_on_discharge']),
        'treatment_summary' => trim($_POST['treatment_summary']),
        'surgery_details' => trim($_POST['surgery_details'] ?? ''),
        'medications_on_discharge' => trim($_POST['medications_on_discharge'] ?? ''),
        'diet_instructions' => trim($_POST['diet_instructions'] ?? ''),
        'exercise_restrictions' => trim($_POST['exercise_restrictions'] ?? ''),
        'follow_up_plan' => trim($_POST['follow_up_plan'] ?? ''),
        'next_appointment' => $_POST['next_appointment'] ?: null,
        'prognosis' => trim($_POST['prognosis'] ?? ''),
        'owner_instructions' => trim($_POST['owner_instructions'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'attending_vet' => $auth->currentUserId(),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    
    if ($summary) {
        $db->update('discharge_summaries', $data, 'id = ?', [$summary['id']]);
        $id = $summary['id'];
    } else {
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = $db->insert('discharge_summaries', $data);
    }
    setFlash('success', '退院サマリーを保存しました');
    redirect('?page=discharge_summary&id=' . $id);
}

$daysAdmitted = $admission['discharge_date'] 
    ? (int)((strtotime($admission['discharge_date']) - strtotime($admission['admission_date'])) / 86400) 
    : (int)((time() - strtotime($admission['admission_date'])) / 86400);
?>

<div class="fade-in">
    <a href="?page=admissions" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>入院管理</a>
    <h4 class="fw-bold mt-1 mb-3">
        <i class="bi bi-file-earmark-medical me-2"></i>退院サマリー - <?= h($admission['patient_name']) ?>
    </h4>

    <?php renderFlash(); ?>

    <!-- Patient Overview -->
    <div class="alert alert-info py-2 mb-3">
        <div class="row">
            <div class="col-md-3"><strong><?= h($admission['patient_name']) ?></strong> (<?= h($admission['patient_code']) ?>)</div>
            <div class="col-md-2"><?= h(getSpeciesName($admission['species'])) ?> / <?= h($admission['breed']) ?></div>
            <div class="col-md-2">入院日: <?= formatDate($admission['admission_date']) ?></div>
            <div class="col-md-2">入院期間: <?= $daysAdmitted ?>日</div>
            <div class="col-md-3">病棟: <?= h($admission['ward']) ?> <?= h($admission['cage_number']) ?></div>
        </div>
    </div>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-clipboard2-pulse me-2"></i>診断・治療概要</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">退院日</label>
                                <input type="date" name="discharge_date" class="form-control" value="<?= h($summary['discharge_date'] ?? $admission['discharge_date'] ?? date('Y-m-d')) ?>">
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label">入院時診断名 <span class="text-danger">*</span></label>
                            <input type="text" name="diagnosis_on_admission" class="form-control" value="<?= h($summary['diagnosis_on_admission'] ?? $admission['diagnosis']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">退院時診断名</label>
                            <input type="text" name="diagnosis_on_discharge" class="form-control" value="<?= h($summary['diagnosis_on_discharge'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">治療経過 <span class="text-danger">*</span></label>
                            <textarea name="treatment_summary" class="form-control" rows="5" required placeholder="入院中に行った治療の概要..."><?= h($summary['treatment_summary'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">手術内容（該当する場合）</label>
                            <textarea name="surgery_details" class="form-control" rows="3"><?= h($summary['surgery_details'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-house me-2"></i>退院後の指示</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">退院時処方薬</label>
                            <textarea name="medications_on_discharge" class="form-control" rows="3" placeholder="薬品名、用量、頻度、期間..."><?= h($summary['medications_on_discharge'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">食事指導</label>
                            <textarea name="diet_instructions" class="form-control" rows="2" placeholder="処方食、給餌量、注意事項..."><?= h($summary['diet_instructions'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">運動制限</label>
                            <textarea name="exercise_restrictions" class="form-control" rows="2" placeholder="安静度、散歩制限..."><?= h($summary['exercise_restrictions'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">飼い主への説明・注意事項</label>
                            <textarea name="owner_instructions" class="form-control" rows="3" placeholder="自宅での観察ポイント、緊急時の対応..."><?= h($summary['owner_instructions'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3" style="position:sticky; top:80px;">
                    <div class="card-header"><i class="bi bi-calendar me-2"></i>フォローアップ</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">経過観察計画</label>
                            <textarea name="follow_up_plan" class="form-control" rows="3" placeholder="再診予定、経過観察のポイント..."><?= h($summary['follow_up_plan'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">次回予約日</label>
                            <input type="date" name="next_appointment" class="form-control" value="<?= h($summary['next_appointment'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">予後</label>
                            <select name="prognosis" class="form-select">
                                <option value="">-- 選択 --</option>
                                <option value="良好" <?= ($summary['prognosis'] ?? '') === '良好' ? 'selected' : '' ?>>良好</option>
                                <option value="やや良好" <?= ($summary['prognosis'] ?? '') === 'やや良好' ? 'selected' : '' ?>>やや良好</option>
                                <option value="要経過観察" <?= ($summary['prognosis'] ?? '') === '要経過観察' ? 'selected' : '' ?>>要経過観察</option>
                                <option value="注意" <?= ($summary['prognosis'] ?? '') === '注意' ? 'selected' : '' ?>>注意</option>
                                <option value="不良" <?= ($summary['prognosis'] ?? '') === '不良' ? 'selected' : '' ?>>不良</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">備考</label>
                            <textarea name="notes" class="form-control" rows="2"><?= h($summary['notes'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i><?= $summary ? '更新' : '保存' ?></button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
