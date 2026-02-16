<?php
/** 書類作成・管理  */
$patient_id = (int)($_GET['patient_id'] ?? 0);
$record_id = (int)($_GET['record_id'] ?? 0);
$edit_id = (int)($_GET['edit_id'] ?? 0);
$copy_id = (int)($_GET['copy_id'] ?? 0);

// ▼ データ取得
$patients_list = $db->fetchAll("SELECT id, name, patient_code FROM patients WHERE is_active=1 ORDER BY name");
$patient = $patient_id ? $db->fetch("SELECT p.*, o.name as owner_name FROM patients p JOIN owners o ON p.owner_id=o.id WHERE p.id=?", [$patient_id]) : null;
$record = $record_id ? $db->fetch("SELECT * FROM medical_records WHERE id=?", [$record_id]) : null;
$initial_data = [];
$form_action = 'create';
$target_doc_id = 0;

if ($edit_id || $copy_id) {
    $target_id = $edit_id ?: $copy_id;
    $doc = $db->fetch("SELECT * FROM issued_documents WHERE id = ?", [$target_id]);
    if ($doc) {
        $initial_data = json_decode($doc['content'], true);
        $patient_id = $doc['patient_id'];
        $record_id = $doc['record_id'];
        $patient = $db->fetch("SELECT p.*, o.name as owner_name FROM patients p JOIN owners o ON p.owner_id=o.id WHERE p.id=?", [$patient_id]);
        
        if ($edit_id) {
            $form_action = 'update';
            $target_doc_id = $edit_id;
        }
    }
}

// ▼ 病院情報のデフォルト値
$def_hosp_name = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'hospital_name'")['setting_value'] ?? 'ベットケア動物病院';
$def_hosp_addr = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'hospital_address'")['setting_value'] ?? '';
$def_hosp_phone = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'hospital_phone'")['setting_value'] ?? '';

