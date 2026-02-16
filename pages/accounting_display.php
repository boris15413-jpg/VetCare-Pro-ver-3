<?php
/**
 * 会計待合表示 - 番号モード対応
 * Public display for accounting queue
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = Database::getInstance();
$today = date('Y-m-d');
$hospitalName = getSetting('hospital_name', APP_NAME);
$displayMode = getSetting('accounting_display_mode', 'name'); // 'name' or 'number'
$priorityReservation = getSetting('priority_reservation', '0') === '1';

// Get today's completed appointments with invoices (accounting queue)
$queue = $db->fetchAll("
    SELECT a.id, a.appointment_time, a.status, a.queue_number,
           p.name as patient_name, p.species, p.patient_code,
           o.name as owner_name,
           i.id as invoice_id, i.payment_status, i.total as invoice_total
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN owners o ON a.owner_id = o.id OR p.owner_id = o.id
    LEFT JOIN invoices i ON i.patient_id = a.patient_id AND DATE(i.created_at) = ?
    WHERE a.appointment_date = ? AND a.status IN ('completed','in_progress')
    ORDER BY 
        CASE i.payment_status WHEN 'unpaid' THEN 1 WHEN NULL THEN 2 ELSE 3 END,
        a.queue_number ASC,
        a.appointment_time ASC
", [$today, $today]);

$waitingForPayment = array_filter($queue, fn($q) => ($q['payment_status'] ?? '') !== 'paid');
$paymentDone = array_filter($queue, fn($q) => ($q['payment_status'] ?? '') === 'paid');
?>
<!DOCTYPE html>
<html lang="ja"><head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="15">
    <title>会計待合表示 - <?= h($hospitalName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+JP:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #f1f5f9; min-height: 100vh; font-family: 'Inter','Noto Sans JP',sans-serif; margin:0; }
        .display-header { padding: 24px 40px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .display-content { padding: 30px 40px; }
        .queue-card {
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
        .queue-card.calling {
            background: rgba(245,158,11,0.15);
            border-color: rgba(245,158,11,0.4);
            animation: pulse 2s infinite;
        }
        .queue-card.done {
            opacity: 0.4;
        }
        .q-number {
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
        .q-info { flex-grow: 1; }
        .q-name { font-size: 1.3rem; font-weight: 700; }
        .q-detail { font-size: 0.9rem; opacity: 0.7; }
        .q-status { font-size: 0.9rem; font-weight: 600; }
        .clock { font-size: 3rem; font-weight: 800; letter-spacing: -0.02em; }
        .date-display { font-size: 1.1rem; opacity: 0.7; }
        .section-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 16px; }
        @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.85; } }
        .priority-notice {
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.3);
            border-radius: 12px;
            padding: 12px 20px;
            margin-bottom: 24px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="display-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;background:linear-gradient(135deg,#6366f1,#06b6d4);border-radius:14px;display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-receipt" style="font-size:1.3rem;"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0"><?= h($hospitalName) ?></h4>
                <small class="opacity-50">会計待合案内</small>
            </div>
        </div>
        <div class="text-end">
            <div class="clock" id="clock"><?= date('H:i') ?></div>
            <div class="date-display"><?= h(formatDateJP($today)) ?></div>
        </div>
    </div>

    <div class="display-content">
        <?php if ($priorityReservation): ?>
        <div class="priority-notice">
            <i class="bi bi-info-circle-fill text-warning me-2"></i>
            <strong>ご予約の方を優先してお呼びいたします。ご了承ください。</strong>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Waiting for payment -->
            <div class="col-lg-6">
                <div class="section-title text-warning">
                    <i class="bi bi-hourglass-split me-2"></i>会計待ち (<?= count($waitingForPayment) ?>件)
                </div>
                <?php if (empty($waitingForPayment)): ?>
                <div class="queue-card">
                    <div class="q-info text-center opacity-50 py-3">
                        <i class="bi bi-check-circle" style="font-size:2rem;"></i>
                        <div class="mt-2">会計待ちの方はいません</div>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($waitingForPayment as $i => $q): ?>
                <div class="queue-card <?= $i === 0 ? 'calling' : '' ?>">
                    <div class="q-number" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                        <?php if ($displayMode === 'number' && $q['queue_number']): ?>
                            <?= $q['queue_number'] ?>
                        <?php else: ?>
                            <?= $i + 1 ?>
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
                        <div class="q-detail">
                            <?php if ($q['invoice_total']): ?>
                            <i class="bi bi-receipt me-1"></i><?= number_format($q['invoice_total']) ?>円
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="q-status text-warning">
                        <?= $i === 0 ? '<i class="bi bi-megaphone-fill me-1"></i>お呼び出し中' : '<i class="bi bi-clock me-1"></i>お待ちください' ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Payment completed -->
            <div class="col-lg-6">
                <div class="section-title text-success">
                    <i class="bi bi-check-circle-fill me-2"></i>お会計済み
                </div>
                <?php if (empty($paymentDone)): ?>
                <div class="queue-card">
                    <div class="q-info text-center opacity-50 py-3">
                        <div>まだありません</div>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach (array_slice($paymentDone, 0, 10) as $q): ?>
                <div class="queue-card done">
                    <div class="q-number" style="background:linear-gradient(135deg,#10b981,#059669);">
                        <?php if ($displayMode === 'number' && $q['queue_number']): ?>
                            <?= $q['queue_number'] ?>
                        <?php else: ?>
                            <i class="bi bi-check-lg"></i>
                        <?php endif; ?>
                    </div>
                    <div class="q-info">
                        <div class="q-name" style="font-size:1rem;">
                            <?php if ($displayMode === 'number' && $q['queue_number']): ?>
                                <?= $q['queue_number'] ?>番
                            <?php else: ?>
                                <?= h($q['owner_name'] ?? '---') ?>様
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="q-status text-success"><i class="bi bi-check-circle me-1"></i>完了</div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
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
