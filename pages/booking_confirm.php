<?php
/**
 * VetCare Pro - Booking Confirmation Page (Public)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = Database::getInstance();
$hospitalName = getSetting('hospital_name', APP_NAME);
$hospitalPhone = getSetting('hospital_phone', '');
$hospitalAddress = getSetting('hospital_address', '');
$token = $_GET['token'] ?? '';
$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';
$isNew = ($_GET['new'] ?? '0') === '1';

// Lookup appointment by token if available
$appointment = null;
if ($token) {
    try {
        $appointment = $db->fetch(
            "SELECT * FROM appointments WHERE reservation_token = ? ORDER BY id DESC LIMIT 1",
            [$token]
        );
        if ($appointment) {
            $date = $appointment['appointment_date'];
            $time = $appointment['appointment_time'];
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>予約完了 - <?= h($hospitalName) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
    body {
        font-family: 'Noto Sans JP', sans-serif;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        min-height: 100vh; margin: 0;
    }
    .confirm-wrapper { max-width: 600px; margin: 0 auto; padding: 40px 16px; }
    .confirm-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        overflow: hidden;
    }
    .confirm-header {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        text-align: center;
        padding: 36px 24px;
    }
    .confirm-icon {
        width: 80px; height: 80px;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        display: inline-flex;
        align-items: center; justify-content: center;
        margin-bottom: 16px;
    }
    .confirm-icon i { font-size: 2.5rem; }
    .confirm-body { padding: 28px; }
    .detail-card {
        background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
        border: 1px solid #bbf7d0;
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .detail-row {
        display: flex; justify-content: space-between;
        padding: 6px 0; font-size: 0.88rem;
    }
    .detail-row .label { color: #6b7280; }
    .detail-row .value { font-weight: 600; }
    </style>
</head>
<body>
<div class="confirm-wrapper">
    <div class="confirm-card">
        <div class="confirm-header">
            <div class="confirm-icon"><i class="bi bi-check-lg"></i></div>
            <h3 class="fw-bold mb-1">ご予約を承りました</h3>
            <p class="mb-0 opacity-75">ご来院をお待ちしております</p>
        </div>
        <div class="confirm-body">
            <div class="detail-card">
                <div class="detail-row" style="font-weight:700;font-size:1rem;border-bottom:2px solid #10b981;padding-bottom:10px;margin-bottom:8px;">
                    <span><i class="bi bi-calendar-check me-2" style="color:#10b981;"></i>予約日時</span>
                    <span style="color:#2563eb;"><?= h(formatDateJP($date)) ?> <?= h($time) ?></span>
                </div>
                <?php if ($token): ?>
                <div class="detail-row">
                    <span class="label">予約番号</span>
                    <span class="value" style="font-family:monospace;"><?= h($token) ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="label">種別</span>
                    <span class="value"><?= $isNew ? '<span style="color:#2563eb;"><i class="bi bi-person-plus me-1"></i>初診</span>' : '再診' ?></span>
                </div>
                <?php if ($appointment): ?>
                <div class="detail-row">
                    <span class="label">お名前</span>
                    <span class="value"><?= h($appointment['owner_name_text'] ?? '-') ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="p-3 rounded-3 mb-3" style="background:#fffbeb;border:1px solid #fde68a;">
                <strong style="font-size:0.88rem;"><i class="bi bi-info-circle me-1 text-warning"></i>ご来院時のお願い</strong>
                <ul class="mb-0 mt-1" style="padding-left:20px;font-size:0.82rem;color:#78350f;">
                    <li>予約時間の <strong>5分前</strong> までにお越しください。</li>
                    <li>初診の方は保険証・ワクチン証明書をお持ちください。</li>
                    <li>キャンセル・変更はお電話にてお願いいたします。</li>
                </ul>
            </div>

            <?php if ($hospitalPhone || $hospitalAddress): ?>
            <div class="p-3 rounded-3 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                <h6 class="fw-bold mb-2" style="font-size:0.88rem;"><i class="bi bi-building me-2"></i><?= h($hospitalName) ?></h6>
                <?php if ($hospitalAddress): ?>
                <div style="font-size:0.82rem;color:#6b7280;"><i class="bi bi-geo-alt me-1"></i><?= h($hospitalAddress) ?></div>
                <?php endif; ?>
                <?php if ($hospitalPhone): ?>
                <div style="font-size:0.82rem;color:#6b7280;"><i class="bi bi-telephone me-1"></i><?= h($hospitalPhone) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="d-grid gap-2">
                <?php
                $bookingLink = (defined('BOOKING_GATEWAY') && BOOKING_GATEWAY) ? './?page=booking' : 'index.php?page=public_booking';
                ?>
                <a href="<?= h($bookingLink) ?>" class="btn btn-outline-primary" style="border-radius:12px;padding:12px;font-weight:600;">
                    <i class="bi bi-calendar-plus me-2"></i>別の予約をする
                </a>
            </div>
        </div>
    </div>
    <div class="text-center mt-3">
        <small style="color:rgba(255,255,255,0.7);">Powered by <?= h(APP_NAME) ?> v<?= APP_VERSION ?></small>
    </div>
</div>
</body>
</html>
