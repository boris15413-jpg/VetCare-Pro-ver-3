<?php
/** プロフィール (Update: 電子印鑑対応) */
$user = $auth->currentUser();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // プロフィール更新
    if (isset($_POST['update_profile'])) {
        $data = [
            'name' => trim($_POST['name']),
            'name_kana' => trim($_POST['name_kana']??''),
            'email' => trim($_POST['email']??''),
            'phone' => trim($_POST['phone']??''),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // 印鑑画像の処理
        if (!empty($_FILES['stamp_image']['name'])) {
            $file = $_FILES['stamp_image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'gif', 'jpg', 'jpeg'])) {
                // ファイル名をランダム化して保存
                $filename = 'stamp_' . $user['id'] . '_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
                    // 古い画像があれば削除
                    if ($user['stamp_image'] && file_exists(UPLOAD_DIR . $user['stamp_image'])) {
                        unlink(UPLOAD_DIR . $user['stamp_image']);
                    }
                    $data['stamp_image'] = $filename;
                }
            } else {
                $err = '画像形式が正しくありません。';
            }
        }
        
        // 印鑑削除フラグ
        if (isset($_POST['delete_stamp']) && $user['stamp_image']) {
            if (file_exists(UPLOAD_DIR . $user['stamp_image'])) {
                unlink(UPLOAD_DIR . $user['stamp_image']);
            }
            $data['stamp_image'] = '';
        }

        if (!$err) {
            $db->update('staff', $data, 'id=?', [$user['id']]);
            $_SESSION['user_name'] = trim($_POST['name']);
            $msg = 'プロフィールを更新しました。';
            $user = $auth->currentUser(); // 再取得
        }
    }

    // パスワード変更
    if (isset($_POST['change_password'])) {
        if (!password_verify($_POST['current_password'], $user['password_hash'])) {
            $err = '現在のパスワードが正しくありません。';
        } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
            $err = '新しいパスワードが一致しません。';
        } elseif (strlen($_POST['new_password']) < PASSWORD_MIN_LENGTH) {
            $err = 'パスワードは' . PASSWORD_MIN_LENGTH . '文字以上にしてください。';
        } else {
            $auth->updatePassword($user['id'], $_POST['new_password']);
            $msg = 'パスワードを変更しました。';
        }
    }
}
?>
<div class="fade-in">
    <h4 class="fw-bold mb-3"><i class="bi bi-person me-2"></i>プロフィール設定</h4>
    
    <?php if ($msg): ?><div class="alert alert-success py-2"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger py-2"><?= h($err) ?></div><?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" class="card h-100" enctype="multipart/form-data">
                <div class="card-header bg-white fw-bold">基本情報・電子印鑑</div>
                <div class="card-body">
                    <div class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-md-6">
                            <label class="form-label">ログインID</label>
                            <input type="text" class="form-control" value="<?= h($user['login_id']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">役割</label>
                            <input type="text" class="form-control" value="<?= h(getRoleName($user['role'])) ?>" disabled>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label required">氏名</label>
                            <input type="text" name="name" class="form-control" value="<?= h($user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">フリガナ</label>
                            <input type="text" name="name_kana" class="form-control" value="<?= h($user['name_kana']) ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">メール</label>
                            <input type="email" name="email" class="form-control" value="<?= h($user['email']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">電話番号</label>
                            <input type="text" name="phone" class="form-control" value="<?= h($user['phone']) ?>">
                        </div>

                        <div class="col-12"><hr></div>

                        <div class="col-12">
                            <label class="form-label">個人の電子印鑑 (署名用)</label>
                            <div class="d-flex align-items-start gap-3">
                                <div class="border rounded p-2 bg-light d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <?php if ($user['stamp_image'] && file_exists(UPLOAD_DIR . $user['stamp_image'])): ?>
                                        <img src="uploads/<?= h($user['stamp_image']) ?>" style="max-width: 100%; max-height: 100%;">
                                    <?php else: ?>
                                        <span class="text-muted small">未登録</span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" name="stamp_image" class="form-control form-control-sm" accept="image/*">
                                    <div class="form-text">背景透過PNG推奨。処方箋などの発行者欄に使用されます。</div>
                                    <?php if ($user['stamp_image']): ?>
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" name="delete_stamp" id="delStamp">
                                        <label class="form-check-label text-danger small" for="delStamp">登録済みの印影を削除する</label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" name="update_profile" value="1" class="btn btn-primary">設定を保存</button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <form method="POST" class="card h-100">
                <div class="card-header bg-white fw-bold">パスワード変更</div>
                <div class="card-body">
                    <div class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-12">
                            <label class="form-label">現在のパスワード</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">新しいパスワード</label>
                            <input type="password" name="new_password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">新しいパスワード（確認）</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" name="change_password" value="1" class="btn btn-warning text-dark">変更する</button>
                </div>
            </form>
        </div>
    </div>
</div>