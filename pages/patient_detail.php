<?php
/** 患畜詳細 - 検査結果表示強化・UI改善 */
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
$nursing_records = $db->fetchAll("SELECT nr.*, s.name as nurse_name FROM nursing_records nr JOIN staff s ON nr.nurse_id = s.id WHERE nr.patient_id = ? ORDER BY nr.created_at DESC LIMIT 10", [$id]);
$weightHistory = $db->fetchAll("SELECT * FROM weight_history WHERE patient_id = ? ORDER BY measured_at DESC LIMIT 5", [$id]);

$insuranceEnabled = getSetting('feature_insurance', '1') === '1';
$insurancePolicy = null;
if ($insuranceEnabled) {
    $insurancePolicy = $db->fetch("SELECT ip.*, im.company_name as master_company FROM insurance_policies ip LEFT JOIN insurance_master im ON ip.company_name = im.company_name WHERE ip.patient_id = ? AND ip.status = 'active' LIMIT 1", [$id]);
}

// Lab results - show ALL, not just 5
$recentLabs = $db->fetchAll("SELECT * FROM lab_results WHERE patient_id = ? ORDER BY tested_at DESC LIMIT 30", [$id]);
$visitStats = $db->fetch("SELECT COUNT(*) as total_visits, MAX(visit_date) as last_visit FROM medical_records WHERE patient_id = ?", [$id]);

// Patient images
$patientImages = [];
try {
    $patientImages = $db->fetchAll("SELECT * FROM patient_images WHERE patient_id = ? ORDER BY is_profile DESC, created_at DESC LIMIT 4", [$id]);
} catch (Exception $e) {}

