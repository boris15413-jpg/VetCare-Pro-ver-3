<?php
/** 保険請求（レセプト）管理 */
$filter = $_GET['status'] ?? 'all';
$where = "1=1";
$params = [];
if ($filter !== 'all') { $where .= " AND ic.claim_status = ?"; $params[] = $filter; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    $claimId = (int)($_POST['claim_id'] ?? 0);
    if ($action === 'update_status' && $claimId) {
        $newStatus = $_POST['new_status'];
        $updates = ['claim_status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')];
        if ($newStatus === 'submitted') $updates['submitted_at'] = date('Y-m-d H:i:s');
        if ($newStatus === 'approved') $updates['approved_at'] = date('Y-m-d H:i:s');
        if ($newStatus === 'paid') $updates['paid_at'] = date('Y-m-d H:i:s');
        $db->update('insurance_claims', $updates, 'id = ?', [$claimId]);
        setFlash('success', 'ステータスを更新しました');
        redirect('?page=insurance_claims&status=' . $filter);
    }
}

$claims = $db->fetchAll("
    SELECT ic.*, p.name as patient_name, p.patient_code, ip.company_name, ip.policy_number, ip.coverage_rate
    FROM insurance_claims ic
    JOIN patients p ON ic.patient_id = p.id
    JOIN insurance_policies ip ON ic.policy_id = ip.id
    WHERE {$where}
    ORDER BY ic.created_at DESC LIMIT 100
", $params);

$statusLabels = [
    'draft' => ['下書き', 'secondary', 'bi-pencil'],
    'submitted' => ['請求済', 'primary', 'bi-send'],
    'under_review' => ['審査中', 'warning', 'bi-hourglass-split'],
    'approved' => ['承認', 'success', 'bi-check-circle'],
    'rejected' => ['却下', 'danger', 'bi-x-circle'],
    'paid' => ['入金済', 'info', 'bi-cash-stack'],
];

// Summary stats
$stats = [
    'total' => $db->fetch("SELECT COUNT(*) as c FROM insurance_claims")['c'],
    'draft' => $db->fetch("SELECT COUNT(*) as c FROM insurance_claims WHERE claim_status='draft'")['c'],
    'submitted' => $db->fetch("SELECT COUNT(*) as c FROM insurance_claims WHERE claim_status='submitted'")['c'],
    'approved' => $db->fetch("SELECT COUNT(*) as c FROM insurance_claims WHERE claim_status='approved'")['c'],
    'paid_amount' => $db->fetch("SELECT COALESCE(SUM(covered_amount),0) as s FROM insurance_claims WHERE claim_status='paid'")['s'],
];
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-medical me-2"></i>保険請求（レセプト）管理</h4>
            <small class="text-muted">ペット保険の請求書作成・提出・追跡</small>
        </div>
        <a href="?page=insurance_claim_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規レセプト作成</a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat-card bg-gradient-primary"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">総請求数</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card bg-gradient-warning"><div class="stat-value"><?= $stats['draft'] + $stats['submitted'] ?></div><div class="stat-label">処理中</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card bg-gradient-success"><div class="stat-value"><?= $stats['approved'] ?></div><div class="stat-label">承認済</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card bg-gradient-info"><div class="stat-value"><?= formatCurrency($stats['paid_amount']) ?></div><div class="stat-label">入金済合計</div></div></div>
    </div>

    <!-- Filter tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $filter==='all'?'active':'' ?>" href="?page=insurance_claims&status=all">全て</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='draft'?'active':'' ?>" href="?page=insurance_claims&status=draft">下書き</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='submitted'?'active':'' ?>" href="?page=insurance_claims&status=submitted">請求済</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='approved'?'active':'' ?>" href="?page=insurance_claims&status=approved">承認</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='paid'?'active':'' ?>" href="?page=insurance_claims&status=paid">入金済</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='rejected'?'active':'' ?>" href="?page=insurance_claims&status=rejected">却下</a></li>
    </ul>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>請求番号</th><th>患畜</th><th>保険会社</th><th>診断名</th>
                        <th class="text-end">医療費</th><th class="text-end">保険負担</th><th>状態</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($claims)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">レセプトデータがありません</td></tr>
                    <?php else: ?>
                    <?php foreach ($claims as $cl): ?>
                    <tr>
                        <td><code><?= h($cl['claim_number']) ?></code><br><small class="text-muted"><?= formatDate($cl['claim_date']) ?></small></td>
                        <td><strong><?= h($cl['patient_name']) ?></strong><br><small class="text-muted"><?= h($cl['patient_code']) ?></small></td>
                        <td><?= h($cl['company_name']) ?><br><small class="text-muted">証券: <?= h($cl['policy_number']) ?> (<?= $cl['coverage_rate'] ?>%)</small></td>
                        <td><?= h($cl['diagnosis_name']) ?></td>
                        <td class="text-end fw-bold"><?= formatCurrency($cl['total_medical_fee']) ?></td>
                        <td class="text-end text-primary fw-bold"><?= formatCurrency($cl['covered_amount']) ?></td>
                        <td>
                            <?php $st = $statusLabels[$cl['claim_status']] ?? ['不明','secondary','bi-question']; ?>
                            <span class="badge bg-<?= $st[1] ?>"><i class="bi <?= $st[2] ?> me-1"></i><?= $st[0] ?></span>
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="?page=insurance_claim_form&id=<?= $cl['id'] ?>"><i class="bi bi-pencil me-2"></i>編集</a></li>
                                    <li><a class="dropdown-item" href="?page=recept_print&id=<?= $cl['id'] ?>" target="_blank"><i class="bi bi-printer me-2"></i>院内用印刷</a></li>
                                    <li><a class="dropdown-item" href="?page=insurance_export&id=<?= $cl['id'] ?>" target="_blank"><i class="bi bi-building me-2"></i>保険会社提出用</a></li>
                                    <li><a class="dropdown-item" href="?page=insurance_export&id=<?= $cl['id'] ?>&format=csv"><i class="bi bi-file-earmark-spreadsheet me-2"></i>CSV出力</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <?php if ($cl['claim_status'] === 'draft'): ?>
                                    <li><form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="claim_id" value="<?= $cl['id'] ?>"><input type="hidden" name="new_status" value="submitted"><button type="submit" class="dropdown-item text-primary"><i class="bi bi-send me-2"></i>請求提出</button></form></li>
                                    <?php endif; ?>
                                    <?php if ($cl['claim_status'] === 'submitted'): ?>
                                    <li><form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="claim_id" value="<?= $cl['id'] ?>"><input type="hidden" name="new_status" value="approved"><button type="submit" class="dropdown-item text-success"><i class="bi bi-check-circle me-2"></i>承認</button></form></li>
                                    <?php endif; ?>
                                    <?php if ($cl['claim_status'] === 'approved'): ?>
                                    <li><form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="claim_id" value="<?= $cl['id'] ?>"><input type="hidden" name="new_status" value="paid"><button type="submit" class="dropdown-item text-info"><i class="bi bi-cash-stack me-2"></i>入金確認</button></form></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
