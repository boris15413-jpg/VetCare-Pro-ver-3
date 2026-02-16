<?php
/** オーダー管理 - 完了バグ修正・UI大幅改善 */

// Handle status change via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_action'])) {
    if (!verify_csrf()) {
        setFlash('danger', 'CSRF検証に失敗しました');
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $orderAction = $_POST['order_action'];
        
        if ($orderId > 0) {
            switch ($orderAction) {
                case 'start':
                    $db->update('orders', [
                        'status' => 'in_progress',
                        'executed_by' => $auth->currentUserId()
                    ], 'id=?', [$orderId]);
                    setFlash('success', 'オーダーを実施中に変更しました');
                    break;
                case 'complete':
                    $db->update('orders', [
                        'status' => 'completed',
                        'executed_by' => $auth->currentUserId(),
                        'executed_at' => date('Y-m-d H:i:s')
                    ], 'id=?', [$orderId]);
                    setFlash('success', 'オーダーを完了しました');
                    break;
                case 'cancel':
                    $db->update('orders', [
                        'status' => 'cancelled'
                    ], 'id=?', [$orderId]);
                    setFlash('info', 'オーダーをキャンセルしました');
                    break;
            }
        }
        redirect('index.php?page=orders&filter=' . urlencode($_GET['filter'] ?? 'pending'));
    }
}

$filter = $_GET['filter'] ?? 'pending';
$type = $_GET['type'] ?? '';
$where = "1=1";
$params = [];
if ($filter && $filter !== 'all') { $where .= " AND od.status = ?"; $params[] = $filter; }
if ($type) { $where .= " AND od.order_type = ?"; $params[] = $type; }

