<?php
/** 患畜画像管理 */
$patientId = (int)($_GET['patient_id'] ?? 0);
$patient = $patientId ? $db->fetch("SELECT p.*, o.name as owner_name FROM patients p JOIN owners o ON p.owner_id = o.id WHERE p.id = ?", [$patientId]) : null;

if (!$patient) {
    // Patient selector
    $patients = $db->fetchAll("SELECT p.id, p.patient_code, p.name, p.species, o.name as owner_name,
        (SELECT COUNT(*) FROM patient_images pi WHERE pi.patient_id = p.id) as image_count
        FROM patients p JOIN owners o ON p.owner_id = o.id WHERE p.is_active = 1 ORDER BY p.name");
    ?>
    <div class="fade-in">
        <h4 class="fw-bold mb-3"><i class="bi bi-images me-2"></i>画像管理 - 患畜選択</h4>
        <div class="card">
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($patients as $pt): ?>
                    <a href="?page=patient_images&patient_id=<?= $pt['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= h($pt['name']) ?></strong>
                            <small class="text-muted ms-2"><?= h($pt['patient_code']) ?> | <?= h(getSpeciesName($pt['species'])) ?> | <?= h($pt['owner_name']) ?></small>
                        </div>
                        <span class="badge bg-primary rounded-pill"><i class="bi bi-image me-1"></i><?= $pt['image_count'] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return;
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? 'upload';
    
    if ($action === 'upload' && !empty($_FILES['images']['name'][0])) {
        $uploadDir = UPLOAD_DIR . 'patients/' . $patientId . '/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $count = 0;
        foreach ($_FILES['images']['name'] as $i => $name) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;
            
            $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $uploadDir . $filename)) {
                $db->insert('patient_images', [
                    'patient_id' => $patientId,
                    'file_path' => 'patients/' . $patientId . '/' . $filename,
                    'image_type' => $_POST['image_type'] ?? 'photo',
                    'caption' => trim($_POST['caption'] ?? ''),
                    'taken_at' => $_POST['taken_at'] ?: date('Y-m-d'),
                    'uploaded_by' => $auth->currentUserId(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $count++;
            }
        }
        if ($count > 0) {
            setFlash('success', $count . '枚の画像をアップロードしました。');
        }
        redirect('?page=patient_images&patient_id=' . $patientId);
    }
    elseif ($action === 'delete') {
        $imageId = (int)$_POST['image_id'];
        $img = $db->fetch("SELECT * FROM patient_images WHERE id = ? AND patient_id = ?", [$imageId, $patientId]);
        if ($img) {
            $filePath = UPLOAD_DIR . $img['file_path'];
            if (file_exists($filePath)) @unlink($filePath);
            $db->delete('patient_images', 'id = ?', [$imageId]);
            setFlash('success', '画像を削除しました。');
        }
        redirect('?page=patient_images&patient_id=' . $patientId);
    }
    elseif ($action === 'update_caption') {
        $imageId = (int)$_POST['image_id'];
        $caption = trim($_POST['caption'] ?? '');
        $db->update('patient_images', ['caption' => $caption], 'id = ? AND patient_id = ?', [$imageId, $patientId]);
        setFlash('success', 'キャプションを更新しました。');
        redirect('?page=patient_images&patient_id=' . $patientId);
    }
    elseif ($action === 'set_profile') {
        $imageId = (int)$_POST['image_id'];
        $img = $db->fetch("SELECT file_path FROM patient_images WHERE id = ? AND patient_id = ?", [$imageId, $patientId]);
        if ($img) {
            $db->update('patients', ['photo_path' => $img['file_path'], 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$patientId]);
            setFlash('success', 'プロフィール画像を設定しました。');
        }
        redirect('?page=patient_images&patient_id=' . $patientId);
    }
}

// Load images
$typeFilter = $_GET['type'] ?? '';
$where = 'patient_id = ?';
$params = [$patientId];
if ($typeFilter) {
    $where .= " AND image_type = ?";
    $params[] = $typeFilter;
}
$images = $db->fetchAll("SELECT pi.*, s.name as uploaded_by_name FROM patient_images pi LEFT JOIN staff s ON pi.uploaded_by = s.id WHERE {$where} ORDER BY pi.created_at DESC", $params);

$imageTypes = [
    'photo' => '写真',
    'xray' => 'レントゲン',
    'ultrasound' => '超音波',
    'surgery' => '手術写真',
    'wound' => '創傷経過',
    'skin' => '皮膚症状',
    'eye' => '眼科',
    'dental' => '歯科',
    'other' => 'その他',
];
?>

<div class="fade-in">
    <a href="?page=patient_detail&id=<?= $patientId ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= h($patient['name']) ?>の詳細</a>
    <h4 class="fw-bold mt-1 mb-3">
        <i class="bi bi-images me-2"></i>画像管理 - <?= h($patient['name']) ?>
        <small class="text-muted"><?= h(getSpeciesName($patient['species'])) ?></small>
    </h4>

    <?php renderFlash(); ?>

    <div class="row g-3">
        <!-- Upload form -->
        <div class="col-lg-4">
            <div class="card" style="position:sticky; top:80px;">
                <div class="card-header fw-bold"><i class="bi bi-cloud-upload me-2"></i>画像をアップロード</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="upload">
                        <div class="mb-3">
                            <label class="form-label">画像ファイル <span class="text-danger">*</span></label>
                            <input type="file" name="images[]" class="form-control" accept="image/*" multiple required>
                            <small class="text-muted">複数選択可（JPG, PNG, GIF, WebP）</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">種類</label>
                            <select name="image_type" class="form-select">
                                <?php foreach ($imageTypes as $key => $name): ?>
                                <option value="<?= h($key) ?>"><?= h($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">撮影日</label>
                            <input type="date" name="taken_at" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">メモ</label>
                            <input type="text" name="caption" class="form-control" placeholder="部位・状態など">
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>アップロード</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Image gallery -->
        <div class="col-lg-8">
            <!-- Filter -->
            <div class="mb-3 d-flex gap-2 flex-wrap">
                <a href="?page=patient_images&patient_id=<?= $patientId ?>" class="btn btn-sm <?= !$typeFilter ? 'btn-primary' : 'btn-outline-secondary' ?>">全て</a>
                <?php foreach ($imageTypes as $key => $name): ?>
                <a href="?page=patient_images&patient_id=<?= $patientId ?>&type=<?= $key ?>" 
                   class="btn btn-sm <?= $typeFilter === $key ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $name ?></a>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-grid me-2"></i>画像一覧 (<?= count($images) ?>枚)</span>
                </div>
                <div class="card-body">
                    <?php if (empty($images)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-image" style="font-size:3rem;"></i>
                        <p class="mt-2">画像がありません</p>
                    </div>
                    <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($images as $img): ?>
                        <div class="col-6 col-md-4">
                            <div class="card h-100">
                                <img src="uploads/<?= h($img['file_path']) ?>" class="card-img-top" style="height:160px; object-fit:cover; cursor:pointer;" 
                                     onclick="window.open('uploads/<?= h($img['file_path']) ?>', '_blank')">
                                <div class="card-body p-2">
                                    <small class="d-block text-muted"><?= formatDate($img['taken_at']) ?></small>
                                    <span class="badge bg-info"><?= h($imageTypes[$img['image_type']] ?? $img['image_type']) ?></span>
                                    <?php if ($img['caption']): ?>
                                    <small class="d-block mt-1"><?= h($img['caption']) ?></small>
                                    <?php endif; ?>
                                    <div class="mt-2 d-flex gap-1">
                                        <form method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="set_profile">
                                            <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="プロフィール画像に設定"><i class="bi bi-person-badge"></i></button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('この画像を削除しますか？');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="削除"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
