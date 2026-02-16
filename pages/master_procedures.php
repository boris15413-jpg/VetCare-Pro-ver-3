<?php
/** 処置マスタ */
$auth->requireRole([ROLE_ADMIN]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = ['procedure_code'=>trim($_POST['procedure_code']??''),'procedure_name'=>trim($_POST['procedure_name']),'category'=>trim($_POST['category']??''),'unit_price'=>(int)$_POST['unit_price'],'default_quantity'=>(float)($_POST['default_quantity']??1),'unit'=>trim($_POST['unit']??'')];
    $pid = (int)($_POST['id'] ?? 0);
    if ($pid) { $db->update('procedure_master', $data, 'id=?', [$pid]); } else { $data['created_at']=date('Y-m-d H:i:s'); $db->insert('procedure_master', $data); }
    redirect("?page=master_procedures");
}
$procs = $db->fetchAll("SELECT * FROM procedure_master WHERE is_active=1 ORDER BY category, procedure_name");
?>
<div class="fade-in">
    <h4 class="fw-bold mb-3"><i class="bi bi-tools me-2"></i>処置マスタ</h4>
    <div class="row g-3">
        <div class="col-lg-8"><div class="card"><div class="card-body p-0"><div class="table-responsive">
            <table class="table table-hover table-sm mb-0"><thead><tr><th>コード</th><th>処置名</th><th>カテゴリ</th><th>単位</th><th>単価</th></tr></thead><tbody>
            <?php foreach ($procs as $p): ?>
            <tr onclick="editProc(<?= htmlspecialchars(json_encode($p)) ?>)" style="cursor:pointer">
                <td><code><?= h($p['procedure_code']) ?></code></td><td><?= h($p['procedure_name']) ?></td><td><?= h($p['category']) ?></td>
                <td><?= h($p['unit']) ?></td><td><?= formatCurrency($p['unit_price']) ?></td>
            </tr><?php endforeach; ?>
            </tbody></table>
        </div></div></div></div>
        <div class="col-lg-4"><form method="POST" class="card"><div class="card-header">処置登録・編集</div><div class="card-body"><div class="row g-2">
            <input type="hidden" name="id" id="pf_id">
            <div class="col-12"><input type="text" name="procedure_code" class="form-control form-control-sm" id="pf_code" placeholder="コード"></div>
            <div class="col-12"><input type="text" name="procedure_name" class="form-control form-control-sm" id="pf_name" placeholder="処置名" required></div>
            <div class="col-6"><input type="text" name="category" class="form-control form-control-sm" id="pf_cat" placeholder="カテゴリ"></div>
            <div class="col-6"><input type="text" name="unit" class="form-control form-control-sm" id="pf_unit" placeholder="単位"></div>
            <div class="col-6"><input type="number" name="unit_price" class="form-control form-control-sm" id="pf_price" placeholder="単価"></div>
            <div class="col-6"><input type="number" name="default_quantity" class="form-control form-control-sm" id="pf_qty" placeholder="既定数量" step="0.01" value="1"></div>
        </div></div><div class="card-footer"><button type="submit" class="btn btn-primary btn-sm w-100">保存</button></div></form></div>
    </div>
</div>
<script>function editProc(p){document.getElementById('pf_id').value=p.id;document.getElementById('pf_code').value=p.procedure_code;document.getElementById('pf_name').value=p.procedure_name;document.getElementById('pf_cat').value=p.category;document.getElementById('pf_unit').value=p.unit;document.getElementById('pf_price').value=p.unit_price;document.getElementById('pf_qty').value=p.default_quantity;}</script>
