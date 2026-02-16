<?php
/** レセプト作成・編集フォーム */
$id = (int)($_GET['id'] ?? 0);
$claim = $id ? $db->fetch("SELECT * FROM insurance_claims WHERE id = ?", [$id]) : null;
$fromInvoice = (int)($_GET['invoice_id'] ?? 0);

$patients = $db->fetchAll("SELECT p.id, p.patient_code, p.name, p.species, o.name as owner_name FROM patients p JOIN owners o ON p.owner_id = o.id WHERE p.is_active = 1 ORDER BY p.name");
$diagnoses = $db->fetchAll("SELECT * FROM diagnosis_master WHERE is_active = 1 ORDER BY diagnosis_code");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $patientId = (int)$_POST['patient_id'];
    $policyId = (int)$_POST['policy_id'];
    $policy = $db->fetch("SELECT * FROM insurance_policies WHERE id = ?", [$policyId]);
    
    $totalFee = (int)$_POST['total_medical_fee'];
    $covRate = $policy ? $policy['coverage_rate'] : 50;
    $coveredAmount = round($totalFee * $covRate / 100);
    $ownerCopay = $totalFee - $coveredAmount;
    
    $data = [
        'policy_id' => $policyId,
        'patient_id' => $patientId,
        'invoice_id' => (int)($_POST['invoice_id'] ?? 0) ?: null,
        'record_id' => (int)($_POST['record_id'] ?? 0) ?: null,
        'claim_date' => $_POST['claim_date'] ?: date('Y-m-d'),
        'treatment_start_date' => $_POST['treatment_start_date'],
        'treatment_end_date' => $_POST['treatment_end_date'],
        'diagnosis_name' => trim($_POST['diagnosis_name']),
        'diagnosis_code' => trim($_POST['diagnosis_code'] ?? ''),
        'total_medical_fee' => $totalFee,
        'covered_amount' => $coveredAmount,
        'owner_copay' => $ownerCopay,
        'deductible' => (int)($_POST['deductible'] ?? 0),
        'notes' => trim($_POST['notes'] ?? ''),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    
    if ($id && $claim) {
        $db->update('insurance_claims', $data, 'id = ?', [$id]);
        // Update items
        $db->delete('insurance_claim_items', 'claim_id = ?', [$id]);
    } else {
        $data['claim_number'] = 'CL' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $data['claim_status'] = 'draft';
        $data['created_by'] = $auth->currentUserId();
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = $db->insert('insurance_claims', $data);
    }
    
    // Save items
    for ($i = 0; $i < count($_POST['item_name'] ?? []); $i++) {
        if (empty($_POST['item_name'][$i])) continue;
        $db->insert('insurance_claim_items', [
            'claim_id' => $id,
            'item_date' => $_POST['item_date'][$i] ?: date('Y-m-d'),
            'item_category' => $_POST['item_category'][$i] ?? '',
            'item_name' => $_POST['item_name'][$i],
            'quantity' => (float)($_POST['item_qty'][$i] ?? 1),
            'unit' => $_POST['item_unit'][$i] ?? '',
            'unit_price' => (int)($_POST['item_price'][$i] ?? 0),
            'amount' => round((float)($_POST['item_qty'][$i] ?? 1) * (int)($_POST['item_price'][$i] ?? 0)),
            'is_covered' => isset($_POST['item_covered'][$i]) ? 1 : 0,
        ]);
    }
    
    setFlash('success', 'レセプトを保存しました');
    redirect('?page=insurance_claims');
}

// Load items if editing
$items = $id ? $db->fetchAll("SELECT * FROM insurance_claim_items WHERE claim_id = ? ORDER BY id", [$id]) : [];

// Load policies for selected patient
$selectedPatientId = $claim ? $claim['patient_id'] : ($fromInvoice ? $db->fetch("SELECT patient_id FROM invoices WHERE id=?", [$fromInvoice])['patient_id'] ?? 0 : 0);
$policies = $selectedPatientId ? $db->fetchAll("SELECT * FROM insurance_policies WHERE patient_id = ? AND status = 'active'", [$selectedPatientId]) : [];

// Load invoice data if creating from invoice
$invoiceData = $fromInvoice ? $db->fetch("SELECT i.*, p.name as pname FROM invoices i JOIN patients p ON i.patient_id=p.id WHERE i.id=?", [$fromInvoice]) : null;
$invoiceItems = $fromInvoice ? $db->fetchAll("SELECT * FROM invoice_items WHERE invoice_id = ?", [$fromInvoice]) : [];
?>

