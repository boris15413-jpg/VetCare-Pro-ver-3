<?php
/**
 * VetCare Pro v2.0 - Helper Functions (Bug-fixed, enhanced)
 */

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function formatDate($date, $format = 'Y/m/d') {
    if (empty($date)) return '-';
    $ts = strtotime($date);
    if ($ts === false) return '-';
    return date($format, $ts);
}

function formatDateTime($datetime) {
    if (empty($datetime)) return '-';
    $ts = strtotime($datetime);
    if ($ts === false) return '-';
    return date('Y/m/d H:i', $ts);
}

function formatDateJP($date) {
    if (empty($date)) return '-';
    $ts = strtotime($date);
    if ($ts === false) return '-';
    $dow = ['日','月','火','水','木','金','土'];
    return date('Y年m月d日', $ts) . '(' . $dow[date('w', $ts)] . ')';
}

function calculateAge($birthdate) {
    if (empty($birthdate)) return '-';
    try {
        $birth = new DateTime($birthdate);
        $now = new DateTime();
        $diff = $now->diff($birth);
        if ($diff->y > 0) {
            return $diff->y . '歳' . ($diff->m > 0 ? $diff->m . 'ヶ月' : '');
        }
        if ($diff->m > 0) {
            return $diff->m . 'ヶ月';
        }
        return $diff->d . '日';
    } catch (Exception $e) {
        return '-';
    }
}

function getSpeciesName($key) {
    return SPECIES_LIST[$key] ?? $key;
}

function getSexName($key) {
    return SEX_LIST[$key] ?? $key;
}

function getRoleName($key) {
    return ROLE_NAMES[$key] ?? $key;
}

function getSpeciesIcon($species) {
    $icons = [
        'dog' => 'bi-github', 'cat' => 'bi-emoji-smile',
        'rabbit' => 'bi-flower1', 'hamster' => 'bi-circle',
        'bird' => 'bi-feather', 'ferret' => 'bi-circle-fill',
        'turtle' => 'bi-shield', 'guinea_pig' => 'bi-circle',
        'hedgehog' => 'bi-star', 'snake' => 'bi-activity',
        'fish' => 'bi-water',
    ];
    return $icons[$species] ?? 'bi-clipboard2-pulse';
}

function getOrderStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>未実施</span>',
        'in_progress' => '<span class="badge bg-info"><i class="bi bi-arrow-repeat me-1"></i>実施中</span>',
        'completed' => '<span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>完了</span>',
        'cancelled' => '<span class="badge bg-secondary"><i class="bi bi-x-lg me-1"></i>キャンセル</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . h($status) . '</span>';
}

function getAdmissionStatusBadge($status) {
    $badges = [
        'admitted' => '<span class="badge bg-primary"><i class="bi bi-hospital me-1"></i>入院中</span>',
        'discharged' => '<span class="badge bg-success"><i class="bi bi-box-arrow-right me-1"></i>退院</span>',
        'transferred' => '<span class="badge bg-warning text-dark"><i class="bi bi-arrow-left-right me-1"></i>転院</span>',
        'deceased' => '<span class="badge bg-dark"><i class="bi bi-dash-circle me-1"></i>死亡</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . h($status) . '</span>';
}

function getAppointmentStatusBadge($status) {
    $badges = [
        'scheduled' => '<span class="badge badge-glass badge-scheduled"><i class="bi bi-calendar-check me-1"></i>予約済</span>',
        'checked_in' => '<span class="badge badge-glass badge-checkedin"><i class="bi bi-person-check me-1"></i>受付済</span>',
        'in_progress' => '<span class="badge badge-glass badge-inprogress"><i class="bi bi-play-circle me-1"></i>診察中</span>',
        'completed' => '<span class="badge badge-glass badge-completed"><i class="bi bi-check-circle me-1"></i>完了</span>',
        'cancelled' => '<span class="badge badge-glass badge-cancelled"><i class="bi bi-x-circle me-1"></i>キャンセル</span>',
        'no_show' => '<span class="badge badge-glass badge-noshow"><i class="bi bi-person-x me-1"></i>無断キャンセル</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . h($status) . '</span>';
}

function uploadFile($file, $subdir = 'images') {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'ファイルアップロードに失敗しました (code: ' . $file['error'] . ')'];
    }
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['error' => 'ファイルサイズが大きすぎます（最大' . round(UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB）'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        return ['error' => '許可されていないファイル形式です'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowedExts)) {
        return ['error' => '許可されていない拡張子です'];
    }
    
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $uploadDir = UPLOAD_DIR . $subdir . '/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filepath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['error' => 'ファイルの保存に失敗しました'];
    }
    
    return ['success' => true, 'filename' => $filename, 'path' => $subdir . '/' . $filename];
}

