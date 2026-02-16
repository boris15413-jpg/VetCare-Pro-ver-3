<?php
/** 患畜一覧 */
$search = $_GET['q'] ?? '';
$species_filter = $_GET['species'] ?? '';
$page_num = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;

$where = 'p.is_active = 1';
$params = [];

if ($search) {
    $where .= " AND (p.name LIKE ? OR p.patient_code LIKE ? OR o.name LIKE ? OR p.microchip_id LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($species_filter) {
    $where .= " AND p.species = ?";
    $params[] = $species_filter;
}

$total = $db->fetch("SELECT COUNT(*) as cnt FROM patients p JOIN owners o ON p.owner_id = o.id WHERE {$where}", $params)['cnt'];
$offset = ($page_num - 1) * $perPage;

$patients = $db->fetchAll("
    SELECT p.*, o.name as owner_name, o.phone as owner_phone,
    (SELECT COUNT(*) FROM admissions a WHERE a.patient_id = p.id AND a.status = 'admitted') as is_admitted
    FROM patients p
    JOIN owners o ON p.owner_id = o.id
    WHERE {$where}
    ORDER BY p.updated_at DESC
    LIMIT {$perPage} OFFSET {$offset}
", $params);
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="fw-bold mb-0"><i class="bi bi-clipboard2-pulse me-2"></i>患畜一覧</h4>
        <a href="?page=patient_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規登録</a>
    </div>

    <!-- 検索 -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="page" value="patients">
                <div class="col-12 col-md-5">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" name="q" class="form-control form-control-sm" placeholder="名前・カルテ番号・飼い主名で検索" value="<?= h($search) ?>">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select name="species" class="form-select form-select-sm">
                        <option value="">全種別</option>
                        <?php foreach (SPECIES_LIST as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $species_filter === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>検索</button>
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
                            <th>カルテNo.</th>
                            <th>患畜名</th>
                            <th class="d-none d-md-table-cell">種別</th>
                            <th class="d-none d-md-table-cell">品種</th>
                            <th class="d-none d-lg-table-cell">性別</th>
                            <th class="d-none d-lg-table-cell">年齢</th>
                            <th>飼い主</th>
                            <th>状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $pt): ?>
                        <tr data-href="?page=patient_detail&id=<?= $pt['id'] ?>">
                            <td><code><?= h($pt['patient_code']) ?></code></td>
                            <td>
                                <strong><?= h($pt['name']) ?></strong>
                                <?php if ($pt['allergies']): ?>
                                <i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="アレルギーあり"></i>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-md-table-cell"><?= h(getSpeciesName($pt['species'])) ?></td>
                            <td class="d-none d-md-table-cell"><?= h($pt['breed']) ?></td>
                            <td class="d-none d-lg-table-cell"><?= h(getSexName($pt['sex'])) ?></td>
                            <td class="d-none d-lg-table-cell"><?= calculateAge($pt['birthdate']) ?></td>
                            <td><?= h($pt['owner_name']) ?></td>
                            <td>
                                <?php if ($pt['is_admitted']): ?>
                                    <span class="badge bg-warning text-dark">入院中</span>
                                <?php elseif ($pt['is_deceased']): ?>
                                    <span class="badge bg-dark">死亡</span>
                                <?php else: ?>
                                    <span class="badge bg-success">通院</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-center">
            <small class="text-muted"><?= $total ?>件中 <?= $offset + 1 ?>-<?= min($offset + $perPage, $total) ?>件</small>
            <?= pagination($total, $page_num, $perPage, '?page=patients&q=' . urlencode($search) . '&species=' . urlencode($species_filter)) ?>
        </div>
    </div>
</div>
