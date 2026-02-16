<?php
/** 薬品マスタ */
$auth->requireRole([ROLE_ADMIN]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = ['drug_code'=>trim($_POST['drug_code']??''),'drug_name'=>trim($_POST['drug_name']),'generic_name'=>trim($_POST['generic_name']??''),'category'=>trim($_POST['category']??''),'unit'=>trim($_POST['unit']??''),'unit_price'=>(int)$_POST['unit_price'],'stock_quantity'=>(float)$_POST['stock_quantity'],'min_stock'=>(int)$_POST['min_stock'],'manufacturer'=>trim($_POST['manufacturer']??'')];
    $did = (int)($_POST['id'] ?? 0);
    if ($did) { $db->update('drug_master', $data, 'id=?', [$did]); }
    else { $data['created_at']=date('Y-m-d H:i:s'); $db->insert('drug_master', $data); }
    redirect("?page=master_drugs");
}
$drugs = $db->fetchAll("SELECT * FROM drug_master WHERE is_active=1 ORDER BY category, drug_name");
?>
<div class="fade-in">
    <h4 class="fw-bold mb-3"><i class="bi bi-capsule me-2"></i>薬品マスタ</h4>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card"><div class="card-body p-0"><div class="table-responsive">
                <table class="table table-hover table-sm mb-0"><thead><tr><th>コード</th><th>薬品名</th><th>カテゴリ</th><th>単位</th><th>単価</th><th>在庫</th></tr></thead><tbody>
                <?php foreach ($drugs as $d): ?>
                <tr onclick="editDrug(<?= htmlspecialchars(json_encode($d)) ?>)" style="cursor:pointer">
                    <td><code><?= h($d['drug_code']) ?></code></td><td><?= h($d['drug_name']) ?></td><td><?= h($d['category']) ?></td>
                    <td><?= h($d['unit']) ?></td><td><?= formatCurrency($d['unit_price']) ?></td>
                    <td><span class="<?= $d['stock_quantity']<=$d['min_stock']?'text-danger fw-bold':'' ?>"><?= $d['stock_quantity'] ?></span></td>
                </tr><?php endforeach; ?>
                </tbody></table>
            </div></div></div>
        </div>
        <div class="col-lg-4">
            <form method="POST" class="card" id="drugForm"><div class="card-header">薬品登録・編集</div><div class="card-body"><div class="row g-2">
                <input type="hidden" name="id" id="df_id">
                <div class="col-12"><input type="text" name="drug_code" class="form-control form-control-sm" id="df_code" placeholder="コード"></div>
                <div class="col-12"><input type="text" name="drug_name" class="form-control form-control-sm" id="df_name" placeholder="薬品名" required></div>
                <div class="col-12"><input type="text" name="generic_name" class="form-control form-control-sm" id="df_generic" placeholder="一般名"></div>
                <div class="col-6"><input type="text" name="category" class="form-control form-control-sm" id="df_cat" placeholder="カテゴリ"></div>
                <div class="col-6"><input type="text" name="unit" class="form-control form-control-sm" id="df_unit" placeholder="単位"></div>
                <div class="col-6"><input type="number" name="unit_price" class="form-control form-control-sm" id="df_price" placeholder="単価"></div>
                <div class="col-6"><input type="number" name="stock_quantity" class="form-control form-control-sm" id="df_stock" placeholder="在庫数" step="0.01"></div>
                <div class="col-6"><input type="number" name="min_stock" class="form-control form-control-sm" id="df_min" placeholder="最低在庫"></div>
                <div class="col-6"><input type="text" name="manufacturer" class="form-control form-control-sm" id="df_mfr" placeholder="メーカー"></div>
            </div></div>
            <div class="card-footer"><button type="submit" class="btn btn-primary btn-sm w-100">保存</button></div></form>
        </div>
    </div>
</div>
<script>
function editDrug(d){document.getElementById('df_id').value=d.id;document.getElementById('df_code').value=d.drug_code;document.getElementById('df_name').value=d.drug_name;document.getElementById('df_generic').value=d.generic_name;document.getElementById('df_cat').value=d.category;document.getElementById('df_unit').value=d.unit;document.getElementById('df_price').value=d.unit_price;document.getElementById('df_stock').value=d.stock_quantity;document.getElementById('df_min').value=d.min_stock;document.getElementById('df_mfr').value=d.manufacturer;}
</script>
