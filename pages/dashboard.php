<?php
/**
 * VetCare Pro v3.0 - Premium Dashboard
 */
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Statistics
$totalPatients = $db->count('patients', 'is_active = 1');
$todayAppointments = $db->count('appointments', 'appointment_date = ? AND status IN (?,?,?)', [$today, 'scheduled', 'checked_in', 'in_progress']);
$admittedCount = $db->count('admissions', 'status = ?', ['admitted']);
$pendingOrders = $db->count('orders', 'status = ?', ['pending']);

// Monthly stats
$monthStart = date('Y-m-01');
$monthPatients = $db->count('medical_records', 'visit_date >= ?', [$monthStart]);

// Unpaid invoices
$unpaidCount = 0;
try { $unpaidCount = $db->count('invoices', "payment_status = 'unpaid'"); } catch (Exception $e) {}

// Vaccine reminders (due within 30 days)
$vaccineReminders = [];
try {
    $vaccineReminders = $db->fetchAll("
        SELECT v.*, p.name as patient_name, p.patient_code, p.species, o.name as owner_name, o.phone as owner_phone
        FROM vaccinations v
        JOIN patients p ON v.patient_id = p.id
        JOIN owners o ON p.owner_id = o.id
        WHERE v.next_due_date BETWEEN ? AND date('now', '+30 days')
        AND p.is_active = 1
        ORDER BY v.next_due_date ASC
        LIMIT 10
    ", [$today]);
} catch (Exception $e) {}

// Pending insurance claims
$pendingClaims = [];
try {
    $pendingClaims = $db->fetchAll("
        SELECT ic.*, p.name as patient_name, ip.company_name
        FROM insurance_claims ic
        JOIN patients p ON ic.patient_id = p.id
        JOIN insurance_policies ip ON ic.policy_id = ip.id
        WHERE ic.claim_status IN ('draft','submitted')
        ORDER BY ic.created_at DESC
        LIMIT 5
    ");
} catch (Exception $e) {}

// Admitted patients
$admittedPatients = $db->fetchAll("
    SELECT a.*, p.name as patient_name, p.species, p.breed, p.patient_code,
           o.name as owner_name, s.name as vet_name
    FROM admissions a
    JOIN patients p ON a.patient_id = p.id
    JOIN owners o ON p.owner_id = o.id
    JOIN staff s ON a.admitted_by = s.id
    WHERE a.status = 'admitted'
    ORDER BY a.admission_date DESC
");

// Today's appointments
$todayAppts = $db->fetchAll("
    SELECT ap.*, p.name as patient_name, p.species, o.name as owner_name, s.name as staff_name
    FROM appointments ap
    LEFT JOIN patients p ON ap.patient_id = p.id
    LEFT JOIN owners o ON ap.owner_id = o.id OR p.owner_id = o.id
    LEFT JOIN staff s ON ap.staff_id = s.id
    WHERE ap.appointment_date = ?
    ORDER BY ap.appointment_time ASC
", [$today]);

// Latest notices
$notices = $db->fetchAll("
    SELECT n.*, s.name as posted_by_name
    FROM notices n
    JOIN staff s ON n.posted_by = s.id
    WHERE n.is_active = 1
    ORDER BY n.created_at DESC LIMIT 5
");

// Pending nursing tasks
$pendingTasks = $db->fetchAll("
    SELECT nt.*, p.name as patient_name, p.species, s.name as assigned_name
    FROM nursing_tasks nt
    JOIN patients p ON nt.patient_id = p.id
    LEFT JOIN staff s ON nt.assigned_to = s.id
    WHERE nt.status = 'pending' AND DATE(nt.scheduled_at) = ?
    ORDER BY nt.priority DESC, nt.scheduled_at ASC
    LIMIT 10
", [$today]);

// Recent records
$recentRecords = $db->fetchAll("
    SELECT mr.*, p.name as patient_name, p.patient_code, s.name as vet_name
    FROM medical_records mr
    JOIN patients p ON mr.patient_id = p.id
    JOIN staff s ON mr.staff_id = s.id
    ORDER BY mr.created_at DESC LIMIT 8
");

$hospitalName = getSetting('hospital_name', '');
?>

<div class="fade-in">
    <!-- Welcome Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bi bi-grid-1x2-fill me-2 text-primary"></i>ダッシュボード
            </h4>
            <small class="text-muted">ようこそ、<?= h($auth->currentUserName()) ?>さん<?php if ($hospitalName): ?> - <?= h($hospitalName) ?><?php endif; ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="?page=patient_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規患畜</a>
            <a href="?page=appointment_form" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-plus me-1"></i>予約追加</a>
            <a href="?page=reception" class="btn btn-outline-secondary btn-sm"><i class="bi bi-display me-1"></i>受付画面</a>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card bg-gradient-primary">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-value"><?= $totalPatients ?></div>
                        <div class="stat-label">登録患畜数</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-clipboard2-pulse"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card bg-gradient-success">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-value"><?= $todayAppointments ?></div>
                        <div class="stat-label">本日の予約</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card bg-gradient-warning">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-value"><?= $admittedCount ?></div>
                        <div class="stat-label">入院中</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-hospital"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card bg-gradient-danger">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="stat-value"><?= $pendingOrders ?></div>
                        <div class="stat-label">未実施オーダー</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-list-check"></i></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($unpaidCount > 0): ?>
    <div class="alert alert-danger py-2 mb-3 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-circle me-2"></i><strong>未払い会計が <?= $unpaidCount ?> 件</strong>あります</span>
        <a href="?page=invoices&filter=unpaid" class="btn btn-sm btn-danger">確認する</a>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Admitted Patients -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-hospital me-2 text-warning"></i>入院中の患畜</span>
                    <a href="?page=admissions" class="btn btn-sm btn-outline-primary">全て見る</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($admittedPatients)): ?>
                    <div class="empty-state py-4">
                        <i class="bi bi-emoji-smile d-block" style="font-size:2rem;"></i>
                        <p class="mb-0">現在入院中の患畜はいません</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>患畜</th><th class="d-none d-md-table-cell">種別</th><th>病棟</th><th>入院日</th><th>経過</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admittedPatients as $ap): 
                                    $days = (int)((time() - strtotime($ap['admission_date'])) / 86400);
                                ?>
                                <tr>
                                    <td>
                                        <a href="?page=patient_detail&id=<?= $ap['patient_id'] ?>" class="text-decoration-none">
                                            <strong><?= h($ap['patient_name']) ?></strong>
                                        </a>
                                        <small class="text-muted d-block"><?= h($ap['patient_code']) ?></small>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?= h(getSpeciesName($ap['species'])) ?></td>
                                    <td><span class="badge bg-light text-dark"><?= h($ap['ward']) ?> <?= h($ap['cage_number']) ?></span></td>
                                    <td><?= formatDate($ap['admission_date'], 'm/d') ?></td>
                                    <td><span class="badge bg-<?= $days > 7 ? 'warning text-dark' : 'info' ?>"><?= $days ?>日目</span></td>
                                    <td>
                                        <a href="?page=temperature_chart&admission_id=<?= $ap['id'] ?>" class="btn btn-sm btn-outline-info" title="温度板"><i class="bi bi-thermometer-half"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Today's Appointments -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar-check me-2 text-success"></i>本日の予約</span>
                    <div class="d-flex gap-2">
                        <a href="?page=reception" class="btn btn-sm btn-success"><i class="bi bi-display me-1"></i>受付画面</a>
                        <a href="?page=appointments" class="btn btn-sm btn-outline-primary">全て見る</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($todayAppts)): ?>
                    <div class="empty-state py-4">
                        <i class="bi bi-calendar-x d-block" style="font-size:2rem;"></i>
                        <p class="mb-0">本日の予約はありません</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>時間</th><th>ステータス</th><th>患畜</th><th class="d-none d-md-table-cell">飼い主</th><th>理由</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todayAppts as $apt): ?>
                                <tr class="<?= $apt['status'] === 'in_progress' ? 'table-warning' : '' ?>">
                                    <td><strong><?= h(substr($apt['appointment_time'], 0, 5)) ?></strong></td>
                                    <td><?= getAppointmentStatusBadge($apt['status']) ?></td>
                                    <td>
                                        <?php if ($apt['patient_name']): ?>
                                        <a href="?page=patient_detail&id=<?= $apt['patient_id'] ?>" class="text-decoration-none"><?= h($apt['patient_name']) ?></a>
                                        <?php else: ?>
                                        <span class="text-muted">新規</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?= h($apt['owner_name'] ?? '-') ?></td>
                                    <td><small><?= h(mb_substr($apt['reason'], 0, 25)) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vaccine Reminders -->
            <?php if (!empty($vaccineReminders)): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-shield-plus me-2 text-danger"></i>ワクチン接種期限（30日以内）</span>
                    <a href="?page=vaccinations" class="btn btn-sm btn-outline-danger">全て見る</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>期限</th><th>患畜</th><th>ワクチン</th><th class="d-none d-md-table-cell">飼い主</th><th class="d-none d-md-table-cell">電話</th></tr></thead>
                            <tbody>
                            <?php foreach ($vaccineReminders as $vr): 
                                $overdue = strtotime($vr['next_due_date']) < time();
                            ?>
                            <tr class="<?= $overdue ? 'table-danger' : '' ?>">
                                <td>
                                    <span class="<?= $overdue ? 'text-danger fw-bold' : '' ?>">
                                        <?= formatDate($vr['next_due_date']) ?>
                                        <?php if ($overdue): ?><i class="bi bi-exclamation-triangle-fill ms-1"></i><?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?page=patient_detail&id=<?= $vr['patient_id'] ?>" class="text-decoration-none">
                                        <strong><?= h($vr['patient_name']) ?></strong>
                                    </a>
                                    <small class="text-muted d-block"><?= h(getSpeciesName($vr['species'])) ?></small>
                                </td>
                                <td><?= h($vr['vaccine_name']) ?></td>
                                <td class="d-none d-md-table-cell"><?= h($vr['owner_name']) ?></td>
                                <td class="d-none d-md-table-cell"><small><?= h($vr['owner_phone']) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Records -->
            <div class="card">
                <div class="card-header"><i class="bi bi-journal-medical me-2 text-info"></i>最近の診察記録</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>日付</th><th>患畜</th><th>診断</th><th>担当医</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRecords as $rec): ?>
                                <tr data-href="?page=medical_record&id=<?= $rec['id'] ?>" style="cursor:pointer;">
                                    <td><?= formatDate($rec['visit_date'], 'm/d') ?></td>
                                    <td>
                                        <strong><?= h($rec['patient_name']) ?></strong>
                                        <small class="text-muted ms-1"><?= h($rec['patient_code']) ?></small>
                                    </td>
                                    <td><?= h(mb_substr($rec['diagnosis_name'] ?: $rec['assessment'], 0, 30)) ?></td>
                                    <td><?= h($rec['vet_name']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Notices -->
            <div class="card">
                <div class="card-header"><i class="bi bi-megaphone me-2 text-danger"></i>お知らせ</div>
                <div class="card-body p-0">
                    <?php if (empty($notices)): ?>
                    <div class="text-center text-muted py-3">お知らせはありません</div>
                    <?php else: ?>
                    <?php foreach ($notices as $notice): ?>
                    <a href="?page=notice_detail&id=<?= $notice['id'] ?>" class="d-block p-3 border-bottom text-decoration-none <?= $notice['priority'] === 'high' ? 'bg-danger bg-opacity-10' : '' ?>" style="color:var(--vc-text);">
                        <div class="d-flex justify-content-between mb-1">
                            <strong class="<?= $notice['priority'] === 'high' ? 'text-danger' : '' ?>" style="font-size:0.85rem;">
                                <?php if ($notice['priority'] === 'high'): ?><i class="bi bi-exclamation-triangle-fill me-1"></i><?php endif; ?>
                                <?= h(mb_substr($notice['title'], 0, 30)) ?>
                            </strong>
                        </div>
                        <small class="text-muted"><?= h(mb_substr($notice['content'], 0, 50)) ?>...</small>
                        <div class="mt-1">
                            <small class="text-muted"><?= h($notice['posted_by_name']) ?> - <?= formatDate($notice['created_at'], 'm/d') ?></small>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Insurance Claims -->
            <?php if (!empty($pendingClaims)): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-medical me-2 text-info"></i>処理待ちレセプト</span>
                    <a href="?page=insurance_claims" class="btn btn-sm btn-outline-info">全て</a>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($pendingClaims as $cl): ?>
                    <a href="?page=insurance_claim_form&id=<?= $cl['id'] ?>" class="d-block p-2 border-bottom text-decoration-none" style="color:var(--vc-text);">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong style="font-size:0.85rem;"><?= h($cl['patient_name']) ?></strong>
                                <small class="text-muted d-block"><?= h($cl['company_name']) ?></small>
                            </div>
                            <span class="badge bg-<?= $cl['claim_status'] === 'draft' ? 'secondary' : 'primary' ?> align-self-center">
                                <?= $cl['claim_status'] === 'draft' ? '下書き' : '請求済' ?>
                            </span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Nursing Tasks -->
            <?php if ($auth->hasRole([ROLE_ADMIN, ROLE_VET, ROLE_NURSE])): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-check2-square me-2 text-primary"></i>今日のタスク</span>
                    <a href="?page=nursing_tasks" class="btn btn-sm btn-outline-primary">全て</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingTasks)): ?>
                    <div class="text-center text-muted py-3"><i class="bi bi-check-circle me-1"></i>未完了タスクなし</div>
                    <?php else: ?>
                    <?php foreach ($pendingTasks as $task): ?>
                    <div class="task-item task-priority-<?= h($task['priority']) ?>">
                        <div class="flex-grow-1">
                            <div class="fw-bold" style="font-size:0.85rem;"><?= h($task['task_name']) ?></div>
                            <small class="text-muted">
                                <?= h($task['patient_name']) ?> (<?= h(getSpeciesName($task['species'])) ?>)
                                <span class="ms-1"><?= date('H:i', strtotime($task['scheduled_at'])) ?></span>
                            </small>
                        </div>
                        <?php if ($task['assigned_name']): ?>
                        <small class="badge bg-light text-dark"><?= h($task['assigned_name']) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header"><i class="bi bi-lightning me-2 text-warning"></i>クイックアクション</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="?page=patient_form" class="btn btn-glass btn-sm text-start">
                            <i class="bi bi-plus-circle text-primary me-2"></i>新規患畜登録
                        </a>
                        <a href="?page=owner_form" class="btn btn-glass btn-sm text-start">
                            <i class="bi bi-person-plus text-success me-2"></i>新規飼い主登録
                        </a>
                        <a href="?page=admission_form" class="btn btn-glass btn-sm text-start">
                            <i class="bi bi-hospital text-warning me-2"></i>新規入院登録
                        </a>
                        <a href="?page=lab_import" class="btn btn-glass btn-sm text-start">
                            <i class="bi bi-file-earmark-arrow-up text-info me-2"></i>検査CSV取込
                        </a>
                        <a href="?page=insurance_claim_form" class="btn btn-glass btn-sm text-start">
                            <i class="bi bi-file-earmark-medical text-danger me-2"></i>レセプト作成
                        </a>
                        <a href="?page=documents" class="btn btn-glass btn-sm text-start">
                            <i class="bi bi-file-earmark-text text-secondary me-2"></i>書類作成
                        </a>
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div class="card">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between">
                        <small class="text-muted"><?= h(APP_NAME) ?> v<?= APP_VERSION ?></small>
                        <small class="text-muted">今月の診察: <?= $monthPatients ?>件</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Click on row to navigate
document.querySelectorAll('tr[data-href]').forEach(row => {
    row.addEventListener('click', function(e) {
        if (e.target.tagName === 'A' || e.target.closest('a')) return;
        location.href = this.dataset.href;
    });
});
</script>
