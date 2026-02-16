<?php
/**
 * 受付・待合管理 - 番号自動発行・優先予約対応
 */
$today = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $today;
$displayMode = getSetting('accounting_display_mode', 'name');
$priorityReservation = getSetting('priority_reservation', '0') === '1';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf()) {
        setFlash('danger', 'CSRF検証に失敗しました');
    } else {
        $aptId = (int)($_POST['appointment_id'] ?? 0);
        $action = $_POST['action'];
        
        $validStatuses = ['checked_in', 'in_progress', 'completed', 'cancelled', 'no_show'];
        if ($aptId > 0 && in_array($action, $validStatuses)) {
            $updateData = ['status' => $action, 'updated_at' => date('Y-m-d H:i:s')];
            
            if ($action === 'checked_in') {
                $updateData['checked_in_at'] = date('Y-m-d H:i:s');
                
                // Auto-issue queue number if number mode is enabled
                if ($displayMode === 'number') {
                    $maxQueue = $db->fetch("SELECT MAX(queue_number) as mx FROM appointments WHERE appointment_date = ?", [$selectedDate])['mx'] ?? 0;
                    $updateData['queue_number'] = $maxQueue + 1;
                }
            }
            
            $db->update('appointments', $updateData, 'id = ?', [$aptId]);
            
            if ($action === 'checked_in' && $displayMode === 'number') {
                $qn = $updateData['queue_number'] ?? '';
                setFlash('success', "受付しました" . ($qn ? " (受付番号: {$qn})" : ''));
            } else {
                setFlash('success', 'ステータスを更新しました');
            }
        }
        redirect('index.php?page=reception&date=' . $selectedDate);
    }
}

// Handle priority toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_priority'])) {
    if (verify_csrf()) {
        $aptId = (int)$_POST['appointment_id'];
        $current = $db->fetch("SELECT is_priority FROM appointments WHERE id=?", [$aptId]);
        $newVal = ($current && $current['is_priority']) ? 0 : 1;
        $db->update('appointments', ['is_priority' => $newVal], 'id=?', [$aptId]);
        setFlash('info', $newVal ? '優先予約に設定しました' : '優先予約を解除しました');
        redirect('index.php?page=reception&date=' . $selectedDate);
    }
}

