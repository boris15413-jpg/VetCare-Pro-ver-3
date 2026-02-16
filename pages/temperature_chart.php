<?php
/** 温度板 - 電子化された入院患畜のバイタルサイン記録 */
$admission_id = (int)($_GET['admission_id'] ?? 0);
$view = $_GET['view'] ?? 'chart';

// 入院一覧表示
if ($view === 'list' || !$admission_id) {
    $admissions = $db->fetchAll("
        SELECT a.*, p.name as patient_name, p.patient_code, p.species, p.breed, o.name as owner_name,
               s.name as vet_name,
               (SELECT tc.body_temperature FROM temperature_chart tc WHERE tc.admission_id = a.id ORDER BY tc.recorded_at DESC LIMIT 1) as last_temp,
               (SELECT tc.recorded_at FROM temperature_chart tc WHERE tc.admission_id = a.id ORDER BY tc.recorded_at DESC LIMIT 1) as last_recorded
        FROM admissions a
        JOIN patients p ON a.patient_id = p.id
        JOIN owners o ON p.owner_id = o.id
        JOIN staff s ON a.admitted_by = s.id
        WHERE a.status = 'admitted'
        ORDER BY a.admission_date DESC
    ");
    ?>
    <div class="fade-in">
        <h4 class="fw-bold mb-3"><i class="bi bi-thermometer-half me-2"></i>温度板 - 入院患畜一覧</h4>
        <div class="row g-3">
            <?php foreach ($admissions as $adm): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100" style="cursor:pointer;" onclick="location.href='?page=temperature_chart&admission_id=<?= $adm['id'] ?>'">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <h6 class="fw-bold mb-0"><?= h($adm['patient_name']) ?></h6>
                            <span class="badge bg-secondary"><?= h($adm['ward']) ?> <?= h($adm['cage_number']) ?></span>
                        </div>
                        <small class="text-muted"><?= h($adm['patient_code']) ?> | <?= h(getSpeciesName($adm['species'])) ?> <?= h($adm['breed']) ?></small>
                        <div class="mt-2">
                            <small>入院日: <?= formatDate($adm['admission_date'], 'm/d') ?></small><br>
                            <small>理由: <?= h(mb_substr($adm['reason'], 0, 40)) ?></small>
                        </div>
                        <?php if ($adm['last_temp']): ?>
                        <div class="mt-2 p-2 bg-light rounded text-center">
                            <small class="text-muted">最終体温</small><br>
                            <strong class="fs-5 <?= (float)$adm['last_temp'] > 39.5 ? 'text-danger' : '' ?>"><?= $adm['last_temp'] ?>℃</strong>
                            <small class="text-muted d-block"><?= formatDateTime($adm['last_recorded']) ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-transparent d-flex gap-2">
                        <a href="?page=temperature_chart&admission_id=<?= $adm['id'] ?>" class="btn btn-primary btn-sm flex-grow-1">温度板</a>
                        <a href="?page=temperature_form&admission_id=<?= $adm['id'] ?>" class="btn btn-success btn-sm flex-grow-1">記録追加</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($admissions)): ?>
            <div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5">現在入院中の患畜はいません</div></div></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return;
}

