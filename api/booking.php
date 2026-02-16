<?php
/**
 * VetCare Pro - Public Booking API
 * Provides AJAX endpoints for the public booking page
 * No authentication required - this is public-facing
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_slots':
        getSlots($db);
        break;
    case 'get_doctors':
        getDoctors($db);
        break;
    case 'get_month_availability':
        getMonthAvailability($db);
        break;
    case 'submit_booking':
        submitBooking($db);
        break;
    default:
        jsonOut(['error' => 'Invalid action'], 400);
}

function jsonOut($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Get available time slots for a specific date
 */
function getSlots($db) {
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        jsonOut(['error' => 'Invalid date'], 400);
    }

    $startTime = getSetting('appointment_start_time', '09:00');
    $endTime = getSetting('appointment_end_time', '18:00');
    $interval = (int)getSetting('appointment_interval', '30');
    $maxPerSlot = (int)getSetting('max_appointments_per_slot', '3');
    $lunchStart = getSetting('booking_lunch_start', '12:00');
    $lunchEnd = getSetting('booking_lunch_end', '13:00');
    $afternoonStart = getSetting('booking_afternoon_start', '');

    // Check closed day
    if (isClosedDay($db, $date)) {
        jsonOut(['closed' => true, 'slots' => [], 'message' => '休診日です']);
    }

    // Generate all time slots
    $allSlots = generateTimeSlots($startTime, $endTime, $interval);

    // Get existing bookings for this date
    $booked = $db->fetchAll(
        "SELECT appointment_time, COUNT(*) as cnt FROM appointments 
         WHERE appointment_date = ? AND status NOT IN ('cancelled','no_show') 
         GROUP BY appointment_time",
        [$date]
    );
    $bookedMap = [];
    foreach ($booked as $b) {
        $bookedMap[substr($b['appointment_time'], 0, 5)] = (int)$b['cnt'];
    }

    // Get doctors working on this date
    $doctors = getWorkingDoctors($db, $date);

    // Build slot data
    $slots = [];
    $isPast = ($date === date('Y-m-d'));
    $now = date('H:i');

    // Determine session periods
    $morningSlots = [];
    $afternoonSlots = [];

    foreach ($allSlots as $slot) {
        $count = $bookedMap[$slot] ?? 0;
        $remaining = $maxPerSlot - $count;
        $isDisabled = $remaining <= 0;
        $isExpired = $isPast && $slot <= $now;

        // Determine if this is a lunch break slot
        $isLunch = false;
        if ($lunchStart && $lunchEnd) {
            $isLunch = ($slot >= $lunchStart && $slot < $lunchEnd);
        }

        $slotData = [
            'time' => $slot,
            'remaining' => max(0, $remaining),
            'max' => $maxPerSlot,
            'booked' => $count,
            'available' => !$isDisabled && !$isExpired && !$isLunch,
            'is_past' => $isExpired,
            'is_lunch' => $isLunch,
            'is_full' => $remaining <= 0,
        ];

        // Split into morning/afternoon
        if ($afternoonStart && $slot >= $afternoonStart) {
            $afternoonSlots[] = $slotData;
        } elseif ($lunchStart && $slot >= $lunchStart && $slot < ($afternoonStart ?: $lunchEnd)) {
            // lunch break - skip or mark
            if (!$isLunch) {
                $afternoonSlots[] = $slotData;
            }
        } else {
            $morningSlots[] = $slotData;
        }

        $slots[] = $slotData;
    }

    jsonOut([
        'closed' => false,
        'date' => $date,
        'date_jp' => formatDateJP($date),
        'slots' => $slots,
        'morning' => $morningSlots,
        'afternoon' => $afternoonSlots,
        'doctors' => $doctors,
        'max_per_slot' => $maxPerSlot,
        'interval' => $interval,
    ]);
}

/**
 * Get doctors working on a specific date
 */
function getDoctors($db) {
    $date = $_GET['date'] ?? date('Y-m-d');
    $doctors = getWorkingDoctors($db, $date);
    jsonOut(['doctors' => $doctors, 'date' => $date]);
}

/**
 * Get month-level availability overview (for calendar coloring)
 */
