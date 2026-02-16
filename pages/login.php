<?php
/**
 * VetCare Pro v2.0 - Premium Login Page
 */
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    redirect('index.php?page=login');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login_id) || empty($password)) {
        $error = 'ログインIDとパスワードを入力してください。';
    } elseif (!Security::checkLoginRateLimit()) {
        $error = 'ログイン試行回数が上限に達しました。15分後に再試行してください。';
    } elseif ($auth->login($login_id, $password)) {
        Security::resetLoginAttempts();
        redirect('index.php?page=dashboard');
    } else {
        Security::recordLoginAttempt();
        $error = 'ログインIDまたはパスワードが正しくありません。';
    }
}

$hospitalName = '';
try { $hospitalName = getSetting('hospital_name', ''); } catch (Exception $e) {}
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
            <div class="logo-icon">
                <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <h3 class="fw-bold mt-3 mb-1"><?= h(APP_NAME) ?></h3>
            <?php if ($hospitalName): ?>
                <p class="text-muted mb-0" style="font-size:0.9rem"><?= h($hospitalName) ?></p>
            <?php else: ?>
                <p class="text-muted mb-0" style="font-size:0.85rem">動物病院 電子カルテシステム</p>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 glass-alert" style="border-radius:10px;">
            <i class="bi bi-exclamation-triangle me-1"></i><?= h($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="index.php?page=login">
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-person me-1"></i>ログインID</label>
                <input type="text" name="login_id" class="form-control form-control-lg" 
                       placeholder="ログインIDを入力" 
                       value="<?= h($_POST['login_id'] ?? '') ?>" 
                       required autofocus
                       autocomplete="username">
            </div>
            <div class="mb-4">
                <label class="form-label"><i class="bi bi-lock me-1"></i>パスワード</label>
                <div class="position-relative">
                    <input type="password" name="password" id="loginPassword" 
                           class="form-control form-control-lg" 
                           placeholder="パスワードを入力" 
                           required
                           autocomplete="current-password">
                    <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y pe-3 text-muted"
                            onclick="togglePasswordVisibility()" tabindex="-1" style="text-decoration:none;">
                        <i class="bi bi-eye" id="passwordToggleIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100" style="padding:12px;">
                <i class="bi bi-box-arrow-in-right me-2"></i>ログイン
            </button>
        </form>

        <div class="mt-4 p-3 rounded" style="background:#f1f5f9; border-radius:10px!important;">
            <small class="text-muted d-block mb-2"><strong><i class="bi bi-info-circle me-1"></i>デモアカウント:</strong></small>
            <div class="row g-1" style="font-size:0.78rem;">
                <div class="col-6">
                    <small class="text-muted">管理者: <code>admin</code> / <code>admin123</code></small>
                </div>
                <div class="col-6">
                    <small class="text-muted">獣医師: <code>dr_suzuki</code> / <code>pass1234</code></small>
                </div>
                <div class="col-6">
                    <small class="text-muted">看護師: <code>ns_sato</code> / <code>pass1234</code></small>
                </div>
                <div class="col-6">
                    <small class="text-muted">受付: <code>rc_kato</code> / <code>pass1234</code></small>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <small class="text-muted"><?= h(APP_NAME) ?> v<?= APP_VERSION ?></small>
        </div>
    </div>

    <script>
    function togglePasswordVisibility() {
        const input = document.getElementById('loginPassword');
        const icon = document.getElementById('passwordToggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }
    </script>
</body>
</html>
