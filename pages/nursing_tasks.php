<?php
/** 看護タスク管理 */
$filter = $_GET['filter'] ?? 'pending';
$today = date('Y-m-d');
$where = "1=1";
if ($filter === 'pending') $where = "nt.status = 'pending'";
elseif ($filter === 'today') $where = "DATE(nt.scheduled_at) = '{$today}'";
elseif ($filter === 'completed') $where = "nt.status = 'completed'";

$tasks = $db->fetchAll("SELECT nt.*, p.name as pname, p.patient_code, p.species, s1.name as assigned_name, s2.name as created_name
    FROM nursing_tasks nt JOIN patients p ON nt.patient_id=p.id LEFT JOIN staff s1 ON nt.assigned_to=s1.id LEFT JOIN staff s2 ON nt.created_by=s2.id
    WHERE {$where} ORDER BY nt.priority DESC, nt.scheduled_at ASC LIMIT 50");

// タスク完了処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_task'])) {
    $db->update('nursing_tasks', ['status'=>'completed','completed_at'=>date('Y-m-d H:i:s'),'completed_by'=>$auth->currentUserId()], 'id=?', [(int)$_POST['complete_task']]);
    redirect("?page=nursing_tasks&filter={$filter}");
}
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-check2-square me-2"></i>看護タスク管理</h4>
    </div>
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $filter==='pending'?'active':'' ?>" href="?page=nursing_tasks&filter=pending">未完了</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='today'?'active':'' ?>" href="?page=nursing_tasks&filter=today">今日</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='completed'?'active':'' ?>" href="?page=nursing_tasks&filter=completed">完了済</a></li>
    </ul>
    <div class="card"><div class="card-body p-0">
        <?php if (empty($tasks)): ?>
        <div class="text-center text-muted py-5">タスクがありません</div>
        <?php else: ?>
        <?php foreach ($tasks as $tk): ?>
        <div class="task-item task-priority-<?= h($tk['priority']) ?> <?= $tk['status']==='completed'?'completed':'' ?>">
            <form method="POST" class="d-inline">
                <?php if ($tk['status']!=='completed'): ?>
                <button type="submit" name="complete_task" value="<?= $tk['id'] ?>" class="btn btn-sm btn-outline-success" title="完了"><i class="bi bi-check-lg"></i></button>
                <?php else: ?>
                <i class="bi bi-check-circle-fill text-success"></i>
                <?php endif; ?>
            </form>
            <div class="flex-grow-1">
                <div class="task-name fw-bold"><?= h($tk['task_name']) ?></div>
                <small class="text-muted"><?= h($tk['pname']) ?> (<?= h(getSpeciesName($tk['species'])) ?>) | <?= h($tk['task_detail']) ?></small><br>
                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= formatDateTime($tk['scheduled_at']) ?>
                    <?php if ($tk['recurrence'] !== 'none'): ?><span class="badge bg-light text-dark ms-1"><?= h($tk['recurrence']) ?></span><?php endif; ?>
                </small>
            </div>
            <?php if ($tk['assigned_name']): ?>
            <small class="badge bg-info"><?= h($tk['assigned_name']) ?></small>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div></div>
</div>