function getMonthAvailability($db) {
    $year = (int)($_GET['year'] ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('m'));
    $maxPerSlot = (int)getSetting('max_appointments_per_slot', '3');
    $startTime = getSetting('appointment_start_time', '09:00');
    $endTime = getSetting('appointment_end_time', '18:00');
    $interval = (int)getSetting('appointment_interval', '30');
    $totalSlots = count(generateTimeSlots($startTime, $endTime, $interval));
    $maxTotal = $totalSlots * $maxPerSlot;

    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate));

    // Get booking counts per day
    $rows = $db->fetchAll(
        "SELECT appointment_date, COUNT(*) as cnt FROM appointments 
         WHERE appointment_date BETWEEN ? AND ? AND status NOT IN ('cancelled','no_show') 
         GROUP BY appointment_date",
        [$startDate, $endDate]
    );
    $dayCounts = [];
    foreach ($rows as $r) {
        $dayCounts[$r['appointment_date']] = (int)$r['cnt'];
    }

    // Build day availability
    $days = [];
    $current = $startDate;
    while ($current <= $endDate) {
        $closed = isClosedDay($db, $current);
        $count = $dayCounts[$current] ?? 0;
        $ratio = $maxTotal > 0 ? $count / $maxTotal : 0;

        $status = 'available'; // green
        if ($closed) {
            $status = 'closed';
        } elseif ($ratio >= 0.9) {
            $status = 'full';
        } elseif ($ratio >= 0.6) {
            $status = 'busy';
        } elseif ($ratio >= 0.3) {
            $status = 'moderate';
        }

        $days[$current] = [
            'date' => $current,
            'status' => $status,
            'booked' => $count,
            'closed' => $closed,
        ];
        $current = date('Y-m-d', strtotime($current . ' +1 day'));
    }

    jsonOut(['year' => $year, 'month' => $month, 'days' => $days]);
}

/**
 * Submit a booking
 */
