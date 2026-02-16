<?php
/** 検査マスタ */
$auth->requireRole([ROLE_ADMIN]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = ['test_code'=>trim($_POST['test_code']??''),'test_name'=>trim($_POST['test_name']),'category'=>trim($_POST['category']??''),'unit'=>trim($_POST['unit']??''),'reference_low'=>trim($_POST['reference_low']??''),'reference_high'=>trim($_POST['reference_high']??''),'unit_price'=>(int)$_POST['unit_price']];
    $tid = (int)($_POST['id'] ?? 0);
    if ($tid) { $db->update('test_master', $data, 'id=?', [$tid]); } else { $data['created_at']=date('Y-m-d H:i:s'); $db->insert('test_master', $data); }
    redirect("?page=master_tests");
}
$tests = $db->fetchAll("SELECT * FROM test_master WHERE is_active=1 ORDER BY category, test_name");
?>
<div class="fade-in">
    <h4 class="fw-bold mb-3"><i class="bi bi-eyedropper me-2"></i>検査マスタ</h4>
    <div class="row g-3">
        <div class="col-lg-8"><div class="card"><div class="card-body p-0"><div class="table-responsive">
            <table class="table table-hover table-sm mb-0"><thead><tr><th>コード</th><th>検査名</th><th>カテゴリ</th><th>単位</th><th>基準値</th><th>単価</th></tr></thead><tbody>
            <?php foreach ($tests as $t): ?>
            <tr onclick="editTest(<?= htmlspecialchars(json_encode($t)) ?>)" style="cursor:pointer">
                <td><code><?= h($t['test_code']) ?></code></td><td><?= h($t['test_name']) ?></td><td><?= h($t['category']) ?></td>
                <td><?= h($t['unit']) ?></td><td><?= h($t['reference_low']) ?> - <?= h($t['reference_high']) ?></td><td><?= formatCurrency($t['unit_price']) ?></td>
            </tr><?php endforeach; ?>
            </tbody></table>
        </div></div></div></div>
        <div class="col-lg-4"><form method="POST" class="card"><div class="card-header">検査登録・編集</div><div class="card-body"><div class="row g-2">
            <input type="hidden" name="id" id="tf_id">
            <div class="col-12"><input type="text" name="test_code" class="form-control form-control-sm" id="tf_code" placeholder="コード"></div>
            <div class="col-12"><input type="text" name="test_name" class="form-control form-control-sm" id="tf_name" placeholder="検査名" required></div>
            <div class="col-6"><input type="text" name="category" class="form-control form-control-sm" id="tf_cat" placeholder="カテゴリ"></div>
            <div class="col-6"><input type="text" name="unit" class="form-control form-control-sm" id="tf_unit" placeholder="単位"></div>
            <div class="col-6"><input type="text" name="reference_low" class="form-control form-control-sm" id="tf_low" placeholder="基準値(低)"></div>
            <div class="col-6"><input type="text" name="reference_high" class="form-control form-control-sm" id="tf_high" placeholder="基準値(高)"></div>
            <div class="col-12"><input type="number" name="unit_price" class="form-control form-control-sm" id="tf_price" placeholder="単価"></div>
        </div></div><div class="card-footer"><button type="submit" class="btn btn-primary btn-sm w-100">保存</button></div></form></div>
    </div>
</div>
<script>function editTest(t){document.getElementById('tf_id').value=t.id;document.getElementById('tf_code').value=t.test_code;document.getElementById('tf_name').value=t.test_name;document.getElementById('tf_cat').value=t.category;document.getElementById('tf_unit').value=t.unit;document.getElementById('tf_low').value=t.reference_low;document.getElementById('tf_high').value=t.reference_high;document.getElementById('tf_price').value=t.unit_price;}</script>
