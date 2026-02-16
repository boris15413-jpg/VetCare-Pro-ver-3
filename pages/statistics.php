<?php
/** 統計・分析 */
$auth->requireRole([ROLE_ADMIN]);

$thisMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('-1 month'));

// 月別統計
$monthlyVisits = $db->fetch("SELECT COUNT(*) as cnt FROM medical_records WHERE visit_date LIKE ?", ["{$thisMonth}%"])['cnt'];
$lastMonthVisits = $db->fetch("SELECT COUNT(*) as cnt FROM medical_records WHERE visit_date LIKE ?", ["{$lastMonth}%"])['cnt'];
$monthlyRevenue = $db->fetch("SELECT COALESCE(SUM(total),0) as total FROM invoices WHERE created_at LIKE ?", ["{$thisMonth}%"])['total'];
$totalPatients = $db->count('patients', 'is_active=1');
$speciesStats = $db->fetchAll("SELECT species, COUNT(*) as cnt FROM patients WHERE is_active=1 GROUP BY species ORDER BY cnt DESC");

// 月別診察数（過去6ヶ月）
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-{$i} months"));
    $cnt = $db->fetch("SELECT COUNT(*) as cnt FROM medical_records WHERE visit_date LIKE ?", ["{$m}%"])['cnt'];
    $rev = $db->fetch("SELECT COALESCE(SUM(total),0) as t FROM invoices WHERE created_at LIKE ?", ["{$m}%"])['t'];
    $monthlyData[] = ['month' => date('Y/m', strtotime("-{$i} months")), 'visits' => $cnt, 'revenue' => $rev];
}

// 診察種別
$visitTypes = $db->fetchAll("SELECT visit_type, COUNT(*) as cnt FROM medical_records GROUP BY visit_type");
?>
<div class="fade-in">
    <h4 class="fw-bold mb-3"><i class="bi bi-bar-chart-line me-2"></i>統計・分析</h4>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card bg-gradient-primary">
                <div class="stat-value"><?= $monthlyVisits ?></div>
                <div class="stat-label">今月の診察数</div>
                <small><?= $lastMonthVisits > 0 ? round(($monthlyVisits / $lastMonthVisits - 1) * 100) : 0 ?>% 前月比</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card bg-gradient-success">
                <div class="stat-value"><?= formatCurrency($monthlyRevenue) ?></div>
                <div class="stat-label">今月の売上</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card bg-gradient-info">
                <div class="stat-value"><?= $totalPatients ?></div>
                <div class="stat-label">登録患畜数</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card bg-gradient-warning">
                <div class="stat-value"><?= $db->count('admissions','status=?',['admitted']) ?></div>
                <div class="stat-label">入院中</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">月別診察数・売上推移</div>
                <div class="card-body"><div class="chart-container"><canvas id="monthlyChart"></canvas></div></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">種別分布</div>
                <div class="card-body"><canvas id="speciesChart" height="250"></canvas></div>
            </div>
            <div class="card">
                <div class="card-header">診察種別</div>
                <div class="card-body p-0">
                    <?php foreach ($visitTypes as $vt): ?>
                    <div class="p-2 border-bottom d-flex justify-content-between">
                        <span><?php $vtn=['outpatient'=>'外来','admission'=>'入院','emergency'=>'救急','follow_up'=>'再診']; echo $vtn[$vt['visit_type']]??$vt['visit_type']; ?></span>
                        <span class="badge bg-primary"><?= $vt['cnt'] ?>件</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mData = <?= json_encode($monthlyData) ?>;
    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar', data: {
            labels: mData.map(d=>d.month),
            datasets: [
                { label: '診察数', data: mData.map(d=>d.visits), backgroundColor: 'rgba(59,130,246,0.7)', yAxisID: 'y' },
                { label: '売上', data: mData.map(d=>d.revenue), type: 'line', borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', yAxisID: 'y1' }
            ]
        }, options: { responsive: true, maintainAspectRatio: false, scales: {
            y: { position: 'left', title: { display: true, text: '診察数' } },
            y1: { position: 'right', title: { display: true, text: '売上' }, grid: { drawOnChartArea: false } }
        }}
    });

    const sData = <?= json_encode($speciesStats) ?>;
    const colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#6366f1'];
    new Chart(document.getElementById('speciesChart'), {
        type: 'doughnut', data: {
            labels: sData.map(s => {
                const names = <?= json_encode(SPECIES_LIST) ?>;
                return names[s.species] || s.species;
            }),
            datasets: [{ data: sData.map(s=>s.cnt), backgroundColor: colors }]
        }, options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
    });
});
</script>
