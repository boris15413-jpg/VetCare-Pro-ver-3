<?php
/** お知らせ詳細 */
$id = (int)($_GET['id'] ?? 0);
$uid = $auth->currentUserId();

// お知らせデータの取得
$notice = $db->fetch("SELECT n.*, s.name as posted_name FROM notices n JOIN staff s ON n.posted_by=s.id WHERE n.id = ?", [$id]);
if (!$notice) { redirect('?page=notices'); }

// 【重要】既読としてマークする
$isRead = $db->fetch("SELECT id FROM notice_reads WHERE notice_id = ? AND user_id = ?", [$id, $uid]);
if (!$isRead) {
    $db->insert('notice_reads', [
        'notice_id' => $id,
        'user_id' => $uid,
        'read_at' => date('Y-m-d H:i:s')
    ]);
}
?>
<div class="fade-in">
    <div class="mb-3">
        <a href="?page=notices" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>お知らせ一覧に戻る</a>
    </div>

    <div class="card <?= $notice['priority']==='high'?'border-danger':'' ?>">
        <div class="card-header d-flex justify-content-between align-items-center <?= $notice['priority']==='high'?'bg-danger text-white':'' ?>">
            <h5 class="mb-0 fw-bold">
                <?php if ($notice['priority']==='high'): ?><i class="bi bi-exclamation-triangle-fill me-2"></i><?php endif; ?>
                <?= h($notice['title']) ?>
            </h5>
            <span class="badge bg-white text-dark"><?= formatDateTime($notice['created_at']) ?></span>
        </div>
        <div class="card-body" style="min-height: 200px;">
            <div class="mb-4" style="white-space: pre-wrap; line-height: 1.8;"><?= h($notice['content']) ?></div>
        </div>
        <div class="card-footer text-muted d-flex justify-content-between">
            <small>投稿者: <?= h($notice['posted_name']) ?></small>
            <?php if ($notice['target_role']): ?>
                <small>対象: <?= h(getRoleName($notice['target_role'])) ?></small>
            <?php else: ?>
                <small>対象: 全員</small>
            <?php endif; ?>
        </div>
    </div>
</div>