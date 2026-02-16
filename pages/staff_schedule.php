<?php
/** 医師・スタッフ勤務表管理 */
if (!$auth->hasRole([ROLE_ADMIN, ROLE_VET])) { redirect('?page=dashboard'); }

$weekStart = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $weekDays[] = date('Y-m-d', strtotime($weekStart . " +{$i} days"));
}
$weekdayNames = ['日', '月', '火', '水', '木', '金', '土'];

// Get vets and staff
$staffList = $db->fetchAll("SELECT id, name, role, color, specialty FROM staff WHERE is_active = 1 AND role IN ('veterinarian','admin','nurse') ORDER BY CASE role WHEN 'veterinarian' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END, name");

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_schedule') {
        $staffId = (int)$_POST['staff_id'];
        $scheduleDate = $_POST['schedule_date'];
        $type = $_POST['schedule_type'] ?? 'regular';
        $startTime = $_POST['start_time'] ?? '09:00';
        $endTime = $_POST['end_time'] ?? '18:00';
        $notes = trim($_POST['notes'] ?? '');
        
        // Delete existing
        $db->delete('staff_schedules', 'staff_id = ? AND schedule_date = ?', [$staffId, $scheduleDate]);
        
        if ($type !== 'off') {
            $db->insert('staff_schedules', [
                'staff_id' => $staffId,
                'schedule_date' => $scheduleDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'schedule_type' => $type,
                'notes' => $notes,
                'created_by' => $auth->currentUserId(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            // Insert off day record
            $db->insert('staff_schedules', [
                'staff_id' => $staffId,
                'schedule_date' => $scheduleDate,
                'start_time' => '',
                'end_time' => '',
                'schedule_type' => 'off',
                'notes' => $notes,
                'created_by' => $auth->currentUserId(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        
        setFlash('success', '勤務予定を保存しました。');
        redirect('?page=staff_schedule&week=' . $weekStart);
    }
    elseif ($action === 'bulk_save') {
        $data = $_POST['schedule'] ?? [];
        foreach ($data as $staffId => $dates) {
            foreach ($dates as $date => $info) {
                $type = $info['type'] ?? 'regular';
                $db->delete('staff_schedules', 'staff_id = ? AND schedule_date = ?', [(int)$staffId, $date]);
                
                if ($type === 'off') {
                    $db->insert('staff_schedules', [
                        'staff_id' => (int)$staffId,
                        'schedule_date' => $date,
                        'schedule_type' => 'off',
                        'start_time' => '', 'end_time' => '',
                        'notes' => '',
                        'created_by' => $auth->currentUserId(),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                } elseif ($type !== 'none') {
                    $db->insert('staff_schedules', [
                        'staff_id' => (int)$staffId,
                        'schedule_date' => $date,
                        'start_time' => $info['start'] ?? '09:00',
                        'end_time' => $info['end'] ?? '18:00',
                        'schedule_type' => $type,
                        'notes' => '',
                        'created_by' => $auth->currentUserId(),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
        setFlash('success', '週間勤務表を保存しました。');
        redirect('?page=staff_schedule&week=' . $weekStart);
    }
}

// Load schedules for the week
$schedules = [];
$scheduleData = $db->fetchAll(
    "SELECT * FROM staff_schedules WHERE schedule_date BETWEEN ? AND ? ORDER BY schedule_date, staff_id",
    [$weekStart, $weekEnd]
);
foreach ($scheduleData as $s) {
    $schedules[$s['staff_id']][$s['schedule_date']] = $s;
}

$prevWeek = date('Y-m-d', strtotime($weekStart . ' -7 days'));
$nextWeek = date('Y-m-d', strtotime($weekStart . ' +7 days'));
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-calendar2-week me-2"></i>勤務表管理</h4>
            <small class="text-muted"><?= formatDate($weekStart, 'Y年m月d日') ?> 〜 <?= formatDate($weekEnd, 'Y年m月d日') ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="?page=staff_schedule&week=<?= $prevWeek ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left"></i> 前週</a>
            <a href="?page=staff_schedule&week=<?= date('Y-m-d', strtotime('monday this week')) ?>" class="btn btn-outline-primary btn-sm">今週</a>
            <a href="?page=staff_schedule&week=<?= $nextWeek ?>" class="btn btn-outline-secondary btn-sm">翌週 <i class="bi bi-chevron-right"></i></a>
        </div>
    </div>

    <?php renderFlash(); ?>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bulk_save">
        
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" style="font-size:0.82rem;">
                        <thead>
                            <tr class="text-center">
                                <th style="min-width:120px; position:sticky; left:0; background:#f8fafc; z-index:1;">スタッフ</th>
                                <?php foreach ($weekDays as $day): 
                                    $dow = date('w', strtotime($day));
                                    $isToday = $day === date('Y-m-d');
                                    $dayClass = $isToday ? 'bg-primary text-white' : ($dow == 0 ? 'text-danger' : ($dow == 6 ? 'text-primary' : ''));
                                ?>
                                <th class="<?= $dayClass ?>" style="min-width:110px;">
                                    <?= date('m/d', strtotime($day)) ?><br>
                                    <small>(<?= $weekdayNames[$dow] ?>)</small>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staffList as $staff): ?>
                            <tr>
                                <td style="position:sticky; left:0; background:var(--vc-surface); z-index:1;">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge" style="background:<?= h($staff['color'] ?? '#4f46e5') ?>;width:6px;height:24px;padding:0;border-radius:3px;"></span>
                                        <div>
                                            <strong><?= h($staff['name']) ?></strong>
                                            <br><small class="text-muted"><?= h(getRoleName($staff['role'])) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <?php foreach ($weekDays as $day): 
                                    $sched = $schedules[$staff['id']][$day] ?? null;
                                    $type = $sched ? $sched['schedule_type'] : 'none';
                                ?>
                                <td class="text-center p-1">
                                    <select name="schedule[<?= $staff['id'] ?>][<?= $day ?>][type]" class="form-select form-select-sm mb-1" style="font-size:0.75rem;" onchange="toggleTimeInputs(this)">
                                        <option value="none" <?= $type === 'none' ? 'selected' : '' ?>>-</option>
                                        <option value="regular" <?= $type === 'regular' ? 'selected' : '' ?>>通常勤務</option>
                                        <option value="am_only" <?= $type === 'am_only' ? 'selected' : '' ?>>午前のみ</option>
                                        <option value="pm_only" <?= $type === 'pm_only' ? 'selected' : '' ?>>午後のみ</option>
                                        <option value="off" <?= $type === 'off' ? 'selected' : '' ?>>休み</option>
                                    </select>
                                    <div class="time-inputs" style="display:<?= ($type !== 'none' && $type !== 'off') ? 'block' : 'none' ?>;">
                                        <input type="time" name="schedule[<?= $staff['id'] ?>][<?= $day ?>][start]" class="form-control form-control-sm" style="font-size:0.7rem;" value="<?= h($sched['start_time'] ?? '09:00') ?>">
                                        <input type="time" name="schedule[<?= $staff['id'] ?>][<?= $day ?>][end]" class="form-control form-control-sm mt-1" style="font-size:0.7rem;" value="<?= h($sched['end_time'] ?? '18:00') ?>">
                                    </div>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>勤務表を保存
                </button>
            </div>
        </div>
    </form>

    <!-- Legend -->
    <div class="mt-3 d-flex gap-3 flex-wrap">
        <small><span class="badge bg-success">通常勤務</span> 終日</small>
        <small><span class="badge bg-info">午前のみ</span></small>
        <small><span class="badge bg-warning text-dark">午後のみ</span></small>
        <small><span class="badge bg-secondary">休み</span></small>
    </div>
</div>

<script>
function toggleTimeInputs(sel) {
    const inputs = sel.parentElement.querySelector('.time-inputs');
    if (inputs) {
        inputs.style.display = (sel.value !== 'none' && sel.value !== 'off') ? 'block' : 'none';
    }
}
</script>
