<?php
/** 患畜登録・編集フォーム  **/
$id = (int)($_GET['id'] ?? 0);
$patient = $id ? $db->fetch("SELECT * FROM patients WHERE id = ?", [$id]) : null;
$owners = $db->fetchAll("SELECT id, name, owner_code FROM owners WHERE is_active = 1 ORDER BY name");
$insuranceCompanies = $db->fetchAll("SELECT * FROM insurance_master WHERE is_active = 1 ORDER BY company_name");

// Get existing insurance policies for this patient
$existingPolicies = $id ? $db->fetchAll("SELECT ip.*, im.company_name as master_name FROM insurance_policies ip LEFT JOIN insurance_master im ON ip.insurance_master_id = im.id WHERE ip.patient_id = ? ORDER BY ip.status DESC, ip.created_at DESC", [$id]) : [];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // カルテ番号の処理
    $pCode = trim($_POST['patient_code'] ?? '');
    
    // 重複チェック (新規登録時、または編集でコードが変更された場合)
    if ($pCode) {
        $existing = $db->fetch("SELECT id FROM patients WHERE patient_code = ? AND id != ?", [$pCode, $id]);
        if ($existing) {
            $error = "エラー: カルテNo '{$pCode}' は既に使用されています。";
        }
    } else {
        // 空の場合は自動生成 (新規のみ)
        if (!$id) {
            $pCode = 'PT-' . str_pad($db->count('patients') + 1, 4, '0', STR_PAD_LEFT);
        } else {
            // 編集で空にされた場合は元のコードを維持
            $pCode = $patient['patient_code'];
        }
    }

    if (!$error) {
        $data = [
            'patient_code' => $pCode,
            'owner_id' => (int)$_POST['owner_id'],
            'name' => trim($_POST['name']),
            'species' => $_POST['species'],
            'breed' => trim($_POST['breed'] ?? ''),
            'color' => trim($_POST['color'] ?? ''),
            'sex' => $_POST['sex'],
            'birthdate' => $_POST['birthdate'] ?: null,
            'weight' => $_POST['weight'] ?: null,
            'microchip_id' => trim($_POST['microchip_id'] ?? ''),
            'blood_type' => trim($_POST['blood_type'] ?? ''),
            'allergies' => trim($_POST['allergies'] ?? ''),
            'chronic_conditions' => trim($_POST['chronic_conditions'] ?? ''),
            'insurance_company' => trim($_POST['insurance_company'] ?? ''),
            'insurance_number' => trim($_POST['insurance_number'] ?? ''),
            'insurance_rate' => (int)($_POST['insurance_rate'] ?? 0),
            'notes' => trim($_POST['notes'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($id) {
            $db->update('patients', $data, 'id = ?', [$id]);
            $auth->logActivity($auth->currentUserId(), 'update_patient', "患畜更新: {$data['name']}", 'patient', $id);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = $db->insert('patients', $data);
            $auth->logActivity($auth->currentUserId(), 'create_patient', "患畜登録: {$data['name']}", 'patient', $id);
        }
        
        // Save insurance policy if provided
        $policyCompany = trim($_POST['policy_company'] ?? '');
        $policyNumber = trim($_POST['policy_number'] ?? '');
        if ($policyCompany && $policyNumber) {
            $existingPolicy = $db->fetch("SELECT id FROM insurance_policies WHERE patient_id = ? AND policy_number = ?", [$id, $policyNumber]);
            $policyData = [
                'patient_id' => $id,
                'insurance_master_id' => (int)($_POST['insurance_master_id'] ?? 0) ?: null,
                'company_name' => $policyCompany,
                'policy_number' => $policyNumber,
                'coverage_rate' => (int)($_POST['policy_coverage_rate'] ?? 50),
                'plan_name' => trim($_POST['plan_name'] ?? ''),
                'holder_name' => trim($_POST['holder_name'] ?? ''),
                'start_date' => $_POST['policy_start_date'] ?: null,
                'end_date' => $_POST['policy_end_date'] ?: null,
                'annual_limit' => (int)($_POST['annual_limit'] ?? 0),
                'status' => 'active',
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($existingPolicy) {
                $db->update('insurance_policies', $policyData, 'id = ?', [$existingPolicy['id']]);
            } else {
                $policyData['created_at'] = date('Y-m-d H:i:s');
                $db->insert('insurance_policies', $policyData);
            }
        }
        
        setFlash('success', ($data['name'] ?? '') . ' の情報を保存しました');
        redirect("?page=patient_detail&id={$id}");
    }
}
$p = $patient ?: [];
$ownerIdFromGet = (int)($_GET['owner_id'] ?? ($p['owner_id'] ?? 0));
?>

<div class="fade-in">
    <a href="?page=patients" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>患畜一覧</a>
    <h4 class="fw-bold mt-1 mb-3"><?= $id ? '患畜情報編集' : '新規患畜登録' ?></h4>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="card">
        <div class="card-body">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12 bg-light p-2 rounded border mb-2">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <label class="form-label">カルテNo (任意)</label>
                            <input type="text" name="patient_code" class="form-control" value="<?= h($p['patient_code'] ?? '') ?>" placeholder="自動生成 (空欄可)">
                        </div>
                        <div class="col-md-9">
                            <small class="text-muted">※紙カルテからの移行などで番号を指定したい場合は入力してください。空欄の場合は自動採番されます。</small>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label required">飼い主</label>
                    <select name="owner_id" class="form-select" required>
                        <option value="">選択してください</option>
                        <?php foreach ($owners as $ow): ?>
                        <option value="<?= $ow['id'] ?>" <?= $ownerIdFromGet == $ow['id'] ? 'selected' : '' ?>><?= h($ow['name']) ?> (<?= h($ow['owner_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small><a href="?page=owner_form" target="_blank">新しい飼い主を登録</a></small>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">患畜名</label>
                    <input type="text" name="name" class="form-control" value="<?= h($p['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">種別</label>
                    <select name="species" class="form-select" required>
                        <?php foreach (SPECIES_LIST as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['species'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">品種</label>
                    <input type="text" name="breed" class="form-control" value="<?= h($p['breed'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">毛色</label>
                    <input type="text" name="color" class="form-control" value="<?= h($p['color'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label required">性別</label>
                    <select name="sex" class="form-select" required>
                        <?php foreach (SEX_LIST as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($p['sex'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">生年月日</label>
                    <input type="text" name="birthdate" class="form-control datepicker" value="<?= h($p['birthdate'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">体重 (kg)</label>
                    <input type="number" name="weight" class="form-control" step="0.01" value="<?= h($p['weight'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">血液型</label>
                    <input type="text" name="blood_type" class="form-control" value="<?= h($p['blood_type'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">マイクロチップID</label>
                    <input type="text" name="microchip_id" class="form-control" value="<?= h($p['microchip_id'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="bi bi-exclamation-triangle text-danger me-1"></i>アレルギー</label>
                    <input type="text" name="allergies" class="form-control" value="<?= h($p['allergies'] ?? '') ?>" placeholder="薬品アレルギー等">
                </div>
                <div class="col-12">
                    <label class="form-label">既往歴・持病</label>
                    <textarea name="chronic_conditions" class="form-control" rows="2"><?= h($p['chronic_conditions'] ?? '') ?></textarea>
                </div>
                <div class="col-12"><hr class="my-2"><h6 class="fw-bold"><i class="bi bi-shield-check me-1 text-info"></i>ペット保険情報</h6></div>
                <div class="col-md-4">
                    <label class="form-label">保険会社</label>
                    <select name="insurance_company" class="form-select" id="insCompanySelect" onchange="fillInsuranceInfo(this)">
                        <option value="">未加入</option>
                        <?php foreach ($insuranceCompanies as $ic): ?>
                        <option value="<?= h($ic['company_name']) ?>" data-id="<?= $ic['id'] ?>" data-rates="<?= h($ic['coverage_rates']) ?>" <?= ($p['insurance_company'] ?? '') === $ic['company_name'] ? 'selected' : '' ?>>
                            <?= h($ic['company_name']) ?>
                        </option>
                        <?php endforeach; ?>
                        <option value="other" <?= ($p['insurance_company'] ?? '') && !in_array($p['insurance_company'] ?? '', array_column($insuranceCompanies, 'company_name')) ? 'selected' : '' ?>>その他（手入力）</option>
                    </select>
                    <input type="hidden" name="insurance_master_id" id="insMasterId" value="">
                </div>
                <div class="col-md-4">
                    <label class="form-label">保険証番号</label>
                    <input type="text" name="insurance_number" class="form-control" value="<?= h($p['insurance_number'] ?? '') ?>" placeholder="証券番号">
                </div>
                <div class="col-md-4">
                    <label class="form-label">保険負担率 (%)</label>
                    <select name="insurance_rate" class="form-select" id="insRateSelect">
                        <option value="0" <?= ($p['insurance_rate'] ?? 0) == 0 ? 'selected' : '' ?>>0% (未加入)</option>
                        <option value="50" <?= ($p['insurance_rate'] ?? 0) == 50 ? 'selected' : '' ?>>50%</option>
                        <option value="70" <?= ($p['insurance_rate'] ?? 0) == 70 ? 'selected' : '' ?>>70%</option>
                        <option value="90" <?= ($p['insurance_rate'] ?? 0) == 90 ? 'selected' : '' ?>>90%</option>
                        <option value="100" <?= ($p['insurance_rate'] ?? 0) == 100 ? 'selected' : '' ?>>100%</option>
                    </select>
                </div>

                <!-- 保険証券登録 (レセプト用) -->
                <div class="col-12 mt-2" id="policySection" style="<?= ($p['insurance_company'] ?? '') ? '' : 'display:none' ?>">
                    <div class="p-3 bg-light rounded border">
                        <h6 class="fw-bold mb-2"><i class="bi bi-file-earmark-text me-1"></i>保険証券情報（レセプト作成に必要）</h6>
                        <?php 
                        $activePolicy = !empty($existingPolicies) ? $existingPolicies[0] : [];
                        ?>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">証券番号（レセプト用）</label>
                                <input type="text" name="policy_number" class="form-control form-control-sm" value="<?= h($activePolicy['policy_number'] ?? ($p['insurance_number'] ?? '')) ?>" placeholder="証券番号">
                                <input type="hidden" name="policy_company" value="<?= h($activePolicy['company_name'] ?? ($p['insurance_company'] ?? '')) ?>" id="policyCompanyHidden">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">補償割合</label>
                                <select name="policy_coverage_rate" class="form-select form-select-sm">
                                    <option value="50" <?= ($activePolicy['coverage_rate'] ?? 50) == 50 ? 'selected' : '' ?>>50%</option>
                                    <option value="70" <?= ($activePolicy['coverage_rate'] ?? 50) == 70 ? 'selected' : '' ?>>70%</option>
                                    <option value="90" <?= ($activePolicy['coverage_rate'] ?? 50) == 90 ? 'selected' : '' ?>>90%</option>
                                    <option value="100" <?= ($activePolicy['coverage_rate'] ?? 50) == 100 ? 'selected' : '' ?>>100%</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">プラン名</label>
                                <input type="text" name="plan_name" class="form-control form-control-sm" value="<?= h($activePolicy['plan_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">契約者名</label>
                                <input type="text" name="holder_name" class="form-control form-control-sm" value="<?= h($activePolicy['holder_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">契約開始日</label>
                                <input type="text" name="policy_start_date" class="form-control form-control-sm datepicker" value="<?= h($activePolicy['start_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">契約終了日</label>
                                <input type="text" name="policy_end_date" class="form-control form-control-sm datepicker" value="<?= h($activePolicy['end_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">年間限度額</label>
                                <input type="number" name="annual_limit" class="form-control form-control-sm" value="<?= h($activePolicy['annual_limit'] ?? 0) ?>">
                            </div>
                        </div>
                        <?php if (!empty($existingPolicies)): ?>
                        <div class="mt-2">
                            <small class="text-success"><i class="bi bi-check-circle me-1"></i>登録済み保険証券: <?= count($existingPolicies) ?>件</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">備考</label>
                    <textarea name="notes" class="form-control" rows="2"><?= h($p['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="?page=patients" class="btn btn-secondary me-2">キャンセル</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $id ? '更新' : '登録' ?></button>
        </div>
    </form>
</div>

<script>
function fillInsuranceInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    const section = document.getElementById('policySection');
    const masterId = document.getElementById('insMasterId');
    const policyCompany = document.getElementById('policyCompanyHidden');
    
    if (sel.value && sel.value !== 'other') {
        section.style.display = '';
        masterId.value = opt.dataset.id || '';
        policyCompany.value = sel.value;
        
        // Update rate dropdown based on company rates
        const rates = (opt.dataset.rates || '50,70').split(',');
        const rateSelect = document.getElementById('insRateSelect');
        // Auto-select first rate
        if (rates.length > 0) {
            for (let o of rateSelect.options) {
                if (o.value === rates[0].trim()) { o.selected = true; break; }
            }
        }
    } else if (sel.value === 'other') {
        section.style.display = '';
        masterId.value = '';
        policyCompany.value = '';
    } else {
        section.style.display = 'none';
        masterId.value = '';
        policyCompany.value = '';
    }
}
</script>