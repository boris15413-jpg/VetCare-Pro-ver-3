<?php
/** オーダー管理 */
$filter = $_GET['filter'] ?? 'pending';
$type = $_GET['type'] ?? '';
$where = "1=1";
$params = [];
if ($filter && $filter !== 'all') { $where .= " AND od.status = ?"; $params[] = $filter; }
if ($type) { $where .= " AND od.order_type = ?"; $params[] = $type; }

$orders_list = $db->fetchAll("SELECT od.*, p.name as pname, p.patient_code, s.name as ordered_name, s2.name as exec_name
    FROM orders od JOIN patients p ON od.patient_id=p.id JOIN staff s ON od.ordered_by=s.id LEFT JOIN staff s2 ON od.executed_by=s2.id
    WHERE {$where} ORDER BY od.ordered_at DESC LIMIT 100", $params);
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-list-check me-2"></i>オーダー管理</h4>
        <a href="?page=order_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規オーダー</a>
    </div>
    <div class="card mb-3"><div class="card-body py-2">
        <div class="d-flex gap-2 flex-wrap">
            <a href="?page=orders&filter=pending" class="btn btn-sm <?= $filter==='pending'?'btn-warning':'btn-outline-warning' ?>">未実施</a>
            <a href="?page=orders&filter=in_progress" class="btn btn-sm <?= $filter==='in_progress'?'btn-info':'btn-outline-info' ?>">実施中</a>
            <a href="?page=orders&filter=completed" class="btn btn-sm <?= $filter==='completed'?'btn-success':'btn-outline-success' ?>">完了</a>
            <a href="?page=orders&filter=all" class="btn btn-sm <?= $filter==='all'?'btn-secondary':'btn-outline-secondary' ?>">全て</a>
            <span class="border-start mx-2"></span>
            <a href="?page=orders&filter=<?= $filter ?>&type=prescription" class="btn btn-sm <?= $type==='prescription'?'btn-primary':'btn-outline-primary' ?>">処方</a>
            <a href="?page=orders&filter=<?= $filter ?>&type=test" class="btn btn-sm <?= $type==='test'?'btn-primary':'btn-outline-primary' ?>">検査</a>
            <a href="?page=orders&filter=<?= $filter ?>&type=procedure" class="btn btn-sm <?= $type==='procedure'?'btn-primary':'btn-outline-primary' ?>">処置</a>
        </div>
    </div></div>
    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0"><thead><tr><th>日時</th><th>患畜</th><th>種別</th><th>内容</th><th class="d-none d-md-table-cell">数量</th><th class="d-none d-md-table-cell">金額</th><th>状態</th><th>操作</th></tr></thead><tbody>
        <?php foreach ($orders_list as $od): ?>
        <tr>
            <td><small><?= formatDate($od['ordered_at'],'m/d H:i') ?></small></td>
            <td><strong><?= h($od['pname']) ?></strong><br><small class="text-muted"><?= h($od['patient_code']) ?></small></td>
            <td><span class="badge bg-<?= $od['order_type']==='prescription'?'success':($od['order_type']==='test'?'info':'warning') ?>"><?= $od['order_type']==='prescription'?'処方':($od['order_type']==='test'?'検査':'処置') ?></span></td>
            <td><?= h($od['order_name']) ?><br><small class="text-muted"><?= h(mb_substr($od['order_detail'],0,30)) ?></small></td>
            <td class="d-none d-md-table-cell"><?= $od['quantity'] ?> <?= h($od['unit']) ?></td>
            <td class="d-none d-md-table-cell"><?= formatCurrency($od['total_price']) ?></td>
            <td><?= getOrderStatusBadge($od['status']) ?></td>
            <td>
                <?php if ($od['status']==='pending'): ?>
                <a href="?page=order_form&id=<?= $od['id'] ?>&action=execute" class="btn btn-success btn-sm" title="実施"><i class="bi bi-check"></i></a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div></div></div>
</div>
