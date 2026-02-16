<?php
/** 病理検査 */
$pathList = $db->fetchAll("SELECT pa.*, p.name as pname, p.patient_code FROM pathology pa JOIN patients p ON pa.patient_id=p.id ORDER BY pa.created_at DESC LIMIT 50");
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-microscope me-2"></i>病理検査</h4>
        <a href="?page=pathology_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規登録</a>
    </div>
    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0"><thead><tr><th>病理番号</th><th>患畜</th><th>検体種類</th><th>採取部位</th><th>採取日</th><th>状態</th><th>診断</th></tr></thead><tbody>
        <?php foreach ($pathList as $pa): ?>
        <tr data-href="?page=pathology_form&id=<?= $pa['id'] ?>">
            <td><code><?= h($pa['pathology_number']) ?></code></td>
            <td><strong><?= h($pa['pname']) ?></strong><br><small><?= h($pa['patient_code']) ?></small></td>
            <td><?= h($pa['specimen_type']) ?></td>
            <td><?= h($pa['collection_site']) ?></td>
            <td><?= formatDate($pa['collection_date'],'m/d') ?></td>
            <td><span class="badge bg-<?= $pa['status']==='completed'?'success':($pa['status']==='in_progress'?'info':'warning') ?>"><?= $pa['status']==='completed'?'報告済':($pa['status']==='in_progress'?'検査中':'未検査') ?></span></td>
            <td><?= h(mb_substr($pa['diagnosis'],0,30)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div></div></div>
</div>
