<?php
/** オーダー作成フォーム */
$id = (int)($_GET['id'] ?? 0);
$patient_id = (int)($_GET['patient_id'] ?? 0);
$record_id = (int)($_GET['record_id'] ?? 0);
$order = $id ? $db->fetch("SELECT * FROM orders WHERE id = ?", [$id]) : null;

// ステータス変更
if ($id && ($_GET['action'] ?? '') === 'execute') {
    $db->update('orders', ['status'=>'completed','executed_by'=>$auth->currentUserId(),'executed_at'=>date('Y-m-d H:i:s')], 'id=?', [$id]);
    redirect("?page=orders");
}

if ($order) { $patient_id = $order['patient_id']; $record_id = $order['record_id'] ?? 0; }
$patients_list = $db->fetchAll("SELECT id, name, patient_code FROM patients WHERE is_active=1 ORDER BY name");
$drugs = $db->fetchAll("SELECT * FROM drug_master WHERE is_active=1 ORDER BY drug_name");
$tests = $db->fetchAll("SELECT * FROM test_master WHERE is_active=1 ORDER BY test_name");
$procedures = $db->fetchAll("SELECT * FROM procedure_master WHERE is_active=1 ORDER BY procedure_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'patient_id' => (int)$_POST['patient_id'],
        'record_id' => $_POST['record_id'] ?: null,
        'admission_id' => $_POST['admission_id'] ?: null,
        'order_type' => $_POST['order_type'],
        'order_category' => trim($_POST['order_category'] ?? ''),
        'order_name' => trim($_POST['order_name']),
        'order_detail' => trim($_POST['order_detail'] ?? ''),
        'quantity' => (float)$_POST['quantity'],
        'unit' => trim($_POST['unit'] ?? ''),
        'unit_price' => (int)$_POST['unit_price'],
        'total_price' => (int)$_POST['total_price'],
        'frequency' => trim($_POST['frequency'] ?? ''),
        'duration' => trim($_POST['duration'] ?? ''),
        'route' => trim($_POST['route'] ?? ''),
        'priority' => $_POST['priority'] ?? 'normal',
        'status' => 'pending',
        'ordered_by' => $auth->currentUserId(),
        'notes' => trim($_POST['notes'] ?? '')
    ];
    if ($id) { $db->update('orders', $data, 'id=?', [$id]); }
    else { $data['ordered_at'] = date('Y-m-d H:i:s'); $db->insert('orders', $data); }
    redirect("?page=orders");
}
$o = $order ?: ['order_type'=>'prescription','priority'=>'normal','quantity'=>1];
?>
<div class="fade-in">
    <a href="?page=orders" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>オーダー管理</a>
    <h4 class="fw-bold mt-1 mb-3"><?= $id ? 'オーダー編集' : '新規オーダー' ?></h4>
    <form method="POST" class="card">
        <div class="card-body"><div class="row g-3"><?= csrf_field() ?>
            <div class="col-md-6"><label class="form-label required">患畜</label>
                <select name="patient_id" class="form-select" required>
                    <option value="">選択</option>
                    <?php foreach ($patients_list as $pt): ?>
                    <option value="<?= $pt['id'] ?>" <?= $patient_id==$pt['id']?'selected':'' ?>><?= h($pt['name']) ?> (<?= h($pt['patient_code']) ?>)</option>
                    <?php endforeach; ?>
                </select></div>
            <input type="hidden" name="record_id" value="<?= $record_id ?>">
            <input type="hidden" name="admission_id" value="<?= $o['admission_id'] ?? '' ?>">
            <div class="col-md-3"><label class="form-label required">オーダー種別</label>
                <select name="order_type" class="form-select" id="orderType" onchange="updateOrderMaster()">
                    <option value="prescription" <?= ($o['order_type']??'')==='prescription'?'selected':'' ?>>処方</option>
                    <option value="test" <?= ($o['order_type']??'')==='test'?'selected':'' ?>>検査</option>
                    <option value="procedure" <?= ($o['order_type']??'')==='procedure'?'selected':'' ?>>処置</option>
                </select></div>
            <div class="col-md-3"><label class="form-label">優先度</label>
                <select name="priority" class="form-select">
                    <option value="normal" <?= ($o['priority']??'')==='normal'?'selected':'' ?>>通常</option>
                    <option value="urgent" <?= ($o['priority']??'')==='urgent'?'selected':'' ?>>緊急</option>
                    <option value="stat" <?= ($o['priority']??'')==='stat'?'selected':'' ?>>至急</option>
                </select></div>
            <div class="col-md-6"><label class="form-label">マスタから選択</label>
                <select class="form-select" id="masterSelect" onchange="applyMaster()">
                    <option value="">-- 直接入力またはマスタから選択 --</option>
                </select></div>
            <div class="col-md-6"><label class="form-label required">オーダー名</label>
                <input type="text" name="order_name" class="form-control" value="<?= h($o['order_name'] ?? '') ?>" required id="orderName"></div>
            <div class="col-md-3"><label class="form-label">カテゴリ</label>
                <input type="text" name="order_category" class="form-control" value="<?= h($o['order_category'] ?? '') ?>" id="orderCat"></div>
            <div class="col-md-9"><label class="form-label">詳細</label>
                <input type="text" name="order_detail" class="form-control" value="<?= h($o['order_detail'] ?? '') ?>"></div>
            <div class="col-md-2"><label class="form-label">数量</label>
                <input type="number" name="quantity" class="form-control" value="<?= h($o['quantity'] ?? 1) ?>" step="0.01" id="quantity" oninput="updateOrderTotal()"></div>
            <div class="col-md-2"><label class="form-label">単位</label>
                <input type="text" name="unit" class="form-control" value="<?= h($o['unit'] ?? '') ?>" id="orderUnit"></div>
            <div class="col-md-3"><label class="form-label">単価</label>
                <input type="number" name="unit_price" class="form-control" value="<?= h($o['unit_price'] ?? 0) ?>" id="unit_price" oninput="updateOrderTotal()"></div>
            <div class="col-md-3"><label class="form-label">合計金額</label>
                <input type="number" name="total_price" class="form-control" value="<?= h($o['total_price'] ?? 0) ?>" id="total_price"></div>
            <div class="col-md-3"><label class="form-label">用法・頻度</label>
                <input type="text" name="frequency" class="form-control" value="<?= h($o['frequency'] ?? '') ?>" placeholder="BID, TID等"></div>
            <div class="col-md-3"><label class="form-label">期間</label>
                <input type="text" name="duration" class="form-control" value="<?= h($o['duration'] ?? '') ?>" placeholder="7日間等"></div>
            <div class="col-md-3"><label class="form-label">投与経路</label>
                <select name="route" class="form-select">
                    <option value="">-</option>
                    <option value="oral" <?= ($o['route']??'')==='oral'?'selected':'' ?>>経口</option>
                    <option value="subcutaneous" <?= ($o['route']??'')==='subcutaneous'?'selected':'' ?>>皮下注射</option>
                    <option value="intramuscular" <?= ($o['route']??'')==='intramuscular'?'selected':'' ?>>筋肉内注射</option>
                    <option value="intravenous" <?= ($o['route']??'')==='intravenous'?'selected':'' ?>>静脈内</option>
                    <option value="topical" <?= ($o['route']??'')==='topical'?'selected':'' ?>>外用</option>
                    <option value="ophthalmic" <?= ($o['route']??'')==='ophthalmic'?'selected':'' ?>>点眼</option>
                    <option value="otic" <?= ($o['route']??'')==='otic'?'selected':'' ?>>点耳</option>
                </select></div>
            <div class="col-12"><label class="form-label">備考</label>
                <textarea name="notes" class="form-control" rows="2"><?= h($o['notes'] ?? '') ?></textarea></div>
        </div></div>
        <div class="card-footer text-end">
            <a href="?page=orders" class="btn btn-secondary me-2">キャンセル</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>オーダー発行</button>
        </div>
    </form>
