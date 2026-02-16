<?php
/** 保険会社マスタ管理 */
$auth->requireRole([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $data = [
            'company_code' => trim($_POST['company_code']),
            'company_name' => trim($_POST['company_name']),
            'company_name_kana' => trim($_POST['company_name_kana'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'fax' => trim($_POST['fax'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'website' => trim($_POST['website'] ?? ''),
            'coverage_rates' => trim($_POST['coverage_rates'] ?? '50,70'),
            'claim_format' => trim($_POST['claim_format'] ?? 'standard'),
            'notes' => trim($_POST['notes'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->update('insurance_master', $data, 'id = ?', [$id]);
            setFlash('success', '保険会社情報を更新しました');
        } else {
            $db->insert('insurance_master', $data);
            setFlash('success', '保険会社を登録しました');
        }
        redirect('?page=insurance_master');
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $current = $db->fetch("SELECT is_active FROM insurance_master WHERE id=?", [$id]);
        $db->update('insurance_master', ['is_active' => $current['is_active'] ? 0 : 1], 'id=?', [$id]);
        setFlash('success', 'ステータスを変更しました');
        redirect('?page=insurance_master');
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $db->fetch("SELECT * FROM insurance_master WHERE id = ?", [(int)$_GET['edit']]);
}

$companies = $db->fetchAll("SELECT * FROM insurance_master ORDER BY is_active DESC, company_name");
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-shield-check me-2"></i>保険会社マスタ</h4>
            <small class="text-muted">ペット保険会社の登録・管理</small>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><i class="bi bi-plus-circle me-2"></i><?= $edit ? '保険会社を編集' : '新規保険会社登録' ?></div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save">
                        <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
                        
                        <div class="row g-3">
                            <div class="col-4">
                                <label class="form-label required">会社コード</label>
                                <input type="text" name="company_code" class="form-control form-control-sm" required value="<?= h($edit['company_code'] ?? '') ?>" placeholder="INS001">
                            </div>
                            <div class="col-8">
                                <label class="form-label required">会社名</label>
                                <input type="text" name="company_name" class="form-control form-control-sm" required value="<?= h($edit['company_name'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">会社名（カナ）</label>
                                <input type="text" name="company_name_kana" class="form-control form-control-sm" value="<?= h($edit['company_name_kana'] ?? '') ?>">
                            </div>
                            <div class="col-4">
                                <label class="form-label">郵便番号</label>
                                <input type="text" name="postal_code" class="form-control form-control-sm" value="<?= h($edit['postal_code'] ?? '') ?>">
                            </div>
                            <div class="col-8">
                                <label class="form-label">電話番号</label>
                                <input type="text" name="phone" class="form-control form-control-sm" value="<?= h($edit['phone'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">住所</label>
                                <input type="text" name="address" class="form-control form-control-sm" value="<?= h($edit['address'] ?? '') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">補償割合（カンマ区切り）</label>
                                <input type="text" name="coverage_rates" class="form-control form-control-sm" value="<?= h($edit['coverage_rates'] ?? '50,70') ?>" placeholder="50,70,100">
                            </div>
                            <div class="col-6">
                                <label class="form-label">請求フォーマット</label>
                                <select name="claim_format" class="form-select form-select-sm">
                                    <option value="standard" <?= ($edit['claim_format'] ?? '') === 'standard' ? 'selected' : '' ?>>標準</option>
                                    <option value="anicom" <?= ($edit['claim_format'] ?? '') === 'anicom' ? 'selected' : '' ?>>アニコム専用</option>
                                    <option value="ipet" <?= ($edit['claim_format'] ?? '') === 'ipet' ? 'selected' : '' ?>>アイペット専用</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">FAX</label>
                                <input type="text" name="fax" class="form-control form-control-sm" value="<?= h($edit['fax'] ?? '') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">メール</label>
                                <input type="email" name="email" class="form-control form-control-sm" value="<?= h($edit['email'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">ウェブサイト</label>
                                <input type="url" name="website" class="form-control form-control-sm" value="<?= h($edit['website'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">備考</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="2"><?= h($edit['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i><?= $edit ? '更新' : '登録' ?></button>
                            <?php if ($edit): ?>
                            <a href="?page=insurance_master" class="btn btn-outline-secondary btn-sm">キャンセル</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="bi bi-list me-2"></i>登録済み保険会社（<?= count($companies) ?>社）</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>コード</th><th>会社名</th><th>補償割合</th><th>電話</th><th>状態</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($companies as $c): ?>
                            <tr>
                                <td><code><?= h($c['company_code']) ?></code></td>
                                <td>
                                    <strong><?= h($c['company_name']) ?></strong>
                                    <?php if ($c['website']): ?><br><small class="text-muted"><?= h($c['website']) ?></small><?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach(explode(',', $c['coverage_rates']) as $r): ?>
                                    <span class="badge bg-info"><?= h(trim($r)) ?>%</span>
                                    <?php endforeach; ?>
                                </td>
                                <td><small><?= h($c['phone']) ?></small></td>
                                <td>
                                    <form method="POST" class="d-inline"><?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="badge <?= $c['is_active'] ? 'bg-success' : 'bg-secondary' ?> border-0" style="cursor:pointer;">
                                            <?= $c['is_active'] ? '有効' : '無効' ?>
                                        </button>
                                    </form>
                                </td>
                                <td><a href="?page=insurance_master&edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
