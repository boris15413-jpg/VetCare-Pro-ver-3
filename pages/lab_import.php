<?php
/**
 * VetCare Pro v2.0 - Lab Results CSV Import
 * Supports: IDEXX, Fuji DRI-CHEM, SPOTCHEM, Generic CSV
 */
require_once __DIR__ . '/../includes/CsvImporter.php';

$importer = new CsvImporter();
$profiles = $importer->getProfiles();
$msg = '';
$error = '';
$importResult = null;

// Get patient list for dropdown
$patients = $db->fetchAll("SELECT p.id, p.patient_code, p.name, p.species, o.name as owner_name 
    FROM patients p JOIN owners o ON p.owner_id = o.id WHERE p.is_active = 1 ORDER BY p.name");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    if (!verify_csrf()) {
        $error = 'CSRF検証に失敗しました';
    } else {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $profileKey = $_POST['profile'] ?? 'generic';
        $category = trim($_POST['category'] ?? 'CSV取込');
        
        if ($patientId <= 0) {
            $error = '患畜を選択してください。';
        } elseif (empty($_FILES['csv_file']['name'])) {
            $error = 'CSVファイルを選択してください。';
        } else {
            $file = $_FILES['csv_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, ['csv', 'txt'])) {
                $error = 'CSVまたはTXTファイルのみアップロード可能です。';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'ファイルサイズは5MB以下にしてください。';
            } else {
                $tmpPath = $file['tmp_name'];
                $importResult = $importer->import($tmpPath, $profileKey, $patientId, null, [
                    'category' => $category,
                    'tested_by' => $auth->currentUserId(),
                    'notes' => 'CSV取込 (' . ($profiles[$profileKey] ?? 'Unknown') . ') - ' . $file['name']
                ]);
                
                if (isset($importResult['success'])) {
                    $msg = $importResult['imported'] . '件の検査結果をインポートしました。';
                    if (!empty($importResult['errors'])) {
                        $msg .= '（' . count($importResult['errors']) . '件のエラーあり）';
                    }
                } else {
                    $error = $importResult['error'] ?? 'インポートに失敗しました。';
                }
            }
        }
    }
}
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-file-earmark-arrow-up me-2"></i>検査データ CSV取込</h4>
            <small class="text-muted">検査機器のCSVデータから検査結果を一括取り込みます</small>
        </div>
        <a href="?page=lab_results" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i>検査結果へ戻る</a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success glass-alert"><i class="bi bi-check-circle me-1"></i><?= h($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger glass-alert"><i class="bi bi-exclamation-triangle me-1"></i><?= h($error) ?></div><?php endif; ?>

    <div class="row g-4">
        <!-- Import Form -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><i class="bi bi-upload me-2"></i>CSVファイル取込</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="import" value="1">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label required">患畜を選択</label>
                                <select name="patient_id" class="form-select" required>
                                    <option value="">-- 患畜を選択 --</option>
                                    <?php foreach ($patients as $pt): ?>
                                    <option value="<?= $pt['id'] ?>" <?= ($_POST['patient_id'] ?? '') == $pt['id'] ? 'selected' : '' ?>>
                                        <?= h($pt['patient_code']) ?> - <?= h($pt['name']) ?> (<?= h(getSpeciesName($pt['species'])) ?>) [<?= h($pt['owner_name']) ?>]
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">検査機器プロファイル</label>
                                <select name="profile" class="form-select" required>
                                    <?php foreach ($profiles as $key => $name): ?>
                                    <option value="<?= h($key) ?>" <?= ($_POST['profile'] ?? '') === $key ? 'selected' : '' ?>><?= h($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">カテゴリ</label>
                                <input type="text" name="category" class="form-control" value="<?= h($_POST['category'] ?? 'CSV取込') ?>" placeholder="例: CBC, 生化学">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required">CSVファイル</label>
                                <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required onchange="previewCSV(this)">
                            </div>
                        </div>

                        <!-- Preview Area -->
                        <div id="csv-preview" class="mt-3" style="display:none; max-height:300px; overflow:auto;"></div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-arrow-up me-2"></i>インポート実行</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Import Results -->
            <?php if ($importResult && isset($importResult['success'])): ?>
            <div class="card">
                <div class="card-header text-success"><i class="bi bi-check-circle me-2"></i>インポート結果</div>
                <div class="card-body">
                    <div class="row g-3 text-center mb-3">
                        <div class="col-4">
                            <div class="p-3 bg-light rounded">
                                <div class="fw-bold fs-4 text-primary"><?= $importResult['total_rows'] ?? 0 ?></div>
                                <small class="text-muted">総行数</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 bg-light rounded">
                                <div class="fw-bold fs-4 text-success"><?= $importResult['imported'] ?></div>
                                <small class="text-muted">取込成功</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-3 bg-light rounded">
                                <div class="fw-bold fs-4 text-danger"><?= count($importResult['errors'] ?? []) ?></div>
                                <small class="text-muted">エラー</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($importResult['errors'])): ?>
                    <div class="alert alert-warning py-2">
                        <strong><i class="bi bi-exclamation-triangle me-1"></i>エラー詳細:</strong>
                        <ul class="mb-0 mt-1">
                            <?php foreach (array_slice($importResult['errors'], 0, 10) as $err): ?>
                            <li><small><?= h($err) ?></small></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Guide -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="bi bi-question-circle me-2"></i>対応機器ガイド</div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="fw-bold"><i class="bi bi-check-circle text-success me-1"></i>IDEXX VetLab</h6>
                        <small class="text-muted">Catalyst One, ProCyte Dx 等<br>CSVエクスポートをそのまま取込可能</small>
                    </div>
                    <div class="mb-3">
                        <h6 class="fw-bold"><i class="bi bi-check-circle text-success me-1"></i>Fuji DRI-CHEM</h6>
                        <small class="text-muted">NX500/NX700, DRI-CHEM 7000<br>Shift-JIS/UTF-8 両対応</small>
                    </div>
                    <div class="mb-3">
                        <h6 class="fw-bold"><i class="bi bi-check-circle text-success me-1"></i>SPOTCHEM</h6>
                        <small class="text-muted">SPOTCHEM EZ, D-Concept<br>標準CSVフォーマット対応</small>
                    </div>
                    <div class="mb-3">
                        <h6 class="fw-bold"><i class="bi bi-check-circle text-success me-1"></i>汎用CSV</h6>
                        <small class="text-muted">1列目:検査名, 2列目:結果, 3列目:単位,<br>4列目:下限, 5列目:上限</small>
                    </div>
                    <hr>
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        ファイルの文字コードは自動検出されます（UTF-8, Shift-JIS, EUC-JP対応）。
                        CSVファイルの1行目はヘッダー行として扱われます。
                    </small>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-download me-2"></i>テンプレート</div>
                <div class="card-body">
                    <p class="text-muted small">汎用フォーマットのテンプレートをダウンロードできます。</p>
                    <a href="#" class="btn btn-outline-primary btn-sm w-100" onclick="downloadTemplate(); return false;">
                        <i class="bi bi-file-earmark-arrow-down me-1"></i>CSVテンプレートDL
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function downloadTemplate() {
    const csv = "検査項目,結果,単位,基準値下限,基準値上限\nWBC,12500,/μL,6000,17000\nRBC,620,万/μL,550,850\nHb,14.2,g/dL,12.0,18.0\nGLU,98,mg/dL,74,143\nBUN,18,mg/dL,7,27\nCRE,1.0,mg/dL,0.5,1.8\nALT,45,U/L,10,125";
    const bom = '\uFEFF';
    const blob = new Blob([bom + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'lab_import_template.csv';
    link.click();
}
</script>