function formatNumber($num) {
    return number_format((float)($num ?? 0));
}

function formatCurrency($amount) {
    return '¥' . number_format((float)($amount ?? 0));
}

function generateReceiptNumber() {
    return 'R' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generateDiagnosisNumber() {
    return 'D' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generateReservationToken() {
    return bin2hex(random_bytes(16));
}

// FIXED: Single definition (was duplicated in original)
function getVitalStatus($type, $value, $species = 'dog') {
    $ranges = [
        'dog' => [
            'temperature' => ['low' => 37.5, 'high' => 39.2],
            'heart_rate' => ['low' => 60, 'high' => 160],
            'respiratory_rate' => ['low' => 10, 'high' => 30]
        ],
        'cat' => [
            'temperature' => ['low' => 38.0, 'high' => 39.5],
            'heart_rate' => ['low' => 120, 'high' => 240],
            'respiratory_rate' => ['low' => 20, 'high' => 40]
        ],
        'rabbit' => [
            'temperature' => ['low' => 38.5, 'high' => 40.0],
            'heart_rate' => ['low' => 130, 'high' => 325],
            'respiratory_rate' => ['low' => 30, 'high' => 60]
        ]
    ];
    
    $sp = $ranges[$species] ?? $ranges['dog'];
    $range = $sp[$type] ?? null;
    
    if (!$range || $value === null || $value === '') return 'normal';
    $value = (float)$value;
    if ($value < $range['low']) return 'low';
    if ($value > $range['high']) return 'high';
    return 'normal';
}

function pagination($total, $page, $perPage, $url) {
    $totalPages = (int)ceil($total / $perPage);
    if ($totalPages <= 1) return '';
    
    $sep = strpos($url, '?') !== false ? '&' : '?';
    $html = '<nav aria-label="ページナビゲーション"><ul class="pagination pagination-sm justify-content-center mb-0">';
    
    if ($page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . h($url) . $sep . 'p=' . ($page - 1) . '">&laquo;</a></li>';
    }
    
    for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
        $active = $i === $page ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . h($url) . $sep . 'p=' . $i . '">' . $i . '</a></li>';
    }
    
    if ($page < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . h($url) . $sep . 'p=' . ($page + 1) . '">&raquo;</a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Get hospital setting from DB
 */
function getSetting($key, $default = '') {
    try {
        $db = Database::getInstance();
        $row = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = ?", [$key]);
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Set hospital setting in DB
 */
function setSetting($key, $value) {
    $db = Database::getInstance();
    $existing = $db->fetch("SELECT setting_key FROM hospital_settings WHERE setting_key = ?", [$key]);
    if ($existing) {
        $db->update('hospital_settings', ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')], 'setting_key = ?', [$key]);
    } else {
        $db->insert('hospital_settings', ['setting_key' => $key, 'setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
    }
}

/**
 * Flash message helper
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash() {
    $flash = getFlash();
    if ($flash) {
        $icon = $flash['type'] === 'success' ? 'bi-check-circle-fill' : ($flash['type'] === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill');
        echo '<div class="alert alert-' . h($flash['type']) . ' alert-dismissible fade show glass-alert" role="alert">';
        echo '<i class="bi ' . $icon . ' me-2"></i>' . h($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

/**
 * Parse CSV file with encoding detection
 */
function parseCSV($filepath, $encoding = 'auto') {
    $content = file_get_contents($filepath);
    if ($content === false) return false;
    
    // Auto-detect encoding
    if ($encoding === 'auto') {
        $detected = mb_detect_encoding($content, ['UTF-8', 'SJIS', 'SJIS-win', 'EUC-JP', 'ISO-2022-JP'], true);
        if ($detected && $detected !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $detected);
        }
    }
    
    // Remove BOM
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
    $rows = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $rows[] = str_getcsv($line);
    }
    return $rows;
}

/**
 * Sanitize filename 
 */
function sanitizeFilename($name) {
    $name = preg_replace('/[^\p{L}\p{N}\s._-]/u', '', $name);
    $name = preg_replace('/\s+/', '_', $name);
    return substr($name, 0, 200);
}

/**
 * Generate a time slot array for appointment scheduling
 */
function generateTimeSlots($start = '09:00', $end = '18:00', $interval = 30) {
    $slots = [];
    $current = strtotime($start);
    $endTime = strtotime($end);
    while ($current < $endTime) {
        $slots[] = date('H:i', $current);
        $current += $interval * 60;
    }
    return $slots;
}
