<?php
/**
 * 待合表示 - 番号モード対応・予約優先注意書き
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = Database::getInstance();
$today = date('Y-m-d');
$hospitalName = getSetting('hospital_name', APP_NAME);
$displayMode = getSetting('accounting_display_mode', 'name');
$priorityReservation = getSetting('priority_reservation', '0') === '1';

$queue = $db->fetchAll("
    SELECT a.id, a.appointment_time, a.status, a.appointment_type, a.queue_number, a.is_priority,
           p.name as patient_name, p.species,
           o.name as owner_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN owners o ON a.owner_id = o.id OR p.owner_id = o.id
    WHERE a.appointment_date = ? AND a.status IN ('checked_in', 'in_progress')
    ORDER BY 
        CASE a.status WHEN 'in_progress' THEN 1 WHEN 'checked_in' THEN 2 END,
        a.is_priority DESC,
        a.queue_number ASC,
        a.appointment_time ASC
", [$today]);

$inProgress = array_filter($queue, fn($q) => $q['status'] === 'in_progress');
$waiting = array_filter($queue, fn($q) => $q['status'] === 'checked_in');
?>
<!DOCTYPE html>
<html lang="ja"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="15">
    <title>待合表示 - <?= h($hospitalName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+JP:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #f1f5f9; min-height: 100vh; font-family: 'Inter','Noto Sans JP',sans-serif; margin:0; }
        .display-header { padding: 24px 40px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .display-content { padding: 30px 40px; }
        .queue-display-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
        }
        .queue-display-card.active {
            background: rgba(16,185,129,0.15);
            border-color: rgba(16,185,129,0.4);
            animation: pulse 2s infinite;
        }
        .queue-display-card .q-number {
            width: 64px; height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }
        .queue-display-card .q-info { flex-grow: 1; }
        .queue-display-card .q-name { font-size: 1.3rem; font-weight: 700; }
        .queue-display-card .q-detail { font-size: 0.9rem; opacity: 0.7; }
        .queue-display-card .q-status { font-size: 0.9rem; font-weight: 600; }
        .clock { font-size: 3rem; font-weight: 800; letter-spacing: -0.02em; }
        .date-display { font-size: 1.1rem; opacity: 0.7; }
        @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.85; } }
        .priority-notice {
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.3);
            border-radius: 12px;
            padding: 16px 24px;
            margin-top: 24px;
            font-size: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="display-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;background:linear-gradient(135deg,#6366f1,#06b6d4);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-heart-pulse-fill" style="font-size:1.3rem;"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0"><?= h($hospitalName) ?></h4>
                <small class="opacity-50">待合案内</small>
            </div>
        </div>
        <div class="text-end">
            <div class="clock" id="clock"><?= date('H:i') ?></div>
            <div class="date-display"><?= h(formatDateJP($today)) ?></div>
        </div>
    </div>

    <div class="display-content">
        <div class="row g-4">
            <!-- Now Serving -->
            <div class="col-lg-6">
                <h5 class="fw-bold mb-3 text-success"><i class="bi bi-play-circle-fill me-2"></i>診察中</h5>
                <?php if (empty($inProgress)): ?>
                <div class="queue-display-card">
                    <div class="q-info text-center opacity-50 py-3">
                        <i class="bi bi-pause-circle" style="font-size:2rem;"></i>
                        <div class="mt-2">現在診察中の方はいません</div>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($inProgress as $q): ?>
                <div class="queue-display-card active">
                    <div class="q-number" style="background:linear-gradient(135deg,#10b981,#059669);">
                        <?php if ($displayMode === 'number' && $q['queue_number']): ?>
                            <?= $q['queue_number'] ?>
                        <?php else: ?>
                            <i class="bi bi-play-fill"></i>
                        <?php endif; ?>
                    </div>
                    <div class="q-info">
                        <div class="q-name">
                            <?php if ($displayMode === 'number' && $q['queue_number']): ?>
                                <?= $q['queue_number'] ?>番
                            <?php else: ?>
                                <?= h($q['owner_name'] ?? $q['patient_name'] ?? '---') ?>様
                            <?php endif; ?>
                        </div>
                        <div class="q-detail"><?= h(substr($q['appointment_time'], 0, 5)) ?></div>
                    </div>
                    <div class="q-status text-success"><i class="bi bi-broadcast me-1"></i>診察中</div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Waiting -->
            <div class="col-lg-6">
                <h5 class="fw-bold mb-3 text-info"><i class="bi bi-hourglass-split me-2"></i>お待ちの方 (<?= count($waiting) ?>名)</h5>
                <?php if (empty($waiting)): ?>
                <div class="queue-display-card">
                    <div class="q-info text-center opacity-50 py-3">
                        <div>お待ちの方はいません</div>
                    </div>
                </div>
                <?php else: ?>
                <?php $wNum = 1; foreach ($waiting as $q): ?>
                <div class="queue-display-card">
                    <div class="q-number" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">
                        <?php if ($displayMode === 'number' && $q['queue_number']): ?>
                            <?= $q['queue_number'] ?>
                        <?php else: ?>
                            <?= $wNum++ ?>
                        <?php endif; ?>
                    </div>
                    <div class="q-info">
                        <div class="q-name">
                            <?php if ($displayMode === 'number' && $q['queue_number']): ?>
                                <?= $q['queue_number'] ?>番
                            <?php else: ?>
                                <?= h($q['owner_name'] ?? $q['patient_name'] ?? '---') ?>様
                            <?php endif; ?>
                        </div>
                        <div class="q-detail"><?= h(substr($q['appointment_time'], 0, 5)) ?></div>
                    </div>
                    <div class="q-status text-info"><i class="bi bi-clock me-1"></i>お待ち</div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <?php if ($priorityReservation): ?>
        <div class="priority-notice">
            <i class="bi bi-info-circle-fill text-warning me-2" style="font-size:1.2rem;"></i>
            <strong>ご予約の方を優先してお呼びいたします。ご了承ください。</strong>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function updateClock() {
        const now = new Date();
        document.getElementById('clock').textContent = 
            String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
    }
    setInterval(updateClock, 1000);
    </script>
</body></html>
