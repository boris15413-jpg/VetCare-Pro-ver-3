<?php
/**
 * データベース構造更新用スクリプト
 * ※実行後は必ず削除してください
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

// 管理者権限チェック（念のため）
require_once __DIR__ . '/../includes/Auth.php';
$auth = new Auth();
if (!$auth->hasRole('admin')) {
    // ログインしていない、または管理者でない場合はログイン画面へ
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    echo "エラー: この操作は管理者のみ可能です。";
    exit;
}

$db = Database::getInstance();
$pdo = $db->getPDO();

echo "<h3>データベース更新ツール</h3>";
echo "<p>新しい機能に必要なテーブルを作成します...</p><hr>";

$isMySQL = DB_DRIVER === 'mysql';
$dt = $isMySQL ? 'DATETIME' : 'TEXT';
$intPK = $isMySQL ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

// 作成・更新するテーブルのSQLリスト
$sqls = [
    // 1. 施設設定テーブル (今回追加)
    "CREATE TABLE IF NOT EXISTS hospital_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT DEFAULT '',
        updated_at {$dt} DEFAULT CURRENT_TIMESTAMP
    )",
    
    // 2. お知らせ既読テーブル (念のため確認)
    "CREATE TABLE IF NOT EXISTS notice_reads (
        id {$intPK},
        notice_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        read_at {$dt} DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (notice_id) REFERENCES notices(id),
        FOREIGN KEY (user_id) REFERENCES staff(id)
    )"
];

foreach ($sqls as $index => $sql) {
    try {
        $pdo->exec($sql);
        // テーブル名を取得して表示
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
        $tableName = $m[1] ?? 'table_' . $index;
        echo "<div style='color:green'>✓ テーブル確認・作成: <strong>{$tableName}</strong></div>";
    } catch (PDOException $e) {
        echo "<div style='color:red'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

echo "<hr>";
echo "<p><strong>処理が完了しました。</strong></p>";
echo "<p style='color:red'>※セキュリティのため、サーバー上の <code>pages/update_db.php</code> ファイルは必ず削除してください。</p>";
echo '<a href="index.php?page=settings">施設設定画面へ移動する</a>';
?>