<?php
/**
 * VetCare Pro - 動物病院電子カルテシステム
 * 設定ファイル
 */

// エラー表示設定
define('DEBUG_MODE', false);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// タイムゾーン
date_default_timezone_set('Asia/Tokyo');

// セッション設定
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// アプリケーション設定
define('APP_NAME', 'VetCare Pro');
define('APP_VERSION', '1.0.0');
define('APP_URL', (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:3000'));
define('BASE_PATH', dirname(__DIR__));

// データベース設定（SQLite / MySQL 切替）
// 'sqlite' または 'mysql' を指定
define('DB_DRIVER', 'sqlite');

// SQLite設定
define('DB_SQLITE_PATH', BASE_PATH . '/data/vetcare.db');

// MySQL設定（レンタルサーバー用）
define('DB_MYSQL_HOST', 'localhost');
define('DB_MYSQL_NAME', 'vetcare_db');
define('DB_MYSQL_USER', 'root');
define('DB_MYSQL_PASS', '');
define('DB_MYSQL_CHARSET', 'utf8mb4');

// アップロード設定
define('UPLOAD_DIR', BASE_PATH . '/uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// パスワード設定
define('PASSWORD_MIN_LENGTH', 8);
define('SESSION_LIFETIME', 28800); // 8時間

// 動物種別マスタ
define('SPECIES_LIST', [
    'dog' => '犬',
    'cat' => '猫',
    'rabbit' => 'ウサギ',
    'hamster' => 'ハムスター',
    'bird' => '鳥',
    'ferret' => 'フェレット',
    'turtle' => 'カメ',
    'guinea_pig' => 'モルモット',
    'chinchilla' => 'チンチラ',
    'hedgehog' => 'ハリネズミ',
    'snake' => 'ヘビ',
    'lizard' => 'トカゲ',
    'fish' => '魚',
    'other' => 'その他'
]);

// 性別マスタ
define('SEX_LIST', [
    'male' => 'オス',
    'female' => 'メス',
    'male_neutered' => 'オス（去勢済）',
    'female_spayed' => 'メス（避妊済）',
    'unknown' => '不明'
]);

// 血液型マスタ
define('BLOOD_TYPE_DOG', ['DEA1.1+', 'DEA1.1-', 'DEA1.2+', 'DEA1.2-', '不明']);
define('BLOOD_TYPE_CAT', ['A型', 'B型', 'AB型', '不明']);

// スタッフ権限
define('ROLE_ADMIN', 'admin');
define('ROLE_VET', 'veterinarian');
define('ROLE_NURSE', 'nurse');
define('ROLE_RECEPTION', 'reception');
define('ROLE_LAB', 'lab_tech');

define('ROLE_NAMES', [
    'admin' => 'システム管理者',
    'veterinarian' => '獣医師',
    'nurse' => '動物看護師',
    'reception' => '受付',
    'lab_tech' => '検査技師'
]);

// オーダーステータス
define('ORDER_STATUS', [
    'pending' => '未実施',
    'in_progress' => '実施中',
    'completed' => '完了',
    'cancelled' => 'キャンセル'
]);

// 入院ステータス
define('ADMISSION_STATUS', [
    'admitted' => '入院中',
    'discharged' => '退院',
    'transferred' => '転院',
    'deceased' => '死亡'
]);
