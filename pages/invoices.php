<?php
/** 会計管理 - 保険対応・レセプト連動 */
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['q'] ?? '';
$where = "1=1";
$params = [];

if ($filter === 'unpaid') { $where .= " AND i.payment_status = 'unpaid'"; }
elseif ($filter === 'paid') { $where .= " AND i.payment_status = 'paid'"; }

if ($search) {
    $where .= " AND (p.name LIKE ? OR o.name LIKE ? OR i.invoice_number LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s]);
}

$page_num = max(1, (int)($_GET['p'] ?? 1));
$perPage = 30;
$total = $db->fetch("SELECT COUNT(*) as cnt FROM invoices i JOIN patients p ON i.patient_id=p.id JOIN owners o ON i.owner_id=o.id WHERE {$where}", $params)['cnt'];
$offset = ($page_num - 1) * $perPage;

$invoices_list = $db->fetchAll("
    SELECT i.*, p.name as pname, p.patient_code, p.insurance_company, p.insurance_rate,
    o.name as oname,
    (SELECT COUNT(*) FROM insurance_claims ic WHERE ic.invoice_id = i.id) as has_claim
    FROM invoices i 
    JOIN patients p ON i.patient_id=p.id 
    JOIN owners o ON i.owner_id=o.id 
    WHERE {$where} 
    ORDER BY i.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
", $params);

// Summary stats (focused on actionable items)
$insuranceEnabled = getSetting('feature_insurance', '1') === '1';
$stats = [
    'unpaid_total' => $db->fetch("SELECT COALESCE(SUM(total),0) as s FROM invoices WHERE payment_status='unpaid'")['s'],
    'unpaid_count' => $db->fetch("SELECT COUNT(*) as c FROM invoices WHERE payment_status='unpaid'")['c'],
    'today_count' => $db->fetch("SELECT COUNT(*) as c FROM invoices WHERE DATE(created_at) = ?", [date('Y-m-d')])['c'],
    'today_total' => $db->fetch("SELECT COALESCE(SUM(total),0) as s FROM invoices WHERE DATE(created_at) = ? AND payment_status='paid'", [date('Y-m-d')])['s'],
];
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-receipt me-2"></i>会計管理</h4>
            <small class="text-muted">請求書発行・保険請求・レセプト連携</small>
        </div>
        <div class="d-flex gap-2">
            <a href="?page=invoice_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規会計</a>
            <a href="?page=insurance_claims" class="btn btn-outline-info btn-sm"><i class="bi bi-file-earmark-medical me-1"></i>レセプト管理</a>
            <a href="index.php?page=accounting_display" target="_blank" class="btn btn-outline-secondary btn-sm" title="会計待合表示"><i class="bi bi-tv me-1"></i>会計表示</a>
        </div>
    </div>

    <!-- Stats (actionable) -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="stat-card bg-gradient-danger"><div class="stat-value"><?= formatCurrency($stats['unpaid_total']) ?></div><div class="stat-label">未払い合計 (<?= $stats['unpaid_count'] ?>件)</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card bg-gradient-primary"><div class="stat-value"><?= formatCurrency($stats['today_total']) ?></div><div class="stat-label">本日の入金</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card bg-gradient-success"><div class="stat-value"><?= $stats['today_count'] ?></div><div class="stat-label">本日の会計数</div></div></div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-gradient-info">
                <div class="stat-value"><?= $total ?></div>
                <div class="stat-label">総会計件数</div>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                <input type="hidden" name="page" value="invoices">
                <div class="search-box flex-fill" style="min-width:200px">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="患畜名・飼い主名・請求番号で検索" value="<?= h($search) ?>">
                </div>
                <div class="btn-group">
                    <a class="btn btn-sm <?= $filter==='all'?'btn-primary':'btn-outline-secondary' ?>" href="?page=invoices&filter=all&q=<?= urlencode($search) ?>">全て</a>
                    <a class="btn btn-sm <?= $filter==='unpaid'?'btn-danger':'btn-outline-secondary' ?>" href="?page=invoices&filter=unpaid&q=<?= urlencode($search) ?>">未払い</a>
                    <a class="btn btn-sm <?= $filter==='paid'?'btn-success':'btn-outline-secondary' ?>" href="?page=invoices&filter=paid&q=<?= urlencode($search) ?>">支払済</a>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0"><div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>請求番号</th><th>患畜</th><th>飼い主</th>
                    <th class="text-end d-none d-md-table-cell">小計</th>
                    <th class="text-end d-none d-lg-table-cell">保険負担</th>
                    <th class="text-end">合計</th><th>状態</th><th>保険</th><th></th>
                </tr></thead>
                <tbody>
                <?php if (empty($invoices_list)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">会計データがありません</td></tr>
                <?php else: ?>
                <?php foreach ($invoices_list as $inv): ?>
                <tr>
                    <td>
                        <code><?= h($inv['invoice_number']) ?></code>
                        <br><small class="text-muted"><?= formatDate($inv['created_at'], 'm/d H:i') ?></small>
                    </td>
                    <td>
                        <a href="?page=patient_detail&id=<?= $inv['patient_id'] ?>" class="text-decoration-none">
                            <strong><?= h($inv['pname']) ?></strong>
                        </a>
                        <br><small class="text-muted"><?= h($inv['patient_code']) ?></small>
                    </td>
                    <td><?= h($inv['oname']) ?></td>
                    <td class="text-end d-none d-md-table-cell"><?= formatCurrency($inv['subtotal']) ?></td>
                    <td class="text-end d-none d-lg-table-cell">
                        <?php if ($inv['insurance_covered'] > 0): ?>
                        <span class="text-info fw-bold"><?= formatCurrency($inv['insurance_covered']) ?></span>
                        <?php else: ?>
                        <small class="text-muted">-</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><strong><?= formatCurrency($inv['total']) ?></strong></td>
                    <td>
                        <span class="badge bg-<?= $inv['payment_status']==='paid'?'success':'danger' ?>">
                            <i class="bi bi-<?= $inv['payment_status']==='paid'?'check-circle':'exclamation-circle' ?> me-1"></i>
                            <?= $inv['payment_status']==='paid'?'支払済':'未払い' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($inv['insurance_company']): ?>
                            <?php if ($inv['has_claim'] > 0): ?>
                            <span class="badge bg-info"><i class="bi bi-file-earmark-check me-1"></i>請求済</span>
                            <?php else: ?>
                            <a href="?page=insurance_claim_form&invoice_id=<?= $inv['id'] ?>" class="badge bg-warning text-dark text-decoration-none" title="レセプト作成">
                                <i class="bi bi-file-earmark-plus me-1"></i>未請求
                            </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <small class="text-muted">-</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="?page=invoice_print&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="印刷"><i class="bi bi-printer"></i></a>
                            <?php if ($inv['insurance_company'] && $inv['has_claim'] == 0): ?>
                            <a href="?page=insurance_claim_form&invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-info" title="レセプト作成"><i class="bi bi-file-earmark-medical"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div></div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted"><?= $total ?>件中 <?= min($offset + 1, $total) ?>-<?= min($offset + $perPage, $total) ?>件</small>
            <?= pagination($total, $page_num, $perPage, '?page=invoices&filter=' . urlencode($filter) . '&q=' . urlencode($search)) ?>
        </div>
    </div>
</div>
