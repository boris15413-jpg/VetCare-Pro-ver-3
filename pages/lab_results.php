<?php
/** 検査結果一覧 - 印刷対応・患畜ページリンク強化 */
$patient_id = (int)($_GET['patient_id'] ?? 0);
$search = $_GET['q'] ?? '';
$where = "1=1";
$params = [];
if ($patient_id) { $where .= " AND lr.patient_id = ?"; $params[] = $patient_id; }
if ($search) { $where .= " AND (p.name LIKE ? OR lr.test_name LIKE ?)"; $s = "%{$search}%"; $params[] = $s; $params[] = $s; }

$labs = $db->fetchAll("SELECT lr.*, p.name as pname, p.patient_code, p.id as pid, p.species, o.name as oname 
    FROM lab_results lr 
    JOIN patients p ON lr.patient_id=p.id 
    LEFT JOIN owners o ON p.owner_id=o.id 
    WHERE {$where} 
    ORDER BY lr.tested_at DESC LIMIT 200", $params);

$patient = $patient_id ? $db->fetch("SELECT p.*, o.name as owner_name FROM patients p JOIN owners o ON p.owner_id=o.id WHERE p.id=?", [$patient_id]) : null;

$grouped = [];
foreach ($labs as $l) {
    $key = $l['patient_id'] . '_' . date('Y-m-d', strtotime($l['tested_at']));
    $grouped[$key][] = $l;
}
$hospitalName = getSetting('hospital_name', APP_NAME);
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <?php if ($patient): ?>
            <a href="?page=patient_detail&id=<?= $patient_id ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i><?= h($patient['name']) ?>の詳細に戻る</a>
            <h4 class="fw-bold mt-1 mb-1"><i class="bi bi-graph-up me-2"></i><?= h($patient['name']) ?>の検査結果</h4>
            <small class="text-muted"><?= h(getSpeciesName($patient['species'])) ?> / <?= h($patient['owner_name']) ?></small>
            <?php else: ?>
            <h4 class="fw-bold mb-1"><i class="bi bi-graph-up me-2"></i>検査結果一覧</h4>
            <small class="text-muted">全患畜の検査結果を表示しています</small>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <a href="?page=lab_import" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-arrow-up me-1"></i>CSV取込</a>
        </div>
    </div>

    <?php if (!$patient_id): ?>
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="d-flex gap-2 align-items-center">
                <input type="hidden" name="page" value="lab_results">
                <div class="search-box flex-fill">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="患畜名・検査項目で検索" value="<?= h($search) ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($grouped)): ?>
    <div class="card">
        <div class="empty-state">
            <i class="bi bi-clipboard2-data d-block"></i>
            <h5>検査結果がありません</h5>
            <p>CSV取込または検査オーダーから検査結果を登録できます</p>
        </div>
    </div>
    <?php endif; ?>

    <?php foreach ($grouped as $key => $items): ?>
    <div class="card mb-3" id="lab-group-<?= md5($key) ?>">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <a href="?page=patient_detail&id=<?= $items[0]['pid'] ?>" class="text-decoration-none fw-bold"><?= h($items[0]['pname']) ?></a>
                <small class="text-muted ms-2">(<?= h($items[0]['patient_code']) ?>)</small>
                <?php if ($items[0]['oname']): ?>
                <small class="text-muted ms-2"><i class="bi bi-person me-1"></i><?= h($items[0]['oname']) ?></small>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark"><i class="bi bi-calendar3 me-1"></i><?= formatDate($items[0]['tested_at']) ?></span>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printLabResult('<?= md5($key) ?>')">
                    <i class="bi bi-printer me-1"></i>印刷
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>カテゴリ</th>
                            <th>検査項目</th>
                            <th class="text-end">結果</th>
                            <th>単位</th>
                            <th>基準値</th>
                            <th class="text-center">判定</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $abnormalCount = 0;
                    foreach ($items as $l): 
                        if ($l['is_abnormal']) $abnormalCount++;
                    ?>
                    <tr class="<?= $l['is_abnormal'] ? 'table-danger' : '' ?>">
                        <td><small><?= h($l['test_category']) ?></small></td>
                        <td class="fw-bold"><?= h($l['test_name']) ?></td>
                        <td class="text-end">
                            <strong class="<?= $l['is_abnormal'] ? 'text-danger' : '' ?>" style="font-size:1rem;">
                                <?= h($l['result_value']) ?>
                            </strong>
                        </td>
                        <td><small class="text-muted"><?= h($l['unit']) ?></small></td>
                        <td>
                            <small class="text-muted">
                                <?php if (!empty($l['reference_low']) || !empty($l['reference_high'])): ?>
                                <?= h($l['reference_low']) ?> - <?= h($l['reference_high']) ?>
                                <?php else: ?>-<?php endif; ?>
                            </small>
                        </td>
                        <td class="text-center">
                            <?php if ($l['is_abnormal']): ?>
                            <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>異常</span>
                            <?php else: ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>正常</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($abnormalCount > 0): ?>
            <div class="px-3 py-2 bg-danger bg-opacity-10 border-top">
                <small class="text-danger fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>異常値: <?= $abnormalCount ?>項目</small>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hidden print content -->
    <div id="print-content-<?= md5($key) ?>" style="display:none;">
        <div style="font-family:'Yu Gothic','Hiragino Sans',serif; font-size:9pt; line-height:1.4; padding:10mm 12mm;">
            <div style="text-align:center; margin-bottom:12px;">
                <h2 style="margin:0; font-size:14pt;"><?= h($hospitalName) ?></h2>
                <h3 style="margin:4px 0 0; font-size:12pt; letter-spacing:0.2em;">検査結果報告書</h3>
            </div>
            <table style="width:100%; margin-bottom:10px; border-collapse:collapse;">
                <tr>
                    <td style="padding:3px 6px; border:1px solid #333; background:#f5f5f5; width:80px; font-weight:bold; font-size:9pt;">患畜名</td>
                    <td style="padding:3px 6px; border:1px solid #333; font-size:9pt;"><?= h($items[0]['pname']) ?> (<?= h($items[0]['patient_code']) ?>)</td>
                    <td style="padding:3px 6px; border:1px solid #333; background:#f5f5f5; width:80px; font-weight:bold; font-size:9pt;">検査日</td>
                    <td style="padding:3px 6px; border:1px solid #333; font-size:9pt;"><?= formatDate($items[0]['tested_at']) ?></td>
                </tr>
                <?php if ($items[0]['oname']): ?>
                <tr>
                    <td style="padding:3px 6px; border:1px solid #333; background:#f5f5f5; font-weight:bold; font-size:9pt;">飼い主名</td>
                    <td colspan="3" style="padding:3px 6px; border:1px solid #333; font-size:9pt;"><?= h($items[0]['oname']) ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#e8e8e8;">
                        <th style="padding:3px 6px; border:1px solid #333; text-align:left; font-size:9pt;">カテゴリ</th>
                        <th style="padding:3px 6px; border:1px solid #333; text-align:left; font-size:9pt;">検査項目</th>
                        <th style="padding:3px 6px; border:1px solid #333; text-align:right; font-size:9pt;">結果</th>
                        <th style="padding:3px 6px; border:1px solid #333; text-align:left; font-size:9pt;">単位</th>
                        <th style="padding:3px 6px; border:1px solid #333; text-align:left; font-size:9pt;">基準値</th>
                        <th style="padding:3px 6px; border:1px solid #333; text-align:center; font-size:9pt;">判定</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $l): ?>
                <tr style="<?= $l['is_abnormal'] ? 'background:#fff0f0;' : '' ?>">
                    <td style="padding:3px 6px; border:1px solid #333; font-size:9pt;"><?= h($l['test_category']) ?></td>
                    <td style="padding:3px 6px; border:1px solid #333; font-weight:bold; font-size:9pt;"><?= h($l['test_name']) ?></td>
                    <td style="padding:3px 6px; border:1px solid #333; text-align:right; font-weight:bold; font-size:9pt; <?= $l['is_abnormal']?'color:#c00;':'' ?>"><?= h($l['result_value']) ?></td>
                    <td style="padding:3px 6px; border:1px solid #333; font-size:9pt;"><?= h($l['unit']) ?></td>
                    <td style="padding:3px 6px; border:1px solid #333; font-size:9pt;">
                        <?php if (!empty($l['reference_low']) || !empty($l['reference_high'])): ?>
                        <?= h($l['reference_low']) ?> - <?= h($l['reference_high']) ?>
                        <?php endif; ?>
                    </td>
                    <td style="padding:3px 6px; border:1px solid #333; text-align:center; font-weight:bold; font-size:9pt; <?= $l['is_abnormal']?'color:#c00;':'' ?>">
                        <?= $l['is_abnormal'] ? '異常' : '正常' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top:12px; text-align:right; font-size:8pt; color:#666;">
                <p style="margin:0;">印刷日: <?= date('Y年m月d日') ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
function printLabResult(groupId) {
    const content = document.getElementById('print-content-' + groupId);
    if (!content) return;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>検査結果報告書</title>
<style>
@page { size: A4; margin: 0; }
body { margin: 0; padding: 0; }
table { page-break-inside: avoid; }
tr { page-break-inside: avoid; }
</style>
</head><body>${content.innerHTML}</body></html>`);
    printWindow.document.close();
    printWindow.onload = function() { printWindow.print(); };
}
</script>
