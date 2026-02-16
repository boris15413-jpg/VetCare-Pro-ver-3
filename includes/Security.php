<?php
/**
 * VetCare Pro - Security Module
 * IP whitelist, access control, rate limiting
 */

class Security {
    
    /**
     * Check if the current request is from a local/private network
     */
    public static function isLocalAccess(): bool {
        $ip = self::getClientIP();
        
        // localhost
        if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'])) {
            return true;
        }
        
        // Private networks (RFC 1918)
        if (
            self::ipInRange($ip, '10.0.0.0', '10.255.255.255') ||
            self::ipInRange($ip, '172.16.0.0', '172.31.255.255') ||
            self::ipInRange($ip, '192.168.0.0', '192.168.255.255') ||
            self::ipInRange($ip, 'fc00::', 'fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff')
        ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if the current IP is in the whitelist
     */
    public static function isIPAllowed(): bool {
        try {
            $db = Database::getInstance();
            $enabled = getSetting('security_ip_whitelist_enabled', '0');
            
            if ($enabled !== '1') {
                return true; // whitelist not enabled = allow all
            }
            
            $ip = self::getClientIP();
            
            // Local access is always allowed
            if (self::isLocalAccess()) {
                return true;
            }
            
            $whitelist = getSetting('security_ip_whitelist', '');
            if (empty($whitelist)) {
                return true; // empty whitelist = allow all
            }
            
            $allowedIPs = array_map('trim', explode("\n", $whitelist));
            foreach ($allowedIPs as $allowed) {
                if (empty($allowed) || $allowed[0] === '#') continue;
                
                // CIDR notation support
                if (strpos($allowed, '/') !== false) {
                    if (self::ipInCIDR($ip, $allowed)) return true;
                }
                // Wildcard support (e.g., 192.168.1.*)
                elseif (strpos($allowed, '*') !== false) {
                    $pattern = str_replace('.', '\\.', str_replace('*', '\\d+', $allowed));
                    if (preg_match('/^' . $pattern . '$/', $ip)) return true;
                }
                // Exact match
                elseif ($ip === $allowed) {
                    return true;
                }
            }
            
            return false;
        } catch (Exception $e) {
            return true; // on error, allow access (fail open for usability)
        }
    }
    
    /**
     * Enforce EMR access control
     * If EMR is deployed at document root AND not local, require subfolder/separate URL
     */
    public static function enforceAccessPolicy(): void {
        try {
            $enforceSeparateAccess = getSetting('security_enforce_separate_access', '0');
            
            if ($enforceSeparateAccess !== '1') {
                return; // not enforced
            }
            
            // If running on localhost/LAN, skip this check entirely
            if (self::isLocalAccess()) {
                return;
            }
            
            // Check if EMR is at document root
            $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
            if ($scriptPath === '/' || $scriptPath === '\\') {
                // EMR is at root - check if access is through allowed gateway
                $allowedGateway = getSetting('security_allowed_gateway', '');
                if (!empty($allowedGateway)) {
                    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
                    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
                    
                    // If the gateway is a path prefix, check it
                    if (strpos($requestUri, $allowedGateway) !== 0) {
                        http_response_code(403);
                        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access Denied</title></head><body style="font-family:sans-serif;text-align:center;margin-top:100px;">';
                        echo '<h1>アクセスが拒否されました</h1>';
                        echo '<p>この電子カルテシステムは指定されたURLからのみアクセスできます。</p>';
                        echo '</body></html>';
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            // silently continue
        }
    }
    
    /**
     * Check login access - enforce IP whitelist on login page
     */
    public static function checkLoginAccess(): bool {
        if (!self::isIPAllowed()) {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>アクセス制限</title>';
            echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
            echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">';
            echo '</head><body style="background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;">';
            echo '<div class="text-center p-5 bg-white rounded-4 shadow" style="max-width:500px;">';
            echo '<i class="bi bi-shield-lock-fill text-danger" style="font-size:3rem;"></i>';
            echo '<h4 class="fw-bold mt-3 mb-2">アクセスが制限されています</h4>';
            echo '<p class="text-muted">このシステムは院内ネットワークからのみアクセスできます。</p>';
            echo '<p class="text-muted small">許可されていないIPアドレスからのアクセスです。<br>管理者にお問い合わせください。</p>';
            echo '<div class="alert alert-light mt-3 small"><strong>あなたのIPアドレス:</strong> ' . htmlspecialchars(self::getClientIP()) . '</div>';
            echo '</div></body></html>';
            exit;
            return false;
        }
        return true;
    }
    
    /**
     * Rate limiting for login attempts
     */
    public static function checkLoginRateLimit(): bool {
        $ip = self::getClientIP();
        $key = 'login_attempts_' . md5($ip);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $data = &$_SESSION[$key];
        
        // Reset after 15 minutes
        if (time() - $data['first_attempt'] > 900) {
            $data = ['count' => 0, 'first_attempt' => time()];
        }
        
        $maxAttempts = 10;
        return $data['count'] < $maxAttempts;
    }
    
    /**
     * Record a login attempt
     */
    public static function recordLoginAttempt(): void {
        $ip = self::getClientIP();
        $key = 'login_attempts_' . md5($ip);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $_SESSION[$key]['count']++;
    }
    
    /**
     * Reset login attempts on successful login
     */
    public static function resetLoginAttempts(): void {
        $ip = self::getClientIP();
        $key = 'login_attempts_' . md5($ip);
        unset($_SESSION[$key]);
    }
    
    /**
     * Generate a secure booking access token
     */
    public static function generateBookingToken(): string {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Validate a booking token
     */
    public static function validateBookingToken(string $token): bool {
        if (empty($token)) return false;
        try {
            $storedToken = getSetting('booking_access_token', '');
            return !empty($storedToken) && hash_equals($storedToken, $token);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP(): string {
        $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if IP is in a given range
     */
    private static function ipInRange(string $ip, string $low, string $high): bool {
        $ipLong = ip2long($ip);
        $lowLong = ip2long($low);
        $highLong = ip2long($high);
        
        if ($ipLong === false || $lowLong === false || $highLong === false) {
            return false;
        }
        
        return ($ipLong >= $lowLong && $ipLong <= $highLong);
    }
    
    /**
     * Check if IP is in CIDR notation range
     */
    private static function ipInCIDR(string $ip, string $cidr): bool {
        list($subnet, $bits) = explode('/', $cidr);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        if ($ip === false || $subnet === false) return false;
        $mask = -1 << (32 - (int)$bits);
        return ($ip & $mask) === ($subnet & $mask);
    }
    
    /**
     * Get security headers for responses
     */
    public static function setSecurityHeaders(): void {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Strict CSP in production
        if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data:;");
        }
    }
}
