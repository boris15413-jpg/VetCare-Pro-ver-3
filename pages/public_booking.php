<?php
/**
 * VetCare Pro - Premium Public Online Booking Page
 * Full-featured: address, doctor display, real-time availability, slot capacity
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = Database::getInstance();

$bookingEnabled = getSetting('public_booking_enabled', '0');
$newPatientEnabled = getSetting('booking_new_patient_enabled', '1');
$hospitalName = getSetting('hospital_name', APP_NAME);
$hospitalPhone = getSetting('hospital_phone', '');
$hospitalAddress = getSetting('hospital_address', '');
$hospitalEmail = getSetting('hospital_email', '');
$startTime = getSetting('appointment_start_time', '09:00');
$endTime = getSetting('appointment_end_time', '18:00');
$interval = (int)getSetting('appointment_interval', '30');
$hoursWeekday = getSetting('business_hours_weekday', '9:00〜12:00 / 16:00〜19:00');
$hoursSat = getSetting('business_hours_saturday', '9:00〜12:00');
$hoursHoliday = getSetting('business_hours_holiday', '休診');
$bookingMessage = getSetting('booking_welcome_message', '');
$bookingNotice = getSetting('booking_notice_message', '');
$maxPerSlot = (int)getSetting('max_appointments_per_slot', '3');
$priorityMode = getSetting('priority_reservation', '0') === '1';
$bookingDaysAhead = (int)getSetting('booking_days_ahead', '60');
$hospitalLogo = getSetting('hospital_logo', '');

// API base path - detect context
if (defined('BOOKING_GATEWAY') && BOOKING_GATEWAY) {
    // Accessed from /booking/ gateway - API is at /api/booking.php
    $apiBase = '../api/booking.php';
} else {
    // Accessed from main index.php
    $apiBase = 'api/booking.php';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web予約 - <?= h($hospitalName) ?></title>
    <meta name="description" content="<?= h($hospitalName) ?>のオンライン診察予約ページです。24時間いつでもWeb予約が可能です。">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
    :root {
        --booking-primary: #2563eb;
        --booking-primary-light: #3b82f6;
        --booking-primary-dark: #1d4ed8;
        --booking-primary-50: #eff6ff;
        --booking-success: #10b981;
        --booking-warning: #f59e0b;
        --booking-danger: #ef4444;
        --booking-gray-50: #f8fafc;
        --booking-gray-100: #f1f5f9;
        --booking-gray-200: #e2e8f0;
        --booking-gray-500: #64748b;
        --booking-gray-700: #334155;
        --booking-gray-900: #0f172a;
        --booking-radius: 16px;
        --booking-shadow: 0 20px 60px rgba(0,0,0,0.08);
    }

    * { box-sizing: border-box; }

    body {
        font-family: 'Noto Sans JP', -apple-system, BlinkMacSystemFont, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        min-height: 100vh;
        margin: 0;
        color: var(--booking-gray-900);
    }

    .booking-wrapper {
        max-width: 780px;
        margin: 0 auto;
        padding: 24px 16px 40px;
    }

    /* Header */
    .booking-hero {
        text-align: center;
        color: #fff;
        padding: 32px 20px 28px;
    }
    .booking-hero .hero-logo {
        width: 64px; height: 64px;
        background: rgba(255,255,255,0.2);
        backdrop-filter: blur(10px);
        border-radius: 18px;
        display: inline-flex;
        align-items: center; justify-content: center;
        font-size: 1.8rem;
        margin-bottom: 16px;
        border: 2px solid rgba(255,255,255,0.3);
    }
    .booking-hero h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 4px; }
    .booking-hero .hero-sub { opacity: 0.85; font-size: 0.9rem; font-weight: 400; }

    /* Main card */
    .booking-main {
        background: #fff;
        border-radius: var(--booking-radius);
        box-shadow: var(--booking-shadow);
        overflow: hidden;
    }

    /* Step indicator */
    .step-indicator {
        display: flex;
        background: var(--booking-gray-50);
        border-bottom: 1px solid var(--booking-gray-200);
        padding: 0;
    }
    .step-item {
        flex: 1;
        text-align: center;
        padding: 14px 8px;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--booking-gray-500);
        position: relative;
        transition: all 0.3s;
        cursor: default;
    }
    .step-item .step-num {
        display: inline-flex;
        width: 24px; height: 24px;
        border-radius: 50%;
        align-items: center; justify-content: center;
        background: var(--booking-gray-200);
        color: var(--booking-gray-500);
        font-size: 0.7rem;
        font-weight: 700;
        margin-right: 6px;
        transition: all 0.3s;
    }
    .step-item.active { color: var(--booking-primary); background: #fff; }
    .step-item.active .step-num { background: var(--booking-primary); color: #fff; }
    .step-item.done { color: var(--booking-success); }
    .step-item.done .step-num { background: var(--booking-success); color: #fff; }
    .step-item.active::after {
        content: '';
        position: absolute; bottom: 0; left: 0; right: 0;
        height: 3px;
        background: var(--booking-primary);
    }

    .booking-body { padding: 28px 28px 32px; }
    @media (max-width: 576px) { .booking-body { padding: 20px 16px 24px; } }

    /* Section titles */
    .section-title {
        font-size: 1rem; font-weight: 700;
        margin-bottom: 16px;
        display: flex; align-items: center; gap: 8px;
    }
    .section-title i { color: var(--booking-primary); font-size: 1.1rem; }

    /* Time slot grid */
    .slot-period-label {
        font-size: 0.75rem; font-weight: 700;
        color: var(--booking-gray-500);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 16px 0 8px;
        display: flex; align-items: center; gap: 6px;
    }
    .slot-period-label::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--booking-gray-200);
    }

    .slot-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        gap: 8px;
    }

    .slot-btn {
        position: relative;
        padding: 10px 6px 8px;
        text-align: center;
        border: 2px solid var(--booking-gray-200);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
        background: #fff;
        user-select: none;
    }
    .slot-btn:hover:not(.disabled) {
        border-color: var(--booking-primary-light);
        background: var(--booking-primary-50);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37,99,235,0.12);
    }
    .slot-btn.selected {
        border-color: var(--booking-primary);
        background: var(--booking-primary);
        color: #fff;
        box-shadow: 0 4px 16px rgba(37,99,235,0.3);
        transform: translateY(-1px);
    }
    .slot-btn.selected .slot-remaining { color: rgba(255,255,255,0.8); }
    .slot-btn.disabled {
        opacity: 0.4;
        cursor: not-allowed;
        background: var(--booking-gray-100);
    }
    .slot-btn.full { border-color: var(--booking-danger); opacity: 0.5; }
    .slot-btn.full .slot-remaining { color: var(--booking-danger); }

    .slot-time { font-size: 0.9rem; font-weight: 700; line-height: 1.2; }
    .slot-remaining { font-size: 0.6rem; color: var(--booking-gray-500); margin-top: 2px; }
    .slot-remaining.low { color: var(--booking-warning); font-weight: 600; }

    /* Doctor cards */
    .doctor-list {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    .doctor-card {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        background: var(--booking-gray-50);
        border: 1px solid var(--booking-gray-200);
        border-radius: 12px;
        font-size: 0.85rem;
        flex: 1 1 180px;
    }
    .doctor-avatar {
        width: 36px; height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--booking-primary) 0%, #818cf8 100%);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 0.9rem; font-weight: 700;
        flex-shrink: 0;
    }
    .doctor-name { font-weight: 600; }
    .doctor-time { font-size: 0.72rem; color: var(--booking-gray-500); }

    /* Info boxes */
    .info-box {
        padding: 16px;
        border-radius: 12px;
        border: 1px solid;
        margin-bottom: 16px;
        font-size: 0.85rem;
    }
    .info-box.info-blue { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
    .info-box.info-amber { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .info-box.info-green { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
    .info-box.info-red { background: #fef2f2; border-color: #fecaca; color: #991b1b; }

    /* Form */
    .form-label { font-weight: 600; font-size: 0.85rem; margin-bottom: 4px; }
    .form-label .required-mark { color: var(--booking-danger); margin-left: 2px; }
    .form-control, .form-select {
        border-radius: 10px;
        border-color: var(--booking-gray-200);
        font-size: 0.9rem;
        padding: 10px 14px;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--booking-primary);
        box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
    }
    .form-text { font-size: 0.75rem; color: var(--booking-gray-500); }

    /* New patient section */
    .new-patient-box {
        background: #f0fdf4;
        border: 2px solid #86efac;
        border-radius: 14px;
        padding: 20px;
        margin-top: 16px;
        display: none;
    }
    .new-patient-box.show { display: block; animation: slideDown 0.3s ease; }

    /* Closed day notice */
    .closed-notice {
        text-align: center;
        padding: 32px 20px;
    }
    .closed-notice i { font-size: 3rem; color: var(--booking-gray-500); opacity: 0.4; }

    /* Summary */
    .summary-card {
        background: linear-gradient(135deg, var(--booking-primary-50) 0%, #e0f2fe 100%);
        border: 1px solid #bae6fd;
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 0.88rem;
    }
    .summary-row .label { color: var(--booking-gray-500); }
    .summary-row .value { font-weight: 600; }

    /* Primary button */
    .btn-booking {
        background: linear-gradient(135deg, var(--booking-primary) 0%, #4f46e5 100%);
        color: #fff;
        border: none;
        border-radius: 14px;
        padding: 16px 24px;
        font-size: 1rem;
        font-weight: 700;
        width: 100%;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .btn-booking:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37,99,235,0.3); color: #fff; }
    .btn-booking:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
    .btn-booking .spinner-border { width: 20px; height: 20px; border-width: 2px; }

    .btn-outline-booking {
        background: #fff;
        color: var(--booking-primary);
        border: 2px solid var(--booking-primary);
        border-radius: 14px;
        padding: 12px 24px;
        font-size: 0.9rem;
        font-weight: 600;
        width: 100%;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-outline-booking:hover { background: var(--booking-primary-50); }

    /* Hospital info */
    .hospital-info {
        background: var(--booking-gray-50);
        border-radius: var(--booking-radius);
        padding: 24px;
        margin-top: 20px;
    }
    .hospital-info h5 { font-size: 1rem; font-weight: 700; margin-bottom: 14px; }
    .hours-table { width: 100%; font-size: 0.82rem; }
    .hours-table td { padding: 5px 0; }
    .hours-table td:first-child { font-weight: 600; width: 80px; color: var(--booking-gray-500); }

    /* Footer */
    .booking-footer {
        text-align: center;
        color: rgba(255,255,255,0.6);
        font-size: 0.75rem;
        padding: 20px;
    }

    /* Disabled overlay */
    .disabled-overlay {
        text-align: center;
        padding: 48px 24px;
    }
    .disabled-overlay i { font-size: 4rem; color: var(--booking-gray-500); opacity: 0.3; margin-bottom: 16px; display: block; }

    /* Loading */
    .loading-spinner {
        text-align: center;
        padding: 32px;
        color: var(--booking-gray-500);
    }
    .loading-spinner .spinner-border { color: var(--booking-primary); }

    /* Animations */
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .fade-in { animation: slideDown 0.3s ease; }

    /* Confirmation page */
    .confirm-icon {
        width: 80px; height: 80px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, var(--booking-success) 0%, #059669 100%);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
    .confirm-icon i { font-size: 2.5rem; color: #fff; }

    /* Switch styling */
    .form-switch .form-check-input { width: 3em; height: 1.5em; cursor: pointer; }
    .form-switch .form-check-input:checked { background-color: var(--booking-primary); border-color: var(--booking-primary); }

    /* Calendar status dots */
    .cal-status { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
    .cal-status.available { background: var(--booking-success); }
    .cal-status.moderate { background: #facc15; }
    .cal-status.busy { background: var(--booking-warning); }
    .cal-status.full { background: var(--booking-danger); }
    .cal-status.closed { background: var(--booking-gray-500); }
    </style>
</head>
<body>

<div class="booking-wrapper">
    <!-- Hero -->
    <div class="booking-hero">
        <div class="hero-logo">
            <?php if ($hospitalLogo): ?>
                <img src="../uploads/<?= h($hospitalLogo) ?>" style="height:38px; object-fit:contain;" alt="Logo">
            <?php else: ?>
                <i class="bi bi-heart-pulse-fill"></i>
            <?php endif; ?>
        </div>
        <h1><?= h($hospitalName) ?></h1>
        <div class="hero-sub"><i class="bi bi-calendar-check me-1"></i>オンライン診察予約</div>
        <?php if ($hospitalAddress): ?>
        <div class="hero-sub mt-1" style="font-size:0.78rem;opacity:0.7;"><i class="bi bi-geo-alt me-1"></i><?= h($hospitalAddress) ?></div>
        <?php endif; ?>
    </div>

    <!-- Main Card -->
    <div class="booking-main" id="bookingApp">

        <?php if ($bookingEnabled !== '1'): ?>
        <!-- Booking Disabled -->
        <div class="disabled-overlay">
            <i class="bi bi-calendar-x"></i>
            <h5 style="font-weight:700;">オンライン予約は現在受け付けておりません</h5>
            <p style="color:var(--booking-gray-500);font-size:0.9rem;">お手数ですが、お電話にてご予約ください。</p>
            <?php if ($hospitalPhone): ?>
            <a href="tel:<?= h($hospitalPhone) ?>" class="btn-booking" style="max-width:320px;margin:20px auto 0;text-decoration:none;">
                <i class="bi bi-telephone-fill"></i><?= h($hospitalPhone) ?>
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-item active" id="stepTab1">
                <span class="step-num">1</span>日時選択
            </div>
            <div class="step-item" id="stepTab2">
                <span class="step-num">2</span>情報入力
            </div>
            <div class="step-item" id="stepTab3">
                <span class="step-num">3</span>確認・予約
            </div>
        </div>

        <!-- Step 1: Date & Time -->
        <div class="booking-body" id="step1">
            <?php if ($bookingMessage): ?>
            <div class="info-box info-blue mb-3"><i class="bi bi-info-circle me-2"></i><?= nl2br(h($bookingMessage)) ?></div>
            <?php endif; ?>

            <?php if ($priorityMode): ?>
            <div class="info-box info-amber mb-3"><i class="bi bi-star-fill me-2"></i>当院は予約優先制です。ご予約の方から優先的にご案内いたします。</div>
            <?php endif; ?>

            <div class="section-title"><i class="bi bi-calendar3"></i>日付を選択</div>

            <!-- Date picker area -->
            <div class="mb-3">
                <input type="date" id="bookingDate" class="form-control form-control-lg text-center"
                       min="<?= date('Y-m-d') ?>"
                       max="<?= date('Y-m-d', strtotime("+{$bookingDaysAhead} days")) ?>"
                       value="<?= h(date('Y-m-d')) ?>"
                       style="font-weight:700; font-size:1.1rem; cursor:pointer;">
            </div>

            <!-- Calendar legend -->
            <div class="d-flex gap-3 justify-content-center mb-3" style="font-size:0.72rem;color:var(--booking-gray-500);">
                <span><span class="cal-status available"></span>空きあり</span>
                <span><span class="cal-status moderate"></span>やや混雑</span>
                <span><span class="cal-status busy"></span>混雑</span>
                <span><span class="cal-status full"></span>満席</span>
                <span><span class="cal-status closed"></span>休診</span>
            </div>

            <!-- Doctor info for selected date -->
            <div id="doctorArea" class="mb-3" style="display:none;">
                <div class="section-title"><i class="bi bi-person-badge"></i>担当医師</div>
                <div class="doctor-list" id="doctorList"></div>
            </div>

            <!-- Time slots area -->
            <div id="slotsArea">
                <div class="loading-spinner">
                    <div class="spinner-border spinner-border-sm"></div>
                    <div class="mt-2" style="font-size:0.85rem;">予約状況を読み込み中...</div>
                </div>
            </div>

            <?php if ($bookingNotice): ?>
            <div class="info-box info-amber mt-3"><i class="bi bi-exclamation-triangle me-2"></i><?= nl2br(h($bookingNotice)) ?></div>
            <?php endif; ?>

            <button type="button" class="btn-booking mt-3" id="btnToStep2" disabled onclick="goToStep(2)">
                <span>次へ：ご予約情報の入力</span><i class="bi bi-arrow-right"></i>
            </button>
        </div>

        <!-- Step 2: Information Input -->
        <div class="booking-body" id="step2" style="display:none;">
            <button type="button" class="btn-outline-booking mb-3" onclick="goToStep(1)" style="width:auto;padding:8px 16px;font-size:0.8rem;">
                <i class="bi bi-arrow-left me-1"></i>日時選択に戻る
            </button>

            <!-- Summary of selected date/time -->
            <div class="summary-card mb-3" id="selectedSummary">
                <div class="summary-row">
                    <span class="label"><i class="bi bi-calendar3 me-1"></i>日付</span>
                    <span class="value" id="summaryDate">-</span>
                </div>
                <div class="summary-row">
                    <span class="label"><i class="bi bi-clock me-1"></i>時間</span>
                    <span class="value" id="summaryTime" style="font-size:1.1rem;color:var(--booking-primary);">-</span>
                </div>
            </div>

            <!-- Visit type toggle -->
            <div class="mb-3 p-3 rounded-3" style="background:var(--booking-gray-50);border:1px solid var(--booking-gray-200);">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="isNewPatient" onchange="toggleNewPatient()">
                    <label class="form-check-label fw-bold" for="isNewPatient">
                        <i class="bi bi-person-plus me-1"></i>初めての来院です（初診）
                    </label>
                </div>
                <div class="form-text mt-1">初めてご来院される方はチェックしてください。飼い主様・ペット情報が自動登録されます。</div>
            </div>

            <form id="bookingForm" onsubmit="return false;">
                <!-- Owner Info -->
                <div class="section-title"><i class="bi bi-person"></i>飼い主様の情報</div>
                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label">お名前<span class="required-mark">*</span></label>
                        <input type="text" class="form-control" id="ownerName" required placeholder="山田 太郎" autocomplete="name">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">電話番号<span class="required-mark">*</span></label>
                        <input type="tel" class="form-control" id="ownerPhone" required placeholder="090-1234-5678" autocomplete="tel">
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label">メールアドレス</label>
                        <input type="email" class="form-control" id="ownerEmail" placeholder="example@mail.com" autocomplete="email">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">ご住所</label>
                        <input type="text" class="form-control" id="ownerAddress" placeholder="東京都渋谷区..." autocomplete="street-address">
                    </div>
                </div>

                <!-- Pet Info -->
                <div class="section-title mt-4"><i class="bi bi-clipboard2-pulse"></i>ペットの情報</div>
                <div class="row g-2 mb-2">
                    <div class="col-sm-4">
                        <label class="form-label">ペットのお名前</label>
                        <input type="text" class="form-control" id="petName" placeholder="ポチ">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">動物の種類</label>
                        <select class="form-select" id="petSpecies">
                            <option value="">選択してください</option>
                            <?php foreach (SPECIES_LIST as $key => $name): ?>
                            <option value="<?= h($key) ?>"><?= h($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">年齢（おおよそ）</label>
                        <input type="text" class="form-control" id="petAge" placeholder="3歳">
                    </div>
                </div>

                <!-- New patient extra fields -->
                <div class="new-patient-box" id="newPatientBox">
                    <h6 class="fw-bold mb-2" style="color:#166534;"><i class="bi bi-person-plus me-1"></i>初診登録用の追加情報</h6>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label class="form-label">品種</label>
                            <input type="text" class="form-control" id="petBreed" placeholder="柴犬、ミックスなど">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">性別</label>
                            <select class="form-select" id="petSex">
                                <option value="">選択してください</option>
                                <?php foreach (SEX_LIST as $key => $name): ?>
                                <option value="<?= h($key) ?>"><?= h($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>初診の場合、飼い主様とペットの情報がカルテに自動登録されます。</div>
                </div>

                <!-- Appointment details -->
                <div class="section-title mt-4"><i class="bi bi-clipboard-check"></i>予約内容</div>
                <div class="row g-2 mb-2">
                    <div class="col-sm-6">
                        <label class="form-label">来院理由<span class="required-mark">*</span></label>
                        <select class="form-select" id="appointmentType" required>
                            <?php foreach (APPOINTMENT_TYPES as $key => $name): ?>
                            <option value="<?= h($key) ?>"><?= h($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">希望医師</label>
                        <select class="form-select" id="preferredDoctor">
                            <option value="">指定なし（どの先生でも可）</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">症状・ご相談内容</label>
                    <textarea class="form-control" id="reason" rows="3" placeholder="気になる症状やご相談内容をお書きください"></textarea>
                </div>

                <button type="button" class="btn-booking" onclick="goToStep(3)">
                    <span>次へ：確認画面</span><i class="bi bi-arrow-right"></i>
                </button>
            </form>
        </div>

        <!-- Step 3: Confirmation -->
        <div class="booking-body" id="step3" style="display:none;">
            <button type="button" class="btn-outline-booking mb-3" onclick="goToStep(2)" style="width:auto;padding:8px 16px;font-size:0.8rem;">
                <i class="bi bi-arrow-left me-1"></i>情報入力に戻る
            </button>

            <div class="section-title"><i class="bi bi-check-circle"></i>予約内容の確認</div>
            <div class="info-box info-blue mb-3">
                <i class="bi bi-info-circle me-1"></i>以下の内容でよろしければ「予約を確定する」ボタンを押してください。
            </div>

            <div class="summary-card" id="confirmSummary"></div>

            <div id="bookingError" class="info-box info-red mb-3" style="display:none;"></div>

            <button type="button" class="btn-booking" id="btnSubmit" onclick="submitBooking()">
                <i class="bi bi-check-circle-fill"></i><span>予約を確定する</span>
            </button>
        </div>

        <!-- Step 4: Confirmation result -->
        <div class="booking-body" id="step4" style="display:none;">
            <div class="text-center fade-in" id="confirmResult"></div>
        </div>

        <?php endif; ?>
    </div>

    <!-- Hospital Info -->
    <div class="hospital-info">
        <h5><i class="bi bi-building me-2"></i><?= h($hospitalName) ?></h5>
        <?php if ($hospitalAddress): ?>
        <div class="mb-2" style="font-size:0.85rem;"><i class="bi bi-geo-alt me-1 text-muted"></i><?= h($hospitalAddress) ?></div>
        <?php endif; ?>
        <?php if ($hospitalPhone): ?>
        <div class="mb-2" style="font-size:0.85rem;"><i class="bi bi-telephone me-1 text-muted"></i><a href="tel:<?= h($hospitalPhone) ?>" style="color:var(--booking-primary);text-decoration:none;font-weight:600;"><?= h($hospitalPhone) ?></a></div>
        <?php endif; ?>
        <?php if ($hospitalEmail): ?>
        <div class="mb-3" style="font-size:0.85rem;"><i class="bi bi-envelope me-1 text-muted"></i><?= h($hospitalEmail) ?></div>
        <?php endif; ?>

        <div style="font-size:0.82rem;font-weight:600;margin-bottom:8px;">診療時間</div>
        <table class="hours-table">
            <tr><td>平日</td><td><?= h($hoursWeekday) ?></td></tr>
            <tr><td>土曜</td><td><?= h($hoursSat) ?></td></tr>
            <tr><td>日祝</td><td><?= h($hoursHoliday) ?></td></tr>
        </table>
    </div>

    <div class="booking-footer">
        Powered by <?= h(APP_NAME) ?> v<?= APP_VERSION ?>
    </div>
</div>

<?php if ($bookingEnabled === '1'): ?>
<script>
const API_BASE = '<?= $apiBase ?>';
const MAX_PER_SLOT = <?= $maxPerSlot ?>;
let selectedDate = '<?= date('Y-m-d') ?>';
let selectedTime = '';
let doctorsCache = {};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadSlots(selectedDate);

    document.getElementById('bookingDate').addEventListener('change', (e) => {
        selectedDate = e.target.value;
        selectedTime = '';
        document.getElementById('btnToStep2').disabled = true;
        loadSlots(selectedDate);
    });
});

async function loadSlots(date) {
    const area = document.getElementById('slotsArea');
    area.innerHTML = '<div class="loading-spinner"><div class="spinner-border spinner-border-sm"></div><div class="mt-2" style="font-size:0.85rem;">予約状況を読み込み中...</div></div>';

    try {
        const res = await fetch(`${API_BASE}?action=get_slots&date=${date}`);
        const data = await res.json();

        if (data.error) {
            area.innerHTML = `<div class="info-box info-red"><i class="bi bi-exclamation-circle me-1"></i>${data.error}</div>`;
            return;
        }

        if (data.closed) {
            area.innerHTML = `<div class="closed-notice">
                <i class="bi bi-calendar-x"></i>
                <h6 class="mt-2 fw-bold" style="color:var(--booking-gray-700);">この日は休診日です</h6>
                <p style="font-size:0.85rem;color:var(--booking-gray-500);margin:0;">別の日付をお選びください。</p>
            </div>`;
            document.getElementById('doctorArea').style.display = 'none';
            return;
        }

        // Render doctors
        if (data.doctors && data.doctors.length > 0) {
            const dArea = document.getElementById('doctorArea');
            const dList = document.getElementById('doctorList');
            dArea.style.display = 'block';
            dList.innerHTML = data.doctors.map(d => `
                <div class="doctor-card">
                    <div class="doctor-avatar">${d.name.charAt(0)}</div>
                    <div>
                        <div class="doctor-name">${escHtml(d.name)}</div>
                        <div class="doctor-time"><i class="bi bi-clock me-1"></i>${d.start_time} - ${d.end_time}</div>
                    </div>
                </div>
            `).join('');

            // Update doctor dropdown in step 2
            doctorsCache = data.doctors;
            updateDoctorSelect(data.doctors);
        } else {
            document.getElementById('doctorArea').style.display = 'none';
        }

        // Render slots
        renderSlots(data.slots, data.date);

    } catch (err) {
        area.innerHTML = `<div class="info-box info-red"><i class="bi bi-exclamation-circle me-1"></i>読み込みに失敗しました。ページを再読み込みしてください。</div>`;
    }
}

function renderSlots(slots, date) {
    const area = document.getElementById('slotsArea');
    if (!slots || slots.length === 0) {
        area.innerHTML = '<div class="info-box info-amber">この日の予約枠はありません。</div>';
        return;
    }

    // Split morning / afternoon by lunch break
    let morningSlots = [];
    let afternoonSlots = [];
    let foundLunch = false;

    slots.forEach(s => {
        if (s.is_lunch) {
            foundLunch = true;
            return;
        }
        if (foundLunch) {
            afternoonSlots.push(s);
        } else {
            // Check if it's after 13:00 for simple split
            if (s.time >= '13:00') {
                afternoonSlots.push(s);
                foundLunch = true;
            } else {
                morningSlots.push(s);
            }
        }
    });

    let html = '<div class="section-title"><i class="bi bi-clock"></i>時間を選択</div>';

    if (morningSlots.length > 0) {
        html += '<div class="slot-period-label"><i class="bi bi-sunrise me-1"></i>午前</div>';
        html += '<div class="slot-grid">';
        html += morningSlots.map(s => renderSlotBtn(s)).join('');
        html += '</div>';
    }

    if (afternoonSlots.length > 0) {
        html += '<div class="slot-period-label mt-3"><i class="bi bi-sunset me-1"></i>午後</div>';
        html += '<div class="slot-grid">';
        html += afternoonSlots.map(s => renderSlotBtn(s)).join('');
        html += '</div>';
    }

    // If no split, show all
    if (morningSlots.length === 0 && afternoonSlots.length === 0) {
        html += '<div class="slot-grid">';
        html += slots.filter(s => !s.is_lunch).map(s => renderSlotBtn(s)).join('');
        html += '</div>';
    }

    area.innerHTML = html;
}

function renderSlotBtn(s) {
    let cls = 'slot-btn';
    if (!s.available) cls += ' disabled';
    if (s.is_full) cls += ' full';
    if (selectedTime === s.time) cls += ' selected';

    let remainText = '';
    if (s.is_full) {
        remainText = '<div class="slot-remaining" style="color:var(--booking-danger);">満席</div>';
    } else if (s.is_past) {
        remainText = '<div class="slot-remaining">受付終了</div>';
    } else {
        let remCls = 'slot-remaining';
        if (s.remaining <= 1) remCls += ' low';
        remainText = `<div class="${remCls}">残 ${s.remaining}枠</div>`;
    }

    const onclick = s.available ? `onclick="selectSlot('${s.time}', this)"` : '';

    return `<div class="${cls}" ${onclick}>
        <div class="slot-time">${s.time}</div>
        ${remainText}
    </div>`;
}

function selectSlot(time, el) {
    document.querySelectorAll('.slot-btn.selected').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    selectedTime = time;
    document.getElementById('btnToStep2').disabled = false;
}

function updateDoctorSelect(doctors) {
    const sel = document.getElementById('preferredDoctor');
    sel.innerHTML = '<option value="">指定なし（どの先生でも可）</option>';
    doctors.forEach(d => {
        sel.innerHTML += `<option value="${d.id}">${escHtml(d.name)}</option>`;
    });
}

// Step navigation
function goToStep(step) {
    if (step === 2 && !selectedTime) {
        alert('時間を選択してください。');
        return;
    }
    if (step === 2) {
        const name = document.getElementById('ownerName').value;
        document.getElementById('summaryDate').textContent = formatDateJP(selectedDate);
        document.getElementById('summaryTime').textContent = selectedTime;
    }

    if (step === 3) {
        // Validate step 2
        const name = document.getElementById('ownerName').value.trim();
        const phone = document.getElementById('ownerPhone').value.trim();
        if (!name || !phone) {
            alert('お名前と電話番号は必須です。');
            return;
        }
        buildConfirmation();
    }

    // Hide all steps
    for (let i = 1; i <= 4; i++) {
        const el = document.getElementById('step' + i);
        if (el) el.style.display = 'none';
    }
    const target = document.getElementById('step' + step);
    if (target) target.style.display = 'block';

    // Update step tabs
    for (let i = 1; i <= 3; i++) {
        const tab = document.getElementById('stepTab' + i);
        if (!tab) continue;
        tab.className = 'step-item';
        if (i < step) tab.classList.add('done');
        if (i === step) tab.classList.add('active');
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function buildConfirmation() {
    const isNew = document.getElementById('isNewPatient').checked;
    const typeSelect = document.getElementById('appointmentType');
    const typeText = typeSelect.options[typeSelect.selectedIndex].text;
    const doctorSelect = document.getElementById('preferredDoctor');
    const doctorText = doctorSelect.options[doctorSelect.selectedIndex].text;
    const speciesSelect = document.getElementById('petSpecies');
    const speciesText = speciesSelect.selectedIndex > 0 ? speciesSelect.options[speciesSelect.selectedIndex].text : '-';

    let html = `
        <div class="summary-row" style="font-weight:700;font-size:1rem;border-bottom:2px solid var(--booking-primary);padding-bottom:10px;margin-bottom:8px;">
            <span><i class="bi bi-calendar-check me-2" style="color:var(--booking-primary);"></i>予約日時</span>
            <span style="color:var(--booking-primary);">${formatDateJP(selectedDate)} ${selectedTime}</span>
        </div>
        <div class="summary-row"><span class="label">来院理由</span><span class="value">${escHtml(typeText)}</span></div>
        <div class="summary-row"><span class="label">希望医師</span><span class="value">${escHtml(doctorText)}</span></div>
        <div class="summary-row"><span class="label">受診種別</span><span class="value">${isNew ? '<span style="color:var(--booking-primary);"><i class="bi bi-person-plus me-1"></i>初診</span>' : '再診'}</span></div>
        <hr style="margin:10px 0;border-color:var(--booking-gray-200);">
        <div class="summary-row"><span class="label">お名前</span><span class="value">${escHtml(document.getElementById('ownerName').value)}</span></div>
        <div class="summary-row"><span class="label">電話番号</span><span class="value">${escHtml(document.getElementById('ownerPhone').value)}</span></div>
    `;
    const email = document.getElementById('ownerEmail').value;
    if (email) html += `<div class="summary-row"><span class="label">メール</span><span class="value">${escHtml(email)}</span></div>`;
    const addr = document.getElementById('ownerAddress').value;
    if (addr) html += `<div class="summary-row"><span class="label">住所</span><span class="value">${escHtml(addr)}</span></div>`;

    const petName = document.getElementById('petName').value;
    if (petName) {
        html += `<hr style="margin:10px 0;border-color:var(--booking-gray-200);">`;
        html += `<div class="summary-row"><span class="label">ペット名</span><span class="value">${escHtml(petName)}</span></div>`;
        html += `<div class="summary-row"><span class="label">種類</span><span class="value">${escHtml(speciesText)}</span></div>`;
    }

    const reason = document.getElementById('reason').value;
    if (reason) {
        html += `<hr style="margin:10px 0;border-color:var(--booking-gray-200);">`;
        html += `<div style="font-size:0.85rem;"><span class="label">症状・相談内容:</span><br><span style="white-space:pre-wrap;">${escHtml(reason)}</span></div>`;
    }

    document.getElementById('confirmSummary').innerHTML = html;
}

async function submitBooking() {
    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span><span>送信中...</span>';
    document.getElementById('bookingError').style.display = 'none';

    const data = {
        action: 'submit_booking',
        owner_name: document.getElementById('ownerName').value.trim(),
        owner_phone: document.getElementById('ownerPhone').value.trim(),
        owner_email: document.getElementById('ownerEmail').value.trim(),
        owner_address: document.getElementById('ownerAddress').value.trim(),
        patient_name: document.getElementById('petName').value.trim(),
        species: document.getElementById('petSpecies').value,
        breed: document.getElementById('petBreed').value.trim(),
        pet_age: document.getElementById('petAge').value.trim(),
        appointment_date: selectedDate,
        appointment_time: selectedTime,
        appointment_type: document.getElementById('appointmentType').value,
        reason: document.getElementById('reason').value.trim(),
        is_new_patient: document.getElementById('isNewPatient').checked ? 1 : 0,
        doctor_id: document.getElementById('preferredDoctor').value,
    };

    try {
        const res = await fetch(`${API_BASE}?action=submit_booking`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();

        if (result.error) {
            document.getElementById('bookingError').style.display = 'block';
            document.getElementById('bookingError').innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>${escHtml(result.error)}`;
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i><span>予約を確定する</span>';
            return;
        }

        // Success! Show confirmation
        showConfirmation(result);
    } catch (err) {
        document.getElementById('bookingError').style.display = 'block';
        document.getElementById('bookingError').innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>通信エラーが発生しました。もう一度お試しください。';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle-fill"></i><span>予約を確定する</span>';
    }
}

function showConfirmation(result) {
    // Update step tabs
    for (let i = 1; i <= 3; i++) {
        const tab = document.getElementById('stepTab' + i);
        if (tab) { tab.className = 'step-item done'; }
    }

    // Hide steps 1-3, show 4
    for (let i = 1; i <= 3; i++) {
        const el = document.getElementById('step' + i);
        if (el) el.style.display = 'none';
    }
    document.getElementById('step4').style.display = 'block';

    const hospitalPhone = '<?= h($hospitalPhone) ?>';
    const hospitalName = '<?= h($hospitalName) ?>';

    document.getElementById('confirmResult').innerHTML = `
        <div class="confirm-icon"><i class="bi bi-check-lg"></i></div>
        <h4 class="fw-bold mb-2">ご予約を承りました</h4>
        <p style="color:var(--booking-gray-500);font-size:0.9rem;">ご来院をお待ちしております。</p>

        <div class="summary-card text-start" style="margin-top:24px;">
            <div class="summary-row" style="font-weight:700;font-size:1rem;border-bottom:2px solid var(--booking-success);padding-bottom:10px;margin-bottom:8px;">
                <span><i class="bi bi-calendar-check me-2" style="color:var(--booking-success);"></i>予約日時</span>
                <span style="color:var(--booking-primary);">${result.date_jp} ${result.time}</span>
            </div>
            <div class="summary-row"><span class="label">予約番号</span><span class="value" style="font-family:monospace;">${result.token}</span></div>
            <div class="summary-row"><span class="label">種別</span><span class="value">${result.is_new_patient ? '<span style="color:var(--booking-primary);"><i class="bi bi-person-plus me-1"></i>初診</span>' : '再診'}</span></div>
        </div>

        <div class="info-box info-amber text-start" style="margin-top:16px;">
            <strong><i class="bi bi-info-circle me-1"></i>ご来院時のお願い</strong>
            <ul class="mb-0 mt-1" style="padding-left:20px;font-size:0.83rem;">
                <li>予約時間の <strong>5分前</strong> までにお越しください。</li>
                <li>初診の方は保険証・ワクチン証明書をお持ちください。</li>
                <li>キャンセル・変更は前日までにお電話ください。</li>
            </ul>
        </div>

        ${hospitalPhone ? `<a href="tel:${hospitalPhone}" class="btn-outline-booking d-flex align-items-center justify-content-center gap-2 mt-3" style="text-decoration:none;"><i class="bi bi-telephone"></i>${hospitalPhone}</a>` : ''}

        <button type="button" class="btn-booking mt-2" onclick="location.reload()">
            <i class="bi bi-calendar-plus"></i><span>別の予約をする</span>
        </button>
    `;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function toggleNewPatient() {
    const box = document.getElementById('newPatientBox');
    const checked = document.getElementById('isNewPatient').checked;
    box.classList.toggle('show', checked);
}

function formatDateJP(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    const dow = ['日','月','火','水','木','金','土'];
    return `${d.getFullYear()}年${String(d.getMonth()+1).padStart(2,'0')}月${String(d.getDate()).padStart(2,'0')}日(${dow[d.getDay()]})`;
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
}
</script>
<?php endif; ?>

</body>
</html>
