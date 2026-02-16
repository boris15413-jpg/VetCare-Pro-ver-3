<?php
/** 検査結果一覧 */
$patient_id = (int)($_GET['patient_id'] ?? 0);
$where = "1=1";
$params = [];
if ($patient_id) { $where .= " AND lr.patient_id = ?"; $params[] = $patient_id; }

$labs = $db->fetchAll("SELECT lr.*, p.name as pname, p.patient_code FROM lab_results lr JOIN patients p ON lr.patient_id=p.id WHERE {$where} ORDER BY lr.tested_at DESC LIMIT 100", $params);

$grouped = [];
foreach ($labs as $l) {
    $key = $l['pname'] . '_' . date('Y-m-d', strtotime($l['tested_at']));
    $grouped[$key][] = $l;
}
?>
<div class="fade-in">
    <h4 class="fw-bold mb-3"><i class="bi bi-graph-up me-2"></i>検査結果</h4>
    <?php foreach ($grouped as $key => $items): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <span><strong><?= h($items[0]['pname']) ?></strong> <small class="text-muted">(<?= h($items[0]['patient_code']) ?>)</small></span>
            <span><?= formatDate($items[0]['tested_at']) ?></span>
        </div>
        <div class="card-body p-0"><div class="table-responsive">
            <table class="table table-sm mb-0"><thead><tr><th>カテゴリ</th><th>検査項目</th><th>結果</th><th>単位</th><th>基準値</th><th>判定</th></tr></thead><tbody>
            <?php foreach ($items as $l): ?>
            <tr class="<?= $l['is_abnormal'] ? 'table-danger' : '' ?>">
                <td><?= h($l['test_category']) ?></td>
                <td><?= h($l['test_name']) ?></td>
                <td><strong><?= h($l['result_value']) ?></strong></td>
                <td><?= h($l['unit']) ?></td>
                <td><?= h($l['reference_low']) ?> - <?= h($l['reference_high']) ?></td>
                <td><?= $l['is_abnormal'] ? '<span class="badge bg-danger">異常</span>' : '<span class="badge bg-success">正常</span>' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody></table>
        </div></div>
    </div>
    <?php endforeach; ?>
</div>