// ▼ POST処理 (保存・更新)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docType = $_POST['document_type'];
    $patId = (int)$_POST['patient_id'];
    $recId = $_POST['record_id'] ?: null;
    
    $patInfo = $db->fetch("SELECT p.*, o.name as owner_name, o.address as owner_address FROM patients p JOIN owners o ON p.owner_id=o.id WHERE p.id=?", [$patId]);
    $customText = '';
    switch ($docType) {
        case 'prescription': $customText = $_POST['prescription_text'] ?? ''; break;
        case 'referral_letter': $customText = $_POST['referral_text'] ?? ''; break;
        case 'death_certificate': $customText = $_POST['death_text'] ?? ''; break;
        default: $customText = $_POST['diagnosis_text'] ?? ''; break; // 診断書・証明書など
    }

    // 保存データ構築
    $contentData = [
        'patient' => $patInfo,
        'hospital_name' => $_POST['hospital_name'],
        'hospital_address' => $_POST['hospital_address'],
        'hospital_phone' => $_POST['hospital_phone'],
        'vet_name' => $_POST['vet_name'] ?: $auth->currentUserName(),
        
        // 統合した本文データ
        'custom_text' => $customText,
        'notes' => $_POST['notes'] ?? '',
        
        // 各種フィールド
        'diagnosis' => $_POST['diagnosis'] ?? '',
        'referral_to_hospital' => $_POST['referral_to_hospital'] ?? '',
        'referral_to_vet' => $_POST['referral_to_vet'] ?? '',
        'purpose' => $_POST['purpose'] ?? '',
        'clinical_course' => $_POST['clinical_course'] ?? '',
        'medication' => $_POST['medication'] ?? '',
        'death_date' => $_POST['death_date'] ?? '',
        'death_place' => $_POST['death_place'] ?? '',
        'death_cause' => $_POST['death_cause'] ?? '',
    ];

    $jsonContent = json_encode($contentData, JSON_UNESCAPED_UNICODE);
    $currentDate = date('Y-m-d');

    if ($_POST['form_action'] === 'update' && $_POST['target_doc_id']) {
        // 更新
        $db->update('issued_documents', [
            'document_type' => $docType,
            'patient_id' => $patId,
            'record_id' => $recId,
            'content' => $jsonContent,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id=?', [$_POST['target_doc_id']]);
        $newId = $_POST['target_doc_id'];
    } else {
        // 新規作成
        $docNum = strtoupper(substr($docType,0,3)) . '-' . date('Ymd') . '-' . str_pad(mt_rand(1,999),3,'0',STR_PAD_LEFT);
        $newId = $db->insert('issued_documents', [
            'document_type' => $docType,
            'document_number' => $docNum,
            'patient_id' => $patId,
            'record_id' => $recId,
            'issued_by' => $auth->currentUserId(),
            'issued_date' => $currentDate,
            'content' => $jsonContent,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    redirect("?page=document_print&id={$newId}");
}

// 履歴リスト取得
$history = $db->fetchAll("SELECT d.*, p.name as pname, s.name as staff_name 
                          FROM issued_documents d 
                          JOIN patients p ON d.patient_id=p.id 
                          LEFT JOIN staff s ON d.issued_by=s.id 
                          ORDER BY d.created_at DESC LIMIT 20");
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2"></i>書類作成・管理</h4>
        <a href="?page=documents" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise"></i> リセット</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-primary h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-pencil-square me-2"></i><?= $edit_id ? '書類を編集' : '新規書類作成' ?></span>
                    <?php if($copy_id): ?><span class="badge bg-warning text-dark">複製モード</span><?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST" id="docForm">
                        <input type="hidden" name="form_action" value="<?= $form_action ?>">
                        <input type="hidden" name="target_doc_id" value="<?= $target_doc_id ?>">
                        <input type="hidden" name="record_id" value="<?= $record_id ?>">
                        <?= csrf_field() ?>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label required">書類タイプ</label>
                                <select name="document_type" id="docType" class="form-select fw-bold" required onchange="updateFormLayout()">
                                    <?php
                                    $types = [
                                        'prescription' => '処方箋',
                                        'referral_letter' => '診療情報提供書 (紹介状)',
                                        'diagnosis_certificate' => '診断書',
                                        'vaccination_certificate' => 'ワクチン接種証明書',
                                        'health_certificate' => '健康診断書',
                                        'death_certificate' => '死亡診断書',
                                        'insurance_claim' => '診療明細書'
                                    ];
                                    $curType = $initial_data['document_type'] ?? ($_GET['type'] ?? 'prescription');
                                    foreach($types as $k => $v) {
                                        $sel = ($k === $curType) ? 'selected' : '';
                                        echo "<option value=\"$k\" $sel>$v</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">患畜選択</label>
                                <select name="patient_id" class="form-select" required>
                                    <option value="">-- 選択してください --</option>
                                    <?php foreach ($patients_list as $pt): ?>
                                    <option value="<?= $pt['id'] ?>" <?= $patient_id==$pt['id']?'selected':'' ?>><?= h($pt['name']) ?> (<?= h($pt['patient_code']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="border rounded p-3 bg-light mb-3">
                            
                            <div id="area_prescription" class="dynamic-area d-none">
                                <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-capsule me-2"></i>処方箋 内容</h6>
                                <div class="mb-3">
                                    <label class="form-label">処方内容 (薬品名・用量・用法)</label>
                                    <textarea name="prescription_text" class="form-control" rows="8" placeholder="例:&#13;&#10;アモキシシリン錠 250mg&#13;&#10;1回1錠 1日2回 7日分"><?= h($initial_data['custom_text'] ?? '') ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">備考</label>
                                    <input type="text" name="notes" class="form-control" value="<?= h($initial_data['notes'] ?? '') ?>" placeholder="飼い主様へのコメントなど">
                                </div>
                            </div>

                            <div id="area_referral" class="dynamic-area d-none">
                                <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-send me-2"></i>紹介状 詳細</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">紹介先医療機関</label>
                                        <input type="text" name="referral_to_hospital" class="form-control" value="<?= h($initial_data['referral_to_hospital'] ?? '') ?>" placeholder="〇〇動物病院 御中">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">紹介先担当医 (任意)</label>
                                        <input type="text" name="referral_to_vet" class="form-control" value="<?= h($initial_data['referral_to_vet'] ?? '') ?>" placeholder="〇〇 先生">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">傷病名</label>
                                        <input type="text" name="diagnosis" class="form-control" value="<?= h($initial_data['diagnosis'] ?? $record['diagnosis_name'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">紹介目的</label>
                                        <textarea name="purpose" class="form-control" rows="2" placeholder="例: 精査・加療のため"><?= h($initial_data['purpose'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">臨床経過・検査所見</label>
                                        <textarea name="clinical_course" class="form-control" rows="6"><?= h($initial_data['clinical_course'] ?? $initial_data['custom_text'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">現在の処方・備考</label>
                                        <textarea name="medication" class="form-control" rows="3"><?= h($initial_data['medication'] ?? '') ?></textarea>
                                    </div>
                                    <input type="hidden" name="referral_text" value=""> 
                                </div>
                            </div>

                            <div id="area_diagnosis" class="dynamic-area d-none">
                                <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="bi bi-file-medical me-2"></i>診断書・証明書 内容</h6>
                                <div class="mb-3">
                                    <label class="form-label">診断名 / 証明内容タイトル</label>
                                    <input type="text" name="diagnosis" class="form-control" value="<?= h($initial_data['diagnosis'] ?? $record['diagnosis_name'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">所見・詳細</label>
                                    <textarea name="diagnosis_text" class="form-control" rows="6"><?= h($initial_data['custom_text'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <div id="area_death" class="dynamic-area d-none">
                                <h6 class="border-bottom pb-2 mb-3 text-dark"><i class="bi bi-x-circle me-2"></i>死亡診断書 詳細</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">死亡日時</label>
                                        <input type="datetime-local" name="death_date" class="form-control" value="<?= h($initial_data['death_date'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">死亡場所</label>
                                        <select name="death_place" class="form-select">
                                            <?php $dp = $initial_data['death_place'] ?? ''; ?>
                                            <option value="hospital" <?= $dp=='hospital'?'selected':'' ?>>当院</option>
                                            <option value="home" <?= $dp=='home'?'selected':'' ?>>自宅</option>
                                            <option value="other" <?= $dp=='other'?'selected':'' ?>>その他</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">直接死因</label>
                                        <input type="text" name="death_cause" class="form-control" value="<?= h($initial_data['death_cause'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">備考</label>
                                        <textarea name="death_text" class="form-control" rows="3"><?= h($initial_data['custom_text'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="accordion mb-4" id="hospInfoAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHosp">
                                        <i class="bi bi-hospital me-2"></i>発行元情報の編集
                                    </button>
                                </h2>
                                <div id="collapseHosp" class="accordion-collapse collapse">
                                    <div class="accordion-body bg-light">
                                        <div class="row g-2">
                                            <div class="col-12">
                                                <label class="form-label small">病院名</label>
                                                <input type="text" name="hospital_name" class="form-control form-control-sm" value="<?= h($initial_data['hospital_name'] ?? $def_hosp_name) ?>">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small">住所</label>
                                                <input type="text" name="hospital_address" class="form-control form-control-sm" value="<?= h($initial_data['hospital_address'] ?? $def_hosp_addr) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">電話番号</label>
                                                <input type="text" name="hospital_phone" class="form-control form-control-sm" value="<?= h($initial_data['hospital_phone'] ?? $def_hosp_phone) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small">発行獣医師</label>
                                                <input type="text" name="vet_name" class="form-control form-control-sm" value="<?= h($initial_data['vet_name'] ?? $auth->currentUserName()) ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-lg px-5">
                                <i class="bi bi-printer me-2"></i><?= $edit_id ? '更新して印刷' : '作成して印刷' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">作成履歴</div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if(empty($history)): ?>
                            <div class="p-3 text-muted text-center">履歴はありません</div>
                        <?php else: ?>
                            <?php foreach($history as $h): 
                                $typeLabel = [
                                    'prescription' => '処方箋',
                                    'referral_letter' => '紹介状',
                                    'diagnosis_certificate' => '診断書',
                                    'death_certificate' => '死亡診断書'
                                ][$h['document_type']] ?? '書類';
                            ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between mb-1">
                                    <strong><?= h($typeLabel) ?></strong>
                                    <small class="text-muted"><?= formatDate($h['created_at']) ?></small>
                                </div>
                                <div class="mb-1 small">
                                    <i class="bi bi-person-circle"></i> <?= h($h['pname']) ?>
                                    <span class="ms-2 text-muted">by <?= h($h['staff_name']) ?></span>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <a href="?page=document_print&id=<?= $h['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark flex-grow-1">
                                        <i class="bi bi-printer"></i> 印刷
                                    </a>
                                    <a href="?page=documents&edit_id=<?= $h['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> 編集
                                    </a>
                                    <a href="?page=documents&copy_id=<?= $h['id'] ?>" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-files"></i> 複製
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 書類タイプに応じてフォームの表示を切り替える
function updateFormLayout() {
    const type = document.getElementById('docType').value;
    
    // 全エリアを非表示
    document.querySelectorAll('.dynamic-area').forEach(el => el.classList.add('d-none'));
    
    let targetId = 'area_diagnosis'; // デフォルト
    
    if (type === 'prescription') {
        targetId = 'area_prescription';
    } else if (type === 'referral_letter') {
        targetId = 'area_referral';
    } else if (type === 'death_certificate') {
        targetId = 'area_death';
    } else if (['diagnosis_certificate', 'vaccination_certificate', 'health_certificate'].includes(type)) {
        targetId = 'area_diagnosis';
    }
    
    // 対象エリアを表示
    const target = document.getElementById(targetId);
    if(target) target.classList.remove('d-none');
}

// 初期化
document.addEventListener('DOMContentLoaded', updateFormLayout);
</script>