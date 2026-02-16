<?php
/** 患畜詳細 */
$id = (int)($_GET['id'] ?? 0);
$patient = $db->fetch("
    SELECT p.*, o.name as owner_name, o.owner_code, o.phone as owner_phone, o.email as owner_email, o.address as owner_address, o.id as owner_id
    FROM patients p JOIN owners o ON p.owner_id = o.id WHERE p.id = ?
", [$id]);
if (!$patient) { redirect('?page=patients'); }

$records = $db->fetchAll("SELECT mr.*, s.name as vet_name FROM medical_records mr JOIN staff s ON mr.staff_id = s.id WHERE mr.patient_id = ? ORDER BY mr.visit_date DESC LIMIT 20", [$id]);
$activeAdmission = $db->fetch("SELECT * FROM admissions WHERE patient_id = ? AND status = 'admitted' ORDER BY admission_date DESC LIMIT 1", [$id]);
$vaccines = $db->fetchAll("SELECT * FROM vaccinations WHERE patient_id = ? ORDER BY administered_date DESC", [$id]);
$allergies_list = $db->fetchAll("SELECT * FROM patient_allergies WHERE patient_id = ?", [$id]);
$recentOrders = $db->fetchAll("SELECT o.*, s.name as ordered_by_name FROM orders o JOIN staff s ON o.ordered_by = s.id WHERE o.patient_id = ? ORDER BY o.ordered_at DESC LIMIT 10", [$id]);
$prescriptions = $db->fetchAll("SELECT pr.*, s.name as vet_name FROM prescriptions pr JOIN staff s ON pr.prescribed_by = s.id WHERE pr.patient_id = ? AND pr.is_active = 1 ORDER BY pr.created_at DESC", [$id]);
$nursing_records = $db->fetchAll("
    SELECT nr.*, s.name as nurse_name 
    FROM nursing_records nr 
    JOIN staff s ON nr.nurse_id = s.id 
    WHERE nr.patient_id = ? 
    ORDER BY nr.created_at DESC LIMIT 20
", [$id]);
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <a href="?page=patients" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>患畜一覧</a>
            <h4 class="fw-bold mt-1 mb-0"><?= h($patient['name']) ?> <small class="text-muted"><?= h($patient['patient_code']) ?></small></h4>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="?page=record_form&patient_id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規カルテ</a>
            <a href="?page=order_form&patient_id=<?= $id ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-list-check me-1"></i>オーダー</a>
            <a href="?page=documents&patient_id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-text me-1"></i>書類</a>
            <a href="?page=patient_form&id=<?= $id ?>" class="btn btn-outline-warning btn-sm"><i class="bi bi-pencil me-1"></i>編集</a>
        </div>
    </div>

    <!-- 基本情報カード -->
    <div class="patient-info-card mb-3">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="patient-photo">
                    <?php
                    $icons = ['dog'=>'🐕','cat'=>'🐈','rabbit'=>'🐇','hamster'=>'🐹','bird'=>'🐦','ferret'=>'🦊','turtle'=>'🐢','guinea_pig'=>'🐹','hedgehog'=>'🦔','snake'=>'🐍','lizard'=>'🦎','fish'=>'🐟'];
                    echo $icons[$patient['species']] ?? '🐾';
                    ?>
                </div>
            </div>
            <div class="col">
                <div class="row g-2">
                    <div class="col-6 col-md-3"><small class="text-muted d-block">種別</small><strong><?= h(getSpeciesName($patient['species'])) ?></strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">品種</small><strong><?= h($patient['breed']) ?></strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">性別</small><strong><?= h(getSexName($patient['sex'])) ?></strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">年齢</small><strong><?= calculateAge($patient['birthdate']) ?></strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">体重</small><strong><?= $patient['weight'] ? $patient['weight'] . 'kg' : '-' ?></strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">毛色</small><strong><?= h($patient['color'] ?: '-') ?></strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">マイクロチップ</small><strong><?= h($patient['microchip_id'] ?: '-') ?></strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">血液型</small><strong><?= h($patient['blood_type'] ?: '-') ?></strong></div>
                </div>
            </div>
        </div>

        <?php if ($patient['allergies'] || $patient['chronic_conditions']): ?>
        <div class="mt-3 pt-3 border-top border-info">
            <?php if ($patient['allergies']): ?>
                <span class="allergy-tag"><i class="bi bi-exclamation-triangle me-1"></i>アレルギー: <?= h($patient['allergies']) ?></span>
            <?php endif; ?>
            <?php if ($patient['chronic_conditions']): ?>
                <span class="chronic-tag ms-1"><i class="bi bi-heart-pulse me-1"></i><?= h($patient['chronic_conditions']) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($patient['insurance_company']): ?>
        <div class="mt-2">
            <small class="text-muted"><i class="bi bi-shield-check me-1"></i>保険: <?= h($patient['insurance_company']) ?> (<?= h($patient['insurance_number']) ?>) <?= $patient['insurance_rate'] ?>%負担</small>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($activeAdmission): ?>
    <div class="alert alert-warning py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-hospital me-2"></i><strong>現在入院中</strong> - <?= h($activeAdmission['ward']) ?> <?= h($activeAdmission['cage_number']) ?> (<?= formatDate($activeAdmission['admission_date'], 'm/d') ?>〜)</span>
        <a href="?page=temperature_chart&admission_id=<?= $activeAdmission['id'] ?>" class="btn btn-warning btn-sm">温度板を見る</a>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <!-- 診察記録タブ -->
            <div class="card">
                <div class="card-header"><i class="bi bi-journal-medical me-2"></i>診察記録</div>
                <div class="card-body p-0">
                    <?php if (empty($records)): ?>
                        <div class="text-center text-muted py-4">診察記録がありません</div>
                    <?php else: ?>
                    <?php foreach ($records as $rec): ?>
                    <div class="p-3 border-bottom" style="cursor:pointer;" onclick="location.href='?page=medical_record&id=<?= $rec['id'] ?>'">
                        <div class="d-flex justify-content-between mb-2">
                            <div>
                                <strong><?= formatDate($rec['visit_date']) ?></strong>
                                <span class="badge bg-<?= $rec['visit_type'] === 'admission' ? 'warning' : 'info' ?> ms-1">
                                    <?= $rec['visit_type'] === 'admission' ? '入院' : ($rec['visit_type'] === 'emergency' ? '救急' : '外来') ?>
                                </span>
                            </div>
                            <small class="text-muted"><?= h($rec['vet_name']) ?></small>
                        </div>
                        <?php if ($rec['diagnosis_name']): ?>
                        <div class="mb-1"><span class="badge bg-dark"><?= h($rec['diagnosis_name']) ?></span></div>
                        <?php endif; ?>
                        <div class="soap-section soap-s mb-1"><small><strong>S:</strong> <?= h(mb_substr($rec['subjective'], 0, 100)) ?></small></div>
                        <div class="soap-section soap-a mb-0"><small><strong>A:</strong> <?= h(mb_substr($rec['assessment'], 0, 100)) ?></small></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>


            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-journal-text me-2"></i>看護記録（直近20件）</span>
                    <a href="?page=nursing_record_form&patient_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-lg"></i> 記録追加
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($nursing_records)): ?>
                        <div class="text-center text-muted py-3 small">記録はありません</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                        <?php foreach ($nursing_records as $nr): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between mb-1">
                                    <small class="text-muted">
                                        <?= date('Y/m/d H:i', strtotime($nr['created_at'])) ?>
                                        <span class="ms-2 badge bg-light text-dark border"><?= h($nr['nurse_name']) ?></span>
                                    </small>
                                    <?php
                                    $bg = match($nr['record_type']) {
                                        'observation' => 'info',
                                        'care' => 'success',
                                        'report' => 'warning text-dark',
                                        default => 'secondary'
                                    };
                                    $label = match($nr['record_type']) {
                                        'observation' => '観察',
                                        'care' => 'ケア',
                                        'report' => '報告',
                                        default => 'その他'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $bg ?>"><?= $label ?></span>
                                </div>
                                <p class="mb-1 small" style="white-space: pre-wrap;"><?= h($nr['content']) ?></p>
                                <?php if ($nr['priority'] === 'high'): ?>
                                    <small class="text-danger"><i class="bi bi-exclamation-circle-fill"></i> 重要事項</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center p-1">
                    <a href="?page=nursing&patient_id=<?= $id ?>" class="text-decoration-none small text-muted">看護管理画面で全て見る &raquo;</a>
                </div>
            </div>
            <div class="card">
            <!-- オーダー履歴 -->
            <div class="card">
                <div class="card-header"><i class="bi bi-list-check me-2"></i>最近のオーダー</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>日時</th><th>種別</th><th>内容</th><th>状態</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentOrders as $ord): ?>
                                <tr>
                                    <td><?= formatDate($ord['ordered_at'], 'm/d H:i') ?></td>
                                    <td><span class="badge bg-<?= $ord['order_type']==='prescription'?'success':($ord['order_type']==='test'?'info':'warning') ?>"><?= h($ord['order_type']==='prescription'?'処方':($ord['order_type']==='test'?'検査':'処置')) ?></span></td>
                                    <td><?= h($ord['order_name']) ?></td>
                                    <td><?= getOrderStatusBadge($ord['status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- 飼い主情報 -->
            <div class="card">
                <div class="card-header"><i class="bi bi-person me-2"></i>飼い主情報</div>
                <div class="card-body">
                    <p class="mb-1"><strong><?= h($patient['owner_name']) ?></strong> <small class="text-muted"><?= h($patient['owner_code']) ?></small></p>
                    <p class="mb-1"><i class="bi bi-telephone me-1"></i><?= h($patient['owner_phone']) ?></p>
                    <?php if ($patient['owner_email']): ?>
                    <p class="mb-1"><i class="bi bi-envelope me-1"></i><?= h($patient['owner_email']) ?></p>
                    <?php endif; ?>
                    <p class="mb-0"><i class="bi bi-geo-alt me-1"></i><?= h($patient['owner_address']) ?></p>
                </div>
            </div>

            <!-- 現在の処方 -->
            <?php if (!empty($prescriptions)): ?>
            <div class="card">
                <div class="card-header"><i class="bi bi-capsule me-2"></i>現在の処方</div>
                <div class="card-body p-0">
                    <?php foreach ($prescriptions as $rx): ?>
                    <div class="p-2 border-bottom">
                        <strong class="d-block"><?= h($rx['drug_name']) ?></strong>
                        <small class="text-muted"><?= h($rx['dosage']) ?> <?= h($rx['frequency']) ?> <?= h($rx['duration']) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ワクチン接種歴 -->
            <div class="card">
                <div class="card-header"><i class="bi bi-shield-plus me-2"></i>ワクチン接種歴</div>
                <div class="card-body p-0">
                    <?php if (empty($vaccines)): ?>
                        <div class="text-center text-muted py-3">接種記録なし</div>
                    <?php else: ?>
                        <?php foreach ($vaccines as $vac): ?>
                        <div class="p-2 border-bottom">
                            <strong class="d-block"><?= h($vac['vaccine_name']) ?></strong>
                            <small class="text-muted">接種日: <?= formatDate($vac['administered_date']) ?>
                            <?php if ($vac['next_due_date']): ?> | 次回: <?= formatDate($vac['next_due_date']) ?><?php endif; ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