</div>
<script>
const drugMaster = <?= json_encode($drugs) ?>;
const testMaster = <?= json_encode($tests) ?>;
const procMaster = <?= json_encode($procedures) ?>;
function updateOrderMaster() {
    const type = document.getElementById('orderType').value;
    const sel = document.getElementById('masterSelect');
    sel.innerHTML = '<option value="">-- マスタから選択 --</option>';
    let items = type === 'prescription' ? drugMaster : (type === 'test' ? testMaster : procMaster);
    items.forEach((it, i) => {
        const name = it.drug_name || it.test_name || it.procedure_name;
        sel.innerHTML += `<option value="${i}">${name} (¥${(it.unit_price||0).toLocaleString()})</option>`;
    });
}
function applyMaster() {
    const type = document.getElementById('orderType').value;
    const idx = document.getElementById('masterSelect').value;
    if (idx === '') return;
    let items = type === 'prescription' ? drugMaster : (type === 'test' ? testMaster : procMaster);
    const it = items[idx];
    document.getElementById('orderName').value = it.drug_name || it.test_name || it.procedure_name;
    document.getElementById('orderCat').value = it.category || '';
    document.getElementById('orderUnit').value = it.unit || '';
    document.getElementById('unit_price').value = it.unit_price || 0;
    updateOrderTotal();
}
document.addEventListener('DOMContentLoaded', updateOrderMaster);
</script>
