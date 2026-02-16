<?php
/** 会計フォーム (修正版: 処置対応・税率連動) **/
$auth->requireLogin();

$patients_list = $db->fetchAll("SELECT p.id, p.name, p.patient_code, p.owner_id, o.name as oname FROM patients p JOIN owners o ON p.owner_id=o.id WHERE p.is_active=1 ORDER BY p.name");
$patient_id = (int)($_GET['patient_id'] ?? 0);

// マスタデータ取得 (薬品 + 処置)
$drugs = $db->fetchAll("SELECT drug_name as name, unit_price, category, unit, id as item_id, 'drug' as type, stock_quantity FROM drug_master WHERE is_active=1");
$procs = $db->fetchAll("SELECT procedure_name as name, unit_price, category, unit, id as item_id, 'procedure' as type, 9999 as stock_quantity FROM procedure_master WHERE is_active=1");
$tests = $db->fetchAll("SELECT test_name as name, unit_price, category, unit, id as item_id, 'test' as type, 9999 as stock_quantity FROM test_master WHERE is_active=1");

// 全アイテムを統合
$allItems = array_merge($drugs, $procs, $tests);

// 税率設定の取得
$tax_setting = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'tax_rate'");
$tax_rate = $tax_setting ? (float)$tax_setting['setting_value'] : 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patId = (int)$_POST['patient_id'];
    $pat = $db->fetch("SELECT owner_id FROM patients WHERE id=?", [$patId]);
    $invNum = generateReceiptNumber();
    $subtotal = 0; $items = [];
    
    $lowStockAlerts = [];

    // トランザクション開始
    $pdo = $db->getPDO();
    $pdo->beginTransaction();

    try {
        $invId = $db->insert('invoices', [
            'invoice_number'=>$invNum, 'patient_id'=>$patId, 'owner_id'=>$pat['owner_id'],
            'payment_method'=>$_POST['payment_method']??'cash', 'payment_status'=>'paid', 
            'paid_at'=>date('Y-m-d H:i:s'), 'created_by'=>$auth->currentUserId(), 'created_at'=>date('Y-m-d H:i:s')
        ]);

        for ($i = 0; $i < count($_POST['item_name'] ?? []); $i++) {
            if (empty($_POST['item_name'][$i])) continue;
            
            $name = $_POST['item_name'][$i];
            $qty = (float)$_POST['item_qty'][$i];
            $price = (int)$_POST['item_price'][$i];
            $amt = round($qty * $price);
            $subtotal += $amt;

            $db->insert('invoice_items', [
                'invoice_id' => $invId,
                'item_name' => $name,
                'category' => $_POST['item_cat'][$i]??'',
                'quantity' => $qty,
                'unit' => $_POST['item_unit'][$i]??'',
                'unit_price' => $price,
                'amount' => $amt,
                'tax_rate' => $tax_rate
            ]);

            // 在庫減算ロジック (薬品の場合のみ)
            // 名前で薬品マスタを検索 (完全一致)
            $drug = $db->fetch("SELECT id, drug_name, stock_quantity, min_stock FROM drug_master WHERE drug_name = ? LIMIT 1", [$name]);
            
            if ($drug) {
                $newStock = $drug['stock_quantity'] - $qty;
                $db->update('drug_master', ['stock_quantity' => $newStock], 'id = ?', [$drug['id']]);
                
                if ($newStock <= $drug['min_stock']) {
                    $lowStockAlerts[] = "{$drug['drug_name']} (残: {$newStock})";
                }
            }
        }

        // 合計計算と更新
        $tax = round($subtotal * ($tax_rate / 100));
        $discount = (int)($_POST['discount'] ?? 0);
        $insurance = (int)($_POST['insurance_covered'] ?? 0);
        $total = $subtotal + $tax - $discount - $insurance;

        $db->update('invoices', ['subtotal'=>$subtotal, 'tax'=>$tax, 'discount'=>$discount, 'insurance_covered'=>$insurance, 'total'=>$total], 'id=?', [$invId]);
        
        $pdo->commit();

        if (!empty($lowStockAlerts)) {
            $_SESSION['stock_alerts'] = $lowStockAlerts;
        }

        redirect("?page=invoice_print&id={$invId}");

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "エラーが発生しました: " . h($e->getMessage());
        exit;
    }
}
?>
<div class="fade-in">
    <a href="?page=invoices" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>会計一覧</a>
    <h4 class="fw-bold mt-1 mb-3">新規会計入力</h4>
    
    <form method="POST" class="card">
        <div class="card-body"><div class="row g-3"><?= csrf_field() ?>
            <div class="col-md-6"><label class="form-label required">患畜</label>
                <select name="patient_id" class="form-select" required>
                    <option value="">選択</option>
                    <?php foreach ($patients_list as $pt): ?>
                    <option value="<?= $pt['id'] ?>" <?= $patient_id==$pt['id']?'selected':'' ?>><?= h($pt['name']) ?> (<?= h($pt['patient_code']) ?>) - <?= h($pt['oname']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label">支払方法</label>
                <select name="payment_method" class="form-select">
                    <option value="cash">現金</option><option value="credit">クレジット</option><option value="electronic">電子マネー</option><option value="bank">振込</option>
                </select></div>
            <div class="col-12"><hr><h6>明細項目 (消費税: <?= $tax_rate ?>%)</h6></div>
            <div class="col-12" id="itemsContainer">
                <div class="row g-2 mb-2 item-row">
                    <div class="col-4">
                        <input type="text" name="item_name[]" class="form-control form-control-sm item-name-input" placeholder="項目名 (薬品・処置名を入力)" list="itemList" onchange="autoFill(this)">
                    </div>
                    <div class="col-2"><input type="text" name="item_cat[]" class="form-control form-control-sm item-cat" placeholder="区分"></div>
                    <div class="col-1"><input type="number" name="item_qty[]" class="form-control form-control-sm" value="1" step="0.01" oninput="calcRow(this)"></div>
                    <div class="col-1"><input type="text" name="item_unit[]" class="form-control form-control-sm item-unit" placeholder="単位"></div>
                    <div class="col-2"><input type="number" name="item_price[]" class="form-control form-control-sm item-price" placeholder="単価" oninput="calcRow(this)"></div>
                    <div class="col-2 d-flex align-items-center"><span class="row-total me-2 fw-bold text-muted">0</span> <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.item-row').remove()">削除</button></div>
                </div>
            </div>
            
            <datalist id="itemList">
                <?php foreach($allItems as $item): ?>
                <option value="<?= h($item['name']) ?>" data-price="<?= $item['unit_price'] ?>" data-cat="<?= $item['category'] ?>" data-unit="<?= $item['unit'] ?>">
                    <?= h($item['category']) ?> - ¥<?= number_format($item['unit_price']) ?>
                </option>
                <?php endforeach; ?>
            </datalist>

            <div class="col-12"><button type="button" class="btn btn-sm btn-outline-primary" onclick="addItemRow()"><i class="bi bi-plus me-1"></i>行追加</button></div>
            <div class="col-md-3"><label class="form-label">値引き</label><input type="number" name="discount" class="form-control" value="0"></div>
            <div class="col-md-3"><label class="form-label">保険負担額</label><input type="number" name="insurance_covered" class="form-control" value="0"></div>
        </div></div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>会計確定</button>
        </div>
    </form>
</div>
<script>
// マスタデータ利用のためのマップ
const itemMap = {};
<?php foreach($allItems as $item): ?>
itemMap["<?= h($item['name']) ?>"] = {
    price: <?= $item['unit_price'] ?>, 
    cat: "<?= h($item['category']) ?>", 
    unit: "<?= h($item['unit']) ?>"
};
<?php endforeach; ?>

function addItemRow() {
    const c = document.getElementById('itemsContainer');
    const row = c.querySelector('.item-row').cloneNode(true);
    row.querySelectorAll('input').forEach(i => i.value = i.name.includes('qty') ? '1' : '');
    row.querySelector('.row-total').innerText = '0';
    c.appendChild(row);
}

function autoFill(input) {
    const val = input.value;
    const row = input.closest('.item-row');
    if(itemMap[val]) {
        row.querySelector('.item-price').value = itemMap[val].price;
        row.querySelector('.item-cat').value = itemMap[val].cat;
        row.querySelector('.item-unit').value = itemMap[val].unit;
        calcRow(input);
    }
}

function calcRow(el) {
    const row = el.closest('.item-row');
    const qty = parseFloat(row.querySelector('[name="item_qty[]"]').value || 0);
    const price = parseFloat(row.querySelector('[name="item_price[]"]').value || 0);
    row.querySelector('.row-total').innerText = '¥' + Math.round(qty * price).toLocaleString();
}
</script>