<div class="fade-in">
    <a href="?page=insurance_claims" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>レセプト一覧</a>
    <h4 class="fw-bold mt-1 mb-3"><i class="bi bi-file-earmark-medical me-2"></i><?= $claim ? 'レセプト編集' : '新規レセプト作成' ?></h4>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><i class="bi bi-info-circle me-2"></i>基本情報</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">患畜</label>
                                <select name="patient_id" class="form-select" required id="patientSelect" onchange="loadPolicies(this.value)">
                                    <option value="">-- 選択 --</option>
                                    <?php foreach ($patients as $pt): ?>
                                    <option value="<?= $pt['id'] ?>" <?= $selectedPatientId == $pt['id'] ? 'selected' : '' ?>>
                                        <?= h($pt['patient_code']) ?> - <?= h($pt['name']) ?> (<?= h(getSpeciesName($pt['species'])) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">保険証券</label>
                                <select name="policy_id" class="form-select" required id="policySelect">
                                    <option value="">-- 患畜を先に選択 --</option>
                                    <?php foreach ($policies as $pol): ?>
                                    <option value="<?= $pol['id'] ?>" data-rate="<?= $pol['coverage_rate'] ?>" <?= ($claim['policy_id'] ?? 0) == $pol['id'] ? 'selected' : '' ?>>
                                        <?= h($pol['company_name']) ?> (<?= h($pol['policy_number']) ?>) <?= $pol['coverage_rate'] ?>%
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">請求日</label>
                                <input type="text" name="claim_date" class="form-control datepicker" value="<?= h($claim['claim_date'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">治療開始日</label>
                                <input type="text" name="treatment_start_date" class="form-control datepicker" value="<?= h($claim['treatment_start_date'] ?? ($invoiceData['created_at'] ?? date('Y-m-d'))) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">治療終了日</label>
                                <input type="text" name="treatment_end_date" class="form-control datepicker" value="<?= h($claim['treatment_end_date'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label required">診断名</label>
                                <input type="text" name="diagnosis_name" class="form-control" list="diagList" value="<?= h($claim['diagnosis_name'] ?? '') ?>" required placeholder="診断名を入力または選択">
                                <datalist id="diagList">
                                    <?php foreach($diagnoses as $dg): ?>
                                    <option value="<?= h($dg['diagnosis_name']) ?>"><?= h($dg['diagnosis_code']) ?> - <?= h($dg['category']) ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">診断コード</label>
                                <input type="text" name="diagnosis_code" class="form-control" value="<?= h($claim['diagnosis_code'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 明細項目 -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <span><i class="bi bi-list-ul me-2"></i>診療明細</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addClaimItem()"><i class="bi bi-plus-lg me-1"></i>行追加</button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0" id="claimItemsTable">
                                <thead><tr><th>日付</th><th>区分</th><th>内容</th><th>数量</th><th>単位</th><th>単価</th><th>金額</th><th>対象</th><th></th></tr></thead>
                                <tbody id="claimItemsBody">
                                <?php
                                $itemsToShow = !empty($items) ? $items : (!empty($invoiceItems) ? array_map(function($ii) { return ['item_date'=>date('Y-m-d'), 'item_category'=>$ii['category'], 'item_name'=>$ii['item_name'], 'quantity'=>$ii['quantity'], 'unit'=>$ii['unit'], 'unit_price'=>$ii['unit_price'], 'amount'=>$ii['amount'], 'is_covered'=>1]; }, $invoiceItems) : []);
                                if (empty($itemsToShow)) $itemsToShow = [['item_date'=>date('Y-m-d'),'item_category'=>'','item_name'=>'','quantity'=>1,'unit'=>'','unit_price'=>0,'amount'=>0,'is_covered'=>1]];
                                foreach ($itemsToShow as $idx => $it):
                                ?>
                                <tr class="claim-item-row">
                                    <td><input type="date" name="item_date[]" class="form-control form-control-sm" value="<?= h($it['item_date'] ?? date('Y-m-d')) ?>" style="width:130px"></td>
                                    <td><select name="item_category[]" class="form-select form-select-sm" style="width:100px">
                                        <option value="診察" <?= ($it['item_category'] ?? '') === '診察' ? 'selected' : '' ?>>診察</option>
                                        <option value="検査" <?= ($it['item_category'] ?? '') === '検査' ? 'selected' : '' ?>>検査</option>
                                        <option value="処置" <?= ($it['item_category'] ?? '') === '処置' ? 'selected' : '' ?>>処置</option>
                                        <option value="投薬" <?= ($it['item_category'] ?? '') === '投薬' ? 'selected' : '' ?>>投薬</option>
                                        <option value="手術" <?= ($it['item_category'] ?? '') === '手術' ? 'selected' : '' ?>>手術</option>
                                        <option value="入院" <?= ($it['item_category'] ?? '') === '入院' ? 'selected' : '' ?>>入院</option>
                                        <option value="その他" <?= ($it['item_category'] ?? '') === 'その他' ? 'selected' : '' ?>>その他</option>
                                    </select></td>
                                    <td><input type="text" name="item_name[]" class="form-control form-control-sm" value="<?= h($it['item_name'] ?? '') ?>" required></td>
                                    <td><input type="number" name="item_qty[]" class="form-control form-control-sm ci-qty" step="0.01" value="<?= $it['quantity'] ?? 1 ?>" style="width:70px" oninput="calcClaimRow(this)"></td>
                                    <td><input type="text" name="item_unit[]" class="form-control form-control-sm" value="<?= h($it['unit'] ?? '') ?>" style="width:60px"></td>
                                    <td><input type="number" name="item_price[]" class="form-control form-control-sm ci-price" value="<?= $it['unit_price'] ?? 0 ?>" style="width:90px" oninput="calcClaimRow(this)"></td>
                                    <td class="ci-amount fw-bold"><?= formatCurrency($it['amount'] ?? 0) ?></td>
                                    <td><input type="checkbox" name="item_covered[<?= $idx ?>]" value="1" <?= ($it['is_covered'] ?? 1) ? 'checked' : '' ?> class="form-check-input"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); calcClaimTotal();">&times;</button></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card" style="position:sticky; top:80px;">
                    <div class="card-header"><i class="bi bi-calculator me-2"></i>金額計算</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">医療費合計</label>
                            <input type="number" name="total_medical_fee" id="totalFee" class="form-control form-control-lg fw-bold text-primary" value="<?= $claim['total_medical_fee'] ?? ($invoiceData ? $invoiceData['subtotal'] + $invoiceData['tax'] : 0) ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">補償割合</label>
                            <div id="coverageDisplay" class="fs-4 fw-bold text-success">
                                <?= $claim ? $db->fetch("SELECT coverage_rate FROM insurance_policies WHERE id=?",[$claim['policy_id']])['coverage_rate'] ?? '??' : '--' ?>%
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">保険負担額（自動計算）</label>
                            <div id="coveredDisplay" class="fs-5 fw-bold text-primary"><?= $claim ? formatCurrency($claim['covered_amount']) : '--' ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">飼い主負担額</label>
                            <div id="copayDisplay" class="fs-5 fw-bold"><?= $claim ? formatCurrency($claim['owner_copay']) : '--' ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">免責額</label>
                            <input type="number" name="deductible" class="form-control" value="<?= $claim['deductible'] ?? 0 ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">備考</label>
                            <textarea name="notes" class="form-control" rows="3"><?= h($claim['notes'] ?? '') ?></textarea>
                        </div>
                        <input type="hidden" name="invoice_id" value="<?= $fromInvoice ?>">
                        <input type="hidden" name="record_id" value="<?= $claim['record_id'] ?? '' ?>">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i><?= $claim ? '更新' : '保存' ?></button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let claimIdx = <?= count($itemsToShow) ?>;