// Fetch appointments
$appointments = $db->fetchAll("
    SELECT a.*, 
           p.name as patient_name, p.species, p.breed, p.patient_code, p.allergies,
           o.name as owner_name, o.phone as owner_phone,
           s.name as staff_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN owners o ON a.owner_id = o.id OR p.owner_id = o.id
    LEFT JOIN staff s ON a.staff_id = s.id
    WHERE a.appointment_date = ?
    ORDER BY 
        CASE a.status 
            WHEN 'in_progress' THEN 1 
            WHEN 'checked_in' THEN 2 
            WHEN 'scheduled' THEN 3 
            WHEN 'completed' THEN 4 
            WHEN 'cancelled' THEN 5 
            ELSE 6 
        END,
        a.is_priority DESC,
        a.appointment_time ASC
", [$selectedDate]);

$statusCounts = ['scheduled' => 0, 'checked_in' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0, 'no_show' => 0];
foreach ($appointments as $apt) {
    $s = $apt['status'];
    if (isset($statusCounts[$s])) $statusCounts[$s]++;
}
$hospitalName = getSetting('hospital_name', APP_NAME);
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-display me-2"></i>受付・待合管理</h4>
            <small class="text-muted"><?= h(formatDateJP($selectedDate)) ?></small>
            <?php if ($displayMode === 'number'): ?>
            <span class="badge bg-info ms-2">番号モード</span>
            <?php endif; ?>
            <?php if ($priorityReservation): ?>
            <span class="badge bg-warning text-dark ms-2">予約優先</span>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <input type="date" class="form-control form-control-sm" value="<?= h($selectedDate) ?>" 
                   onchange="location.href='?page=reception&date='+this.value" style="width:160px;">
            <a href="?page=reception&date=<?= date('Y-m-d') ?>" class="btn btn-outline-primary btn-sm">今日</a>
            <a href="?page=appointment_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>予約追加</a>
            <a href="index.php?page=reception_display" target="_blank" class="btn btn-outline-secondary btn-sm" title="待合ディスプレイ">
                <i class="bi bi-tv me-1"></i>待合表示
            </a>
            <a href="index.php?page=accounting_display" target="_blank" class="btn btn-outline-info btn-sm" title="会計表示">
                <i class="bi bi-receipt me-1"></i>会計表示
            </a>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-2">
            <div class="stat-card bg-gradient-info" style="padding:16px;">
                <div class="stat-value" style="font-size:1.5rem;"><?= $statusCounts['scheduled'] ?></div>
                <div class="stat-label">予約済</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card bg-gradient-success" style="padding:16px;">
                <div class="stat-value" style="font-size:1.5rem;"><?= $statusCounts['checked_in'] ?></div>
                <div class="stat-label">受付済(待ち)</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card bg-gradient-warning" style="padding:16px;">
                <div class="stat-value" style="font-size:1.5rem;"><?= $statusCounts['in_progress'] ?></div>
                <div class="stat-label">診察中</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card bg-gradient-purple" style="padding:16px;">
                <div class="stat-value" style="font-size:1.5rem;"><?= $statusCounts['completed'] ?></div>
                <div class="stat-label">完了</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card bg-gradient-danger" style="padding:16px;">
                <div class="stat-value" style="font-size:1.5rem;"><?= $statusCounts['cancelled'] + $statusCounts['no_show'] ?></div>
                <div class="stat-label">キャンセル</div>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card bg-gradient-primary" style="padding:16px;">
                <div class="stat-value" style="font-size:1.5rem;"><?= count($appointments) ?></div>
                <div class="stat-label">合計</div>
            </div>
        </div>
    </div>

    <!-- Queue List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people me-2"></i>本日の予約一覧 (<?= count($appointments) ?>件)</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x d-block"></i>
                <h5>予約がありません</h5>
                <p>この日の予約はまだ登録されていません。</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px">No.</th>
                            <th>時間</th>
                            <th>ステータス</th>
                            <th>患畜</th>
                            <th class="d-none d-md-table-cell">飼い主</th>
                            <th class="d-none d-md-table-cell">来院理由</th>
                            <th class="d-none d-lg-table-cell">担当</th>
                            <th style="min-width:240px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $seqNum = 1; foreach ($appointments as $apt): ?>
                    <tr class="<?= $apt['status'] === 'in_progress' ? 'table-warning' : ($apt['status'] === 'checked_in' ? 'table-info' : ($apt['is_priority'] ? 'table-light' : '')) ?>">
                        <td>
                            <div class="queue-number bg-gradient-<?= $apt['status'] === 'in_progress' ? 'warning' : ($apt['status'] === 'checked_in' ? 'success' : 'info') ?>" style="width:38px;height:38px;border-radius:10px;font-size:0.9rem;">
                                <?php if ($displayMode === 'number' && $apt['queue_number']): ?>
                                    <?= $apt['queue_number'] ?>
                                <?php else: ?>
                                    <?= $seqNum++ ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <strong><?= h(substr($apt['appointment_time'], 0, 5)) ?></strong>
                            <?php if ($apt['is_priority']): ?>
                            <br><span class="badge bg-warning text-dark" style="font-size:0.65rem;"><i class="bi bi-star-fill me-1"></i>優先</span>
                            <?php endif; ?>
                        </td>
                        <td><?= getAppointmentStatusBadge($apt['status']) ?></td>
                        <td>
                            <?php if ($apt['patient_id']): ?>
                            <a href="?page=patient_detail&id=<?= $apt['patient_id'] ?>" class="text-decoration-none fw-bold"><?= h($apt['patient_name'] ?? '新規来院') ?></a>
                            <?php else: ?>
                            <strong><?= h($apt['pet_name_text'] ?? $apt['patient_name'] ?? '新規来院') ?></strong>
                            <?php endif; ?>
                            <?php if ($apt['species']): ?>
                            <small class="text-muted d-block"><?= h(getSpeciesName($apt['species'])) ?></small>
                            <?php endif; ?>
                            <?php if ($apt['allergies']): ?>
                            <span class="allergy-tag mt-1" style="font-size:0.65rem;"><i class="bi bi-exclamation-triangle"></i><?= h($apt['allergies']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?= h($apt['owner_name'] ?? $apt['owner_name_text'] ?? '-') ?>
                            <?php if ($apt['owner_phone'] ?? $apt['phone_text'] ?? ''): ?>
                            <small class="text-muted d-block"><?= h($apt['owner_phone'] ?? $apt['phone_text']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <small><?= h(mb_substr($apt['reason'] ?? '', 0, 30)) ?></small>
                        </td>
                        <td class="d-none d-lg-table-cell"><?= h($apt['staff_name'] ?? '-') ?></td>
                        <td>
                            <div class="d-inline-flex gap-1 flex-wrap">
                                <form method="POST" class="d-inline-flex gap-1 no-navigate">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                    
                                    <?php if ($apt['status'] === 'scheduled'): ?>
                                    <button type="submit" name="action" value="checked_in" class="btn btn-success btn-sm">
                                        <i class="bi bi-person-check me-1"></i>受付
                                    </button>
                                    <button type="submit" name="action" value="cancelled" class="btn btn-outline-secondary btn-sm" onclick="return confirm('キャンセルしますか？')">
                                        <i class="bi bi-x"></i>
                                    </button>
                                    <?php elseif ($apt['status'] === 'checked_in'): ?>
                                    <button type="submit" name="action" value="in_progress" class="btn btn-warning btn-sm">
                                        <i class="bi bi-play-fill me-1"></i>診察開始
                                    </button>
                                    <?php elseif ($apt['status'] === 'in_progress'): ?>
                                    <button type="submit" name="action" value="completed" class="btn btn-primary btn-sm">
                                        <i class="bi bi-check-lg me-1"></i>完了
                                    </button>
                                    <?php if ($apt['patient_id']): ?>
                                    <a href="?page=record_form&patient_id=<?= $apt['patient_id'] ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-pencil me-1"></i>カルテ
                                    </a>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </form>
                                
                                <?php if (in_array($apt['status'], ['scheduled', 'checked_in'])): ?>
                                <form method="POST" class="d-inline no-navigate">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                    <button type="submit" name="toggle_priority" value="1" class="btn btn-sm <?= $apt['is_priority'] ? 'btn-warning' : 'btn-outline-warning' ?>" title="<?= $apt['is_priority'] ? '優先解除' : '優先設定' ?>">
                                        <i class="bi bi-star<?= $apt['is_priority'] ? '-fill' : '' ?>"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
