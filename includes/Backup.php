<?php
/**
 * 自動バックアップ管理クラス
 */
require_once __DIR__ . '/config.php';

class Backup {
    /**
     * バックアップを実行する（1日1回）
     * @param bool $force 強制的に実行するか
     */
    public static function run($force = false) {
        // SQLiteのみ対応
        if (DB_DRIVER !== 'sqlite') {
            return;
        }

        $source = DB_SQLITE_PATH; // config.phpで定義されたDBパス
        $backupDir = BASE_PATH . '/backups/';
        
        // ディレクトリがなければ作成
        if (!is_dir($backupDir)) {
            if (!mkdir($backupDir, 0700, true)) {
                return; // 作成失敗
            }
            // セキュリティ対策: .htaccessを置いて外部からアクセス不可にする
            file_put_contents($backupDir . '.htaccess', "Order deny,allow\nDeny from all");
        }

        $today = date('Ymd');
        
        // 強制実行でなければ、今日のバックアップがあるか確認
        if (!$force) {
            $files = glob($backupDir . "vetcare_backup_{$today}_*.db");
            if (count($files) > 0) {
                return; // 既にバックアップあり
            }
        }

        // バックアップファイル名（日時付き）
        $dest = $backupDir . 'vetcare_backup_' . date('Ymd_His') . '.db';
        
        // コピー実行
        if (copy($source, $dest)) {
            // 古いバックアップ（30日以上前）を削除
            self::cleanup($backupDir);
        }
    }

    /**
     * 古いバックアップの削除
     */
    private static function cleanup($dir) {
        $files = glob($dir . "*.db");
        $now = time();
        $retention = 60 * 60 * 24 * 30; // 30日間保存
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= $retention) {
                    unlink($file);
                }
            }
        }
    }
}