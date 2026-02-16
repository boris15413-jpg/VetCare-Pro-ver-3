<?php
/** 会計管理 */
$filter = $_GET['filter'] ?? 'all';
$where = "1=1";
if ($filter === 'unpaid') $where = "i.payment_status = 'unpaid'";
elseif ($filter === 'paid') $where = "i.payment_status = 'paid'";

$invoices_list = $db->fetchAll("SELECT i.*, p.name as pname, p.patient_code, o.name as oname FROM invoices i JOIN patients p ON i.patient_id=p.id JOIN owners o ON i.owner_id=o.id WHERE {$where} ORDER BY i.created_at DESC LIMIT 50");
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-receipt me-2"></i>会計管理</h4>
        <a href="?page=invoice_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規会計</a>
    </div>
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $filter==='all'?'active':'' ?>" href="?page=invoices&filter=all">全て</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='unpaid'?'active':'' ?>" href="?page=invoices&filter=unpaid">未払い</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='paid'?'active':'' ?>" href="?page=invoices&filter=paid">支払済</a></li>
    </ul>
    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0"><thead><tr><th>請求番号</th><th>患畜</th><th>飼い主</th><th class="d-none d-md-table-cell">小計</th><th>合計</th><th>状態</th><th></th></tr></thead><tbody>
        <?php foreach ($invoices_list as $inv): ?>
        <tr>
            <td><code><?= h($inv['invoice_number']) ?></code></td>
            <td><?= h($inv['pname']) ?></td>
            <td><?= h($inv['oname']) ?></td>
            <td class="d-none d-md-table-cell"><?= formatCurrency($inv['subtotal']) ?></td>
            <td><strong><?= formatCurrency($inv['total']) ?></strong></td>
            <td><span class="badge bg-<?= $inv['payment_status']==='paid'?'success':'danger' ?>"><?= $inv['payment_status']==='paid'?'支払済':'未払い' ?></span></td>
            <td><a href="?page=invoice_print&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-printer"></i></a></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div></div></div>
</div>
