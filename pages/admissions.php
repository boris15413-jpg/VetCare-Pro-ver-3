<?php
/** 入院管理 */
$filter = $_GET['filter'] ?? 'admitted';
$where = $filter === 'all' ? '1=1' : "a.status = '{$filter}'";
$admissions = $db->fetchAll("SELECT a.*, p.name as pname, p.patient_code, p.species, p.breed, o.name as oname, s.name as vname FROM admissions a JOIN patients p ON a.patient_id=p.id JOIN owners o ON p.owner_id=o.id JOIN staff s ON a.admitted_by=s.id WHERE {$where} ORDER BY a.admission_date DESC LIMIT 50");
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-hospital me-2"></i>入院管理</h4>
        <a href="?page=admission_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規入院</a>
    </div>
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $filter==='admitted'?'active':'' ?>" href="?page=admissions&filter=admitted">入院中</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='discharged'?'active':'' ?>" href="?page=admissions&filter=discharged">退院済</a></li>
        <li class="nav-item"><a class="nav-link <?= $filter==='all'?'active':'' ?>" href="?page=admissions&filter=all">全て</a></li>
    </ul>
    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0"><thead><tr><th>患畜</th><th class="d-none d-md-table-cell">種別</th><th>病棟</th><th>入院日</th><th>退院日</th><th>状態</th><th>操作</th></tr></thead><tbody>
        <?php foreach ($admissions as $a): ?>
        <tr><td><strong><?= h($a['pname']) ?></strong><br><small class="text-muted"><?= h($a['patient_code']) ?> | <?= h($a['oname']) ?></small></td>
            <td class="d-none d-md-table-cell"><?= h(getSpeciesName($a['species'])) ?></td>
            <td><span class="badge bg-secondary"><?= h($a['ward']) ?> <?= h($a['cage_number']) ?></span></td>
            <td><?= formatDate($a['admission_date'],'m/d') ?></td>
            <td><?= $a['discharge_date'] ? formatDate($a['discharge_date'],'m/d') : '-' ?></td>
            <td><?= getAdmissionStatusBadge($a['status']) ?></td>
            <td><div class="d-flex gap-1">
                <?php if ($a['status']==='admitted'): ?>
                <a href="?page=temperature_chart&admission_id=<?= $a['id'] ?>" class="btn btn-info btn-sm" title="温度板"><i class="bi bi-thermometer-half"></i></a>
                <a href="?page=admission_form&id=<?= $a['id'] ?>" class="btn btn-warning btn-sm" title="編集"><i class="bi bi-pencil"></i></a>
                <?php endif; ?>
            </div></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div></div></div>
</div>