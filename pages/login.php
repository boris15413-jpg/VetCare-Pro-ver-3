<?php
/**
 * ログインページ
 */
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    redirect('index.php?page=login');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = $_POST['login_id'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($auth->login($login_id, $password)) {
        redirect('index.php?page=dashboard');
    } else {
        $error = 'ログインIDまたはパスワードが正しくありません。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - <?= h(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-card fade-in">
        <div class="login-logo">
            <i class="bi bi-heart-pulse-fill"></i>
            <h3 class="mt-2 fw-bold"><?= h(APP_NAME) ?></h3>
            <p class="text-muted mb-0">動物病院電子カルテシステム</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2">
            <i class="bi bi-exclamation-circle me-1"></i><?= h($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="index.php?page=login">
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-person me-1"></i>ログインID</label>
                <input type="text" name="login_id" class="form-control form-control-lg" placeholder="ログインIDを入力" value="<?= h($_POST['login_id'] ?? '') ?>" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label"><i class="bi bi-lock me-1"></i>パスワード</label>
                <input type="password" name="password" class="form-control form-control-lg" placeholder="パスワードを入力" required>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>ログイン
            </button>
        </form>

        <div class="mt-4 p-3 bg-light rounded">
            <small class="text-muted d-block mb-1"><strong>デモアカウント:</strong></small>
            <small class="text-muted">管理者: <code>admin</code> / <code>admin123</code></small><br>
            <small class="text-muted">獣医師: <code>dr_suzuki</code> / <code>pass1234</code></small><br>
            <small class="text-muted">看護師: <code>ns_sato</code> / <code>pass1234</code></small><br>
            <small class="text-muted">受付: <code>rc_kato</code> / <code>pass1234</code></small>
        </div>
    </div>
</body>
</html>
