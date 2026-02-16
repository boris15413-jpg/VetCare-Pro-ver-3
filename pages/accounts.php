<?php
/** アカウント管理 */
$auth->requireRole([ROLE_ADMIN]);
$staffList = $db->fetchAll("SELECT * FROM staff ORDER BY role, name");

// 削除・有効切替
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    $sid = (int)$_POST['toggle_active'];
    $st = $db->fetch("SELECT is_active FROM staff WHERE id=?", [$sid]);
    $db->update('staff', ['is_active'=>$st['is_active']?0:1], 'id=?', [$sid]);
    redirect("?page=accounts");
}
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-person-gear me-2"></i>アカウント管理</h4>
        <a href="?page=account_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規作成</a>
    </div>
    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0"><thead><tr><th>ログインID</th><th>氏名</th><th>役割</th><th class="d-none d-md-table-cell">メール</th><th>状態</th><th class="d-none d-md-table-cell">最終ログイン</th><th>操作</th></tr></thead><tbody>
        <?php foreach ($staffList as $st): ?>
        <tr>
            <td><code><?= h($st['login_id']) ?></code></td>
            <td><strong><?= h($st['name']) ?></strong><br><small class="text-muted"><?= h($st['name_kana']) ?></small></td>
            <td><span class="badge bg-<?= $st['role']==='admin'?'danger':($st['role']==='veterinarian'?'primary':($st['role']==='nurse'?'success':'secondary')) ?>"><?= h(getRoleName($st['role'])) ?></span></td>
            <td class="d-none d-md-table-cell"><?= h($st['email']) ?></td>
            <td><?= $st['is_active'] ? '<span class="badge bg-success">有効</span>' : '<span class="badge bg-secondary">無効</span>' ?></td>
            <td class="d-none d-md-table-cell"><?= $st['last_login'] ? formatDateTime($st['last_login']) : '-' ?></td>
            <td>
                <div class="d-flex gap-1">
                    <a href="?page=account_form&id=<?= $st['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil"></i></a>
                    <form method="POST" class="d-inline"><button type="submit" name="toggle_active" value="<?= $st['id'] ?>" class="btn btn-sm btn-outline-<?= $st['is_active']?'secondary':'success' ?>" title="<?= $st['is_active']?'無効化':'有効化' ?>"><i class="bi bi-<?= $st['is_active']?'pause':'play' ?>"></i></button></form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div></div></div>
</div>
