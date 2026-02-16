<?php
/**
 * VetCare Pro v2.0 - LINE Messaging Integration
 */

class LineNotification {
    private $channelAccessToken;
    private $channelSecret;
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->channelAccessToken = getSetting('line_channel_access_token', LINE_CHANNEL_ACCESS_TOKEN);
        $this->channelSecret = getSetting('line_channel_secret', LINE_CHANNEL_SECRET);
    }
    
    /**
     * Check if LINE is configured
     */
    public function isConfigured() {
        return !empty($this->channelAccessToken) && !empty($this->channelSecret);
    }
    
    /**
     * Send push message to a LINE user
     */
    public function sendMessage($lineUserId, $messages) {
        if (!$this->isConfigured() || empty($lineUserId)) return false;
        
        if (is_string($messages)) {
            $messages = [['type' => 'text', 'text' => $messages]];
        }
        
        $data = [
            'to' => $lineUserId,
            'messages' => $messages
        ];
        
        return $this->callAPI('https://api.line.me/v2/bot/message/push', $data);
    }
    
    /**
     * Send appointment reminder
     */
    public function sendAppointmentReminder($appointmentId) {
        $apt = $this->db->fetch("
            SELECT a.*, p.name as patient_name, o.name as owner_name, o.id as owner_id,
                   s.name as staff_name
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN owners o ON a.owner_id = o.id OR p.owner_id = o.id
            LEFT JOIN staff s ON a.staff_id = s.id
            WHERE a.id = ?
        ", [$appointmentId]);
        
        if (!$apt) return false;
        
        $lineId = $this->getOwnerLineId($apt['owner_id']);
        if (!$lineId) return false;
        
        $date = formatDate($apt['appointment_date'], 'Y年m月d日');
        $time = substr($apt['appointment_time'], 0, 5);
        $hospitalName = getSetting('hospital_name', 'VetCare動物病院');
        
        $message = "【予約リマインダー】\n\n"
            . "{$hospitalName}からのお知らせです。\n\n"
            . "■ 予約日時: {$date} {$time}\n"
            . "■ 患者名: " . ($apt['patient_name'] ?? '新規') . "\n"
            . "■ 内容: " . ($apt['reason'] ?? '一般診察') . "\n";
        
        if ($apt['staff_name']) {
            $message .= "■ 担当: {$apt['staff_name']}\n";
        }
        
        $message .= "\nお気をつけてお越しください。";
        
        return $this->sendMessage($lineId, $message);
    }
    
    /**
     * Send appointment confirmation
     */
    public function sendAppointmentConfirmation($appointmentId) {
        $apt = $this->db->fetch("
            SELECT a.*, p.name as patient_name, o.name as owner_name, o.id as owner_id
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN owners o ON a.owner_id = o.id OR p.owner_id = o.id
            WHERE a.id = ?
        ", [$appointmentId]);
        
        if (!$apt) return false;
        
        $lineId = $this->getOwnerLineId($apt['owner_id']);
        if (!$lineId) return false;
        
        $date = formatDate($apt['appointment_date'], 'Y年m月d日');
        $time = substr($apt['appointment_time'], 0, 5);
        $hospitalName = getSetting('hospital_name', 'VetCare動物病院');
        
        $message = "【予約確定のお知らせ】\n\n"
            . "{$hospitalName}より、ご予約を承りました。\n\n"
            . "■ 予約日時: {$date} {$time}\n"
            . "■ 患者名: " . ($apt['patient_name'] ?? '新規来院') . "\n"
            . "■ 内容: " . ($apt['reason'] ?? '一般診察') . "\n\n"
            . "変更・キャンセルの場合はお電話ください。\n"
            . "Tel: " . getSetting('hospital_phone', '');
        
        return $this->sendMessage($lineId, $message);
    }
    
    /**
     * Send vaccination reminder
     */
    public function sendVaccinationReminder($patientId) {
        $patient = $this->db->fetch("
            SELECT p.*, o.id as owner_id, o.name as owner_name
            FROM patients p
            JOIN owners o ON p.owner_id = o.id
            WHERE p.id = ?
        ", [$patientId]);
        
        if (!$patient) return false;
        
        $lineId = $this->getOwnerLineId($patient['owner_id']);
        if (!$lineId) return false;
        
        $vaccines = $this->db->fetchAll("
            SELECT * FROM vaccinations 
            WHERE patient_id = ? AND next_due_date IS NOT NULL 
            AND next_due_date <= DATE('now', '+30 days')
            ORDER BY next_due_date
        ", [$patientId]);
        
        if (empty($vaccines)) return false;
        
        $hospitalName = getSetting('hospital_name', 'VetCare動物病院');
        $message = "【ワクチン接種のお知らせ】\n\n"
            . "{$patient['name']}ちゃんのワクチン接種時期が近づいています。\n\n";
        
        foreach ($vaccines as $v) {
            $message .= "■ {$v['vaccine_name']}\n  期限: " . formatDate($v['next_due_date'], 'Y年m月d日') . "\n";
        }
        
        $message .= "\nご予約をお待ちしております。\n{$hospitalName}";
        
        return $this->sendMessage($lineId, $message);
    }
    
    /**
     * Get owner's LINE user ID
     */
    private function getOwnerLineId($ownerId) {
        if (!$ownerId) return null;
        $owner = $this->db->fetch("SELECT line_user_id FROM owners WHERE id = ? AND line_user_id IS NOT NULL AND line_user_id != ''", [$ownerId]);
        return $owner ? $owner['line_user_id'] : null;
    }
    
    /**
     * Call LINE Messaging API
     */
    private function callAPI($url, $data) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->channelAccessToken
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return true;
        }
        
        error_log("LINE API Error (HTTP {$httpCode}): {$response}");
        return false;
    }
    
    /**
     * Verify webhook signature
     */
    public function verifySignature($body, $signature) {
        $hash = base64_encode(hash_hmac('sha256', $body, $this->channelSecret, true));
        return hash_equals($hash, $signature);
    }
}
