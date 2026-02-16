<?php
/** お知らせ管理 */
$uid = $auth->currentUserId();
$role = $auth->currentUserRole();

// 一括既読処理
if (isset($_POST['mark_all_read'])) {
$targets = $db->fetchAll("SELECT id FROM notices WHERE is_active=1 AND (target_role=? OR target_role=?)", [$role, '']);    foreach ($targets as $t) {
        $exists = $db->fetch("SELECT id FROM notice_reads WHERE notice_id=? AND user_id=?", [$t['id'], $uid]);
        if (!$exists) {
            $db->insert('notice_reads', ['notice_id'=>$t['id'], 'user_id'=>$uid, 'read_at'=>date('Y-m-d H:i:s')]);
        }
    }
    redirect("?page=notices");
}

// 新規投稿処理（管理者・獣医師のみ）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $db->insert('notices', [
        'title'=>trim($_POST['title']), 'content'=>trim($_POST['content']),
        'priority'=>$_POST['priority']??'normal', 'target_role'=>$_POST['target_role']??'',
        'posted_by'=>$uid, 'is_active'=>1, 'created_at'=>date('Y-m-d H:i:s')
    ]);
    redirect("?page=notices");
}

$notices = $db->fetchAll("
    SELECT n.*, s.name as posted_name, 
    (SELECT COUNT(*) FROM notice_reads nr WHERE nr.notice_id = n.id AND nr.user_id = ?) as is_read
    FROM notices n 
    JOIN staff s ON n.posted_by=s.id 
    WHERE n.is_active=1 
    ORDER BY n.created_at DESC
", [$uid]);
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-megaphone me-2"></i>お知らせ</h4>
        <form method="POST" onsubmit="return confirm('すべて既読にしますか？');">
            <button type="submit" name="mark_all_read" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-check2-all me-1"></i>すべて既読にする
            </button>
        </form>
    </div>

    <div class="row g-3">
        <?php if ($auth->hasRole([ROLE_ADMIN, ROLE_VET])): ?>
        <div class="col-lg-4 order-lg-2">
            <form method="POST" class="card h-100"><div class="card-header bg-light">新規投稿</div><div class="card-body"><div class="row g-2">
                <?= csrf_field() ?>
                <div class="col-12"><input type="text" name="title" class="form-control" placeholder="タイトル" required></div>
                <div class="col-6"><select name="priority" class="form-select"><option value="normal">通常</option><option value="high">重要</option></select></div>
                <div class="col-6"><select name="target_role" class="form-select"><option value="">全員</option><?php foreach(ROLE_NAMES as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><textarea name="content" class="form-control" rows="5" placeholder="内容" required></textarea></div>
                <div class="col-12 mt-3"><button type="submit" class="btn btn-primary w-100">投稿する</button></div>
            </div></div></form>
        </div>
        <?php endif; ?>

        <div class="<?= $auth->hasRole([ROLE_ADMIN, ROLE_VET]) ? 'col-lg-8 order-lg-1' : 'col-12' ?>">
            <?php foreach ($notices as $n): ?>
            <a href="?page=notice_detail&id=<?= $n['id'] ?>" class="text-decoration-none text-dark">
                <div class="card mb-2 hover-shadow <?= $n['priority']==='high'?'border-danger':'' ?>" style="transition:0.2s;">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="d-flex align-items-center">
                                <?php if (!$n['is_read']): ?>
                                    <span class="badge bg-primary me-2 rounded-pill" style="font-size:0.7rem;">NEW</span>
                                <?php endif; ?>
                                <strong class="<?= $n['priority']==='high'?'text-danger':'' ?>">
                                    <?php if ($n['priority']==='high'): ?><i class="bi bi-exclamation-triangle me-1"></i><?php endif; ?>
                                    <?= h($n['title']) ?>
                                </strong>
                            </div>
                            <small class="text-muted"><?= formatDateTime($n['created_at']) ?></small>
                        </div>
                        <p class="mb-1 text-secondary text-truncate"><?= h(mb_substr($n['content'], 0, 50)) ?>...</p>
                        <small class="text-muted" style="font-size:0.8rem;">
                            投稿: <?= h($n['posted_name']) ?>
                            <?php if ($n['target_role']): ?> <span class="badge bg-light text-dark border ms-1">To: <?= h(getRoleName($n['target_role'])) ?></span><?php endif; ?>
                        </small>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>