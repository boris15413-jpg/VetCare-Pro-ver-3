<?php
/** 温度板記録フォーム */
$admission_id = (int)($_GET['admission_id'] ?? 0);
$admission = $db->fetch("SELECT a.*, p.name as patient_name, p.patient_code, p.species, p.id as patient_id FROM admissions a JOIN patients p ON a.patient_id = p.id WHERE a.id = ?", [$admission_id]);
if (!$admission) redirect('?page=temperature_chart&view=list');

$lastRecord = $db->fetch("SELECT * FROM temperature_chart WHERE admission_id = ? ORDER BY recorded_at DESC LIMIT 1", [$admission_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'admission_id' => $admission_id,
        'patient_id' => $admission['patient_id'],
        'recorded_at' => $_POST['recorded_date'] . ' ' . $_POST['recorded_time'] . ':00',
        'recorded_by' => $auth->currentUserId(),
        'body_temperature' => $_POST['body_temperature'] ?: null,
        'heart_rate' => $_POST['heart_rate'] ?: null,
        'respiratory_rate' => $_POST['respiratory_rate'] ?: null,
        'blood_pressure_sys' => $_POST['blood_pressure_sys'] ?: null,
        'blood_pressure_dia' => $_POST['blood_pressure_dia'] ?: null,
        'spo2' => $_POST['spo2'] ?: null,
        'body_weight' => $_POST['body_weight'] ?: null,
        'food_intake' => $_POST['food_intake'] ?? '',
        'food_amount' => trim($_POST['food_amount'] ?? ''),
        'water_intake' => $_POST['water_intake'] ?? '',
        'urine' => $_POST['urine'] ?? '',
        'urine_amount' => trim($_POST['urine_amount'] ?? ''),
        'feces' => $_POST['feces'] ?? '',
        'feces_consistency' => $_POST['feces_consistency'] ?? '',
        'vomiting' => isset($_POST['vomiting']) ? 1 : 0,
        'vomiting_detail' => trim($_POST['vomiting_detail'] ?? ''),
        'mental_status' => $_POST['mental_status'] ?? '',
        'pain_level' => $_POST['pain_level'] !== '' ? (int)$_POST['pain_level'] : null,
        'mucous_membrane' => $_POST['mucous_membrane'] ?? '',
        'crt' => $_POST['crt'] ?: null,
        'iv_fluid_type' => trim($_POST['iv_fluid_type'] ?? ''),
        'iv_fluid_rate' => trim($_POST['iv_fluid_rate'] ?? ''),
        'iv_fluid_amount' => trim($_POST['iv_fluid_amount'] ?? ''),
        'medications_given' => trim($_POST['medications_given'] ?? ''),
        'treatments' => trim($_POST['treatments'] ?? ''),
        'nursing_notes' => trim($_POST['nursing_notes'] ?? ''),
        'created_at' => date('Y-m-d H:i:s')
    ];
    $db->insert('temperature_chart', $data);
    redirect("?page=temperature_chart&admission_id={$admission_id}");
}
?>