$orders_list = $db->fetchAll("SELECT od.*, p.name as pname, p.patient_code, p.id as pid, s.name as ordered_name, s2.name as exec_name
    FROM orders od JOIN patients p ON od.patient_id=p.id JOIN staff s ON od.ordered_by=s.id LEFT JOIN staff s2 ON od.executed_by=s2.id
    WHERE {$where} ORDER BY 
    CASE od.priority WHEN 'stat' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END,
    od.ordered_at DESC LIMIT 200", $params);

// Status counts
$counts = [];
$countRows = $db->fetchAll("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status");
foreach ($countRows as $r) $counts[$r['status']] = $r['cnt'];
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-list-check me-2"></i>オーダー管理</h4>
            <small class="text-muted">処方・検査・処置のオーダーを管理します</small>
        </div>
        <a href="?page=order_form" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>新規オーダー</a>
    </div>

    <!-- Status counts -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <a href="?page=orders&filter=pending" class="text-decoration-none d-block">
                <div class="stat-card bg-gradient-warning">
                    <div class="d-flex justify-content-between">
                        <div><div class="stat-value"><?= $counts['pending'] ?? 0 ?></div><div class="stat-label">未実施</div></div>
                        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="?page=orders&filter=in_progress" class="text-decoration-none d-block">
                <div class="stat-card bg-gradient-info">
                    <div class="d-flex justify-content-between">
                        <div><div class="stat-value"><?= $counts['in_progress'] ?? 0 ?></div><div class="stat-label">実施中</div></div>
                        <div class="stat-icon"><i class="bi bi-play-circle"></i></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="?page=orders&filter=completed" class="text-decoration-none d-block">
                <div class="stat-card bg-gradient-success">
                    <div class="d-flex justify-content-between">
                        <div><div class="stat-value"><?= $counts['completed'] ?? 0 ?></div><div class="stat-label">完了</div></div>
                        <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="?page=orders&filter=all" class="text-decoration-none d-block">
                <div class="stat-card bg-gradient-purple">
                    <div class="d-flex justify-content-between">
                        <div><div class="stat-value"><?= array_sum($counts) ?></div><div class="stat-label">全件</div></div>
                        <div class="stat-icon"><i class="bi bi-archive"></i></div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="fw-bold small text-muted me-1">状態:</span>
                <a href="?page=orders&filter=pending<?= $type?"&type=$type":'' ?>" class="btn btn-sm <?= $filter==='pending'?'btn-warning':'btn-outline-warning' ?>"><i class="bi bi-hourglass-split me-1"></i>未実施</a>
                <a href="?page=orders&filter=in_progress<?= $type?"&type=$type":'' ?>" class="btn btn-sm <?= $filter==='in_progress'?'btn-info':'btn-outline-info' ?>"><i class="bi bi-play-fill me-1"></i>実施中</a>
                <a href="?page=orders&filter=completed<?= $type?"&type=$type":'' ?>" class="btn btn-sm <?= $filter==='completed'?'btn-success':'btn-outline-success' ?>"><i class="bi bi-check-lg me-1"></i>完了</a>
                <a href="?page=orders&filter=all<?= $type?"&type=$type":'' ?>" class="btn btn-sm <?= $filter==='all'?'btn-secondary':'btn-outline-secondary' ?>">全て</a>
                <span class="border-start mx-2" style="height:24px;"></span>
                <span class="fw-bold small text-muted me-1">種別:</span>
                <a href="?page=orders&filter=<?= $filter ?>&type=prescription" class="btn btn-sm <?= $type==='prescription'?'btn-primary':'btn-outline-primary' ?>"><i class="bi bi-capsule me-1"></i>処方</a>
                <a href="?page=orders&filter=<?= $filter ?>&type=test" class="btn btn-sm <?= $type==='test'?'btn-primary':'btn-outline-primary' ?>"><i class="bi bi-eyedropper me-1"></i>検査</a>
                <a href="?page=orders&filter=<?= $filter ?>&type=procedure" class="btn btn-sm <?= $type==='procedure'?'btn-primary':'btn-outline-primary' ?>"><i class="bi bi-tools me-1"></i>処置</a>
                <?php if ($type): ?>
                <a href="?page=orders&filter=<?= $filter ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-x me-1"></i>種別解除</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Order List -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($orders_list)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox d-block"></i>
                <h5>オーダーがありません</h5>
                <p>この条件に該当するオーダーはありません</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px"></th>
                            <th>日時</th>
                            <th>患畜</th>
                            <th>種別</th>
                            <th>内容</th>
                            <th class="d-none d-md-table-cell">数量</th>
                            <th class="d-none d-md-table-cell text-end">金額</th>
                            <th>状態</th>
                            <th style="min-width:200px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders_list as $od): ?>
                    <tr class="<?= $od['priority']==='stat'?'table-danger':($od['priority']==='urgent'?'table-warning':'') ?>">
                        <td class="text-center">
                            <?php if ($od['priority'] === 'stat'): ?>
                                <span class="badge bg-danger" title="至急"><i class="bi bi-exclamation-triangle-fill"></i></span>
                            <?php elseif ($od['priority'] === 'urgent'): ?>
                                <span class="badge bg-warning text-dark" title="緊急"><i class="bi bi-exclamation-circle-fill"></i></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= formatDate($od['ordered_at'],'m/d') ?></strong>
                            <br><small class="text-muted"><?= formatDate($od['ordered_at'],'H:i') ?></small>
                        </td>
                        <td>
                            <a href="?page=patient_detail&id=<?= $od['pid'] ?>" class="text-decoration-none fw-bold"><?= h($od['pname']) ?></a>
                            <br><small class="text-muted"><?= h($od['patient_code']) ?></small>
                        </td>
                        <td>
                            <?php
                            $typeIcon = $od['order_type']==='prescription' ? 'capsule' : ($od['order_type']==='test' ? 'eyedropper' : 'tools');
                            $typeColor = $od['order_type']==='prescription' ? 'success' : ($od['order_type']==='test' ? 'info' : 'warning');
                            $typeName = $od['order_type']==='prescription' ? '処方' : ($od['order_type']==='test' ? '検査' : '処置');
                            ?>
                            <span class="badge bg-<?= $typeColor ?>"><i class="bi bi-<?= $typeIcon ?> me-1"></i><?= $typeName ?></span>
                        </td>
                        <td>
                            <strong><?= h($od['order_name']) ?></strong>
                            <?php if ($od['order_detail']): ?>
                            <br><small class="text-muted"><?= h(mb_substr($od['order_detail'],0,40)) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell"><?= $od['quantity'] ?> <?= h($od['unit']) ?></td>
                        <td class="d-none d-md-table-cell text-end"><?= formatCurrency($od['total_price']) ?></td>
                        <td><?= getOrderStatusBadge($od['status']) ?></td>
                        <td>
                            <form method="POST" class="d-inline-flex gap-1 flex-wrap no-navigate">
                                <?= csrf_field() ?>
                                <input type="hidden" name="order_id" value="<?= $od['id'] ?>">
                                
                                <?php if ($od['status'] === 'pending'): ?>
                                    <button type="submit" name="order_action" value="start" 
                                        class="btn btn-info btn-sm" title="実施開始">
                                        <i class="bi bi-play-fill me-1"></i>開始
                                    </button>
                                    <button type="submit" name="order_action" value="complete" 
                                        class="btn btn-success btn-sm" title="即完了">
                                        <i class="bi bi-check-lg me-1"></i>完了
                                    </button>
                                    <button type="submit" name="order_action" value="cancel" 
                                        class="btn btn-outline-secondary btn-sm" title="キャンセル"
                                        onclick="return confirm('このオーダーをキャンセルしますか？')">
                                        <i class="bi bi-x"></i>
                                    </button>
                                <?php elseif ($od['status'] === 'in_progress'): ?>
                                    <button type="submit" name="order_action" value="complete" 
                                        class="btn btn-success btn-sm" title="完了">
                                        <i class="bi bi-check-lg me-1"></i>完了
                                    </button>
                                <?php endif; ?>
                                
                                <a href="?page=order_form&id=<?= $od['id'] ?>" class="btn btn-outline-primary btn-sm" title="編集">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php if (!empty($orders_list)): ?>
        <div class="card-footer">
            <small class="text-muted">表示: <?= count($orders_list) ?>件</small>
        </div>
        <?php endif; ?>
    </div>
</div>
