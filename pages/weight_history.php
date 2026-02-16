<?php
/** 体重記録・推移管理 */
$patientId = (int)($_GET['patient_id'] ?? 0);
$patient = $patientId ? $db->fetch("SELECT p.*, o.name as owner_name FROM patients p JOIN owners o ON p.owner_id = o.id WHERE p.id = ?", [$patientId]) : null;

if (!$patient) {
    // Show patient selector
    $patients = $db->fetchAll("SELECT p.id, p.patient_code, p.name, p.species, p.weight, o.name as owner_name FROM patients p JOIN owners o ON p.owner_id = o.id WHERE p.is_active = 1 ORDER BY p.name");
    ?>
    <div class="fade-in">
        <h4 class="fw-bold mb-3"><i class="bi bi-graph-up-arrow me-2"></i>体重管理 - 患畜選択</h4>
        <div class="card">
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($patients as $pt): ?>
                    <a href="?page=weight_history&patient_id=<?= $pt['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= h($pt['name']) ?></strong>
                            <small class="text-muted ms-2"><?= h($pt['patient_code']) ?> | <?= h(getSpeciesName($pt['species'])) ?> | <?= h($pt['owner_name']) ?></small>
                        </div>
                        <span class="badge bg-primary rounded-pill"><?= $pt['weight'] ? $pt['weight'] . 'kg' : '-' ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return;
}

// Handle weight recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $weight = (float)$_POST['weight'];
    $measured_at = $_POST['measured_at'] ?: date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    
    if ($weight > 0) {
        $db->insert('weight_history', [
            'patient_id' => $patientId,
            'weight' => $weight,
            'measured_at' => $measured_at,
            'measured_by' => $auth->currentUserId(),
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        // Update patient current weight
        $db->update('patients', ['weight' => $weight, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$patientId]);
        setFlash('success', '体重を記録しました: ' . $weight . 'kg');
        redirect('?page=weight_history&patient_id=' . $patientId);
    }
}

$weights = $db->fetchAll("
    SELECT wh.*, s.name as measured_by_name 
    FROM weight_history wh 
    LEFT JOIN staff s ON wh.measured_by = s.id 
    WHERE wh.patient_id = ? 
    ORDER BY wh.measured_at DESC
", [$patientId]);

// Calculate trends
$latestWeight = $patient['weight'];
$previousWeight = count($weights) > 1 ? $weights[1]['weight'] : null;
$weightChange = ($previousWeight && $latestWeight) ? round($latestWeight - $previousWeight, 2) : null;
$weightChangePercent = ($previousWeight && $latestWeight) ? round(($latestWeight - $previousWeight) / $previousWeight * 100, 1) : null;
?>

<div class="fade-in">
    <a href="?page=patient_detail&id=<?= $patientId ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= h($patient['name']) ?>の詳細</a>
    <h4 class="fw-bold mt-1 mb-3">
        <i class="bi bi-graph-up-arrow me-2"></i>体重管理 - <?= h($patient['name']) ?>
        <small class="text-muted"><?= h(getSpeciesName($patient['species'])) ?></small>
    </h4>

    <?php renderFlash(); ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card bg-gradient-primary">
                <div class="stat-value"><?= $latestWeight ? $latestWeight . 'kg' : '-' ?></div>
                <div class="stat-label">現在の体重</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-gradient-<?= $weightChange === null ? 'secondary' : ($weightChange > 0 ? 'warning' : ($weightChange < 0 ? 'info' : 'success')) ?>">
                <div class="stat-value">
                    <?= $weightChange !== null ? ($weightChange > 0 ? '+' : '') . $weightChange . 'kg' : '-' ?>
                </div>
                <div class="stat-label">前回比</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-gradient-info">
                <div class="stat-value"><?= $weightChangePercent !== null ? ($weightChangePercent > 0 ? '+' : '') . $weightChangePercent . '%' : '-' ?></div>
                <div class="stat-label">変化率</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card bg-gradient-success">
                <div class="stat-value"><?= count($weights) ?></div>
                <div class="stat-label">計測回数</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <!-- Weight Chart -->
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-graph-up me-2"></i>体重推移グラフ</div>
                <div class="card-body">
                    <canvas id="weightChart" height="300"></canvas>
                </div>
            </div>

            <!-- Weight History -->
            <div class="card">
                <div class="card-header"><i class="bi bi-table me-2"></i>体重記録一覧</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>測定日</th><th class="text-end">体重</th><th class="text-end">前回比</th><th>計測者</th><th>メモ</th></tr></thead>
                            <tbody>
                            <?php 
                            $prevW = null;
                            $reversed = array_reverse($weights);
                            foreach ($weights as $idx => $w): 
                                $prevIdx = $idx + 1;
                                $change = isset($weights[$prevIdx]) ? round($w['weight'] - $weights[$prevIdx]['weight'], 2) : null;
                            ?>
                            <tr>
                                <td><?= formatDate($w['measured_at']) ?></td>
                                <td class="text-end fw-bold"><?= $w['weight'] ?>kg</td>
                                <td class="text-end">
                                    <?php if ($change !== null): ?>
                                    <span class="text-<?= $change > 0 ? 'danger' : ($change < 0 ? 'info' : 'success') ?>">
                                        <?= ($change > 0 ? '+' : '') . $change ?>kg
                                    </span>
                                    <?php else: ?>
                                    <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($w['measured_by_name'] ?? '-') ?></td>
                                <td><small><?= h($w['notes']) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($weights)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">体重記録がありません</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Record Form -->
            <div class="card" style="position:sticky; top:80px;">
                <div class="card-header fw-bold"><i class="bi bi-plus-circle me-2"></i>体重を記録</div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">測定日</label>
                            <input type="date" name="measured_at" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">体重 (kg) <span class="text-danger">*</span></label>
                            <input type="number" name="weight" class="form-control form-control-lg" step="0.01" min="0.01" required
                                   value="<?= h($latestWeight) ?>" placeholder="例: 5.2">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">メモ</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="ダイエット中、食欲良好 等"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>記録</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const weightData = <?= json_encode(array_reverse(array_map(function($w) {
    return ['date' => $w['measured_at'], 'weight' => (float)$w['weight']];
}, $weights))) ?>;

if (weightData.length > 0) {
    new Chart(document.getElementById('weightChart'), {
        type: 'line',
        data: {
            labels: weightData.map(d => d.date),
            datasets: [{
                label: '体重 (kg)',
                data: weightData.map(d => d.weight),
                borderColor: 'rgb(99, 102, 241)',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 5,
                pointHoverRadius: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: false, title: { display: true, text: 'kg' } },
                x: { title: { display: true, text: '測定日' } }
            }
        }
    });
}
</script>
