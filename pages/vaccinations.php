<?php
/** ワクチン記録 */
$vacs = $db->fetchAll("SELECT v.*, p.name as pname, p.patient_code, p.species, s.name as vet_name FROM vaccinations v JOIN patients p ON v.patient_id=p.id LEFT JOIN staff s ON v.administered_by=s.id ORDER BY v.administered_date DESC LIMIT 50");
?>
<div class="fade-in">
    <h4 class="fw-bold mb-3"><i class="bi bi-shield-plus me-2"></i>ワクチン接種記録</h4>
    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0"><thead><tr><th>接種日</th><th>患畜</th><th>ワクチン名</th><th class="d-none d-md-table-cell">ロット番号</th><th>次回予定</th><th class="d-none d-md-table-cell">接種者</th></tr></thead><tbody>
        <?php foreach ($vacs as $v): ?>
        <tr>
            <td><?= formatDate($v['administered_date'],'m/d') ?></td>
            <td><strong><?= h($v['pname']) ?></strong> <small class="text-muted">(<?= h(getSpeciesName($v['species'])) ?>)</small></td>
            <td><?= h($v['vaccine_name']) ?><br><small class="text-muted"><?= h($v['vaccine_type']) ?></small></td>
            <td class="d-none d-md-table-cell"><?= h($v['lot_number']) ?></td>
            <td><?php
                if ($v['next_due_date']) {
                    $due = strtotime($v['next_due_date']);
                    $overdue = $due < time();
                    echo '<span class="'.($overdue?'text-danger fw-bold':'').'">'.formatDate($v['next_due_date'],'Y/m/d').'</span>';
                    if ($overdue) echo ' <span class="badge bg-danger">期限超過</span>';
                } else echo '-';
            ?></td>
            <td class="d-none d-md-table-cell"><?= h($v['vet_name'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div></div></div>
</div>
