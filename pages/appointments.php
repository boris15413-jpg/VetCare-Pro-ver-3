<?php
/** 予約管理・受付 */
$date = $_GET['date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $newStatus = $_POST['status'];
        
        $db->update('appointments', [
            'status' => $newStatus, 
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id=?', [(int)$_POST['id']]);
        
        if ($newStatus === 'paid') {
             $appt = $db->fetch("SELECT patient_id FROM appointments WHERE id=?", [(int)$_POST['id']]);
             redirect("?page=invoice_form&patient_id=" . $appt['patient_id']);
        }
        
        if ($newStatus === 'admitted') {
             $appt = $db->fetch("SELECT patient_id FROM appointments WHERE id=?", [(int)$_POST['id']]);
             redirect("?page=admission_form&patient_id=" . $appt['patient_id']);
        }
        
        redirect("?page=appointments&date={$date}");
    }
    
    if (isset($_POST['walk_in'])) {
        $patId = $_POST['patient_id'] ?: null;
        $staffId = $_POST['staff_id'] ?: null;
        $ownerId = null;
        if ($patId) {
            $pat = $db->fetch("SELECT owner_id FROM patients WHERE id=?", [$patId]);
            $ownerId = $pat['owner_id'] ?? null;
        }

        $db->insert('appointments', [
            'patient_id' => $patId,
            'owner_id' => $ownerId,
            'staff_id' => $staffId,
            'appointment_date' => $date,
            'appointment_time' => date('H:i'), 
            'duration' => 30,
            'appointment_type' => 'walk_in', 
            'status' => 'checked_in', 
            'reason' => '直接来院',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        redirect("?page=appointments&date={$date}");
    }
}

// 予約リスト取得
$appts = $db->fetchAll("SELECT ap.*, p.name as pname, p.patient_code, p.species, o.name as oname, s.name as sname
    FROM appointments ap 
    LEFT JOIN patients p ON ap.patient_id=p.id 
    LEFT JOIN owners o ON ap.owner_id=o.id OR p.owner_id=o.id 
    LEFT JOIN staff s ON ap.staff_id=s.id
    WHERE ap.appointment_date = ? 
    ORDER BY 
    CASE status 
        WHEN 'checked_in' THEN 1 
        WHEN 'in_consult' THEN 2
        WHEN 'scheduled' THEN 3
        ELSE 4 
    END, 
    ap.appointment_time ASC", [$date]);

// マスタデータ
$patients_list = $db->fetchAll("SELECT id, name, patient_code FROM patients WHERE is_active=1 ORDER BY name");
$staff_list = $db->fetchAll("SELECT id, name FROM staff WHERE is_active=1 AND role='veterinarian'");
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="fw-bold mb-0"><i class="bi bi-calendar-check me-2"></i>予約・受付管理</h4>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-warning text-dark btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#walkInModal">
                <i class="bi bi-person-walking me-1"></i>直接来院 (受付)
            </button>
            <a href="?page=appointment_form" class="btn btn-primary btn-sm shadow-sm"><i class="bi bi-plus-lg me-1"></i>予約登録</a>
        </div>
    </div>

    <div class="card mb-3"><div class="card-body py-2">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
            <input type="hidden" name="page" value="appointments">
            <input type="text" name="date" class="form-control form-control-sm datepicker" value="<?= h($date) ?>" style="max-width:150px">
            <button type="submit" class="btn btn-primary btn-sm">移動</button>
            <a href="?page=appointments&date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">今日</a>
            <div class="ms-auto small">
                <span class="badge bg-primary me-1">予約</span>
                <span class="badge bg-success me-1">受付済</span>
                <span class="badge bg-info text-dark me-1">診察中</span>
                <span class="badge bg-secondary me-1">会計済</span>
                <span class="badge bg-warning text-dark">入院</span>
            </div>
        </form>
    </div></div>

    <h5><?= formatDate($date) ?> (<?= ['日','月','火','水','木','金','土'][date('w', strtotime($date))] ?>)</h5>
    
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive"> 
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>時間/種別</th><th>患畜</th><th>飼い主/理由</th><th>担当</th><th>状態</th><th style="width:100px">アクション</th></tr></thead>
                    <tbody>
                    <?php if (empty($appts)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">予約・来院はありません</td></tr>
                    <?php else: foreach ($appts as $ap): ?>
                    <?php 
                        // 行の色分け
                        $rowClass = '';
                        if ($ap['status'] === 'checked_in') $rowClass = 'table-success bg-opacity-10';
                        if ($ap['status'] === 'in_consult') $rowClass = 'table-info bg-opacity-10';
                        if ($ap['status'] === 'paid' || $ap['status'] === 'completed') $rowClass = 'text-muted bg-light';
                        
                        // JSに渡すデータ
                        $jsonData = htmlspecialchars(json_encode([
                            'id' => $ap['id'],
                            'status' => $ap['status'],
                            'pname' => $ap['pname'] ?? '未登録患畜',
                            'patient_id' => $ap['patient_id']
                        ]), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td>
                            <strong class="fs-5"><?= h(substr($ap['appointment_time'],0,5)) ?></strong>
                            <div class="small text-muted">
                                <?php 
                                $types=['general'=>'一般','walk_in'=>'<i class="bi bi-person-walking"></i> 直接','vaccination'=>'予防','surgery'=>'手術']; 
                                echo $types[$ap['appointment_type']] ?? $ap['appointment_type']; 
                                ?>
                            </div>
                        </td>
                        <td>
                            <?php if($ap['pname']): ?>
                                <a href="?page=patient_detail&id=<?= $ap['patient_id'] ?>" class="text-decoration-none fw-bold text-dark">
                                    <?= h($ap['pname']) ?>
                                </a>
                                <div class="small text-muted"><?= h($ap['species']) ?> (<?= h($ap['patient_code']) ?>)</div>
                            <?php else: ?>
                                <span class="text-muted">新規/未登録</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= h($ap['oname'] ?? '-') ?><br>
                            <small class="text-muted"><?= h($ap['reason']) ?></small>
                        </td>
                        <td><?= h($ap['sname'] ?? '-') ?></td>
                        <td>
                            <?php
                            $stBadge = [
                                'scheduled' => ['bg'=>'primary', 'lbl'=>'予約中'],
                                'checked_in' => ['bg'=>'success', 'lbl'=>'受付済'],
                                'in_consult' => ['bg'=>'info text-dark', 'lbl'=>'診察中'],
                                'completed' => ['bg'=>'secondary', 'lbl'=>'完了'],
                                'paid'      => ['bg'=>'secondary', 'lbl'=>'会計済'],
                                'admitted'  => ['bg'=>'warning text-dark', 'lbl'=>'入院'],
                                'cancelled' => ['bg'=>'dark', 'lbl'=>'取消']
                            ];
                            $st = $stBadge[$ap['status']] ?? ['bg'=>'secondary','lbl'=>$ap['status']];
                            ?>
                            <span class="badge bg-<?= $st['bg'] ?>"><?= $st['lbl'] ?></span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" 
                                onclick="openActionModal(<?= $jsonData ?>)">
                                操作
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold" id="actionModalTitle">操作メニュー</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-grid gap-2">
                    <form method="POST" id="formCheckin">
                        <input type="hidden" name="update_status" value="1"><input type="hidden" name="id" class="target-id">
                        <input type="hidden" name="status" value="checked_in">
                        <button class="btn btn-success w-100 fw-bold"><i class="bi bi-check-circle me-2"></i>受付する</button>
                    </form>

                    <form method="POST">
                        <input type="hidden" name="update_status" value="1"><input type="hidden" name="id" class="target-id">
                        <input type="hidden" name="status" value="in_consult">
                        <button class="btn btn-info text-dark w-100"><i class="bi bi-stethoscope me-2"></i>診察中へ</button>
                    </form>

                    <form method="POST">
                        <input type="hidden" name="update_status" value="1"><input type="hidden" name="id" class="target-id">
                        <input type="hidden" name="status" value="paid">
                        <button class="btn btn-secondary w-100"><i class="bi bi-currency-yen me-2"></i>会計済へ移動</button>
                    </form>

                    <form method="POST">
                        <input type="hidden" name="update_status" value="1"><input type="hidden" name="id" class="target-id">
                        <input type="hidden" name="status" value="admitted">
                        <button class="btn btn-warning text-dark w-100"><i class="bi bi-hospital me-2"></i>入院へ移動</button>
                    </form>

                    <hr class="my-2">

                    <a href="#" id="linkChart" class="btn btn-outline-primary w-100"><i class="bi bi-journal-medical me-2"></i>カルテを開く</a>

                    <form method="POST" onsubmit="return confirm('本当にキャンセルしますか？')">
                        <input type="hidden" name="update_status" value="1"><input type="hidden" name="id" class="target-id">
                        <input type="hidden" name="status" value="cancelled">
                        <button class="btn btn-outline-danger w-100 mt-2">キャンセル</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="walkInModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-walking me-2"></i>直接来院受付</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">患畜</label>
                    <select name="patient_id" class="form-select" required>
                        <option value="">-- 選択してください --</option>
                        <?php foreach($patients_list as $pt): ?>
                        <option value="<?= $pt['id'] ?>"><?= h($pt['name']) ?> (<?= h($pt['patient_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">担当医 (任意)</label>
                    <select name="staff_id" class="form-select">
                        <option value="">指名なし</option>
                        <?php foreach($staff_list as $st): ?>
                        <option value="<?= $st['id'] ?>"><?= h($st['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                <button type="submit" name="walk_in" value="1" class="btn btn-primary fw-bold">受付登録</button>
            </div>
        </form>
    </div>
</div>

<script>
function openActionModal(data) {
    const modalEl = document.getElementById('actionModal');
    const modal = new bootstrap.Modal(modalEl);
        document.getElementById('actionModalTitle').textContent = data.pname + ' の操作';
    document.querySelectorAll('.target-id').forEach(el => el.value = data.id);
        const formCheckin = document.getElementById('formCheckin');
    if (data.status === 'scheduled') {
        formCheckin.style.display = 'block';
    } else {
        formCheckin.style.display = 'none';
    }
    
    // カルテリンクの設定
    const linkChart = document.getElementById('linkChart');
    if (data.patient_id) {
        linkChart.href = '?page=medical_record&patient_id=' + data.patient_id;
        linkChart.classList.remove('disabled');
    } else {
        linkChart.href = '#';
        linkChart.classList.add('disabled');
    }
    
    modal.show();
}
</script>