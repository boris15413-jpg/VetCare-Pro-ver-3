<?php
/** 職種・権限管理 */
$auth->requireRole(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_role'])) {
        $db->delete('staff_roles', 'id = ? AND is_system = 0', [(int)$_POST['delete_role']]);
    } else {
        $key = trim($_POST['role_key']);
        $name = trim($_POST['role_name']);
        $perms = isset($_POST['perms']) ? implode(',', $_POST['perms']) : '';
        
        if ($_POST['id']) {
            $db->update('staff_roles', ['role_name'=>$name, 'permissions'=>$perms], 'id=?', [$_POST['id']]);
        } else {
            $db->insert('staff_roles', ['role_key'=>$key, 'role_name'=>$name, 'permissions'=>$perms]);
        }
    }
    redirect("?page=master_roles");
}

$roles = $db->fetchAll("SELECT * FROM staff_roles ORDER BY id");
$perm_list = ['all'=>'全権限', 'medical'=>'医療行為', 'prescribe'=>'処方', 'nursing'=>'看護', 'appointment'=>'予約', 'accounting'=>'会計', 'lab'=>'検査', 'settings'=>'設定'];
?>
<div class="fade-in">
    <h4 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2"></i>職種・権限管理</h4>
    
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>ID</th><th>職種名</th><th>権限セット</th><th>操作</th></tr></thead>
                        <tbody>
                            <?php foreach ($roles as $r): ?>
                            <tr>
                                <td><code><?= h($r['role_key']) ?></code></td>
                                <td><strong><?= h($r['role_name']) ?></strong></td>
                                <td><small class="text-muted"><?= h($r['permissions']) ?></small></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="editRole(<?= htmlspecialchars(json_encode($r)) ?>)">編集</button>
                                    <?php if(!$r['is_system']): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('削除しますか？')">
                                        <button type="submit" name="delete_role" value="<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger">削除</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <form method="POST" class="card">
                <div class="card-header">職種編集</div>
                <div class="card-body">
                    <input type="hidden" name="id" id="roleId">
                    <div class="mb-3">
                        <label>職種キー (英数)</label>
                        <input type="text" name="role_key" id="roleKey" class="form-control" required pattern="[a-zA-Z0-9_]+" placeholder="ex: assistant">
                    </div>
                    <div class="mb-3">
                        <label>職種名</label>
                        <input type="text" name="role_name" id="roleName" class="form-control" required placeholder="ex: 診療助手">
                    </div>
                    <div class="mb-3">
                        <label>権限</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach($perm_list as $k=>$v): ?>
                            <div class="form-check">
                                <input class="form-check-input role-perm" type="checkbox" name="perms[]" value="<?= $k ?>" id="perm_<?= $k ?>">
                                <label class="form-check-label" for="perm_<?= $k ?>"><?= $v ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">保存</button>
                    <button type="button" class="btn btn-secondary w-100 mt-2" onclick="resetForm()">新規作成モードへ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editRole(r) {
    document.getElementById('roleId').value = r.id;
    document.getElementById('roleKey').value = r.role_key;
    if(r.is_system) document.getElementById('roleKey').setAttribute('readonly', true);
    else document.getElementById('roleKey').removeAttribute('readonly');
    
    document.getElementById('roleName').value = r.role_name;
    
    // 権限チェックボックス
    document.querySelectorAll('.role-perm').forEach(el => el.checked = false);
    if(r.permissions) {
        r.permissions.split(',').forEach(p => {
            const el = document.getElementById('perm_' + p);
            if(el) el.checked = true;
        });
    }
}
function resetForm() {
    document.getElementById('roleId').value = '';
    document.getElementById('roleKey').value = '';
    document.getElementById('roleKey').removeAttribute('readonly');
    document.getElementById('roleName').value = '';
    document.querySelectorAll('.role-perm').forEach(el => el.checked = false);
}
</script>