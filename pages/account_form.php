<?php
/** アカウント作成・編集 */
$auth->requireRole([ROLE_ADMIN]);
$id = (int)($_GET['id'] ?? 0);
$staff = $id ? $db->fetch("SELECT * FROM staff WHERE id=?", [$id]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($id) {
        $data = ['name'=>trim($_POST['name']), 'name_kana'=>trim($_POST['name_kana']??''), 'role'=>$_POST['role'], 'license_number'=>trim($_POST['license_number']??''), 'email'=>trim($_POST['email']??''), 'phone'=>trim($_POST['phone']??''), 'updated_at'=>date('Y-m-d H:i:s')];
        if (!empty($_POST['password'])) $data['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $db->update('staff', $data, 'id=?', [$id]);
    } else {
        $result = $auth->createAccount([
            'login_id'=>trim($_POST['login_id']), 'password'=>$_POST['password'],
            'name'=>trim($_POST['name']), 'name_kana'=>trim($_POST['name_kana']??''),
            'role'=>$_POST['role'], 'license_number'=>trim($_POST['license_number']??''),
            'email'=>trim($_POST['email']??''), 'phone'=>trim($_POST['phone']??'')
        ]);
        if (isset($result['error'])) { $error = $result['error']; } else { redirect("?page=accounts"); }
    }
    if (!isset($error)) redirect("?page=accounts");
}
$s = $staff ?: [];
?>
<div class="fade-in">
    <a href="?page=accounts" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>アカウント管理</a>
    <h4 class="fw-bold mt-1 mb-3"><?= $id ? 'アカウント編集' : '新規アカウント作成' ?></h4>
    <?php if (isset($error)): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
    <form method="POST" class="card"><div class="card-body"><div class="row g-3"><?= csrf_field() ?>
        <?php if (!$id): ?>
        <div class="col-md-6"><label class="form-label required">ログインID</label>
            <input type="text" name="login_id" class="form-control" required></div>
        <?php endif; ?>
        <div class="col-md-6"><label class="form-label <?= $id?'':'required' ?>">パスワード<?= $id?' (変更する場合のみ)':'' ?></label>
            <input type="password" name="password" class="form-control" <?= $id?'':'required' ?> minlength="<?= PASSWORD_MIN_LENGTH ?>"></div>
        <div class="col-md-6"><label class="form-label required">氏名</label>
            <input type="text" name="name" class="form-control" value="<?= h($s['name']??'') ?>" required></div>
        <div class="col-md-6"><label class="form-label">フリガナ</label>
            <input type="text" name="name_kana" class="form-control" value="<?= h($s['name_kana']??'') ?>"></div>
        <div class="col-md-4"><label class="form-label required">役割</label>
            <select name="role" class="form-select" required>
                <?php foreach (ROLE_NAMES as $k=>$v): ?>
                <option value="<?= $k ?>" <?= ($s['role']??'')===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-4"><label class="form-label">獣医師免許番号</label>
            <input type="text" name="license_number" class="form-control" value="<?= h($s['license_number']??'') ?>"></div>
        <div class="col-md-4"><label class="form-label">電話番号</label>
            <input type="text" name="phone" class="form-control" value="<?= h($s['phone']??'') ?>"></div>
        <div class="col-md-6"><label class="form-label">メールアドレス</label>
            <input type="email" name="email" class="form-control" value="<?= h($s['email']??'') ?>"></div>
    </div></div>
    <div class="card-footer text-end">
        <a href="?page=accounts" class="btn btn-secondary me-2">キャンセル</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $id?'更新':'作成' ?></button>
    </div></form>
</div>
