<?php
/**
 * インストールページ - 初回セットアップ
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/helpers.php';


$step = $_POST['step'] ?? $_GET['step'] ?? '1';
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === '1') {
        // マイグレーション実行
        ob_start();
        require_once BASE_PATH . '/migrations/001_create_tables.php';
        $output = ob_get_clean();
        $msg = 'データベーステーブルの作成が完了しました。';
        $step = '2';
    }
    elseif ($step === '2') {
        // サンプルデータ
        if (isset($_POST['load_sample'])) {
            ob_start();
            require_once BASE_PATH . '/migrations/002_seed_data.php';
            $output = ob_get_clean();
            $msg = 'サンプルデータの投入が完了しました。';
        }
        $step = '3';
    }
    elseif ($step === '3') {
        // 管理者アカウント作成
        $db = Database::getInstance();
        $auth = new Auth();
        $existing = $db->fetch("SELECT id FROM staff WHERE login_id = ?", [trim($_POST['login_id'])]);
        if ($existing) {
            $msg = '管理者アカウントは既に存在します。ログインページへ進んでください。';
        } else {
            $result = $auth->createAccount([
                'login_id' => trim($_POST['login_id']),
                'password' => $_POST['password'],
                'name' => trim($_POST['name']),
                'name_kana' => '',
                'role' => 'admin',
            ]);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $msg = '管理者アカウントを作成しました。';
            }
        }
        $step = 'done';
    }
}
?>
<!DOCTYPE html>
<html lang="ja"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>初期セットアップ - <?= h(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head><body class="login-page">
<div class="login-card" style="max-width:500px">
    <div class="login-logo">
        <i class="bi bi-heart-pulse-fill"></i>
        <h4 class="mt-2"><?= h(APP_NAME) ?> セットアップ</h4>
    </div>

    <?php if ($msg): ?><div class="alert alert-success py-2"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= h($error) ?></div><?php endif; ?>

    <?php if ($step === '1'): ?>
    <h5>Step 1: データベース初期化</h5>
    <p class="text-muted">データベーステーブルを作成します。</p>
    <form method="POST"><input type="hidden" name="step" value="1">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-database me-2"></i>データベースを初期化</button>
    </form>

    <?php elseif ($step === '2'): ?>
    <h5>Step 2: サンプルデータ</h5>
    <p class="text-muted">デモ用のサンプルデータを投入しますか？</p>
    <form method="POST" class="d-grid gap-2"><input type="hidden" name="step" value="2">
        <button type="submit" name="load_sample" value="1" class="btn btn-primary"><i class="bi bi-box me-2"></i>サンプルデータを投入</button>
        <button type="submit" class="btn btn-outline-secondary">スキップ</button>
    </form>

    <?php elseif ($step === '3'): ?>
    <h5>Step 3: 管理者アカウント作成</h5>
    <form method="POST"><input type="hidden" name="step" value="3">
        <div class="mb-3"><label class="form-label">ログインID</label><input type="text" name="login_id" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">パスワード</label><input type="password" name="password" class="form-control" required minlength="8"></div>
        <div class="mb-3"><label class="form-label">氏名</label><input type="text" name="name" class="form-control" required></div>
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-person-plus me-2"></i>アカウント作成</button>
    </form>

    <?php elseif ($step === 'done'): ?>
    <div class="text-center">
        <i class="bi bi-check-circle text-success" style="font-size:4rem"></i>
        <h5 class="mt-3">セットアップ完了</h5>
        <p>システムの準備が整いました。</p>
        <a href="./index.php" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-2"></i>ログインページへ</a>    </div>
    <?php endif; ?>
</div>
</body></html>
