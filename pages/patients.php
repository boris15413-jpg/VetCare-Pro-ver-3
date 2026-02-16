<?php
/** 患畜一覧 - 高機能ソート・フィルタ対応 v3 */
$search = $_GET['q'] ?? '';
$species_filter = $_GET['species'] ?? '';
$sex_filter = $_GET['sex'] ?? '';
$status_filter = $_GET['status'] ?? '';
$insurance_filter = $_GET['insurance'] ?? '';
$activity_filter = $_GET['activity'] ?? ''; // active / inactive / all
$sort = $_GET['sort'] ?? 'last_visit';
$order = $_GET['order'] ?? 'desc';
$page_num = max(1, (int)($_GET['p'] ?? 1));
$perPage = 25;

// Allowed sort columns
$allowedSorts = [
    'patient_code' => 'p.patient_code',
    'name' => 'p.name',
    'species' => 'p.species',
    'breed' => 'p.breed',
    'sex' => 'p.sex',
    'birthdate' => 'p.birthdate',
    'weight' => 'p.weight',
    'owner_name' => 'o.name',
    'updated_at' => 'p.updated_at',
    'created_at' => 'p.created_at',
    'insurance' => 'p.insurance_company',
    'last_visit' => 'last_visit_date',
];
$sortCol = $allowedSorts[$sort] ?? 'last_visit_date';
$orderDir = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

$where = 'p.is_active = 1';
$params = [];

