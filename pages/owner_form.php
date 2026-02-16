<?php
/** 飼い主登録・編集 */
$id = (int)($_GET['id'] ?? 0);
$owner = $id ? $db->fetch("SELECT * FROM owners WHERE id = ?", [$id]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name']),
        'name_kana' => trim($_POST['name_kana'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'phone2' => trim($_POST['phone2'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
        'emergency_phone' => trim($_POST['emergency_phone'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    if ($id) {
        $db->update('owners', $data, 'id = ?', [$id]);
    } else {
        $code = 'OW-' . str_pad($db->count('owners') + 1, 4, '0', STR_PAD_LEFT);
        $data['owner_code'] = $code;
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = $db->insert('owners', $data);
    }
    redirect("?page=owners");
}
$o = $owner ?: [];
$ownerPets = $id ? $db->fetchAll("SELECT * FROM patients WHERE owner_id = ? AND is_active = 1", [$id]) : [];
?>
<div class="fade-in">
    <a href="?page=owners" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>飼い主一覧</a>
    <h4 class="fw-bold mt-1 mb-3"><?= $id ? '飼い主情報編集' : '新規飼い主登録' ?></h4>
    <div class="row g-3">
        <div class="col-lg-8">
            <form method="POST" class="card">
                <div class="card-body">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label required">氏名</label>
                            <input type="text" name="name" class="form-control" value="<?= h($o['name'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label">フリガナ</label>
                            <input type="text" name="name_kana" class="form-control" value="<?= h($o['name_kana'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">郵便番号</label>
                            <input type="text" name="postal_code" class="form-control" value="<?= h($o['postal_code'] ?? '') ?>" placeholder="000-0000"></div>
                        <div class="col-md-9"><label class="form-label">住所</label>
                            <input type="text" name="address" class="form-control" value="<?= h($o['address'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">電話番号</label>
                            <input type="text" name="phone" class="form-control" value="<?= h($o['phone'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">電話番号2</label>
                            <input type="text" name="phone2" class="form-control" value="<?= h($o['phone2'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">メール</label>
                            <input type="email" name="email" class="form-control" value="<?= h($o['email'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">緊急連絡先（氏名）</label>
                            <input type="text" name="emergency_contact" class="form-control" value="<?= h($o['emergency_contact'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">緊急連絡先（電話）</label>
                            <input type="text" name="emergency_phone" class="form-control" value="<?= h($o['emergency_phone'] ?? '') ?>"></div>
                        <div class="col-12"><label class="form-label">備考</label>
                            <textarea name="notes" class="form-control" rows="2"><?= h($o['notes'] ?? '') ?></textarea></div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a href="?page=owners" class="btn btn-secondary me-2">キャンセル</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $id ? '更新' : '登録' ?></button>
                </div>
            </form>
        </div>
        <?php if ($id && !empty($ownerPets)): ?>
        <div class="col-lg-4">
            <div class="card"><div class="card-header"><i class="bi bi-clipboard2-pulse me-2"></i>登録ペット</div>
                <div class="card-body p-0">
                    <?php foreach ($ownerPets as $pet): ?>
                    <a href="?page=patient_detail&id=<?= $pet['id'] ?>" class="d-block p-3 border-bottom text-decoration-none">
                        <strong><?= h($pet['name']) ?></strong> <small class="text-muted"><?= h($pet['patient_code']) ?></small><br>
                        <small><?= h(getSpeciesName($pet['species'])) ?> / <?= h($pet['breed']) ?></small>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <a href="?page=patient_form&owner_id=<?= $id ?>" class="btn btn-outline-primary btn-sm mt-2 w-100">
                <i class="bi bi-plus-lg me-1"></i>ペットを追加
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
