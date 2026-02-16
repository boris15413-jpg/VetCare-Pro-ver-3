<?php
/** 休診日管理 - 柔軟な定休日設定 */
if (!$auth->hasRole([ROLE_ADMIN])) { redirect('?page=dashboard'); }

$weekdayNames = ['日曜', '月曜', '火曜', '水曜', '木曜', '金曜', '土曜'];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_weekdays') {
        $selected = $_POST['closed_weekdays'] ?? [];
        setSetting('closed_weekdays', implode(',', $selected));
        setFlash('success', '定休曜日を更新しました。');
        redirect('?page=closed_days');
    }
    elseif ($action === 'add_special') {
        $date = trim($_POST['closed_date'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if ($date) {
            $exists = $db->fetch("SELECT id FROM closed_days WHERE closed_date = ?", [$date]);
            if (!$exists) {
                $db->insert('closed_days', [
                    'closed_date' => $date,
                    'reason' => $reason,
                    'is_recurring' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                setFlash('success', $date . ' を臨時休診日に追加しました。');
            } else {
                setFlash('warning', 'その日は既に登録されています。');
            }
        }
        redirect('?page=closed_days');
    }
    elseif ($action === 'delete_special') {
        $id = (int)$_POST['id'];
        $db->delete('closed_days', 'id = ?', [$id]);
        setFlash('success', '臨時休診日を削除しました。');
        redirect('?page=closed_days');
    }
}

// Load current settings
$closedWeekdays = explode(',', getSetting('closed_weekdays', '0'));
$closedWeekdays = array_filter($closedWeekdays, function($v) { return $v !== ''; });

// Load special closed days
$specialDays = $db->fetchAll("SELECT * FROM closed_days WHERE closed_date >= ? ORDER BY closed_date ASC", [date('Y-m-d')]);
$pastDays = $db->fetchAll("SELECT * FROM closed_days WHERE closed_date < ? ORDER BY closed_date DESC LIMIT 10", [date('Y-m-d')]);
?>

<div class="fade-in">
    <h4 class="fw-bold mb-4"><i class="bi bi-calendar-x me-2"></i>休診日管理</h4>
    <?php renderFlash(); ?>

    <div class="row g-4">
        <!-- Weekly closed days -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-calendar-week me-2"></i>定休曜日</div>
                <div class="card-body">
                    <p class="text-muted small">毎週の定休日を曜日で指定します。選択しない場合は「定休日なし（年中無休）」になります。</p>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_weekdays">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php foreach ($weekdayNames as $i => $name): ?>
                            <label class="btn <?= in_array((string)$i, $closedWeekdays) ? 'btn-danger' : 'btn-outline-secondary' ?>" style="min-width:70px;">
                                <input type="checkbox" name="closed_weekdays[]" value="<?= $i ?>" class="d-none"
                                    <?= in_array((string)$i, $closedWeekdays) ? 'checked' : '' ?>
                                    onchange="this.parentElement.className='btn ' + (this.checked ? 'btn-danger' : 'btn-outline-secondary'); ">
                                <?= $name ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="p-2 bg-light rounded mb-3">
                            <small>
                                <i class="bi bi-info-circle me-1"></i>
                                現在の設定: 
                                <?php if (empty($closedWeekdays) || (count($closedWeekdays) === 1 && $closedWeekdays[0] === '')): ?>
                                    <strong class="text-success">定休日なし（年中無休）</strong>
                                <?php else: ?>
                                    <strong class="text-danger"><?= implode('、', array_map(function($d) use ($weekdayNames) { return $weekdayNames[(int)$d] ?? ''; }, $closedWeekdays)) ?></strong>
                                <?php endif; ?>
                            </small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>定休曜日を保存
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Special closed days -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header fw-bold"><i class="bi bi-calendar-plus me-2"></i>臨時休診日を追加</div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_special">
                        <div class="row g-2 mb-3">
                            <div class="col-5">
                                <label class="form-label">日付</label>
                                <input type="date" name="closed_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-5">
                                <label class="form-label">理由</label>
                                <input type="text" name="reason" class="form-control" placeholder="お盆休み、年末年始 等">
                            </div>
                            <div class="col-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100"><i class="bi bi-plus-lg"></i></button>
                            </div>
                        </div>
                    </form>

                    <?php if (!empty($specialDays)): ?>
                    <h6 class="fw-bold mt-3 mb-2">今後の臨時休診日</h6>
                    <div class="list-group">
                        <?php foreach ($specialDays as $sd): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= h(formatDateJP($sd['closed_date'])) ?></strong>
                                <?php if ($sd['reason']): ?>
                                <small class="text-muted ms-2"><?= h($sd['reason']) ?></small>
                                <?php endif; ?>
                            </div>
                            <form method="POST" class="d-inline" onsubmit="return confirm('削除しますか？');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_special">
                                <input type="hidden" name="id" value="<?= $sd['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center mt-3 small">臨時休診日は設定されていません。</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
