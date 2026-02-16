<?php
/** テンプレート管理 - カルテ・SOAP・処方テンプレート */
if (!$auth->hasRole([ROLE_ADMIN, ROLE_VET])) { redirect('?page=dashboard'); }

$action = $_GET['action'] ?? 'list';
$templateId = (int)($_GET['id'] ?? 0);

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'delete') {
    if (verify_csrf()) {
        $db->delete('clinical_templates', 'id=?', [(int)$_POST['template_id']]);
        setFlash('success', 'テンプレートを削除しました');
    }
    redirect('?page=clinical_templates');
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'save') {
    if (!verify_csrf()) {
        setFlash('danger', 'CSRF検証に失敗しました');
    } else {
        $data = [
            'template_type' => $_POST['template_type'],
            'template_name' => trim($_POST['template_name']),
            'category' => trim($_POST['category'] ?? ''),
            'species' => trim($_POST['species'] ?? ''),
            'content' => json_encode([
                'subjective' => trim($_POST['content_subjective'] ?? ''),
                'objective' => trim($_POST['content_objective'] ?? ''),
                'assessment' => trim($_POST['content_assessment'] ?? ''),
                'plan' => trim($_POST['content_plan'] ?? ''),
                'chief_complaint' => trim($_POST['content_chief_complaint'] ?? ''),
                'notes' => trim($_POST['content_notes'] ?? ''),
            ], JSON_UNESCAPED_UNICODE),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($templateId) {
            $db->update('clinical_templates', $data, 'id=?', [$templateId]);
            setFlash('success', 'テンプレートを更新しました');
        } else {
            $data['created_by'] = $auth->currentUserId();
            $data['created_at'] = date('Y-m-d H:i:s');
            $db->insert('clinical_templates', $data);
            setFlash('success', 'テンプレートを登録しました');
        }
        redirect('?page=clinical_templates');
    }
}

if ($action === 'form' || $action === 'edit') {
    $template = $templateId ? $db->fetch("SELECT * FROM clinical_templates WHERE id=?", [$templateId]) : null;
    $t = $template ?: ['template_type'=>'soap','template_name'=>'','category'=>'','species'=>'','content'=>'{}','is_active'=>1,'sort_order'=>0];
    $content = json_decode($t['content'], true) ?: [];
    ?>
    <div class="fade-in">
        <a href="?page=clinical_templates" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>テンプレート一覧</a>
        <h4 class="fw-bold mt-2 mb-3"><?= $templateId ? 'テンプレート編集' : '新規テンプレート登録' ?></h4>
        
        <form method="POST" class="card">
            <div class="card-body">
                <div class="row g-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_action" value="save">
                    
                    <div class="col-md-4">
                        <label class="form-label required">テンプレート名</label>
                        <input type="text" name="template_name" class="form-control" value="<?= h($t['template_name']) ?>" required placeholder="例: 皮膚炎初診">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">種類</label>
                        <select name="template_type" class="form-select">
                            <option value="soap" <?= $t['template_type']==='soap'?'selected':'' ?>>SOAP記録</option>
                            <option value="prescription" <?= $t['template_type']==='prescription'?'selected':'' ?>>処方セット</option>
                            <option value="procedure" <?= $t['template_type']==='procedure'?'selected':'' ?>>処置セット</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">対象種別</label>
                        <select name="species" class="form-select">
                            <option value="">全種別</option>
                            <?php foreach (SPECIES_LIST as $key => $name): ?>
                            <option value="<?= $key ?>" <?= $t['species']===$key?'selected':'' ?>><?= h($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">表示順</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= $t['sort_order'] ?>" min="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">カテゴリ</label>
                        <input type="text" name="category" class="form-control" value="<?= h($t['category']) ?>" placeholder="例: 皮膚科, 整形, 内科">
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= $t['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isActive">有効</label>
                        </div>
                    </div>
                    
                    <div class="col-12"><hr><h6 class="fw-bold"><i class="bi bi-journal-text me-2"></i>テンプレート内容</h6></div>
                    
                    <div class="col-12">
                        <label class="form-label">主訴</label>
                        <textarea name="content_chief_complaint" class="form-control" rows="2" placeholder="例: 皮膚の発赤・掻痒"><?= h($content['chief_complaint'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">S (主観的情報)</label>
                        <textarea name="content_subjective" class="form-control" rows="3" placeholder="飼い主からの訴え"><?= h($content['subjective'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">O (客観的情報)</label>
                        <textarea name="content_objective" class="form-control" rows="3" placeholder="身体検査所見"><?= h($content['objective'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">A (評価)</label>
                        <textarea name="content_assessment" class="form-control" rows="3" placeholder="診断・評価"><?= h($content['assessment'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">P (計画)</label>
                        <textarea name="content_plan" class="form-control" rows="3" placeholder="治療計画"><?= h($content['plan'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">備考</label>
                        <textarea name="content_notes" class="form-control" rows="2"><?= h($content['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="?page=clinical_templates" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>戻る</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $templateId ? '更新' : '登録' ?></button>
            </div>
        </form>
    </div>
    <?php
    return;
}

// List
$templates = $db->fetchAll("SELECT ct.*, s.name as creator_name FROM clinical_templates ct LEFT JOIN staff s ON ct.created_by=s.id ORDER BY ct.sort_order, ct.template_name");
$categories = array_unique(array_filter(array_column($templates, 'category')));
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-ruled me-2"></i>テンプレート管理</h4>
            <small class="text-muted">カルテ・SOAP記録のテンプレートを登録・管理します</small>
        </div>
        <a href="?page=clinical_templates&action=form" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>新規テンプレート</a>
    </div>

    <?php if (empty($templates)): ?>
    <div class="card">
        <div class="empty-state">
            <i class="bi bi-file-earmark-ruled d-block"></i>
            <h5>テンプレートがありません</h5>
            <p>「新規テンプレート」から登録してください。<br>よく使うSOAP記録や処方セットを登録すると、カルテ作成が効率化します。</p>
            <a href="?page=clinical_templates&action=form" class="btn btn-primary mt-2"><i class="bi bi-plus-lg me-1"></i>テンプレートを作成</a>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>テンプレート名</th>
                            <th>種類</th>
                            <th>カテゴリ</th>
                            <th>対象種別</th>
                            <th>状態</th>
                            <th>作成者</th>
                            <th style="width:120px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($templates as $tpl): ?>
                    <tr>
                        <td class="fw-bold"><?= h($tpl['template_name']) ?></td>
                        <td>
                            <?php
                            $typeLabel = match($tpl['template_type']) { 'soap'=>'SOAP記録','prescription'=>'処方セット','procedure'=>'処置セット',default=>'その他' };
                            $typeColor = match($tpl['template_type']) { 'soap'=>'primary','prescription'=>'success','procedure'=>'warning',default=>'secondary' };
                            ?>
                            <span class="badge bg-<?= $typeColor ?>"><?= $typeLabel ?></span>
                        </td>
                        <td><?= h($tpl['category'] ?: '-') ?></td>
                        <td><?= $tpl['species'] ? h(getSpeciesName($tpl['species'])) : '全種別' ?></td>
                        <td>
                            <?php if ($tpl['is_active']): ?>
                            <span class="badge bg-success">有効</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">無効</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= h($tpl['creator_name'] ?? '-') ?></small></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="?page=clinical_templates&action=edit&id=<?= $tpl['id'] ?>" class="btn btn-outline-primary btn-sm" title="編集"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline no-navigate">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_action" value="delete">
                                    <input type="hidden" name="template_id" value="<?= $tpl['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="削除" onclick="return confirm('このテンプレートを削除しますか？')"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
