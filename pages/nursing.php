<?php
/**
 * 看護記録（経過記録）管理画面
 * 左サイドバーに入院患者リスト、右側に選択した患畜の記録を表示する形式に変更
 */

// 1. 入院中の患者リストを取得（サイドバー用）
$inpatients = $db->fetchAll("
    SELECT p.id, p.name, p.patient_code, p.species, a.ward, a.cage_number
    FROM admissions a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.status = 'admitted'
    ORDER BY a.ward ASC, a.cage_number ASC
");

// 2. 選択された患者IDを取得
$current_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;
$records = [];
$selected_patient = null;

if ($current_patient_id) {
    // 患者情報を取得
    $selected_patient = $db->fetch("SELECT * FROM patients WHERE id = ?", [$current_patient_id]);
    
    // その患者の看護記録を取得（新しい順）
    $records = $db->fetchAll("
        SELECT nr.*, s.name as nurse_name 
        FROM nursing_records nr 
        JOIN staff s ON nr.nurse_id = s.id 
        WHERE nr.patient_id = ? 
        ORDER BY nr.created_at DESC
    ", [$current_patient_id]);
} elseif (!empty($inpatients)) {
    // 未選択ならリストの最初の人を選択状態にする（または案内を表示）
    // ここでは案内を表示するため何もしない
}
?>

<div class="fade-in h-100">
    <div class="row g-0 h-100">
        <div class="col-md-3 border-end bg-light" style="min-height: 80vh;">
            <div class="p-3 border-bottom bg-white">
                <h5 class="fw-bold mb-0"><i class="bi bi-hospital me-2"></i>入院患者</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($inpatients)): ?>
                    <div class="p-3 text-muted text-center small">現在入院中の患畜はいません</div>
                <?php else: ?>
                    <?php foreach ($inpatients as $pt): ?>
                        <a href="?page=nursing&patient_id=<?= $pt['id'] ?>" 
                           class="list-group-item list-group-item-action py-3 <?= $current_patient_id === $pt['id'] ? 'active' : '' ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 fw-bold">
                                    <?php 
                                    $icons = ['dog'=>'🐕','cat'=>'🐈','rabbit'=>'🐇','hamster'=>'🐹'];
                                    echo $icons[$pt['species']] ?? '🐾';
                                    ?>
                                    <?= h($pt['name']) ?>
                                </h6>
                                <small><?= h($pt['ward']) ?>-<?= h($pt['cage_number']) ?></small>
                            </div>
                            <small class="<?= $current_patient_id === $pt['id'] ? 'text-white-50' : 'text-muted' ?>">
                                <?= h($pt['patient_code']) ?>
                            </small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <a href="?page=patients" class="list-group-item list-group-item-action text-center text-primary bg-light small">
                    <i class="bi bi-search me-1"></i>全ての患畜から探す
                </a>
            </div>
        </div>

        <div class="col-md-9 bg-white">
            <?php if ($selected_patient): ?>
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h4 class="fw-bold mb-1">
                                <?= h($selected_patient['name']) ?> の看護記録
                            </h4>
                            <p class="text-muted mb-0">
                                <span class="me-3">ID: <?= h($selected_patient['patient_code']) ?></span>
                                <span><?= h(getSpeciesName($selected_patient['species'])) ?> / <?= h(getSexName($selected_patient['sex'])) ?></span>
                            </p>
                        </div>
                        <div>
                            <a href="?page=nursing_record_form&patient_id=<?= $current_patient_id ?>" class="btn btn-primary">
                                <i class="bi bi-plus-lg me-1"></i>記録を追加
                            </a>
                            <a href="?page=patient_detail&id=<?= $current_patient_id ?>" class="btn btn-outline-secondary ms-2">
                                詳細画面へ
                            </a>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 180px;">日時</th>
                                            <th style="width: 100px;">種別</th>
                                            <th>記録内容</th>
                                            <th style="width: 120px;">担当</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($records)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-5 text-muted">
                                                    記録はまだありません
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($records as $rec): ?>
                                                <tr class="<?= $rec['priority'] === 'high' ? 'table-warning' : '' ?>">
                                                    <td class="align-middle">
                                                        <strong><?= date('Y/m/d', strtotime($rec['created_at'])) ?></strong><br>
                                                        <span class="text-muted"><?= date('H:i', strtotime($rec['created_at'])) ?></span>
                                                    </td>
                                                    <td class="align-middle">
                                                        <?php
                                                        $badges = [
                                                            'observation' => ['bg'=>'info', 'text'=>'観察'],
                                                            'care' => ['bg'=>'success', 'text'=>'処置・ケア'],
                                                            'report' => ['bg'=>'warning text-dark', 'text'=>'申し送り'],
                                                        ];
                                                        $b = $badges[$rec['record_type']] ?? ['bg'=>'secondary', 'text'=>'その他'];
                                                        ?>
                                                        <span class="badge bg-<?= $b['bg'] ?>"><?= $b['text'] ?></span>
                                                    </td>
                                                    <td class="align-middle py-3">
                                                        <?php if ($rec['priority'] === 'high'): ?>
                                                            <div class="text-danger small fw-bold mb-1">
                                                                <i class="bi bi-exclamation-circle-fill me-1"></i>重要
                                                            </div>
                                                        <?php endif; ?>
                                                        <?= nl2br(h($rec['content'])) ?>
                                                    </td>
                                                    <td class="align-middle text-muted small">
                                                        <?= h($rec['nurse_name']) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                    <div class="text-center">
                        <i class="bi bi-arrow-left-circle display-4 mb-3 d-block"></i>
                        <h5>左のリストから患者を選択してください</h5>
                        <p class="small">入院中の患者の看護記録を表示します。</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>