function submitBooking($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonOut(['error' => 'POST required'], 405);
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;

    $bookingEnabled = getSetting('public_booking_enabled', '0');
    if ($bookingEnabled !== '1') {
        jsonOut(['error' => 'オンライン予約は現在受け付けておりません。'], 403);
    }

    // Validate required fields
    $required = ['owner_name', 'owner_phone', 'appointment_date', 'appointment_time'];
    foreach ($required as $field) {
        if (empty(trim($data[$field] ?? ''))) {
            jsonOut(['error' => '必須項目（' . $field . '）が入力されていません。'], 400);
        }
    }

    $date = trim($data['appointment_date']);
    $time = trim($data['appointment_time']);
    $ownerName = trim($data['owner_name']);
    $ownerPhone = trim($data['owner_phone']);
    $ownerEmail = trim($data['owner_email'] ?? '');
    $ownerAddress = trim($data['owner_address'] ?? '');
    $patientName = trim($data['patient_name'] ?? '');
    $species = trim($data['species'] ?? '');
    $breed = trim($data['breed'] ?? '');
    $age = trim($data['pet_age'] ?? '');
    $type = trim($data['appointment_type'] ?? 'general');
    $reason = trim($data['reason'] ?? '');
    $isNewPatient = !empty($data['is_new_patient']) ? 1 : 0;
    $doctorId = !empty($data['doctor_id']) ? (int)$data['doctor_id'] : null;

    // Date validation
    if (strtotime($date) < strtotime('today')) {
        jsonOut(['error' => '過去の日付は選択できません。'], 400);
    }
    if (isClosedDay($db, $date)) {
        jsonOut(['error' => 'この日は休診日です。'], 400);
    }

    // Slot availability check
    $maxPerSlot = (int)getSetting('max_appointments_per_slot', '3');
    $existing = $db->count('appointments',
        'appointment_date = ? AND appointment_time = ? AND status NOT IN (?,?)',
        [$date, $time, 'cancelled', 'no_show']);

    if ($existing >= $maxPerSlot) {
        jsonOut(['error' => 'この時間枠は既に満席です。別の時間をお選びください。'], 409);
    }

    // Process owner/patient registration
    $ownerId = null;
    $patientId = null;
    $newPatientEnabled = getSetting('booking_new_patient_enabled', '1');

    // Try to find existing owner
    $owner = $db->fetch("SELECT id FROM owners WHERE phone = ? AND name = ?", [$ownerPhone, $ownerName]);
    if ($owner) {
        $ownerId = $owner['id'];
    }

    // Register new owner/patient if enabled
    if ($newPatientEnabled === '1' && $isNewPatient && !$ownerId) {
        $ownerCode = 'OW' . date('Ymd') . str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT);
        $ownerData = [
            'owner_code' => $ownerCode,
            'name' => $ownerName,
            'phone' => $ownerPhone,
            'email' => $ownerEmail,
            'address' => $ownerAddress,
            'notes' => '【オンライン予約より自動登録】',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $ownerId = $db->insert('owners', $ownerData);

        if (!empty($patientName) && !empty($species) && $ownerId) {
            $patientCode = 'PT' . date('Ymd') . str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT);
            $patientData = [
                'patient_code' => $patientCode,
                'owner_id' => $ownerId,
                'name' => $patientName,
                'species' => $species,
                'breed' => $breed,
                'registration_date' => date('Y-m-d'),
                'notes' => '【オンライン予約より自動登録】' . ($age ? " 年齢: {$age}" : ''),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            $patientId = $db->insert('patients', $patientData);
        }
    }

    $token = generateReservationToken();
    $interval = (int)getSetting('appointment_interval', '30');

    $appointmentData = [
        'patient_id' => $patientId,
        'owner_id' => $ownerId,
        'staff_id' => $doctorId,
        'appointment_date' => $date,
        'appointment_time' => $time,
        'duration' => $interval,
        'appointment_type' => $type,
        'status' => 'scheduled',
        'reason' => $reason,
        'is_new_patient' => $isNewPatient,
        'owner_name_text' => $ownerName,
        'pet_name_text' => $patientName,
        'phone_text' => $ownerPhone,
        'owner_email_text' => $ownerEmail,
        'species_text' => $species,
        'booking_source' => 'online',
        'notes' => "【オンライン予約】" . ($isNewPatient ? '(初診)' : '(再診)') . " Token: {$token}",
        'reservation_token' => $token,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    $db->insert('appointments', $appointmentData);

    // Try LINE notification
    try {
        require_once __DIR__ . '/../includes/LineNotification.php';
        $line = new LineNotification();
        $aptId = $db->lastInsertId();
        if ($line->isConfigured() && $ownerId) {
            $line->sendAppointmentConfirmation($aptId);
        }
    } catch (Exception $e) {}

    jsonOut([
        'success' => true,
        'token' => $token,
        'date' => $date,
        'time' => $time,
        'date_jp' => formatDateJP($date),
        'is_new_patient' => $isNewPatient,
        'message' => 'ご予約を承りました。',
    ]);
}

// ---- Helper functions ----

function isClosedDay($db, $date) {
    $closedWeekdays = getSetting('closed_weekdays', '0');
    $closedWeekdayArr = array_filter(explode(',', $closedWeekdays), fn($v) => $v !== '');
    $dow = date('w', strtotime($date));
    if (in_array($dow, $closedWeekdayArr)) return true;

    try {
        $sp = $db->fetch("SELECT id FROM closed_days WHERE closed_date = ?", [$date]);
        if ($sp) return true;
    } catch (Exception $e) {}

    return false;
}

function getWorkingDoctors($db, $date) {
    $dow = date('w', strtotime($date));
    $doctors = [];

    try {
        // Get vets with schedule for this specific date
        $scheduled = $db->fetchAll(
            "SELECT s.id, s.name, s.role, ss.start_time, ss.end_time, ss.schedule_type
             FROM staff s
             JOIN staff_schedules ss ON s.id = ss.staff_id
             WHERE ss.schedule_date = ? AND ss.schedule_type != 'off' AND s.is_active = 1 AND s.role IN ('veterinarian','admin')
             ORDER BY s.name",
            [$date]
        );
        if (!empty($scheduled)) {
            foreach ($scheduled as $d) {
                $doctors[] = [
                    'id' => (int)$d['id'],
                    'name' => $d['name'],
                    'role' => $d['role'],
                    'start_time' => $d['start_time'],
                    'end_time' => $d['end_time'],
                ];
            }
            return $doctors;
        }

        // Fallback: check weekly template
        $templates = $db->fetchAll(
            "SELECT s.id, s.name, s.role, st.start_time, st.end_time, st.is_off
             FROM staff s
             JOIN staff_schedule_templates st ON s.id = st.staff_id
             WHERE st.day_of_week = ? AND st.is_off = 0 AND s.is_active = 1 AND s.role IN ('veterinarian','admin')
             ORDER BY s.name",
            [$dow]
        );
        if (!empty($templates)) {
            foreach ($templates as $d) {
                $doctors[] = [
                    'id' => (int)$d['id'],
                    'name' => $d['name'],
                    'role' => $d['role'],
                    'start_time' => $d['start_time'],
                    'end_time' => $d['end_time'],
                ];
            }
            return $doctors;
        }

        // Final fallback: all active vets
        $allVets = $db->fetchAll(
            "SELECT id, name, role FROM staff WHERE is_active = 1 AND role IN ('veterinarian','admin') ORDER BY name"
        );
        foreach ($allVets as $v) {
            $doctors[] = [
                'id' => (int)$v['id'],
                'name' => $v['name'],
                'role' => $v['role'],
                'start_time' => getSetting('appointment_start_time', '09:00'),
                'end_time' => getSetting('appointment_end_time', '18:00'),
            ];
        }
    } catch (Exception $e) {
        // Tables may not exist yet
    }

    return $doctors;
}
