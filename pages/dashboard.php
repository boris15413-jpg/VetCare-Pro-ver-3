<?php
/**
 * ダッシュボード
 */
$today = date('Y-m-d');

// 統計
$totalPatients = $db->count('patients', 'is_active = 1');
$todayAppointments = $db->count('appointments', 'appointment_date = ? AND status = ?', [$today, 'scheduled']);
$admittedCount = $db->count('admissions', 'status = ?', ['admitted']);
$pendingOrders = $db->count('orders', 'status = ?', ['pending']);

// 入院中の患畜
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

// 今日の予約
$todayAppts = $db->fetchAll("
    SELECT ap.*, p.name as patient_name, p.species, o.name as owner_name, s.name as staff_name
    FROM appointments ap
    LEFT JOIN patients p ON ap.patient_id = p.id
    LEFT JOIN owners o ON ap.owner_id = o.id OR p.owner_id = o.id
    LEFT JOIN staff s ON ap.staff_id = s.id
    WHERE ap.appointment_date = ?
    ORDER BY ap.appointment_time ASC
", [$today]);

// 最新のお知らせ
$notices = $db->fetchAll("
    SELECT n.*, s.name as posted_by_name
    FROM notices n
    JOIN staff s ON n.posted_by = s.id
    WHERE n.is_active = 1
    ORDER BY n.created_at DESC LIMIT 5
");

// 未完了の看護タスク
$pendingTasks = $db->fetchAll("
    SELECT nt.*, p.name as patient_name, p.species, s.name as assigned_name
    FROM nursing_tasks nt
    JOIN patients p ON nt.patient_id = p.id
    LEFT JOIN staff s ON nt.assigned_to = s.id
    WHERE nt.status = 'pending' AND DATE(nt.scheduled_at) = ?
    ORDER BY nt.priority DESC, nt.scheduled_at ASC
    LIMIT 10
", [$today]);

// 最近の活動
$recentRecords = $db->fetchAll("
    SELECT mr.*, p.name as patient_name, p.patient_code, s.name as vet_name
    FROM medical_records mr
    JOIN patients p ON mr.patient_id = p.id
    JOIN staff s ON mr.staff_id = s.id
    ORDER BY mr.created_at DESC LIMIT 8
");
?>

<div class="fade-in">
    <!-- ウェルカム -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-speedometer2 me-2"></i>ダッシュボード</h4>
            <small class="text-muted">ようこそ、<?= h($auth->currentUserName()) ?>さん</small>
        </div>
        <div class="d-flex gap-2">
            <a href="?page=patient_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規患畜</a>
            <a href="?page=appointment_form" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-plus me-1"></i>予約追加</a>
        </div>
    </div>

    <!-- 統計カード -->
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

    <div class="row g-3">
        <!-- 左カラム -->
        <div class="col-lg-8">
            <!-- 入院中の患畜 -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-hospital me-2"></i>入院中の患畜</span>
                    <a href="?page=admissions" class="btn btn-sm btn-outline-primary">全て見る</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($admittedPatients)): ?>
                        <div class="text-center text-muted py-4">現在入院中の患畜はいません</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>患畜</th><th class="d-none d-md-table-cell">種別</th><th>病棟/ケージ</th><th>入院日</th><th>状態</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admittedPatients as $ap): ?>
                                <tr data-href="?page=temperature_chart&admission_id=<?= $ap['id'] ?>">
                                    <td>
                                        <strong><?= h($ap['patient_name']) ?></strong>
                                        <small class="text-muted d-block"><?= h($ap['patient_code']) ?></small>
                                    </td>
                                    <td class="d-none d-md-table-cell"><?= h(getSpeciesName($ap['species'])) ?> <?= h($ap['breed']) ?></td>
                                    <td><span class="badge bg-secondary"><?= h($ap['ward']) ?> <?= h($ap['cage_number']) ?></span></td>
                                    <td><?= formatDate($ap['admission_date'], 'm/d') ?></td>
                                    <td><?= getAdmissionStatusBadge($ap['status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 今日の予約 -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar-check me-2"></i>本日の予約</span>
                    <a href="?page=appointments" class="btn btn-sm btn-outline-primary">全て見る</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($todayAppts)): ?>
                        <div class="text-center text-muted py-4">本日の予約はありません</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>時間</th><th>患畜</th><th class="d-none d-md-table-cell">飼い主</th><th>理由</th><th>担当</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todayAppts as $apt): ?>
                                <tr>
                                    <td><strong><?= h(substr($apt['appointment_time'], 0, 5)) ?></strong></td>
                                    <td><?= h($apt['patient_name'] ?? '新規') ?></td>
                                    <td class="d-none d-md-table-cell"><?= h($apt['owner_name'] ?? '-') ?></td>
                                    <td><?= h($apt['reason']) ?></td>
                                    <td><?= h($apt['staff_name'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 最近の診察記録 -->
            <div class="card">
                <div class="card-header"><i class="bi bi-journal-medical me-2"></i>最近の診察記録</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr><th>日付</th><th>患畜</th><th>診断</th><th>担当医</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRecords as $rec): ?>
                                <tr data-href="?page=medical_record&id=<?= $rec['id'] ?>">
                                    <td><?= formatDate($rec['visit_date'], 'm/d') ?></td>
                                    <td>
                                        <strong><?= h($rec['patient_name']) ?></strong>
                                        <small class="text-muted"><?= h($rec['patient_code']) ?></small>
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

        <!-- 右カラム -->
        <div class="col-lg-4">
            <!-- お知らせ -->
            <div class="card">
                <div class="card-header"><i class="bi bi-megaphone me-2"></i>お知らせ</div>
                <div class="card-body p-0">
                    <?php foreach ($notices as $notice): ?>
                    <div class="p-3 border-bottom <?= $notice['priority'] === 'high' ? 'bg-danger bg-opacity-10' : '' ?>">
                        <div class="d-flex justify-content-between mb-1">
                            <strong class="<?= $notice['priority'] === 'high' ? 'text-danger' : '' ?>">
                                <?php if ($notice['priority'] === 'high'): ?><i class="bi bi-exclamation-triangle me-1"></i><?php endif; ?>
                                <?= h($notice['title']) ?>
                            </strong>
                        </div>
                        <small class="text-muted"><?= h(mb_substr($notice['content'], 0, 60)) ?>...</small>
                        <div class="mt-1">
                            <small class="text-muted"><?= h($notice['posted_by_name']) ?> - <?= formatDate($notice['created_at'], 'm/d H:i') ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 看護タスク -->
            <?php if ($auth->hasRole([ROLE_ADMIN, ROLE_VET, ROLE_NURSE])): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-check2-square me-2"></i>今日の看護タスク</span>
                    <a href="?page=nursing_tasks" class="btn btn-sm btn-outline-primary">全て</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pendingTasks)): ?>
                        <div class="text-center text-muted py-3">未完了タスクなし</div>
                    <?php else: ?>
                        <?php foreach ($pendingTasks as $task): ?>
                        <div class="task-item task-priority-<?= h($task['priority']) ?>">
                            <div class="flex-grow-1">
                                <div class="fw-bold"><?= h($task['task_name']) ?></div>
                                <small class="text-muted">
                                    <?= h($task['patient_name']) ?> (<?= h(getSpeciesName($task['species'])) ?>)
                                    · <?= date('H:i', strtotime($task['scheduled_at'])) ?>
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

            <!-- クイックアクション -->
            <div class="card">
                <div class="card-header"><i class="bi bi-lightning me-2"></i>クイックアクション</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="?page=patient_form" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-plus-circle me-2"></i>新規患畜登録
                        </a>
                        <a href="?page=owner_form" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-person-plus me-2"></i>新規飼い主登録
                        </a>
                        <a href="?page=admission_form" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-hospital me-2"></i>新規入院登録
                        </a>
                        <a href="?page=order_form" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-list-check me-2"></i>新規オーダー作成
                        </a>
                        <a href="?page=documents" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-file-earmark-text me-2"></i>書類作成
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