function addClaimItem() {
    const row = document.querySelector('.claim-item-row').cloneNode(true);
    row.querySelectorAll('input[type="text"], input[type="number"]').forEach(i => { if (!i.name.includes('date')) i.value = i.name.includes('qty') ? '1' : ''; });
    row.querySelector('.ci-amount').innerText = '¥0';
    const cb = row.querySelector('input[type="checkbox"]');
    cb.name = `item_covered[${claimIdx}]`; cb.checked = true;
    claimIdx++;
    document.getElementById('claimItemsBody').appendChild(row);
}

function calcClaimRow(el) {
    const row = el.closest('tr');
    const qty = parseFloat(row.querySelector('.ci-qty').value || 0);
    const price = parseFloat(row.querySelector('.ci-price').value || 0);
    row.querySelector('.ci-amount').innerText = '¥' + Math.round(qty * price).toLocaleString();
    calcClaimTotal();
}

function calcClaimTotal() {
    let total = 0;
    document.querySelectorAll('.claim-item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.ci-qty')?.value || 0);
        const price = parseFloat(row.querySelector('.ci-price')?.value || 0);
        total += Math.round(qty * price);
    });
    document.getElementById('totalFee').value = total;
    
    const psel = document.getElementById('policySelect');
    const opt = psel.options[psel.selectedIndex];
    if (opt && opt.dataset.rate) {
        const rate = parseFloat(opt.dataset.rate);
        const covered = Math.round(total * rate / 100);
        document.getElementById('coverageDisplay').innerText = rate + '%';
        document.getElementById('coveredDisplay').innerText = '¥' + covered.toLocaleString();
        document.getElementById('copayDisplay').innerText = '¥' + (total - covered).toLocaleString();
    }
}

function loadPolicies(patientId) {
    // For simplicity, reload page with patient selected
    if (patientId) {
        // Use AJAX or simple redirect
        fetch('?page=api_insurance_policies&patient_id=' + patientId)
            .then(r => r.json())
            .then(data => {
                const sel = document.getElementById('policySelect');
                sel.innerHTML = '<option value="">-- 選択 --</option>';
                (data.policies || []).forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.dataset.rate = p.coverage_rate;
                    opt.textContent = `${p.company_name} (${p.policy_number}) ${p.coverage_rate}%`;
                    sel.appendChild(opt);
                });
            }).catch(() => {});
    }
}

document.getElementById('policySelect')?.addEventListener('change', function(){ calcClaimTotal(); });
</script>
