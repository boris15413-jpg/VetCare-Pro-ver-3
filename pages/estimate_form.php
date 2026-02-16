<?php
/** 見積もりフォーム */
$id = (int)($_GET['id'] ?? 0);
$estimate = $id ? $db->fetch("SELECT * FROM estimates WHERE id = ?", [$id]) : null;
$patient_id = (int)($_GET['patient_id'] ?? ($estimate['patient_id'] ?? 0));

$patients_list = $db->fetchAll("SELECT p.id, p.name, p.patient_code, p.owner_id, p.insurance_company, p.insurance_rate, o.name as oname FROM patients p JOIN owners o ON p.owner_id=o.id WHERE p.is_active=1 ORDER BY p.name");
$drugs = $db->fetchAll("SELECT drug_name as name, unit_price, category, unit FROM drug_master WHERE is_active=1");
$procs = $db->fetchAll("SELECT procedure_name as name, unit_price, category, unit FROM procedure_master WHERE is_active=1");
$tests = $db->fetchAll("SELECT test_name as name, unit_price, category, unit FROM test_master WHERE is_active=1");
$allItems = array_merge($drugs, $procs, $tests);

$tax_rate = (float)(getSetting('tax_rate', '10'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $patId = (int)$_POST['patient_id'];
    $pat = $db->fetch("SELECT owner_id, insurance_rate FROM patients WHERE id=?", [$patId]);
    $subtotal = 0;

    for ($i = 0; $i < count($_POST['item_name'] ?? []); $i++) {
        if (empty($_POST['item_name'][$i])) continue;
        $subtotal += round((float)$_POST['item_qty'][$i] * (int)$_POST['item_price'][$i]);
    }

    $tax = round($subtotal * ($tax_rate / 100));
    $total = $subtotal + $tax;
    $insuranceEst = round($total * ((int)($pat['insurance_rate'] ?? 0)) / 100);
    $ownerEst = $total - $insuranceEst;

    $data = [
        'patient_id' => $patId, 'owner_id' => $pat['owner_id'],
        'title' => trim($_POST['title']),
        'subtotal' => $subtotal, 'tax' => $tax, 'total' => $total,
        'insurance_estimate' => $insuranceEst, 'owner_estimate' => $ownerEst,
        'valid_until' => $_POST['valid_until'] ?: null,
        'status' => $_POST['status'] ?? 'draft',
        'notes' => trim($_POST['notes'] ?? ''),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    if ($id && $estimate) {
        $db->update('estimates', $data, 'id=?', [$id]);
        $db->delete('estimate_items', 'estimate_id=?', [$id]);
    } else {
        $data['estimate_number'] = 'EST' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $data['created_by'] = $auth->currentUserId();
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = $db->insert('estimates', $data);
    }

    for ($i = 0; $i < count($_POST['item_name'] ?? []); $i++) {
        if (empty($_POST['item_name'][$i])) continue;
        $db->insert('estimate_items', [
            'estimate_id' => $id,
            'item_name' => $_POST['item_name'][$i],
            'category' => $_POST['item_cat'][$i] ?? '',
            'quantity' => (float)$_POST['item_qty'][$i],
            'unit' => $_POST['item_unit'][$i] ?? '',
            'unit_price' => (int)$_POST['item_price'][$i],
            'amount' => round((float)$_POST['item_qty'][$i] * (int)$_POST['item_price'][$i]),
        ]);
    }

    setFlash('success', '見積もりを保存しました');
    redirect('?page=estimates');
}

$items = $id ? $db->fetchAll("SELECT * FROM estimate_items WHERE estimate_id=? ORDER BY id", [$id]) : [];
?>

<div class="fade-in">
    <a href="?page=estimates" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>見積もり一覧</a>
    <h4 class="fw-bold mt-1 mb-3"><?= $estimate ? '見積もり編集' : '新規見積もり作成' ?></h4>

    <form method="POST" class="card">
        <div class="card-body"><?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label required">患畜</label>
                    <select name="patient_id" class="form-select" required>
                        <option value="">選択</option>
                        <?php foreach ($patients_list as $pt): ?>
                        <option value="<?= $pt['id'] ?>" data-rate="<?= $pt['insurance_rate'] ?>" <?= $patient_id==$pt['id']?'selected':'' ?>>
                            <?= h($pt['name']) ?> (<?= h($pt['patient_code']) ?>) - <?= h($pt['oname']) ?>
                            <?= $pt['insurance_company'] ? ' [保険:' . $pt['insurance_rate'] . '%]' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">タイトル</label>
                    <input type="text" name="title" class="form-control" required value="<?= h($estimate['title'] ?? '') ?>" placeholder="例: 去勢手術概算見積">
                </div>
                <div class="col-md-2">
                    <label class="form-label">有効期限</label>
                    <input type="text" name="valid_until" class="form-control datepicker" value="<?= h($estimate['valid_until'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">ステータス</label>
                    <select name="status" class="form-select">
                        <option value="draft" <?= ($estimate['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>下書き</option>
                        <option value="sent" <?= ($estimate['status'] ?? '') === 'sent' ? 'selected' : '' ?>>提示済</option>
                        <option value="approved" <?= ($estimate['status'] ?? '') === 'approved' ? 'selected' : '' ?>>承認</option>
                    </select>
                </div>

                <div class="col-12"><hr><h6>明細項目</h6></div>
                <div class="col-12" id="estItemsContainer">
                    <?php
                    $showItems = !empty($items) ? $items : [['item_name'=>'','category'=>'','quantity'=>1,'unit'=>'','unit_price'=>0]];
                    foreach ($showItems as $it):
                    ?>
                    <div class="row g-2 mb-2 est-item-row">
                        <div class="col-4"><input type="text" name="item_name[]" class="form-control form-control-sm" placeholder="項目名" list="itemList2" value="<?= h($it['item_name'] ?? '') ?>" onchange="autoFillEst(this)"></div>
                        <div class="col-2"><input type="text" name="item_cat[]" class="form-control form-control-sm est-cat" placeholder="区分" value="<?= h($it['category'] ?? '') ?>"></div>
                        <div class="col-1"><input type="number" name="item_qty[]" class="form-control form-control-sm" value="<?= $it['quantity'] ?? 1 ?>" step="0.01" oninput="calcEstRow(this)"></div>
                        <div class="col-1"><input type="text" name="item_unit[]" class="form-control form-control-sm est-unit" placeholder="単位" value="<?= h($it['unit'] ?? '') ?>"></div>
                        <div class="col-2"><input type="number" name="item_price[]" class="form-control form-control-sm est-price" placeholder="単価" value="<?= $it['unit_price'] ?? 0 ?>" oninput="calcEstRow(this)"></div>
                        <div class="col-2 d-flex align-items-center"><span class="est-total me-2 fw-bold"><?= formatCurrency(($it['quantity'] ?? 1) * ($it['unit_price'] ?? 0)) ?></span> <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.est-item-row').remove()">&times;</button></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <datalist id="itemList2">
                    <?php foreach($allItems as $item): ?>
                    <option value="<?= h($item['name']) ?>" data-price="<?= $item['unit_price'] ?>" data-cat="<?= $item['category'] ?>" data-unit="<?= $item['unit'] ?>"><?= h($item['category']) ?></option>
                    <?php endforeach; ?>
                </datalist>

                <div class="col-12"><button type="button" class="btn btn-sm btn-outline-primary" onclick="addEstRow()"><i class="bi bi-plus me-1"></i>行追加</button></div>
                <div class="col-12">
                    <label class="form-label">備考</label>
                    <textarea name="notes" class="form-control" rows="2"><?= h($estimate['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $estimate ? '更新' : '保存' ?></button>
        </div>
    </form>
</div>

<script>
const estItemMap = {};
<?php foreach($allItems as $item): ?>
estItemMap["<?= h($item['name']) ?>"] = {price:<?= $item['unit_price'] ?>,cat:"<?= h($item['category']) ?>",unit:"<?= h($item['unit']) ?>"};
<?php endforeach; ?>

function addEstRow() {
    const c = document.getElementById('estItemsContainer');
    const row = c.querySelector('.est-item-row').cloneNode(true);
    row.querySelectorAll('input').forEach(i => i.value = i.name.includes('qty') ? '1' : '');
    row.querySelector('.est-total').innerText = '¥0';
    c.appendChild(row);
}
function autoFillEst(input) {
    const val = input.value, row = input.closest('.est-item-row');
    if(estItemMap[val]) {
        row.querySelector('.est-price').value = estItemMap[val].price;
        row.querySelector('.est-cat').value = estItemMap[val].cat;
        row.querySelector('.est-unit').value = estItemMap[val].unit;
        calcEstRow(input);
    }
}
function calcEstRow(el) {
    const row = el.closest('.est-item-row');
    const qty = parseFloat(row.querySelector('[name="item_qty[]"]').value || 0);
    const price = parseFloat(row.querySelector('[name="item_price[]"]').value || 0);
    row.querySelector('.est-total').innerText = '¥' + Math.round(qty * price).toLocaleString();
}
</script>