if ($search) {
    $where .= " AND (p.name LIKE ? OR p.patient_code LIKE ? OR o.name LIKE ? OR p.microchip_id LIKE ? OR p.breed LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}
if ($species_filter) {
    $where .= " AND p.species = ?";
    $params[] = $species_filter;
}
if ($sex_filter) {
    $where .= " AND p.sex = ?";
    $params[] = $sex_filter;
}
if ($status_filter === 'admitted') {
    $where .= " AND EXISTS (SELECT 1 FROM admissions a WHERE a.patient_id = p.id AND a.status = 'admitted')";
} elseif ($status_filter === 'deceased') {
    $where .= " AND p.is_deceased = 1";
} elseif ($status_filter === 'outpatient') {
    $where .= " AND p.is_deceased = 0 AND NOT EXISTS (SELECT 1 FROM admissions a WHERE a.patient_id = p.id AND a.status = 'admitted')";
}
if ($insurance_filter === 'insured') {
    $where .= " AND p.insurance_company != '' AND p.insurance_company IS NOT NULL";
} elseif ($insurance_filter === 'uninsured') {
    $where .= " AND (p.insurance_company = '' OR p.insurance_company IS NULL)";
}

// Activity filter: hide patients with no visits in last X months
if ($activity_filter === 'active') {
    // Visited in last 6 months
    $where .= " AND EXISTS (SELECT 1 FROM medical_records mr WHERE mr.patient_id = p.id AND mr.visit_date >= date('now', '-6 months'))";
} elseif ($activity_filter === 'recent') {
    // Visited in last 1 month
    $where .= " AND EXISTS (SELECT 1 FROM medical_records mr WHERE mr.patient_id = p.id AND mr.visit_date >= date('now', '-1 month'))";
} elseif ($activity_filter === 'inactive') {
    // No visit in last 12 months
    $where .= " AND NOT EXISTS (SELECT 1 FROM medical_records mr WHERE mr.patient_id = p.id AND mr.visit_date >= date('now', '-12 months'))";
}

$total = $db->fetch("
    SELECT COUNT(*) as cnt 
    FROM patients p 
    JOIN owners o ON p.owner_id = o.id 
    WHERE {$where}
", $params)['cnt'];
$offset = ($page_num - 1) * $perPage;

// Null-safe sort for last_visit_date
$nullSort = ($sort === 'last_visit') ? "CASE WHEN last_visit_date IS NULL THEN 1 ELSE 0 END, " : "";

$patients = $db->fetchAll("
    SELECT p.*, o.name as owner_name, o.phone as owner_phone,
    (SELECT COUNT(*) FROM admissions a WHERE a.patient_id = p.id AND a.status = 'admitted') as is_admitted,
    (SELECT MAX(mr.visit_date) FROM medical_records mr WHERE mr.patient_id = p.id) as last_visit_date,
    (SELECT COUNT(*) FROM medical_records mr WHERE mr.patient_id = p.id) as visit_count
    FROM patients p
    JOIN owners o ON p.owner_id = o.id
    WHERE {$where}
    ORDER BY {$nullSort}{$sortCol} {$orderDir}
    LIMIT {$perPage} OFFSET {$offset}
", $params);

// Build base URL for sorting links
$baseUrl = '?page=patients&q=' . urlencode($search) . '&species=' . urlencode($species_filter) . '&sex=' . urlencode($sex_filter) . '&status=' . urlencode($status_filter) . '&insurance=' . urlencode($insurance_filter) . '&activity=' . urlencode($activity_filter);

function sortLink($column, $label, $currentSort, $currentOrder, $baseUrl) {
    $isActive = $currentSort === $column;
    $newOrder = ($isActive && $currentOrder === 'asc') ? 'desc' : 'asc';
    $icon = '';
    if ($isActive) {
        $icon = $currentOrder === 'asc' ? '<i class="bi bi-sort-up ms-1"></i>' : '<i class="bi bi-sort-down ms-1"></i>';
    }
    return '<a href="' . h($baseUrl) . '&sort=' . $column . '&order=' . $newOrder . '" class="text-decoration-none ' . ($isActive ? 'text-primary fw-bold' : 'text-secondary') . '">' . $label . $icon . '</a>';
}

// Count by activity
$totalAll = $db->fetch("SELECT COUNT(*) as c FROM patients WHERE is_active = 1")['c'];
$totalActive6m = $db->fetch("SELECT COUNT(*) as c FROM patients p WHERE p.is_active = 1 AND EXISTS (SELECT 1 FROM medical_records mr WHERE mr.patient_id = p.id AND mr.visit_date >= date('now', '-6 months'))")['c'];
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>患畜一覧</h4>
            <small class="text-muted"><?= $total ?>件表示 / 全<?= $totalAll ?>件登録（直近6ヶ月来院: <?= $totalActive6m ?>件）</small>
        </div>
        <div class="d-flex gap-2">
            <a href="?page=patient_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規登録</a>
        </div>
    </div>

    <!-- 高機能フィルタ -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="page" value="patients">
                <input type="hidden" name="sort" value="<?= h($sort) ?>">
                <input type="hidden" name="order" value="<?= h($order) ?>">
                <div class="col-12 col-md-3">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="名前・カルテNo・飼い主名・品種で検索" value="<?= h($search) ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select name="species" class="form-select form-select-sm">
                        <option value="">全種別</option>
                        <?php foreach (SPECIES_LIST as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $species_filter === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <select name="sex" class="form-select form-select-sm">
                        <option value="">性別</option>
                        <?php foreach (SEX_LIST as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $sex_filter === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">状態</option>
                        <option value="outpatient" <?= $status_filter === 'outpatient' ? 'selected' : '' ?>>通院中</option>
                        <option value="admitted" <?= $status_filter === 'admitted' ? 'selected' : '' ?>>入院中</option>
                        <option value="deceased" <?= $status_filter === 'deceased' ? 'selected' : '' ?>>死亡</option>
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <select name="insurance" class="form-select form-select-sm">
                        <option value="">保険</option>
                        <option value="insured" <?= $insurance_filter === 'insured' ? 'selected' : '' ?>>あり</option>
                        <option value="uninsured" <?= $insurance_filter === 'uninsured' ? 'selected' : '' ?>>なし</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="activity" class="form-select form-select-sm">
                        <option value="">全来院履歴</option>
                        <option value="recent" <?= $activity_filter === 'recent' ? 'selected' : '' ?>>直近1ヶ月来院</option>
                        <option value="active" <?= $activity_filter === 'active' ? 'selected' : '' ?>>直近6ヶ月来院</option>
                        <option value="inactive" <?= $activity_filter === 'inactive' ? 'selected' : '' ?>>1年以上来院なし</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-search me-1"></i>検索</button>
                    <a href="?page=patients" class="btn btn-outline-secondary btn-sm" title="リセット"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th><?= sortLink('patient_code', 'カルテNo.', $sort, $order, $baseUrl) ?></th>
                            <th><?= sortLink('name', '患畜名', $sort, $order, $baseUrl) ?></th>
                            <th class="d-none d-md-table-cell"><?= sortLink('species', '種別', $sort, $order, $baseUrl) ?></th>
                            <th class="d-none d-lg-table-cell"><?= sortLink('breed', '品種', $sort, $order, $baseUrl) ?></th>
                            <th class="d-none d-lg-table-cell"><?= sortLink('sex', '性別', $sort, $order, $baseUrl) ?></th>
                            <th class="d-none d-lg-table-cell"><?= sortLink('birthdate', '年齢', $sort, $order, $baseUrl) ?></th>
                            <th class="d-none d-xl-table-cell"><?= sortLink('weight', '体重', $sort, $order, $baseUrl) ?></th>
                            <th><?= sortLink('owner_name', '飼い主', $sort, $order, $baseUrl) ?></th>
                            <th class="d-none d-lg-table-cell"><?= sortLink('last_visit', '最終来院', $sort, $order, $baseUrl) ?></th>
                            <th class="d-none d-xl-table-cell"><?= sortLink('insurance', '保険', $sort, $order, $baseUrl) ?></th>
                            <th>状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">
                            <i class="bi bi-search fs-3 d-block mb-2 opacity-50"></i>
                            該当する患畜が見つかりません
                        </td></tr>
                        <?php else: ?>
                        <?php foreach ($patients as $pt): ?>
                        <tr data-href="?page=patient_detail&id=<?= $pt['id'] ?>" style="cursor:pointer;">
                            <td><code><?= h($pt['patient_code']) ?></code></td>
                            <td>
                                <strong><?= h($pt['name']) ?></strong>
                                <?php if ($pt['allergies']): ?>
                                <i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="アレルギーあり"></i>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <span class="badge bg-light text-dark border"><?= h(getSpeciesName($pt['species'])) ?></span>
                            </td>
                            <td class="d-none d-lg-table-cell"><?= h($pt['breed']) ?></td>
                            <td class="d-none d-lg-table-cell"><?= h(getSexName($pt['sex'])) ?></td>
                            <td class="d-none d-lg-table-cell"><?= calculateAge($pt['birthdate']) ?></td>
                            <td class="d-none d-xl-table-cell"><?= $pt['weight'] ? $pt['weight'] . 'kg' : '-' ?></td>
                            <td><?= h($pt['owner_name']) ?></td>
                            <td class="d-none d-lg-table-cell">
                                <?php if ($pt['last_visit_date']): ?>
                                    <?php
                                    $daysSince = (int)((time() - strtotime($pt['last_visit_date'])) / 86400);
                                    $visitClass = $daysSince <= 30 ? 'text-success' : ($daysSince <= 180 ? 'text-muted' : 'text-danger');
                                    ?>
                                    <small class="<?= $visitClass ?>">
                                        <?= formatDate($pt['last_visit_date'], 'Y/m/d') ?>
                                        <span class="d-none d-xl-inline">(<?= $pt['visit_count'] ?>回)</span>
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">未来院</small>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-xl-table-cell">
                                <?php if ($pt['insurance_company']): ?>
                                <span class="badge bg-info"><i class="bi bi-shield-check me-1"></i><?= h(mb_substr($pt['insurance_company'], 0, 6)) ?></span>
                                <?php else: ?>
                                <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pt['is_admitted']): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-hospital me-1"></i>入院中</span>
                                <?php elseif ($pt['is_deceased'] ?? false): ?>
                                    <span class="badge bg-dark">死亡</span>
                                <?php else: ?>
                                    <span class="badge bg-success">通院</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted"><?= $total ?>件中 <?= $total > 0 ? min($offset + 1, $total) : 0 ?>-<?= min($offset + $perPage, $total) ?>件表示</small>
            <?= pagination($total, $page_num, $perPage, $baseUrl . '&sort=' . urlencode($sort) . '&order=' . urlencode($order)) ?>
        </div>
    </div>
</div>

<script>
// Click on row to navigate
document.querySelectorAll('tr[data-href]').forEach(row => {
    row.addEventListener('click', function(e) {
        if (e.target.tagName === 'A' || e.target.closest('a')) return;
        location.href = this.dataset.href;
    });
});
</script>
