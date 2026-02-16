<?php
/** 見積もり管理 */
$filter = $_GET['status'] ?? 'all';
$where = "1=1"; $params = [];
if ($filter !== 'all') { $where .= " AND e.status = ?"; $params[] = $filter; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'convert' && ($estId = (int)($_POST['estimate_id'] ?? 0))) {
        // Convert estimate to invoice
        $est = $db->fetch("SELECT * FROM estimates WHERE id=?", [$estId]);
        if ($est) {
            $invNum = generateReceiptNumber();
            $invId = $db->insert('invoices', [
                'invoice_number' => $invNum, 'patient_id' => $est['patient_id'], 'owner_id' => $est['owner_id'],
                'estimate_id' => $estId, 'subtotal' => $est['subtotal'], 'tax' => $est['tax'],
                'total' => $est['total'], 'insurance_covered' => $est['insurance_estimate'],
                'payment_status' => 'unpaid', 'created_by' => $auth->currentUserId(), 'created_at' => date('Y-m-d H:i:s'),
            ]);
            // Copy items
            $estItems = $db->fetchAll("SELECT * FROM estimate_items WHERE estimate_id=?", [$estId]);
            foreach ($estItems as $ei) {
                $db->insert('invoice_items', [
                    'invoice_id' => $invId, 'item_name' => $ei['item_name'], 'category' => $ei['category'],
                    'quantity' => $ei['quantity'], 'unit' => $ei['unit'], 'unit_price' => $ei['unit_price'],
                    'amount' => $ei['amount'], 'tax_rate' => 10,
                ]);
            }
            $db->update('estimates', ['status' => 'converted', 'updated_at' => date('Y-m-d H:i:s')], 'id=?', [$estId]);
            setFlash('success', '見積もりから会計を作成しました（' . $invNum . '）');
            redirect('?page=invoice_form&invoice_id=' . $invId);
        }
    }
}

$estimates = $db->fetchAll("
    SELECT e.*, p.name as pname, p.patient_code, o.name as oname
    FROM estimates e JOIN patients p ON e.patient_id=p.id JOIN owners o ON e.owner_id=o.id
    WHERE {$where} ORDER BY e.created_at DESC LIMIT 50
", $params);
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-calculator me-2"></i>見積もり管理</h4>
            <small class="text-muted">治療前の概算見積もり作成・管理</small>
        </div>
        <a href="?page=estimate_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規見積もり</a>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $filter==='all'?'active':'' ?>" href="?page=estimates&status=all">全て</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='draft'?'active':'' ?>" href="?page=estimates&status=draft">下書き</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='sent'?'active':'' ?>" href="?page=estimates&status=sent">提示済</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='approved'?'active':'' ?>" href="?page=estimates&status=approved">承認</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='converted'?'active':'' ?>" href="?page=estimates&status=converted">会計変換済</a></li>
    </ul>

    <div class="card">
        <div class="card-body p-0"><div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>見積番号</th><th>患畜</th><th>飼い主</th><th>タイトル</th><th class="text-end">合計</th><th class="text-end">保険概算</th><th>状態</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($estimates)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">見積もりデータがありません</td></tr>
                <?php else: ?>
                <?php foreach ($estimates as $est): ?>
                <tr>
                    <td><code><?= h($est['estimate_number']) ?></code><br><small class="text-muted"><?= formatDate($est['created_at']) ?></small></td>
                    <td><strong><?= h($est['pname']) ?></strong><br><small class="text-muted"><?= h($est['patient_code']) ?></small></td>
                    <td><?= h($est['oname']) ?></td>
                    <td><?= h($est['title']) ?></td>
                    <td class="text-end fw-bold"><?= formatCurrency($est['total']) ?></td>
                    <td class="text-end text-info"><?= $est['insurance_estimate'] > 0 ? formatCurrency($est['insurance_estimate']) : '-' ?></td>
                    <td>
                        <?php
                        $stMap = ['draft'=>['下書き','secondary'], 'sent'=>['提示済','primary'], 'approved'=>['承認','success'], 'rejected'=>['却下','danger'], 'converted'=>['変換済','info']];
                        $st = $stMap[$est['status']] ?? ['不明','secondary'];
                        ?>
                        <span class="badge bg-<?= $st[1] ?>"><?= $st[0] ?></span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="?page=estimate_form&id=<?= $est['id'] ?>" class="btn btn-sm btn-outline-primary" title="編集"><i class="bi bi-pencil"></i></a>
                            <?php if (in_array($est['status'], ['draft', 'sent', 'approved'])): ?>
                            <form method="POST" class="d-inline"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="convert">
                                <input type="hidden" name="estimate_id" value="<?= $est['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="会計に変換" onclick="return confirm('この見積もりから会計を作成しますか？')"><i class="bi bi-arrow-right-circle"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>
