<?php
/** 診察記録詳細（2カラム・画像表示対応版） */
$id = (int)($_GET['id'] ?? 0);

// 1. メインのカルテ情報を取得
$rec = $db->fetch("
    SELECT mr.*, p.name as patient_name, p.patient_code, p.species, p.breed, p.birthdate, p.sex, p.owner_id, 
           s.name as vet_name, o.name as owner_name 
    FROM medical_records mr 
    JOIN patients p ON mr.patient_id = p.id 
    JOIN staff s ON mr.staff_id = s.id 
    JOIN owners o ON p.owner_id = o.id 
    WHERE mr.id = ?
", [$id]);

if (!$rec) redirect('?page=patients');

$patient_id = $rec['patient_id'];

// 2. この患者の過去のカルテ一覧を取得（左サイドバー用）
$history = $db->fetchAll("
    SELECT id, visit_date, visit_type, diagnosis_name 
    FROM medical_records 
    WHERE patient_id = ? 
    ORDER BY visit_date DESC, created_at DESC
", [$patient_id]);

// 3. 関連データ（画像・オーダー・検査結果）を取得
$orders = $db->fetchAll("SELECT * FROM orders WHERE record_id = ? ORDER BY ordered_at", [$id]);
$images = $db->fetchAll("SELECT * FROM record_images WHERE record_id = ?", [$id]);
$labs = $db->fetchAll("SELECT * FROM lab_results WHERE record_id = ?", [$id]);

// 年齢計算
$age = calculateAge($rec['birthdate']);
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="?page=patient_detail&id=<?= $patient_id ?>" class="text-decoration-none text-secondary">
                <i class="bi bi-arrow-left me-1"></i>患畜詳細へ戻る
            </a>
            <h4 class="fw-bold mt-1 mb-0">
                <i class="bi bi-journal-medical me-2"></i>診察記録詳細
            </h4>
        </div>
        <div class="d-flex gap-2">
            <a href="?page=documents&patient_id=<?= $patient_id ?>&record_id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-file-text me-1"></i>書類作成
            </a>
            <a href="?page=record_form&id=<?= $id ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>編集
            </a>
        </div>
    </div>

    <div class="row g-0 h-100">
        <div class="col-md-3 border-end bg-light" style="min-height: 80vh;">
            <div class="p-3 border-bottom bg-white">
                <h6 class="fw-bold mb-1"><?= h($rec['patient_name']) ?> <small class="text-muted">(<?= h($rec['patient_code']) ?>)</small></h6>
                <small class="text-muted">
                    <?= h(getSpeciesName($rec['species'])) ?> / <?= h(getSexName($rec['sex'])) ?> / <?= $age ?>
                </small>
            </div>
            <div class="list-group list-group-flush" style="max-height: 80vh; overflow-y: auto;">
                <?php foreach ($history as $h): ?>
                    <?php $isActive = ($h['id'] === $id); ?>
                    <a href="?page=medical_record&id=<?= $h['id'] ?>" 
                       class="list-group-item list-group-item-action py-3 <?= $isActive ? 'active' : '' ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <strong><?= formatDate($h['visit_date']) ?></strong>
                            <small class="<?= $isActive ? 'text-white-50' : 'text-muted' ?>">
                                <?= $h['visit_type'] === 'admission' ? '入院' : '外来' ?>
                            </small>
                        </div>
                        <div class="small text-truncate <?= $isActive ? 'text-white' : 'text-muted' ?>">
                            <?= h($h['diagnosis_name'] ?: '(診断名なし)') ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="p-3 text-center border-top">
                <a href="?page=record_form&patient_id=<?= $patient_id ?>" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-plus-lg me-1"></i>新しいカルテを作成
                </a>
            </div>
        </div>

        <div class="col-md-9 bg-white">
            <div class="p-4">
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="badge bg-<?= $rec['visit_type']==='admission'?'warning':'info' ?> fs-6">
                            <?= $rec['visit_type']==='admission'?'入院':($rec['visit_type']==='emergency'?'救急':'外来') ?>
                        </span>
                        <small class="text-muted">担当医: <?= h($rec['vet_name']) ?></small>
                    </div>
                    <div class="card-body">
                        <?php if ($rec['diagnosis_name']): ?>
                            <h5 class="fw-bold mb-3"><i class="bi bi-tag me-2"></i><?= h($rec['diagnosis_name']) ?></h5>
                        <?php endif; ?>
                        
                        <?php if ($rec['chief_complaint']): ?>
                            <div class="mb-3 p-2 bg-light rounded border-start border-4 border-primary">
                                <strong>主訴:</strong> <?= h($rec['chief_complaint']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="row g-2 text-center mt-3">
                            <div class="col-md-2 col-4"><div class="p-2 border rounded bg-light"><small class="d-block text-muted">体重</small><strong><?= $rec['body_weight'] ? $rec['body_weight'].'kg' : '-' ?></strong></div></div>
                            <div class="col-md-2 col-4"><div class="p-2 border rounded bg-light"><small class="d-block text-muted">体温</small><strong><?= $rec['body_temperature'] ? $rec['body_temperature'].'℃' : '-' ?></strong></div></div>
                            <div class="col-md-2 col-4"><div class="p-2 border rounded bg-light"><small class="d-block text-muted">心拍</small><strong><?= $rec['heart_rate'] ? $rec['heart_rate'].'/分' : '-' ?></strong></div></div>
                            <div class="col-md-2 col-4"><div class="p-2 border rounded bg-light"><small class="d-block text-muted">呼吸</small><strong><?= $rec['respiratory_rate'] ? $rec['respiratory_rate'].'/分' : '-' ?></strong></div></div>
                            <div class="col-md-2 col-6"><div class="p-2 border rounded bg-light"><small class="d-block text-muted">血圧</small><strong><?= $rec['blood_pressure_sys'] ? $rec['blood_pressure_sys'].'/'.$rec['blood_pressure_dia'] : '-' ?></strong></div></div>
                            <div class="col-md-2 col-6"><div class="p-2 border rounded bg-light"><small class="d-block text-muted">BCS</small><strong><?= $rec['bcs'] ? $rec['bcs'].'/9' : '-' ?></strong></div></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($images)): ?>
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-transparent border-0 ps-0 fw-bold text-dark">
                        <i class="bi bi-images me-2 text-primary"></i>添付画像・検査画像
                    </div>
                    <div class="card-body pt-0 ps-0">
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($images as $img): ?>
                            <div class="position-relative">
                                <a href="uploads/<?= h($img['file_path']) ?>" target="_blank" class="d-block border rounded overflow-hidden shadow-sm hover-zoom">
                                    <img src="uploads/<?= h($img['file_path']) ?>" style="height: 180px; width: auto; object-fit: contain; background:#f8f9fa;">
                                </a>
                                <?php if ($img['caption']): ?>
                                <div class="small text-muted mt-1 text-truncate" style="max-width: 180px;">
                                    <?= h($img['caption']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="soap-section h-100">
                                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-2"><i class="bi bi-chat-left-text me-2"></i>Subjective (主観的情報)</h6>
                                    <p class="mb-0 text-break"><?= nl2br(h($rec['subjective'])) ?: '<span class="text-muted small">記載なし</span>' ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="soap-section h-100">
                                    <h6 class="fw-bold text-success border-bottom pb-2 mb-2"><i class="bi bi-search me-2"></i>Objective (客観的情報)</h6>
                                    <p class="mb-0 text-break"><?= nl2br(h($rec['objective'])) ?: '<span class="text-muted small">記載なし</span>' ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="soap-section h-100">
                                    <h6 class="fw-bold text-warning text-dark border-bottom pb-2 mb-2"><i class="bi bi-lightbulb me-2"></i>Assessment (評価)</h6>
                                    <p class="mb-0 text-break"><?= nl2br(h($rec['assessment'])) ?: '<span class="text-muted small">記載なし</span>' ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="soap-section h-100">
                                    <h6 class="fw-bold text-danger border-bottom pb-2 mb-2"><i class="bi bi-calendar-check me-2"></i>Plan (計画)</h6>
                                    <p class="mb-0 text-break"><?= nl2br(h($rec['plan'])) ?: '<span class="text-muted small">記載なし</span>' ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($rec['notes']): ?>
                        <div class="mt-4 p-3 bg-light rounded border small">
                            <strong><i class="bi bi-sticky me-2"></i>備考:</strong><br>
                            <?= nl2br(h($rec['notes'])) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($labs) || !empty($orders)): ?>
                <div class="row g-3">
                    <?php if (!empty($labs)): ?>
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white fw-bold"><i class="bi bi-graph-up me-2"></i>検査結果</div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light"><tr><th>項目</th><th>結果</th><th>単位</th><th>基準値</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($labs as $lb): ?>
                                    <tr class="<?= $lb['is_abnormal'] ? 'table-danger' : '' ?>">
                                        <td><?= h($lb['test_name']) ?></td>
                                        <td><strong><?= h($lb['result_value']) ?></strong> <?= $lb['is_abnormal'] ? '⚠️' : '' ?></td>
                                        <td><?= h($lb['unit']) ?></td>
                                        <td><small class="text-muted"><?= h($lb['reference_low']) ?>-<?= h($lb['reference_high']) ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($orders)): ?>
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white fw-bold"><i class="bi bi-list-check me-2"></i>オーダー・処方</div>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light"><tr><th>種別</th><th>内容</th><th>数量</th><th>状態</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($orders as $ord): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?= $ord['order_type']=='prescription'?'処方':'検査/処置' ?></span></td>
                                        <td><?= h($ord['order_name']) ?></td>
                                        <td><?= $ord['quantity'] ?><?= h($ord['unit']) ?></td>
                                        <td><?= getOrderStatusBadge($ord['status']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>