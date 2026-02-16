<?php
/**
 * 監査ログ管理クラス（改ざん検知機能付き）
 */
require_once __DIR__ . '/Database.php';

class Audit {
    
    /**
     * ログを記録する
     * @param string $table 対象テーブル名
     * @param int $target_id 対象ID
     * @param string $action 操作 (CREATE, UPDATE, DELETE, LOGIN)
     * @param array|null $old 変更前のデータ（連想配列）
     * @param array|null $new 変更後のデータ（連想配列）
     */
    public static function log($table, $target_id, $action, $old = null, $new = null) {
        $db = Database::getInstance();
        $auth = new Auth(); // Authクラスが必要
        $userId = $auth->currentUserId() ?: 0; // 0はシステム処理
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // データのJSON化（差分のみ記録するなど軽量化も可だが、証拠能力優先で全記録）
        $oldJson = $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null;
        $timestamp = date('Y-m-d H:i:s');

        // 1. 直前のログのハッシュ値を取得（鎖を繋ぐ）
        $lastLog = $db->fetch("SELECT record_hash FROM audit_logs ORDER BY id DESC LIMIT 1");
        $prevHash = $lastLog ? $lastLog['record_hash'] : 'GENESIS_BLOCK'; // 最初は固定値

        // 2. このレコードのハッシュを計算
        // (直前のハッシュ + 今回の内容 + 時間) を混ぜてSHA256ハッシュを作る
        $dataToHash = $prevHash . $userId . $table . $target_id . $action . $oldJson . $newJson . $timestamp;
        $currentHash = hash('sha256', $dataToHash);

        // 3. データベースに保存
        $db->insert('audit_logs', [
            'user_id' => $userId,
            'target_table' => $table,
            'target_id' => $target_id,
            'action_type' => $action,
            'old_value' => $oldJson,
            'new_value' => $newJson,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'previous_hash' => $prevHash,
            'record_hash' => $currentHash,
            'created_at' => $timestamp
        ]);
    }

    /**
     * 改ざんチェック（全レコードの整合性を検証）
     * @return array エラーのあったIDリスト
     */
    public static function verifyIntegrity() {
        $db = Database::getInstance();
        $logs = $db->fetchAll("SELECT * FROM audit_logs ORDER BY id ASC");
        
        $errors = [];
        $prevHash = 'GENESIS_BLOCK';

        foreach ($logs as $log) {
            // 1. 前のハッシュと繋がっているか確認
            if ($log['previous_hash'] !== $prevHash) {
                $errors[] = ['id' => $log['id'], 'reason' => 'チェーン不整合（直前のレコードが削除または改ざんされています）'];
            }

            // 2. 内容の計算が合うか確認
            $dataToHash = $prevHash . $log['user_id'] . $log['target_table'] . $log['target_id'] . 
                          $log['action_type'] . $log['old_value'] . $log['new_value'] . $log['created_at'];
            $calculatedHash = hash('sha256', $dataToHash);

            if ($calculatedHash !== $log['record_hash']) {
                $errors[] = ['id' => $log['id'], 'reason' => '内容改ざん（データの内容とハッシュ値が一致しません）'];
            }

            $prevHash = $log['record_hash'];
        }

        return $errors;
    }
}