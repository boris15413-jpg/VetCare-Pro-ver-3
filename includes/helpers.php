<?php
/**
 * VetCare Pro - ヘルパー関数
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
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function formatDate($date, $format = 'Y年m月d日') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

function formatDateTime($datetime) {
    if (empty($datetime)) return '-';
    return date('Y/m/d H:i', strtotime($datetime));
}

function calculateAge($birthdate) {
    if (empty($birthdate)) return '-';
    $birth = new DateTime($birthdate);
    $now = new DateTime();
    $diff = $now->diff($birth);
    if ($diff->y > 0) {
        return $diff->y . '歳' . ($diff->m > 0 ? $diff->m . 'ヶ月' : '');
    }
    return $diff->m . 'ヶ月';
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

function getOrderStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning text-dark">未実施</span>',
        'in_progress' => '<span class="badge bg-info">実施中</span>',
        'completed' => '<span class="badge bg-success">完了</span>',
        'cancelled' => '<span class="badge bg-secondary">キャンセル</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . h($status) . '</span>';
}

function getAdmissionStatusBadge($status) {
    $badges = [
        'admitted' => '<span class="badge bg-primary">入院中</span>',
        'discharged' => '<span class="badge bg-success">退院</span>',
        'transferred' => '<span class="badge bg-warning">転院</span>',
        'deceased' => '<span class="badge bg-dark">死亡</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . h($status) . '</span>';
}

function uploadFile($file, $subdir = 'images') {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'ファイルアップロードに失敗しました'];
    }
    
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['error' => 'ファイルサイズが大きすぎます（最大10MB）'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        return ['error' => '許可されていないファイル形式です'];
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
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
    return number_format((float)$num);
}

function formatCurrency($amount) {
    return '¥' . number_format((float)$amount);
}

function generateReceiptNumber() {
    return 'R' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function generateDiagnosisNumber() {
    return 'D' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

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
        ]
    ];
    
    $sp = $ranges[$species] ?? $ranges['dog'];
    $range = $sp[$type] ?? null;
    
    if (!$range || $value === null || $value === '') return 'normal';
    if ($value < $range['low']) return 'low';
    if ($value > $range['high']) return 'high';
    return 'normal';
}

function pagination($total, $page, $perPage, $url) {
    $totalPages = ceil($total / $perPage);
    if ($totalPages <= 1) return '';
    
    $html = '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';
    
    if ($page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&p=' . ($page - 1) . '">«</a></li>';
    }
    
    for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
        $active = $i === $page ? ' active' : '';
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $url . '&p=' . $i . '">' . $i . '</a></li>';
    }
    
    if ($page < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&p=' . ($page + 1) . '">»</a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

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
        ]
    ];
    
    $sp = $ranges[$species] ?? $ranges['dog'];
    $range = $sp[$type] ?? null;
    
    if (!$range || $value === null || $value === '') return 'normal';
    if ($value < $range['low']) return 'low';
    if ($value > $range['high']) return 'high';
    return 'normal';
}