$hospitalName = getSetting('hospital_name', APP_NAME);
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <a href="?page=patients" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>患畜一覧</a>
            <h4 class="fw-bold mt-1 mb-0"><?= h($patient['name']) ?> <small class="text-muted"><?= h($patient['patient_code']) ?></small></h4>
            <small class="text-muted">
                来院回数: <?= $visitStats['total_visits'] ?>回
                <?php if ($visitStats['last_visit']): ?> | 最終来院: <?= formatDate($visitStats['last_visit']) ?><?php endif; ?>
            </small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="?page=record_form&patient_id=<?= $id ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>新規カルテ</a>
            <a href="?page=order_form&patient_id=<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-list-check me-1"></i>オーダー</a>
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots me-1"></i>その他
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item py-2" href="?page=documents&patient_id=<?= $id ?>"><i class="bi bi-file-text me-2"></i>書類作成</a></li>
                    <li><a class="dropdown-item py-2" href="?page=weight_history&patient_id=<?= $id ?>"><i class="bi bi-graph-up-arrow me-2"></i>体重管理</a></li>
                    <li><a class="dropdown-item py-2" href="?page=patient_images&patient_id=<?= $id ?>"><i class="bi bi-images me-2"></i>画像管理</a></li>
                    <li><a class="dropdown-item py-2" href="?page=lab_results&patient_id=<?= $id ?>"><i class="bi bi-graph-up me-2"></i>検査結果</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2" href="?page=referral_form&patient_id=<?= $id ?>"><i class="bi bi-envelope-paper me-2"></i>紹介状作成</a></li>
                    <li><a class="dropdown-item py-2" href="?page=consent_form&action=form&patient_id=<?= $id ?>"><i class="bi bi-file-earmark-check me-2"></i>同意書作成</a></li>
                    <li><a class="dropdown-item py-2" href="?page=invoice_form&patient_id=<?= $id ?>"><i class="bi bi-receipt me-2"></i>会計作成</a></li>
                    <li><a class="dropdown-item py-2" href="?page=estimate_form&patient_id=<?= $id ?>"><i class="bi bi-calculator me-2"></i>見積もり作成</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2" href="?page=patient_form&id=<?= $id ?>"><i class="bi bi-pencil me-2"></i>患畜情報編集</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Patient Info Card -->
    <div class="patient-info-card mb-3">
        <div class="row align-items-center">
            <div class="col-auto">
                <div class="patient-photo">
                    <?php
                    $icons = ['dog'=>'&#128021;','cat'=>'&#128008;','rabbit'=>'&#128007;','hamster'=>'&#128057;','bird'=>'&#128038;','ferret'=>'&#129418;','turtle'=>'&#128034;','guinea_pig'=>'&#128057;','hedgehog'=>'&#129428;','snake'=>'&#128013;','lizard'=>'&#129422;','fish'=>'&#128031;'];
                    echo $icons[$patient['species']] ?? '&#128062;';
                    ?>
                </div>
            </div>
            <div class="col">
                <div class="row g-2">
                    <div class="col-6 col-md-3"><small class="text-muted d-block">種別</small><strong><?= h(getSpeciesName($patient['species'])) ?></strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">品種</small><strong><?= h($patient['breed']) ?></strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">性別</small><strong><?= h(getSexName($patient['sex'])) ?></strong></div>
                    <div class="col-6 col-md-3"><small class="text-muted d-block">年齢</small><strong><?= calculateAge($patient['birthdate']) ?></strong></div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block">体重</small>
                        <strong>
                            <?= $patient['weight'] ? $patient['weight'] . 'kg' : '-' ?>
                            <a href="?page=weight_history&patient_id=<?= $id ?>" class="text-decoration-none ms-1" title="体重推移"><i class="bi bi-graph-up-arrow text-info"></i></a>
                        </strong>
                    </div>
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

        <?php if ($insuranceEnabled && ($patient['insurance_company'] || $insurancePolicy)): ?>
        <div class="mt-2 p-2 bg-light rounded">
            <i class="bi bi-shield-check me-1 text-info"></i>
            <strong>保険:</strong>
            <?= h($insurancePolicy['company_name'] ?? $patient['insurance_company']) ?>
            <?php if ($insurancePolicy): ?>
                (証券: <?= h($insurancePolicy['policy_number']) ?> / <?= $insurancePolicy['coverage_rate'] ?>%補償)
                <a href="?page=insurance_claims&patient_id=<?= $id ?>" class="ms-2 text-decoration-none"><i class="bi bi-file-earmark-medical"></i> レセプト</a>
            <?php else: ?>
                (<?= h($patient['insurance_number']) ?>) <?= $patient['insurance_rate'] ?>%補償
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($activeAdmission): ?>
    <div class="alert alert-warning py-2 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-hospital me-2"></i><strong>現在入院中</strong> - <?= h($activeAdmission['ward']) ?> <?= h($activeAdmission['cage_number']) ?> (<?= formatDate($activeAdmission['admission_date'], 'm/d') ?>~)</span>
        <div class="d-flex gap-2">
            <a href="?page=temperature_chart&admission_id=<?= $activeAdmission['id'] ?>" class="btn btn-warning btn-sm">温度板</a>
            <a href="?page=discharge_summary&admission_id=<?= $activeAdmission['id'] ?>" class="btn btn-outline-warning btn-sm">退院サマリー</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <!-- Medical Records -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-journal-medical me-2"></i>診察記録</span>
                    <a href="?page=record_form&patient_id=<?= $id ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>新規</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($records)): ?>
                        <div class="text-center text-muted py-4">診察記録がありません</div>
                    <?php else: ?>
                    <?php foreach ($records as $rec): ?>
                    <a href="?page=medical_record&id=<?= $rec['id'] ?>" class="d-block p-3 border-bottom text-decoration-none text-body" style="transition:background 0.2s;" onmouseover="this.style.background='var(--vc-primary-50)'" onmouseout="this.style.background=''">
                        <div class="d-flex justify-content-between mb-1">
                            <div>
                                <strong><?= formatDate($rec['visit_date']) ?></strong>
                                <span class="badge bg-<?= $rec['visit_type'] === 'admission' ? 'warning' : ($rec['visit_type'] === 'emergency' ? 'danger' : 'info') ?> ms-1">
                                    <?= $rec['visit_type'] === 'admission' ? '入院' : ($rec['visit_type'] === 'emergency' ? '救急' : '外来') ?>
                                </span>
                            </div>
                            <small class="text-muted"><i class="bi bi-person me-1"></i><?= h($rec['vet_name']) ?></small>
                        </div>
                        <?php if ($rec['diagnosis_name']): ?>
                        <div class="mb-1"><span class="badge bg-dark"><?= h($rec['diagnosis_name']) ?></span></div>
                        <?php endif; ?>
                        <div class="soap-section soap-s mb-1 py-1 px-2"><small><strong>S:</strong> <?= h(mb_substr($rec['subjective'], 0, 100)) ?></small></div>
                        <div class="soap-section soap-a mb-0 py-1 px-2"><small><strong>A:</strong> <?= h(mb_substr($rec['assessment'], 0, 100)) ?></small></div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lab Results - Full display with print -->
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-graph-up me-2"></i>検査結果</span>
                    <div class="d-flex gap-2">
                        <?php if (!empty($recentLabs)): ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="printLabResult('patient-labs')"><i class="bi bi-printer me-1"></i>印刷</button>
                        <?php endif; ?>
                        <a href="?page=lab_results&patient_id=<?= $id ?>" class="btn btn-sm btn-outline-info">全て見る</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentLabs)): ?>
                    <div class="text-center text-muted py-3">検査結果がありません</div>
                    <?php else: ?>
                    <?php
                    $labGrouped = [];
                    foreach ($recentLabs as $l) {
                        $ldate = date('Y-m-d', strtotime($l['tested_at']));
                        $labGrouped[$ldate][] = $l;
                    }
                    foreach ($labGrouped as $ldate => $litems): ?>
                    <div class="px-3 py-2 bg-light border-bottom">
                        <strong><i class="bi bi-calendar3 me-1"></i><?= formatDate($ldate) ?></strong>
                        <small class="text-muted ms-2">(<?= count($litems) ?>項目)</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>検査項目</th><th class="text-end">結果</th><th>単位</th><th>基準値</th><th class="text-center">判定</th></tr></thead>
                            <tbody>
                            <?php foreach ($litems as $lab): ?>
                            <tr class="<?= $lab['is_abnormal'] ? 'table-danger' : '' ?>">
                                <td class="fw-bold"><?= h($lab['test_name']) ?></td>
                                <td class="text-end"><strong class="<?= $lab['is_abnormal'] ? 'text-danger' : '' ?>"><?= h($lab['result_value']) ?></strong></td>
                                <td><small><?= h($lab['unit']) ?></small></td>
                                <td><small class="text-muted"><?= $lab['reference_min'] ? $lab['reference_min'].'-'.$lab['reference_max'] : ($lab['reference_low'] ? $lab['reference_low'].'-'.$lab['reference_high'] : '-') ?></small></td>
                                <td class="text-center">
                                    <?php if ($lab['is_abnormal']): ?>
                                    <span class="badge bg-danger">異常</span>
                                    <?php else: ?>
                                    <span class="badge bg-success">正常</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hidden print content for lab results -->
            <?php if (!empty($recentLabs)): ?>
            <div id="print-content-patient-labs" style="display:none;">
                <div style="font-family:'Yu Gothic','Hiragino Sans',serif; font-size:11pt; line-height:1.8; padding:15mm;">
                    <div style="text-align:center; margin-bottom:20px;">
                        <h2 style="margin:0; font-size:16pt;"><?= h($hospitalName) ?></h2>
                        <h3 style="margin:8px 0 0; font-size:14pt; letter-spacing:0.2em;">検査結果報告書</h3>
                    </div>
                    <table style="width:100%; margin-bottom:15px; border-collapse:collapse;">
                        <tr>
                            <td style="padding:4px 8px; border:1px solid #333; background:#f5f5f5; width:120px; font-weight:bold;">患畜名</td>
                            <td style="padding:4px 8px; border:1px solid #333;"><?= h($patient['name']) ?> (<?= h($patient['patient_code']) ?>)</td>
                            <td style="padding:4px 8px; border:1px solid #333; background:#f5f5f5; width:120px; font-weight:bold;">飼い主名</td>
                            <td style="padding:4px 8px; border:1px solid #333;"><?= h($patient['owner_name']) ?></td>
                        </tr>
                    </table>
                    <?php foreach ($labGrouped as $ldate => $litems): ?>
                    <h4 style="font-size:12pt; margin:15px 0 5px;">検査日: <?= formatDate($ldate) ?></h4>
                    <table style="width:100%; border-collapse:collapse;">
                        <thead><tr style="background:#e8e8e8;"><th style="padding:6px 8px; border:1px solid #333;">検査項目</th><th style="padding:6px 8px; border:1px solid #333; text-align:right;">結果</th><th style="padding:6px 8px; border:1px solid #333;">単位</th><th style="padding:6px 8px; border:1px solid #333;">基準値</th><th style="padding:6px 8px; border:1px solid #333; text-align:center;">判定</th></tr></thead>
                        <tbody>
                        <?php foreach ($litems as $lab): ?>
                        <tr style="<?= $lab['is_abnormal']?'background:#fff0f0;':'' ?>">
                            <td style="padding:4px 8px; border:1px solid #333; font-weight:bold;"><?= h($lab['test_name']) ?></td>
                            <td style="padding:4px 8px; border:1px solid #333; text-align:right; font-weight:bold; <?= $lab['is_abnormal']?'color:#c00;':'' ?>"><?= h($lab['result_value']) ?></td>
                            <td style="padding:4px 8px; border:1px solid #333;"><?= h($lab['unit']) ?></td>
                            <td style="padding:4px 8px; border:1px solid #333;"><?= $lab['reference_min'] ? $lab['reference_min'].'-'.$lab['reference_max'] : ($lab['reference_low'] ? $lab['reference_low'].'-'.$lab['reference_high'] : '') ?></td>
                            <td style="padding:4px 8px; border:1px solid #333; text-align:center; <?= $lab['is_abnormal']?'color:#c00; font-weight:bold;':'' ?>"><?= $lab['is_abnormal']?'異常':'正常' ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endforeach; ?>
                    <div style="margin-top:20px; text-align:right; font-size:10pt; color:#666;">印刷日: <?= date('Y年m月d日') ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Orders -->
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-2"></i>最近のオーダー</span>
                    <a href="?page=order_form&patient_id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>新規</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentOrders)): ?>
                    <div class="text-center text-muted py-3">オーダーなし</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>日時</th><th>種別</th><th>内容</th><th>状態</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentOrders as $ord): ?>
                            <tr>
                                <td><?= formatDate($ord['ordered_at'], 'm/d H:i') ?></td>
                                <td><span class="badge bg-<?= $ord['order_type']==='prescription'?'success':($ord['order_type']==='test'?'info':'warning') ?>"><?= $ord['order_type']==='prescription'?'処方':($ord['order_type']==='test'?'検査':'処置') ?></span></td>
                                <td><?= h($ord['order_name']) ?></td>
                                <td><?= getOrderStatusBadge($ord['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Nursing Records -->
            <?php if (!empty($nursing_records)): ?>
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-journal-text me-2"></i>看護記録</span>
                    <a href="?page=nursing&patient_id=<?= $id ?>" class="btn btn-sm btn-outline-primary">全て見る</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                    <?php foreach (array_slice($nursing_records, 0, 5) as $nr): ?>
                        <div class="list-group-item py-2">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted"><?= date('m/d H:i', strtotime($nr['created_at'])) ?> - <?= h($nr['nurse_name']) ?></small>
                                <?php
                                $label = match($nr['record_type']) { 'observation'=>'観察','care'=>'ケア','report'=>'報告',default=>'その他' };
                                $bg = match($nr['record_type']) { 'observation'=>'info','care'=>'success','report'=>'warning text-dark',default=>'secondary' };
                                ?>
                                <span class="badge bg-<?= $bg ?>"><?= $label ?></span>
                            </div>
                            <small style="white-space:pre-wrap;"><?= h(mb_substr($nr['content'],0,100)) ?></small>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Owner Info -->
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

            <!-- Weight History Mini -->
            <?php if (!empty($weightHistory)): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-graph-up-arrow me-2"></i>体重推移</span>
                    <a href="?page=weight_history&patient_id=<?= $id ?>" class="btn btn-sm btn-outline-info">詳細</a>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($weightHistory as $wh): ?>
                    <div class="p-2 border-bottom d-flex justify-content-between">
                        <small><?= formatDate($wh['measured_at']) ?></small>
                        <strong><?= $wh['weight'] ?>kg</strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Current Prescriptions -->
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

            <!-- Vaccinations -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-shield-plus me-2"></i>ワクチン接種歴</span>
                    <a href="?page=vaccinations&patient_id=<?= $id ?>" class="btn btn-sm btn-outline-success">追加</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($vaccines)): ?>
                        <div class="text-center text-muted py-3">接種記録なし</div>
                    <?php else: ?>
                        <?php foreach ($vaccines as $vac): ?>
                        <div class="p-2 border-bottom">
                            <strong class="d-block"><?= h($vac['vaccine_name']) ?></strong>
                            <small class="text-muted">
                                接種日: <?= formatDate($vac['administered_date']) ?>
                                <?php if ($vac['next_due_date']): ?>
                                    | 次回: <span class="<?= strtotime($vac['next_due_date']) < time() ? 'text-danger fw-bold' : '' ?>"><?= formatDate($vac['next_due_date']) ?></span>
                                <?php endif; ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header"><i class="bi bi-lightning me-2"></i>クイック操作</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="?page=record_form&patient_id=<?= $id ?>" class="btn btn-glass text-start">
                            <i class="bi bi-journal-plus text-primary me-2"></i>新規カルテ作成
                        </a>
                        <a href="?page=lab_results&patient_id=<?= $id ?>" class="btn btn-glass text-start">
                            <i class="bi bi-graph-up text-info me-2"></i>検査結果一覧
                        </a>
                        <a href="?page=weight_history&patient_id=<?= $id ?>" class="btn btn-glass text-start">
                            <i class="bi bi-graph-up-arrow text-info me-2"></i>体重記録
                        </a>
                        <a href="?page=invoice_form&patient_id=<?= $id ?>" class="btn btn-glass text-start">
                            <i class="bi bi-receipt text-success me-2"></i>会計作成
                        </a>
                        <a href="?page=referral_form&patient_id=<?= $id ?>" class="btn btn-glass text-start">
                            <i class="bi bi-envelope-paper text-warning me-2"></i>紹介状作成
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printLabResult(groupId) {
    const content = document.getElementById('print-content-' + groupId);
    if (!content) return;
    const printWindow = window.open('', '_blank');
    printWindow.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>検査結果報告書</title><style>@media print { body { margin:0; } }</style></head><body>' + content.innerHTML + '</body></html>');
    printWindow.document.close();
    printWindow.onload = function() { printWindow.print(); };
}
</script>
