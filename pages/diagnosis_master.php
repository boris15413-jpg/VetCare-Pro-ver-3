<?php
/** 診断マスタ管理 */
$auth->requireRole([ROLE_ADMIN, ROLE_VET]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $data = [
            'diagnosis_code' => trim($_POST['diagnosis_code']),
            'diagnosis_name' => trim($_POST['diagnosis_name']),
            'category' => trim($_POST['category'] ?? ''),
            'species_applicable' => trim($_POST['species_applicable'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
        ];
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->update('diagnosis_master', $data, 'id = ?', [$id]);
            setFlash('success', '診断コードを更新しました');
        } else {
            $db->insert('diagnosis_master', $data);
            setFlash('success', '診断コードを登録しました');
        }
        redirect('?page=diagnosis_master');
    }
}

$search = $_GET['q'] ?? '';
$where = "1=1"; $params = [];
if ($search) {
    $where .= " AND (diagnosis_name LIKE ? OR diagnosis_code LIKE ? OR category LIKE ?)";
    $s = "%{$search}%";
    $params = [$s, $s, $s];
}
$diagnoses = $db->fetchAll("SELECT * FROM diagnosis_master WHERE {$where} ORDER BY diagnosis_code", $params);

$edit = isset($_GET['edit']) ? $db->fetch("SELECT * FROM diagnosis_master WHERE id=?", [(int)$_GET['edit']]) : null;
$categories = $db->fetchAll("SELECT DISTINCT category FROM diagnosis_master WHERE category != '' ORDER BY category");
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-journal-code me-2"></i>診断マスタ</h4>
            <small class="text-muted">レセプト作成に使用する診断コードの管理</small>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-plus-circle me-2"></i><?= $edit ? '編集' : '新規登録' ?></div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?><input type="hidden" name="action" value="save">
                        <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
                        <div class="mb-2"><label class="form-label required">コード</label>
                            <input type="text" name="diagnosis_code" class="form-control form-control-sm" required value="<?= h($edit['diagnosis_code'] ?? '') ?>" placeholder="D001">
                        </div>
                        <div class="mb-2"><label class="form-label required">診断名</label>
                            <input type="text" name="diagnosis_name" class="form-control form-control-sm" required value="<?= h($edit['diagnosis_name'] ?? '') ?>">
                        </div>
                        <div class="mb-2"><label class="form-label">診療科</label>
                            <input type="text" name="category" class="form-control form-control-sm" value="<?= h($edit['category'] ?? '') ?>" list="catList" placeholder="例: 皮膚科">
                            <datalist id="catList"><?php foreach($categories as $c): ?><option value="<?= h($c['category']) ?>"><?php endforeach; ?></datalist>
                        </div>
                        <div class="mb-2"><label class="form-label">対象動物種</label>
                            <input type="text" name="species_applicable" class="form-control form-control-sm" value="<?= h($edit['species_applicable'] ?? '') ?>" placeholder="dog,cat">
                        </div>
                        <div class="mb-2"><label class="form-label">備考</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2"><?= h($edit['notes'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i><?= $edit ? '更新' : '登録' ?></button>
                        <?php if ($edit): ?><a href="?page=diagnosis_master" class="btn btn-outline-secondary btn-sm">キャンセル</a><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <span><i class="bi bi-list me-2"></i>診断コード一覧（<?= count($diagnoses) ?>件）</span>
                    <form method="GET" class="d-flex gap-2">
                        <input type="hidden" name="page" value="diagnosis_master">
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="検索..." value="<?= h($search) ?>" style="width:200px">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                    </form>
                </div>
                <div class="card-body p-0"><div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>コード</th><th>診断名</th><th>診療科</th><th>対象種</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($diagnoses as $d): ?>
                        <tr>
                            <td><code><?= h($d['diagnosis_code']) ?></code></td>
                            <td><strong><?= h($d['diagnosis_name']) ?></strong></td>
                            <td><span class="badge bg-light text-dark border"><?= h($d['category'] ?: '-') ?></span></td>
                            <td><small class="text-muted"><?= h($d['species_applicable'] ?: '全種') ?></small></td>
                            <td><a href="?page=diagnosis_master&edit=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div></div>
            </div>
        </div>
    </div>
</div>