// 温度板表示
$admission = $db->fetch("
    SELECT a.*, p.name as patient_name, p.patient_code, p.species, p.breed, p.weight, p.allergies, p.chronic_conditions,
           o.name as owner_name, s.name as vet_name
    FROM admissions a JOIN patients p ON a.patient_id = p.id JOIN owners o ON p.owner_id = o.id JOIN staff s ON a.admitted_by = s.id
    WHERE a.id = ?
", [$admission_id]);
if (!$admission) redirect('?page=temperature_chart&view=list');

$chartData = $db->fetchAll("
    SELECT tc.*, s.name as nurse_name
    FROM temperature_chart tc
    JOIN staff s ON tc.recorded_by = s.id
    WHERE tc.admission_id = ?
    ORDER BY tc.recorded_at ASC
", [$admission_id]);

// 日付ごとにグループ化
$byDate = [];
foreach ($chartData as $row) {
    $date = date('m/d', strtotime($row['recorded_at']));
    $time = date('H:i', strtotime($row['recorded_at']));
    $byDate[$date][$time] = $row;
}

// グラフ用データ
$graphLabels = [];
$tempData = [];
$hrData = [];
$rrData = [];
$weightData = [];
foreach ($chartData as $row) {
    $label = date('m/d H:i', strtotime($row['recorded_at']));
    $graphLabels[] = $label;
    $tempData[] = $row['body_temperature'];
    $hrData[] = $row['heart_rate'];
    $rrData[] = $row['respiratory_rate'];
    if ($row['body_weight']) $weightData[$label] = $row['body_weight'];
}
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <a href="?page=temperature_chart&view=list" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>入院一覧</a>
            <h4 class="fw-bold mt-1 mb-0">
                <i class="bi bi-thermometer-half me-2"></i>温度板 - <?= h($admission['patient_name']) ?>
                <small class="text-muted"><?= h($admission['patient_code']) ?></small>
            </h4>
        </div>
        <div class="d-flex gap-2">
            <a href="?page=temperature_form&admission_id=<?= $admission_id ?>" class="btn btn-success btn-sm"><i class="bi bi-plus-lg me-1"></i>記録追加</a>
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>印刷</button>
        </div>
    </div>

    <!-- 患畜情報 -->
    <div class="patient-info-card mb-3">
        <div class="row g-2 text-center">
            <div class="col-4 col-md-2"><small class="text-muted d-block">種別</small><strong><?= h(getSpeciesName($admission['species'])) ?> <?= h($admission['breed']) ?></strong></div>
            <div class="col-4 col-md-2"><small class="text-muted d-block">病棟/ケージ</small><strong><?= h($admission['ward']) ?> <?= h($admission['cage_number']) ?></strong></div>
            <div class="col-4 col-md-2"><small class="text-muted d-block">入院日</small><strong><?= formatDate($admission['admission_date'], 'm/d') ?></strong></div>
            <div class="col-6 col-md-3"><small class="text-muted d-block">主治医</small><strong><?= h($admission['vet_name']) ?></strong></div>
            <div class="col-6 col-md-3"><small class="text-muted d-block">飼い主</small><strong><?= h($admission['owner_name']) ?></strong></div>
        </div>
        <div class="mt-2"><small><strong>入院理由:</strong> <?= h($admission['reason']) ?></small></div>
        <?php if ($admission['allergies']): ?>
        <div class="mt-1"><span class="allergy-tag"><i class="bi bi-exclamation-triangle"></i> アレルギー: <?= h($admission['allergies']) ?></span></div>
        <?php endif; ?>
        <?php if ($admission['diet_instructions']): ?>
        <div class="mt-1"><small><strong>食事指示:</strong> <?= h($admission['diet_instructions']) ?></small></div>
        <?php endif; ?>
        <?php if ($admission['special_notes']): ?>
        <div class="mt-1"><small class="text-danger"><strong>注意事項:</strong> <?= h($admission['special_notes']) ?></small></div>
        <?php endif; ?>
    </div>

    <!-- バイタルグラフ -->
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-graph-up me-2"></i>バイタルサイン推移グラフ</div>
        <div class="card-body">
            <div class="chart-container"><canvas id="vitalChart"></canvas></div>
        </div>
    </div>

    <!-- 温度板テーブル -->
    <div class="card">
        <div class="card-header"><i class="bi bi-table me-2"></i>温度板記録</div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height:600px; overflow:auto;">
                <table class="temp-chart-table">
                    <thead>
                        <tr>
                            <th class="time-header" style="min-width:120px;">項目</th>
                            <?php foreach ($byDate as $date => $times): foreach ($times as $time => $row): ?>
                            <th class="time-header"><?= $date ?><br><?= $time ?></th>
                            <?php endforeach; endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 体温 -->
                        <tr class="section-header"><td colspan="<?= count($chartData) + 1 ?>">バイタルサイン</td></tr>
                        <tr><th>体温 (℃)</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <?php $ts = getVitalStatus('temperature', $row['body_temperature'], $admission['species']); ?>
                            <td class="<?= $ts==='high'?'temp-cell-high':($ts==='low'?'temp-cell-low':'') ?>"><?= $row['body_temperature'] ?: '-' ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>心拍数 (/分)</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?= $row['heart_rate'] ?: '-' ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>呼吸数 (/分)</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?= $row['respiratory_rate'] ?: '-' ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>血圧 (mmHg)</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?= $row['blood_pressure_sys'] ? $row['blood_pressure_sys'].'/'.$row['blood_pressure_dia'] : '-' ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>SpO2 (%)</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?= $row['spo2'] ?: '-' ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>体重 (kg)</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?= $row['body_weight'] ?: '-' ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>

                        <!-- 食事・排泄 -->
                        <tr class="section-header"><td colspan="<?= count($chartData) + 1 ?>">食事・飲水・排泄</td></tr>
                        <tr><th>食欲</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?php
                                $fi = ['good'=>'<span class="text-success">良好</span>','moderate'=>'<span class="text-warning">普通</span>','poor'=>'<span class="text-danger">不良</span>','none'=>'<span class="text-danger fw-bold">なし</span>','forced'=>'<span class="text-info">強制</span>'];
                                echo $fi[$row['food_intake']] ?? '-';
                            ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>食事量</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?= h($row['food_amount']) ?: '-' ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>飲水</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?php
                                $wi = ['normal'=>'普通','decreased'=>'<span class="text-warning">減少</span>','excessive'=>'<span class="text-danger">多飲</span>','minimal'=>'<span class="text-danger">わずか</span>'];
                                echo $wi[$row['water_intake']] ?? '-';
                            ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>排尿</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?php
                                $ur = ['normal'=>'正常','decreased'=>'<span class="text-warning">減少</span>','excessive'=>'<span class="text-danger">多尿</span>','minimal'=>'<span class="text-danger">わずか</span>','none'=>'<span class="text-danger fw-bold">無尿</span>'];
                                echo $ur[$row['urine']] ?? '-';
                            ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>排便</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?php
                                $fc = ['normal'=>'正常','soft'=>'軟便','hard'=>'硬便','diarrhea'=>'<span class="text-danger">下痢</span>','bloody'=>'<span class="text-danger fw-bold">血便</span>'];
                                echo $fc[$row['feces']] ?? ($row['feces'] ?: '-');
                            ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>嘔吐</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?= $row['vomiting'] ? '<span class="text-danger">あり</span>' . ($row['vomiting_detail'] ? '<br><small>'.$row['vomiting_detail'].'</small>' : '') : '-' ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>

                        <!-- 状態 -->
                        <tr class="section-header"><td colspan="<?= count($chartData) + 1 ?>">全身状態</td></tr>
                        <tr><th>精神状態</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?php
                                $ms = ['bright'=>'<span class="text-success">活発</span>','alert'=>'正常','quiet'=>'<span class="text-warning">静か</span>','depressed'=>'<span class="text-danger">沈鬱</span>','stupor'=>'<span class="text-danger fw-bold">昏迷</span>'];
                                echo $ms[$row['mental_status']] ?? '-';
                            ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>疼痛スケール</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?php
                                if ($row['pain_level'] !== null && $row['pain_level'] !== '') {
                                    $pl = (int)$row['pain_level'];
                                    echo $pl >= 4 ? '<span class="text-danger fw-bold">'.$pl.'/5</span>' : ($pl >= 2 ? '<span class="text-warning">'.$pl.'/5</span>' : '<span class="text-success">'.$pl.'/5</span>');
                                } else echo '-';
                            ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>粘膜色</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?php
                                $mm = ['pink'=>'ピンク','pale_pink'=>'<span class="text-warning">淡ピンク</span>','pale'=>'<span class="text-danger">蒼白</span>','cyanotic'=>'<span class="text-danger fw-bold">チアノーゼ</span>','icteric'=>'<span class="text-warning">黄疸</span>'];
                                echo $mm[$row['mucous_membrane']] ?? '-';
                            ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>CRT (秒)</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td class="<?= $row['crt'] && (float)$row['crt'] > 2 ? 'temp-cell-high' : '' ?>"><?= $row['crt'] ?: '-' ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>

                        <!-- 輸液・投薬 -->
                        <tr class="section-header"><td colspan="<?= count($chartData) + 1 ?>">輸液・投薬・処置</td></tr>
                        <tr><th>輸液種類</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><small><?= h($row['iv_fluid_type']) ?: '-' ?></small></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>輸液速度</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><?= h($row['iv_fluid_rate']) ?: '-' ?></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>投薬</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><small><?= h($row['medications_given']) ?: '-' ?></small></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>処置</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><small><?= h($row['treatments']) ?: '-' ?></small></td>
                            <?php endforeach; endforeach; ?>
                        </tr>

                        <!-- 看護メモ -->
                        <tr class="section-header"><td colspan="<?= count($chartData) + 1 ?>">看護記録</td></tr>
                        <tr><th>看護メモ</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><small><?= h(mb_substr($row['nursing_notes'], 0, 30)) ?: '-' ?></small></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                        <tr><th>記録者</th>
                            <?php foreach ($byDate as $times): foreach ($times as $row): ?>
                            <td><small><?= h($row['nurse_name']) ?></small></td>
                            <?php endforeach; endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('vitalChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($graphLabels) ?>,
            datasets: [
                { label: '体温 (℃)', data: <?= json_encode($tempData) ?>, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.1)', tension: 0.3, yAxisID: 'y' },
                { label: '心拍数 (/分)', data: <?= json_encode($hrData) ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', tension: 0.3, yAxisID: 'y1' },
                { label: '呼吸数 (/分)', data: <?= json_encode($rrData) ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', tension: 0.3, yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: {
                y: { type: 'linear', position: 'left', title: { display: true, text: '体温 (℃)' }, min: 37, max: 41 },
                y1: { type: 'linear', position: 'right', title: { display: true, text: '心拍/呼吸 (/分)' }, grid: { drawOnChartArea: false } },
                x: { ticks: { maxTicksLimit: 15, maxRotation: 45 } }
            }
        }
    });
});
</script>