<div class="fade-in">
    <a href="?page=temperature_chart&admission_id=<?= $admission_id ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>温度板に戻る</a>
    <h4 class="fw-bold mt-1 mb-3"><i class="bi bi-thermometer-half me-2"></i>バイタルサイン記録 - <?= h($admission['patient_name']) ?></h4>

    <form method="POST">
        <div class="row g-3">
            <!-- 日時 -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><i class="bi bi-clock me-2"></i>記録日時</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label required">日付</label>
                                <input type="text" name="recorded_date" class="form-control datepicker" value="<?= date('Y-m-d') ?>" required></div>
                            <div class="col-6"><label class="form-label required">時刻</label>
                                <input type="text" name="recorded_time" class="form-control timepicker" value="<?= date('H:i') ?>" required></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- バイタルサイン -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-activity me-2"></i>バイタルサイン</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label">体温 (℃)</label>
                                <input type="number" name="body_temperature" class="form-control" step="0.1" min="35" max="42" placeholder="38.5" oninput="onVitalInput(this,'temperature','<?= $admission['species'] ?>')"></div>
                            <div class="col-6"><label class="form-label">心拍数 (/分)</label>
                                <input type="number" name="heart_rate" class="form-control" oninput="onVitalInput(this,'heart_rate','<?= $admission['species'] ?>')"></div>
                            <div class="col-6"><label class="form-label">呼吸数 (/分)</label>
                                <input type="number" name="respiratory_rate" class="form-control" oninput="onVitalInput(this,'respiratory_rate','<?= $admission['species'] ?>')"></div>
                            <div class="col-6"><label class="form-label">SpO2 (%)</label>
                                <input type="number" name="spo2" class="form-control" min="0" max="100"></div>
                            <div class="col-6"><label class="form-label">血圧(収縮期)</label>
                                <input type="number" name="blood_pressure_sys" class="form-control"></div>
                            <div class="col-6"><label class="form-label">血圧(拡張期)</label>
                                <input type="number" name="blood_pressure_dia" class="form-control"></div>
                            <div class="col-12"><label class="form-label">体重 (kg)</label>
                                <input type="number" name="body_weight" class="form-control" step="0.01" placeholder="<?= $lastRecord['body_weight'] ?? '' ?>"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 食事・排泄 -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-cup-straw me-2"></i>食事・飲水・排泄</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label">食欲</label>
                                <select name="food_intake" class="form-select"><option value="">-</option><option value="good">良好</option><option value="moderate">普通</option><option value="poor">不良</option><option value="none">なし</option><option value="forced">強制給餌</option></select></div>
                            <div class="col-6"><label class="form-label">食事量</label>
                                <input type="text" name="food_amount" class="form-control" placeholder="例: 80g"></div>
                            <div class="col-6"><label class="form-label">飲水</label>
                                <select name="water_intake" class="form-select"><option value="">-</option><option value="normal">普通</option><option value="decreased">減少</option><option value="excessive">多飲</option><option value="minimal">わずか</option></select></div>
                            <div class="col-6"><label class="form-label">排尿</label>
                                <select name="urine" class="form-select"><option value="">-</option><option value="normal">正常</option><option value="decreased">減少</option><option value="excessive">多尿</option><option value="minimal">わずか</option><option value="none">無尿</option></select></div>
                            <div class="col-6"><label class="form-label">排便</label>
                                <select name="feces" class="form-select"><option value="">-</option><option value="normal">正常</option><option value="soft">軟便</option><option value="hard">硬便</option><option value="diarrhea">下痢</option><option value="bloody">血便</option></select></div>
                            <div class="col-6"><div class="form-check mt-4"><input type="checkbox" name="vomiting" class="form-check-input" id="vomiting"><label for="vomiting" class="form-check-label">嘔吐あり</label></div></div>
                            <div class="col-12"><label class="form-label">嘔吐詳細</label>
                                <input type="text" name="vomiting_detail" class="form-control" placeholder="嘔吐物の性状等"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 全身状態 -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-eye me-2"></i>全身状態</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label">精神状態</label>
                                <select name="mental_status" class="form-select"><option value="">-</option><option value="bright">活発</option><option value="alert">正常</option><option value="quiet">静か</option><option value="depressed">沈鬱</option><option value="stupor">昏迷</option></select></div>
                            <div class="col-6"><label class="form-label">疼痛スケール</label>
                                <select name="pain_level" class="form-select"><option value="">-</option><option value="0">0 (なし)</option><option value="1">1 (軽度)</option><option value="2">2 (中等度)</option><option value="3">3 (やや強い)</option><option value="4">4 (強い)</option><option value="5">5 (激痛)</option></select></div>
                            <div class="col-6"><label class="form-label">粘膜色</label>
                                <select name="mucous_membrane" class="form-select"><option value="">-</option><option value="pink">ピンク(正常)</option><option value="pale_pink">淡ピンク</option><option value="pale">蒼白</option><option value="cyanotic">チアノーゼ</option><option value="icteric">黄疸</option></select></div>
                            <div class="col-6"><label class="form-label">CRT (秒)</label>
                                <input type="number" name="crt" class="form-control" step="0.5" min="0" max="10"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 輸液・投薬 -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-droplet me-2"></i>輸液・投薬・処置</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label">輸液種類</label>
                                <input type="text" name="iv_fluid_type" class="form-control" placeholder="例: 生理食塩水"></div>
                            <div class="col-6"><label class="form-label">輸液速度</label>
                                <input type="text" name="iv_fluid_rate" class="form-control" placeholder="例: 20ml/h"></div>
                            <div class="col-6"><label class="form-label">輸液量</label>
                                <input type="text" name="iv_fluid_amount" class="form-control" placeholder="例: 200ml"></div>
                            <div class="col-12"><label class="form-label">投薬内容</label>
                                <textarea name="medications_given" class="form-control" rows="2" placeholder="投薬した薬品名・量"></textarea></div>
                            <div class="col-12"><label class="form-label">処置内容</label>
                                <textarea name="treatments" class="form-control" rows="2" placeholder="実施した処置"></textarea></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 看護メモ -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><i class="bi bi-journal me-2"></i>看護メモ</div>
                    <div class="card-body">
                        <textarea name="nursing_notes" class="form-control" rows="3" placeholder="観察事項、特記事項等"></textarea>
                    </div>
                </div>
            </div>

            <div class="col-12 text-end">
                <a href="?page=temperature_chart&admission_id=<?= $admission_id ?>" class="btn btn-secondary me-2">キャンセル</a>
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-check-lg me-1"></i>記録を保存</button>
            </div>
        </div>
    </form>
</div>
