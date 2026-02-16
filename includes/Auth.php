<?php
/**
 * VetCare Pro - 認証クラス
 */

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function login($login_id, $password) {
        $user = $this->db->fetch(
            "SELECT * FROM staff WHERE login_id = ? AND is_active = 1",
            [$login_id]
        );
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        
        $this->db->update('staff', [
            'last_login' => date('Y-m-d H:i:s')
        ], 'id = ?', [$user['id']]);
        
        $this->logActivity($user['id'], 'login', 'ログインしました');
        
        return $user;
    }
    
    public function logout() {
        if ($this->isLoggedIn()) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'ログアウトしました');
        }
        session_destroy();
        session_start();
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id'])) return false;
        if (time() - ($_SESSION['login_time'] ?? 0) > SESSION_LIFETIME) {
            $this->logout();
            return false;
        }
        return true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . APP_URL . '/index.php?page=login');
            exit;
        }
    }
    
    public function requireRole($roles) {
        $this->requireLogin();
        if (!is_array($roles)) $roles = [$roles];
        if (!in_array($_SESSION['user_role'], $roles)) {
            header('HTTP/1.1 403 Forbidden');
            echo '<div style="text-align:center;margin-top:100px;font-family:sans-serif;">';
            echo '<h1>アクセス権限がありません</h1>';
            echo '<p>この操作には適切な権限が必要です。</p>';
            echo '<a href="' . APP_URL . '/">ダッシュボードに戻る</a>';
            echo '</div>';
            exit;
        }
    }
    
    public function currentUser() {
        if (!$this->isLoggedIn()) return null;
        return $this->db->fetch("SELECT * FROM staff WHERE id = ?", [$_SESSION['user_id']]);
    }
    
    public function currentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function currentUserRole() {
        return $_SESSION['user_role'] ?? null;
    }
    
    public function currentUserName() {
        return $_SESSION['user_name'] ?? 'ゲスト';
    }
    
    public function hasRole($roles) {
        if (!is_array($roles)) $roles = [$roles];
        return in_array($_SESSION['user_role'] ?? '', $roles);
    }
    
    public function createAccount($data) {
        $existing = $this->db->fetch("SELECT id FROM staff WHERE login_id = ?", [$data['login_id']]);
        if ($existing) {
            return ['error' => 'このログインIDは既に使用されています'];
        }
        
        $id = $this->db->insert('staff', [
            'login_id' => $data['login_id'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'name' => $data['name'],
            'name_kana' => $data['name_kana'] ?? '',
            'role' => $data['role'],
            'license_number' => $data['license_number'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return ['success' => true, 'id' => $id];
    }
    
    public function updatePassword($userId, $newPassword) {
        return $this->db->update('staff', [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
        ], 'id = ?', [$userId]);
    }
    
    public function logActivity($userId, $action, $details = '', $targetType = '', $targetId = null) {
        $this->db->insert('activity_log', [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}
