<?php
/** 同意書管理 */
$patientId = (int)($_GET['patient_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? 'form';

if ($action === 'list') {
    // List all consent forms (optionally filtered by patient)
    $where = '1=1';
    $params = [];
    if ($patientId) {
        $where .= ' AND cf.patient_id = ?';
        $params[] = $patientId;
    }
    $consents = $db->fetchAll("
        SELECT cf.*, p.name as patient_name, p.patient_code, o.name as owner_name
        FROM consent_forms cf
        JOIN patients p ON cf.patient_id = p.id
        JOIN owners o ON p.owner_id = o.id
        WHERE {$where}
        ORDER BY cf.created_at DESC LIMIT 100
    ", $params);
    ?>
    <div class="fade-in">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-check me-2"></i>同意書管理</h4>
            <a href="?page=consent_form&action=form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規作成</a>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>作成日</th><th>患畜</th><th>飼い主</th><th>種類</th><th>タイトル</th><th>状態</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($consents as $cf): ?>
                        <tr>
                            <td><?= formatDate($cf['created_at']) ?></td>
                            <td><strong><?= h($cf['patient_name']) ?></strong><br><small class="text-muted"><?= h($cf['patient_code']) ?></small></td>
                            <td><?= h($cf['owner_name']) ?></td>
                            <td><span class="badge bg-info"><?= h($cf['consent_type']) ?></span></td>
                            <td><?= h($cf['title']) ?></td>
                            <td>
                                <?php if ($cf['signed_at']): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>署名済</span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>未署名</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=consent_form&id=<?= $cf['id'] ?>&action=form" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($consents)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">同意書がありません</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
    return;
}

// Form mode
$consent = $id ? $db->fetch("SELECT * FROM consent_forms WHERE id = ?", [$id]) : null;
if ($consent) $patientId = $consent['patient_id'];

$patient = $patientId ? $db->fetch("SELECT p.*, o.name as owner_name, o.id as owner_id FROM patients p JOIN owners o ON p.owner_id = o.id WHERE p.id = ?", [$patientId]) : null;
$patients = $db->fetchAll("SELECT p.id, p.patient_code, p.name, p.species, o.name as owner_name FROM patients p JOIN owners o ON p.owner_id = o.id WHERE p.is_active = 1 ORDER BY p.name");

$consentTypes = [
    '手術同意書' => '手術の目的、方法、リスク、合併症の可能性について説明を受け、手術に同意します。',
    '麻酔同意書' => '全身麻酔のリスクと合併症の可能性について説明を受け、麻酔の実施に同意します。',
    '検査同意書' => '検査の目的、方法、リスクについて説明を受け、検査の実施に同意します。',
    '入院同意書' => '入院の目的、期間、費用の見積もりについて説明を受け、入院に同意します。',
    '輸血同意書' => '輸血の必要性とリスクについて説明を受け、輸血に同意します。',
    'DNR同意書' => '蘇生措置不実施の意味と影響について説明を受け、DNRに同意します。',
    '安楽死同意書' => '安楽死の決断と方法について説明を受け、安楽死の実施に同意します。',
    'その他' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $data = [
        'patient_id' => (int)$_POST['patient_id'],
        'consent_type' => trim($_POST['consent_type']),
        'title' => trim($_POST['title']),
        'content' => trim($_POST['content']),
        'risks_explained' => trim($_POST['risks_explained'] ?? ''),
        'alternatives_explained' => trim($_POST['alternatives_explained'] ?? ''),
        'created_by' => $auth->currentUserId(),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    
    if (isset($_POST['mark_signed'])) {
        $data['signed_at'] = date('Y-m-d H:i:s');
        $data['signed_by_name'] = trim($_POST['signed_by_name'] ?? '');
    }
    
    if ($consent) {
        $db->update('consent_forms', $data, 'id = ?', [$id]);
    } else {
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = $db->insert('consent_forms', $data);
    }
    setFlash('success', '同意書を保存しました');
    redirect('?page=consent_form&action=list');
}
?>

<div class="fade-in">
    <a href="?page=consent_form&action=list" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>同意書一覧</a>
    <h4 class="fw-bold mt-1 mb-3"><i class="bi bi-file-earmark-check me-2"></i><?= $consent ? '同意書編集' : '新規同意書作成' ?></h4>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-info-circle me-2"></i>基本情報</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">患畜 <span class="text-danger">*</span></label>
                                <select name="patient_id" class="form-select" required>
                                    <option value="">-- 選択 --</option>
                                    <?php foreach ($patients as $pt): ?>
                                    <option value="<?= $pt['id'] ?>" <?= $patientId == $pt['id'] ? 'selected' : '' ?>>
                                        <?= h($pt['patient_code']) ?> - <?= h($pt['name']) ?> (<?= h($pt['owner_name']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">同意書種類 <span class="text-danger">*</span></label>
                                <select name="consent_type" class="form-select" required id="consentType" onchange="prefillContent()">
                                    <?php foreach ($consentTypes as $type => $template): ?>
                                    <option value="<?= h($type) ?>" <?= ($consent['consent_type'] ?? '') === $type ? 'selected' : '' ?> data-template="<?= h($template) ?>"><?= h($type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">タイトル</label>
                                <input type="text" name="title" class="form-control" value="<?= h($consent['title'] ?? '') ?>" placeholder="手術同意書 - ○○手術">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-file-text me-2"></i>同意内容</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">説明内容・同意事項 <span class="text-danger">*</span></label>
                            <textarea name="content" class="form-control" rows="6" required id="consentContent"><?= h($consent['content'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">リスク説明</label>
                            <textarea name="risks_explained" class="form-control" rows="3" placeholder="考えられるリスクと合併症..."><?= h($consent['risks_explained'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">代替治療の説明</label>
                            <textarea name="alternatives_explained" class="form-control" rows="2" placeholder="その他の治療選択肢..."><?= h($consent['alternatives_explained'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card" style="position:sticky; top:80px;">
                    <div class="card-header"><i class="bi bi-pen me-2"></i>署名</div>
                    <div class="card-body">
                        <?php if ($consent && $consent['signed_at']): ?>
                        <div class="alert alert-success py-2">
                            <i class="bi bi-check-circle-fill me-1"></i>署名済
                            <br><small><?= formatDateTime($consent['signed_at']) ?></small>
                            <?php if ($consent['signed_by_name']): ?>
                            <br><small>署名者: <?= h($consent['signed_by_name']) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label">署名者名（飼い主）</label>
                            <input type="text" name="signed_by_name" class="form-control" value="<?= h($patient['owner_name'] ?? '') ?>">
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="mark_signed" value="1" class="form-check-input" id="markSigned">
                            <label class="form-check-label" for="markSigned">署名済みとしてマーク</label>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i><?= $consent ? '更新' : '保存' ?></button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function prefillContent() {
    const sel = document.getElementById('consentType');
    const opt = sel.options[sel.selectedIndex];
    const content = document.getElementById('consentContent');
    if (opt.dataset.template && !content.value.trim()) {
        content.value = opt.dataset.template;
    }
}
</script>
