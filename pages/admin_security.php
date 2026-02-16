<?php
/** セキュリティ管理（監査ログ・バックアップ） */
require_once __DIR__ . '/../includes/Audit.php';
require_once __DIR__ . '/../includes/Backup.php';

// 管理者権限チェック
if (!$auth->hasRole(ROLE_ADMIN)) {
    redirect('?page=dashboard');
}

// メッセージ初期化
$msg = '';
$checkMsg = '';
$integrityErrors = [];

// 手動バックアップ処理
if (isset($_POST['run_backup'])) {
    if (DB_DRIVER === 'sqlite') {
        $src = DB_SQLITE_PATH;
        $backupDir = BASE_PATH . '/backups/';
        if (!is_dir($backupDir)) mkdir($backupDir, 0700, true);
        
        $dest = $backupDir . 'manual_' . date('Ymd_His') . '.db';
        if (copy($src, $dest)) {
            $msg = "手動バックアップを作成しました: " . basename($dest);
        } else {
            $msg = "バックアップに失敗しました。権限を確認してください。";
        }
    } else {
        $msg = "MySQLのバックアップはphpMyAdmin等から行ってください。";
    }
}

// 改ざんチェック処理
if (isset($_POST['check_integrity'])) {
    $integrityErrors = Audit::verifyIntegrity();
    if (empty($integrityErrors)) {
        $checkMsg = "✅ データの整合性は正常です。改ざんは検出されませんでした。";
    } else {
        $checkMsg = "⚠️ 警告！データの不整合が見つかりました。";
    }
}

// ログ取得（最新50件）
$logs = $db->fetchAll("
    SELECT a.*, s.name as user_name 
    FROM audit_logs a 
    LEFT JOIN staff s ON a.user_id = s.id 
    ORDER BY a.created_at DESC 
    LIMIT 50
");
?>

<div class="fade-in">
    <h4 class="fw-bold mb-4"><i class="bi bi-shield-lock-fill me-2"></i>セキュリティ監査 & バックアップ</h4>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-bold bg-light">データ保全</div>
                <div class="card-body">
                    <p class="text-muted">データベースのコピーを作成し、サーバー内の <code>/backups/</code> フォルダに保存します。</p>
                    <p class="small">※定期バックアップは毎日自動で行われます。</p>
                    <form method="POST">
                        <button type="submit" name="run_backup" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>今すぐバックアップを作成
                        </button>
                    </form>
                    <?php if($msg): ?><div class="alert alert-info mt-3"><?= h($msg) ?></div><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100 border-danger">
                <div class="card-header fw-bold text-danger bg-danger-subtle">改ざん検知スキャン</div>
                <div class="card-body">
                    <p class="text-muted">全操作ログの「ハッシュチェーン」を検証し、不正な書き換えや削除がないか数学的にチェックします。</p>
                    <form method="POST">
                        <button type="submit" name="check_integrity" class="btn btn-outline-danger">
                            <i class="bi bi-search me-2"></i>整合性チェックを実行
                        </button>
                    </form>
                    <?php if($checkMsg): ?>
                        <div class="alert <?= empty($integrityErrors)?'alert-success':'alert-danger' ?> mt-3">
                            <?= h($checkMsg) ?>
                            <?php if(!empty($integrityErrors)): ?>
                                <ul class="mb-0 mt-2">
                                <?php foreach($integrityErrors as $err): ?>
                                    <li>ID: <?= $err['id'] ?> - <?= h($err['reason']) ?></li>
                                <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-bold">操作履歴（監査ログ） - 直近50件</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th style="width:150px">日時</th>
                            <th style="width:120px">操作者</th>
                            <th style="width:150px">対象</th>
                            <th style="width:80px">操作</th>
                            <th>変更内容</th>
                            <th style="width:120px">IPアドレス</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= $log['created_at'] ?></td>
                            <td><?= h($log['user_name'] ?: 'System/Unknown') ?></td>
                            <td><?= h($log['target_table']) ?> (ID:<?= $log['target_id'] ?>)</td>
                            <td>
                                <span class="badge bg-<?= $log['action_type']==='DELETE'?'danger':($log['action_type']==='UPDATE'?'warning':'success') ?>">
                                    <?= h($log['action_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if($log['old_value'] || $log['new_value']): ?>
                                <button class="btn btn-xs btn-link p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#logDetail<?= $log['id'] ?>">
                                    <i class="bi bi-caret-down-fill"></i> 詳細を表示
                                </button>
                                <div class="collapse" id="logDetail<?= $log['id'] ?>">
                                    <div class="p-2 bg-light border rounded mt-1 text-break font-monospace" style="font-size:0.85em;">
                                        <?php if($log['old_value']): ?>
                                            <div class="text-danger mb-1"><strong>[前]</strong> <?= h(mb_substr($log['old_value'], 0, 200)) ?><?= mb_strlen($log['old_value'])>200?'...':'' ?></div>
                                        <?php endif; ?>
                                        <?php if($log['new_value']): ?>
                                            <div class="text-success"><strong>[後]</strong> <?= h(mb_substr($log['new_value'], 0, 200)) ?><?= mb_strlen($log['new_value'])>200?'...':'' ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h($log['ip_address']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-muted small">
            ※全ての操作はハッシュチェーンにより保護されており、データベースを直接操作しても痕跡が残ります。
        </div>
    </div>
</div>