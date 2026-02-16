<?php
/**
 * VetCare Pro - サンプルデータ投入 (完全版)
 * 初期セットアップおよびデモ用データをデータベースに投入します
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();

echo "サンプルデータ投入開始...\n";

// --- 1. 職種マスタ (v2新機能) ---
// 権限設定を含む職種の初期データを投入
$roles = [
    ['admin', 'システム管理者', 'all', 1],
    ['veterinarian', '獣医師', 'medical,prescribe', 1],
    ['nurse', '動物看護師', 'nursing', 1],
    ['reception', '受付', 'appointment,accounting', 1],
    ['lab_tech', '検査技師', 'lab', 1]
];
foreach ($roles as $r) {
    $db->query("INSERT OR IGNORE INTO staff_roles (role_key, role_name, permissions, is_system) VALUES (?, ?, ?, ?)", $r);
}
echo "✓ 職種マスタ投入完了\n";

// --- 2. スタッフ (アカウント) ---
$staffData = [
    ['admin', password_hash('admin123', PASSWORD_DEFAULT), '院長 山田太郎', 'ヤマダタロウ', 'admin', 'VET-001', 'yamada@vetcare.jp', '090-1111-1111'],
    ['dr_suzuki', password_hash('pass1234', PASSWORD_DEFAULT), '鈴木花子', 'スズキハナコ', 'veterinarian', 'VET-002', 'suzuki@vetcare.jp', '090-2222-2222'],
    ['dr_tanaka', password_hash('pass1234', PASSWORD_DEFAULT), '田中一郎', 'タナカイチロウ', 'veterinarian', 'VET-003', 'tanaka@vetcare.jp', '090-3333-3333'],
    ['ns_sato', password_hash('pass1234', PASSWORD_DEFAULT), '佐藤美咲', 'サトウミサキ', 'nurse', '', 'sato@vetcare.jp', '090-4444-4444'],
    ['ns_ito', password_hash('pass1234', PASSWORD_DEFAULT), '伊藤めぐみ', 'イトウメグミ', 'nurse', '', 'ito@vetcare.jp', '090-5555-5555'],
    ['rc_kato', password_hash('pass1234', PASSWORD_DEFAULT), '加藤由美', 'カトウユミ', 'reception', '', 'kato@vetcare.jp', '090-6666-6666'],
    ['lab_nakamura', password_hash('pass1234', PASSWORD_DEFAULT), '中村健太', 'ナカムラケンタ', 'lab_tech', '', 'nakamura@vetcare.jp', '090-7777-7777'],
];

foreach ($staffData as $s) {
    $db->query("INSERT OR IGNORE INTO staff (login_id, password_hash, name, name_kana, role, license_number, email, phone, is_active, created_at) VALUES (?,?,?,?,?,?,?,?,1,datetime('now','localtime'))", $s);
}
echo "✓ スタッフデータ投入完了\n";

// --- 3. 飼い主 ---
$owners = [
    ['OW-0001', '高橋正明', 'タカハシマサアキ', '150-0001', '東京都渋谷区神宮前1-2-3', '03-1111-2222', '', 'takahashi@email.com', '高橋良子 090-1234-0001', '090-1234-0001'],
    ['OW-0002', '渡辺真理', 'ワタナベマリ', '160-0023', '東京都新宿区西新宿4-5-6', '03-2222-3333', '090-2234-0002', 'watanabe@email.com', '', ''],
    ['OW-0003', '小林大輔', 'コバヤシダイスケ', '170-0005', '東京都豊島区南大塚7-8-9', '03-3333-4444', '', 'kobayashi@email.com', '小林久美子 03-3333-5555', '03-3333-5555'],
    ['OW-0004', '加藤恵美', 'カトウエミ', '180-0004', '東京都武蔵野市吉祥寺本町2-3-1', '0422-11-2233', '090-3234-0004', 'kato-e@email.com', '', ''],
    ['OW-0005', '松本隆', 'マツモトタカシ', '155-0031', '東京都世田谷区北沢5-12-8', '03-5555-6666', '090-4234-0005', 'matsumoto@email.com', '松本千恵 090-4234-0006', '090-4234-0006'],
    ['OW-0006', '井上和子', 'イノウエカズコ', '141-0021', '東京都品川区上大崎3-7-2', '03-6666-7777', '', 'inoue@email.com', '', ''],
    ['OW-0007', '木村健一', 'キムラケンイチ', '166-0003', '東京都杉並区高円寺南4-1-9', '03-7777-8888', '090-5234-0007', 'kimura@email.com', '木村美和 090-5234-0008', '090-5234-0008'],
    ['OW-0008', '林陽子', 'ハヤシヨウコ', '114-0024', '東京都北区西ケ原1-5-3', '03-8888-9999', '090-6234-0008', 'hayashi@email.com', '', ''],
];

foreach ($owners as $o) {
    $db->query("INSERT OR IGNORE INTO owners (owner_code, name, name_kana, postal_code, address, phone, phone2, email, emergency_contact, emergency_phone, is_active, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,1,datetime('now','localtime'))", $o);
}
echo "✓ 飼い主データ投入完了\n";

// --- 4. 患畜 (患者) ---
$patients = [
    ['PT-0001', 1, 'ポチ', 'dog', '柴犬', '赤', 'male_neutered', '2019-05-15', 10.2, 'MC-1234567890', 'DEA1.1+', '', '', 'アニコム', 'A-12345', 70],
    ['PT-0002', 1, 'タマ', 'cat', 'スコティッシュフォールド', 'クリーム', 'female_spayed', '2020-08-20', 3.8, '', 'A型', '', '', '', '', 0],
    ['PT-0003', 2, 'マロン', 'dog', 'トイプードル', 'レッド', 'female_spayed', '2018-03-10', 4.5, 'MC-2345678901', 'DEA1.1-', 'セファレキシン', '僧帽弁閉鎖不全症', 'アイペット', 'I-23456', 50],
    ['PT-0004', 3, 'レオ', 'dog', 'ゴールデンレトリバー', 'ゴールド', 'male', '2017-01-25', 32.5, 'MC-3456789012', 'DEA1.1+', '', '股関節形成不全', 'アニコム', 'A-34567', 70],
    ['PT-0005', 3, 'ミケ', 'cat', '日本猫（三毛）', '三毛', 'female_spayed', '2021-06-01', 4.2, '', 'B型', '', '', '', '', 0],
    ['PT-0006', 4, 'チョコ', 'rabbit', 'ネザーランドドワーフ', 'チョコレート', 'male', '2022-09-15', 1.2, '', '', '', '', '', '', 0],
    ['PT-0007', 5, 'ハナ', 'dog', 'ラブラドールレトリバー', 'チョコ', 'female', '2016-11-20', 28.0, 'MC-4567890123', 'DEA1.1+', '', 'リンパ腫（治療中）', 'アニコム', 'A-45678', 70],
    ['PT-0008', 5, 'ソラ', 'cat', 'ロシアンブルー', 'ブルー', 'male_neutered', '2020-04-10', 5.1, 'MC-5678901234', 'A型', '', '', 'アイペット', 'I-56789', 50],
    ['PT-0009', 6, 'モモ', 'dog', 'チワワ', 'クリーム', 'female_spayed', '2019-12-01', 2.3, '', '', '', '', '', '', 0],
    ['PT-0010', 7, 'ラッキー', 'dog', 'ミニチュアダックスフンド', 'レッド', 'male_neutered', '2015-07-07', 5.8, 'MC-6789012345', 'DEA1.1+', 'ペニシリン系', '椎間板ヘルニア（既往）', 'アニコム', 'A-67890', 70],
    ['PT-0011', 7, 'プリン', 'hamster', 'ゴールデンハムスター', 'キンクマ', 'female', '2025-01-10', 0.15, '', '', '', '', '', '', 0],
    ['PT-0012', 8, 'コタロウ', 'cat', 'アメリカンショートヘア', 'シルバータビー', 'male_neutered', '2018-02-14', 5.5, 'MC-7890123456', 'A型', '', '糖尿病（インスリン管理中）', '', '', 0],
];

foreach ($patients as $p) {
    $db->query("INSERT OR IGNORE INTO patients (patient_code, owner_id, name, species, breed, color, sex, birthdate, weight, microchip_id, blood_type, allergies, chronic_conditions, insurance_company, insurance_number, insurance_rate, is_active, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,datetime('now','localtime'))", $p);
}
echo "✓ 患畜データ投入完了\n";

// --- 5. 入院データ ---
$admissions = [
    // ハナ（リンパ腫治療のため長期入院中 - 2週間）
    [7, 2, '2026-02-01 10:00:00', null, 'admitted', '犬病棟A', 'A-3', 'リンパ腫に対する化学療法（CHOP protocol）第2クール', '低脂肪消化器サポート 1日2回 各120g', '短時間の歩行のみ（10分程度×2回）', '感染予防のため面会制限あり。嘔吐時は制吐剤投与。', '2026-02-18'],
    // コタロウ（糖尿病のインスリン量調整のため入院）
    [12, 1, '2026-02-10 14:00:00', null, 'admitted', '猫病棟B', 'B-2', '糖尿病コントロール不良。インスリン量の再調整が必要。', '糖尿病用処方食 w/d 1日3回 各40g', 'ケージレスト', '血糖曲線モニタリング中。低血糖兆候に注意。'],
    // レオ（退院済み - 手術）
    [4, 3, '2026-01-20 09:00:00', '2026-01-27 15:00:00', 'discharged', '犬病棟A', 'A-1', '左後肢大腿骨骨折 プレート固定術', '消化器サポート → 通常食へ段階的移行', '術後5日目よりリハビリ開始', '経過良好にて退院。2週間後再診。'],
];

foreach ($admissions as $a) {
    $db->query("INSERT INTO admissions (patient_id, admitted_by, admission_date, discharge_date, status, ward, cage_number, reason, diet_instructions, exercise_instructions, special_notes, estimated_discharge, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,datetime('now','localtime'))", $a);
}
echo "✓ 入院データ投入完了\n";

// --- 6. 温度板データ (大量データ) ---
echo "温度板データ投入中（ハナ - リンパ腫治療 14日分）...\n";

$baseDate = '2026-02-01';
$times = ['08:00:00', '14:00:00', '20:00:00'];

// ハナの化学療法中の経過
$hanaVitals = [
    // Day1: 入院初日
    [['38.5',88,22,120,75,98,'28.0','good','120g','normal','normal','200ml','normal','normal',0,'','alert',1,'pink','1.5','','','','','CHOP-Day1: シクロフォスファミド投与','バイタル安定。化学療法開始。食欲あり。'],
     ['38.6',90,24,122,76,97,'','moderate','','normal','normal','150ml','normal','','0','','alert',1,'pink','1.5','','','','','ドキソルビシン静注40分','投与後嘔気なし。元気あり。'],
     ['38.8',92,22,118,74,97,'','good','100g','normal','moderate','100ml','normal','','0','','alert',2,'pink','2.0','生食','20ml/h','','','メトクロプラミド予防投与','夜間安定。軽度の倦怠感。']],
    // Day2
    [['38.9',95,24,125,78,96,'27.8','poor','50g','normal','decreased','80ml','soft','','0','','quiet',3,'pale_pink','2.0','生食+5%Glu','25ml/h','200ml','','制吐剤マロピタント投与','食欲低下。軽度嘔気あり。'],
     ['39.1',98,26,128,80,96,'','poor','30g','normal','decreased','50ml','soft','','1','少量透明液','quiet',3,'pale_pink','2.0','生食+5%Glu','25ml/h','','','マロピタント追加投与','嘔吐1回。水分摂取やや低下。'],
     ['39.0',96,24,126,78,96,'','poor','40g','normal','decreased','60ml','normal','','0','','quiet',2,'pink','2.0','生食+5%Glu','20ml/h','','','','嘔気軽減傾向。ゆっくり休息中。']],
    // Day3
    [['39.2',100,26,130,82,95,'27.6','poor','20g','normal','decreased','40ml','','','0','','depressed',4,'pale_pink','2.5','ソルラクト','30ml/h','300ml','','G-CSF投与開始。セファゾリン開始。','WBC低下あり（好中球減少）。感染予防強化。'],
     ['39.4',105,28,135,85,94,'','none','0g','forced','minimal','30ml','','','1','胆汁様','depressed',4,'pale','2.5','ソルラクト','30ml/h','','','マロピタント+オンダンセトロン','食欲廃絶。嘔吐あり。点滴量増加。'],
     ['39.3',102,26,132,82,95,'','none','0g','forced','minimal','20ml','','','0','','depressed',3,'pale_pink','2.5','ソルラクト','30ml/h','','','','やや改善。安静に休む。']],
    // Day4
    [['39.5',108,28,138,88,94,'27.4','none','0g','forced','minimal','20ml','','','0','','depressed',4,'pale','3.0','ソルラクト+KCl','35ml/h','400ml','','G-CSF継続。アンピシリン追加。','最低値期。厳重監視中。感染兆候なし。'],
     ['39.3',104,26,135,85,95,'','poor','10g','forced','decreased','40ml','soft','','0','','quiet',3,'pale_pink','2.5','ソルラクト+KCl','35ml/h','','','栄養補助スープ経口投与試みる','少量摂取可。'],
     ['39.1',100,24,130,82,95,'','poor','30g','normal','decreased','60ml','soft','','0','','quiet',3,'pale_pink','2.0','ソルラクト','30ml/h','','','','わずかに改善傾向。']],
    // Day5
    [['38.9',96,24,128,80,96,'27.5','moderate','60g','normal','moderate','80ml','normal','','0','','alert',2,'pink','2.0','生食','25ml/h','300ml','','G-CSF継続','食欲回復傾向。WBC上昇開始。'],
     ['38.8',94,22,125,78,96,'','moderate','50g','normal','moderate','100ml','normal','','0','','alert',2,'pink','1.5','生食','25ml/h','','','','元気回復中。尾を振る姿あり。'],
     ['38.7',92,22,122,76,97,'','good','80g','normal','normal','120ml','normal','normal',0,'','alert',1,'pink','1.5','生食','20ml/h','','','抗菌薬を経口に切替','順調に回復。']],
    // Day6
    [['38.6',90,20,120,75,97,'27.6','good','100g','normal','normal','150ml','normal','normal',0,'','alert',1,'pink','1.5','生食','15ml/h','200ml','','G-CSF終了','WBC正常域に回復。'],
     ['38.5',88,20,118,74,98,'','good','110g','normal','normal','180ml','normal','normal',0,'','alert',1,'pink','1.5','','','','','セファゾリン終了','血液検査改善。点滴漸減中。'],
     ['38.5',86,20,118,74,98,'','good','100g','normal','normal','150ml','normal','normal',0,'','alert',1,'pink','1.5','','','','','','良好。短時間歩行開始。']],
    // Day7
    [['38.4',86,20,118,74,98,'27.8','good','120g','normal','normal','200ml','normal','normal',0,'','bright',1,'pink','1.5','','','','','','体重回復傾向。元気良好。'],
     ['38.5',88,20,120,75,98,'','good','120g','normal','normal','200ml','normal','normal',0,'','bright',0,'pink','1.5','','','','','10分間の散歩','散歩楽しそう。食欲旺盛。'],
     ['38.5',86,20,118,74,98,'','good','100g','normal','normal','180ml','normal','normal',0,'','alert',0,'pink','1.5','','','','','','安定。夜間も穏やか。']],
    // Day8
    [['38.5',88,22,120,75,98,'28.0','good','110g','normal','normal','200ml','normal','normal',0,'','alert',1,'pink','1.5','','','','','血液検査実施 → 投与可能判定','好中球数十分。CHOP-Day8開始可。'],
     ['38.6',90,22,122,76,97,'','good','80g','normal','normal','150ml','normal','','0','','alert',1,'pink','1.5','生食','50ml/h','100ml','','ビンクリスチン静注','投与後バイタル安定。'],
     ['38.7',92,24,124,78,97,'','moderate','60g','normal','moderate','100ml','normal','','0','','quiet',2,'pink','2.0','','','','','メトクロプラミド予防投与','軽度の食欲低下。前回より良好。']],
    // Day9
    [['38.8',94,24,125,78,96,'27.9','moderate','70g','normal','moderate','120ml','soft','','0','','quiet',2,'pink','2.0','','','','','','軽度消化器症状のみ。前回より軽い。'],
     ['38.7',92,22,122,76,97,'','moderate','80g','normal','moderate','150ml','normal','','0','','alert',2,'pink','1.5','','','','','','改善傾向。'],
     ['38.6',90,22,120,75,97,'','good','90g','normal','normal','160ml','normal','normal',0,'','alert',1,'pink','1.5','','','','','','回復早い。']],
    // Day10
    [['38.5',88,20,118,74,98,'28.0','good','120g','normal','normal','200ml','normal','normal',0,'','bright',1,'pink','1.5','','','','','','食欲旺盛。元気。'],
     ['38.5',86,20,118,74,98,'','good','120g','normal','normal','200ml','normal','normal',0,'','bright',0,'pink','1.5','','','','','散歩15分','よく歩く。'],
     ['38.4',86,20,116,74,98,'','good','100g','normal','normal','180ml','normal','normal',0,'','alert',0,'pink','1.5','','','','','','安定。']],
    // Day11
    [['38.5',88,20,118,74,98,'28.1','good','120g','normal','normal','200ml','normal','normal',0,'','bright',0,'pink','1.5','','','','','血液検査','WBC安定。経過良好。'],
     ['38.4',86,20,118,74,98,'','good','120g','normal','normal','200ml','normal','normal',0,'','bright',0,'pink','1.5','','','','','','安定。退院検討開始。'],
     ['38.5',86,20,118,74,98,'','good','100g','normal','normal','180ml','normal','normal',0,'','alert',0,'pink','1.5','','','','','','夜間安定。']],
    // Day12
    [['38.5',88,20,120,75,98,'28.2','good','120g','normal','normal','200ml','normal','normal',0,'','bright',0,'pink','1.5','','','','','エコー検査：リンパ節縮小確認','治療効果良好。部分奏効(PR)。'],
     ['38.4',86,20,118,74,98,'','good','120g','normal','normal','200ml','normal','normal',0,'','bright',0,'pink','1.5','','','','','','状態安定。'],
     ['38.5',86,20,118,74,98,'','good','100g','normal','normal','180ml','normal','normal',0,'','alert',0,'pink','1.5','','','','','','退院準備開始。']],
    // Day13
    [['38.4',86,20,118,74,98,'28.3','good','120g','normal','normal','200ml','normal','normal',0,'','bright',0,'pink','1.5','','','','','退院前最終検査','血液検査・画像検査ともに安定。'],
     ['38.5',86,20,118,74,98,'','good','120g','normal','normal','200ml','normal','normal',0,'','bright',0,'pink','1.5','','','','','退院指導（飼い主面談）','自宅管理の説明完了。'],
     ['38.4',86,20,118,74,98,'','good','100g','normal','normal','180ml','normal','normal',0,'','alert',0,'pink','1.5','','','','','','退院準備完了。明日午前退院予定。']],
    // Day14 (退院日)
    [['38.5',88,20,120,75,98,'28.4','good','120g','normal','normal','200ml','normal','normal',0,'','bright',0,'pink','1.5','','','','','退院日朝のバイタル確認','全て正常範囲。退院準備完了。'],
     ['38.5',86,20,118,74,98,'','good','','normal','normal','','normal','normal',0,'','bright',0,'pink','1.5','','','','','退院','退院。2週間後に第3クール予定。'],
     null],
];

for ($day = 0; $day < 14; $day++) {
    $date = date('Y-m-d', strtotime($baseDate . " +{$day} days"));
    for ($t = 0; $t < 3; $t++) {
        $v = $hanaVitals[$day][$t] ?? null;
        if ($v === null) continue;
        
        $recordedAt = $date . ' ' . $times[$t];
        $nurseId = ($t % 2 === 0) ? 4 : 5; // 佐藤看護師 or 伊藤看護師
        
        $db->query("INSERT INTO temperature_chart (admission_id, patient_id, recorded_at, recorded_by, body_temperature, heart_rate, respiratory_rate, blood_pressure_sys, blood_pressure_dia, spo2, body_weight, food_intake, food_amount, water_intake, urine, urine_amount, feces, feces_consistency, vomiting, vomiting_detail, mental_status, pain_level, mucous_membrane, crt, iv_fluid_type, iv_fluid_rate, iv_fluid_amount, medications_given, treatments, nursing_notes, created_at) VALUES (1,7,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,datetime('now','localtime'))", [
            $recordedAt, $nurseId,
            $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6],
            $v[7], $v[8], $v[9], $v[10], $v[11], $v[12], $v[13],
            $v[14], $v[15], $v[16], $v[17], $v[18], $v[19],
            $v[20], $v[21], $v[22], $v[23], $v[24], $v[25]
        ]);
    }
}
echo "✓ ハナの温度板データ(14日分)投入完了\n";

echo "温度板データ投入中（コタロウ - 糖尿病管理 5日分）...\n";

$kBaseDate = '2026-02-10';
$kotaroVitals = [
    [['38.8',180,28,140,90,97,'5.5','good','40g','normal','excessive','300ml','normal','normal',0,'','alert',0,'pink','1.5','','','','インスリン グラルギン 1.0U BID','血糖曲線開始','入院時血糖380mg/dL。多飲多尿顕著。'],
     ['38.9',175,26,138,88,97,'','good','35g','normal','excessive','250ml','normal','','0','','alert',0,'pink','1.5','','','','','血糖測定(2h): 320mg/dL','高血糖持続。インスリン増量検討。'],
     ['38.8',178,26,140,90,97,'','good','40g','normal','moderate','200ml','normal','normal',0,'','alert',0,'pink','1.5','','','','インスリン グラルギン 1.5U','血糖測定: 290mg/dL','インスリン増量。夜間モニタリング継続。']],
    [['38.7',172,26,138,88,97,'5.4','good','40g','normal','moderate','220ml','normal','normal',0,'','alert',0,'pink','1.5','','','','インスリン グラルギン 1.5U BID','血糖: 260mg/dL','やや改善傾向。'],
     ['38.8',175,26,138,88,97,'','good','40g','normal','moderate','200ml','normal','','0','','alert',0,'pink','1.5','','','','','血糖: 230mg/dL','低下傾向。'],
     ['38.7',170,26,136,86,98,'','good','40g','normal','moderate','180ml','normal','normal',0,'','alert',0,'pink','1.5','','','','インスリン グラルギン 2.0U','血糖: 250mg/dL','増量。']],
    [['38.6',168,24,135,85,98,'5.4','good','40g','normal','normal','180ml','normal','normal',0,'','alert',0,'pink','1.5','','','','インスリン グラルギン 2.0U BID','血糖: 200mg/dL','改善。多尿改善傾向。'],
     ['38.7',170,24,136,86,98,'','good','40g','normal','normal','160ml','normal','','0','','alert',0,'pink','1.5','','','','','血糖: 180mg/dL','良好な推移。'],
     ['38.6',168,24,135,85,98,'','good','40g','normal','normal','160ml','normal','normal',0,'','alert',0,'pink','1.5','','','','インスリン グラルギン 2.0U','血糖: 210mg/dL','安定。']],
    [['38.6',165,24,134,84,98,'5.5','good','40g','normal','normal','160ml','normal','normal',0,'','alert',0,'pink','1.5','','','','インスリン グラルギン 2.0U BID','血糖: 190mg/dL','良好。飲水量正常化。'],
     ['38.7',168,24,135,85,98,'','good','40g','normal','normal','150ml','normal','','0','','alert',0,'pink','1.5','','','','','血糖: 160mg/dL','目標範囲に近づく。'],
     ['38.6',165,24,134,84,98,'','good','40g','normal','normal','150ml','normal','normal',0,'','alert',0,'pink','1.5','','','','インスリン グラルギン 2.0U','血糖: 185mg/dL','安定。']],
    [['38.5',162,24,132,82,98,'5.5','good','40g','normal','normal','150ml','normal','normal',0,'','bright',0,'pink','1.5','','','','インスリン グラルギン 2.0U BID','血糖曲線最終日: 170mg/dL','コントロール良好。退院検討。'],
     ['38.6',165,24,134,84,98,'','good','40g','normal','normal','150ml','normal','','0','','bright',0,'pink','1.5','','','','','血糖: 150mg/dL','目標達成。'],
     ['38.5',162,24,132,82,98,'','good','40g','normal','normal','140ml','normal','normal',0,'','bright',0,'pink','1.5','','','','インスリン グラルギン 2.0U','血糖: 175mg/dL','安定。明日退院予定。']],
];

for ($day = 0; $day < 5; $day++) {
    $date = date('Y-m-d', strtotime($kBaseDate . " +{$day} days"));
    for ($t = 0; $t < 3; $t++) {
        $v = $kotaroVitals[$day][$t] ?? null;
        if ($v === null) continue;
        
        $recordedAt = $date . ' ' . $times[$t];
        $nurseId = ($t % 2 === 0) ? 4 : 5;
        
        $db->query("INSERT INTO temperature_chart (admission_id, patient_id, recorded_at, recorded_by, body_temperature, heart_rate, respiratory_rate, blood_pressure_sys, blood_pressure_dia, spo2, body_weight, food_intake, food_amount, water_intake, urine, urine_amount, feces, feces_consistency, vomiting, vomiting_detail, mental_status, pain_level, mucous_membrane, crt, iv_fluid_type, iv_fluid_rate, iv_fluid_amount, medications_given, treatments, nursing_notes, created_at) VALUES (2,12,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,datetime('now','localtime'))", [
            $recordedAt, $nurseId,
            $v[0], $v[1], $v[2], $v[3], $v[4], $v[5], $v[6],
            $v[7], $v[8], $v[9], $v[10], $v[11], $v[12], $v[13],
            $v[14], $v[15], $v[16], $v[17], $v[18], $v[19],
            $v[20], $v[21], $v[22], $v[23], $v[24], $v[25]
        ]);
    }
}
echo "✓ コタロウの温度板データ(5日分)投入完了\n";

// --- 7. 診察記録 ---
$records = [
    [1, 2, '2026-02-10', 'outpatient', '元気がない、食欲低下', '昨日から食欲が落ちている。散歩には行くが途中で座り込む。', '体温38.8℃、心拍数92、呼吸数22、BW10.0kg。腹部触診で軽度の緊張。', '急性胃腸炎疑い', '絶食24時間後に消化器サポート食開始。制吐剤処方。3日後再診。', '', '急性胃腸炎', 10.0, 38.8, 92, 22, null, null, 4],
    [3, 2, '2026-02-05', 'outpatient', '定期検診・心臓チェック', '特に変わった様子はないとのこと。', '体温38.6℃、心拍数110、呼吸数20、BW4.5kg。聴診にて心雑音Grade3/6（僧帽弁領域）', '僧帽弁閉鎖不全症 - 安定', '現投薬継続。3ヶ月後心エコー再検予定。', '', '僧帽弁閉鎖不全症', 4.5, 38.6, 110, 20, 130, 80, 3],
    [7, 1, '2026-02-01', 'admission', 'リンパ腫化学療法第2クール', '2025年12月にリンパ腫（多中心型）と診断。第1クール奏効率良好。', '体温38.5℃、心拍数88、呼吸数22、BW28.0kg。表在リンパ節触知あるも前回より縮小。全身状態良好。', '多中心型リンパ腫 - CHOP protocol第2クール開始', '入院にてCHOP protocol継続。Day1: シクロフォスファミド+ドキソルビシン', '', '多中心型リンパ腫', 28.0, 38.5, 88, 22, 120, 75, 4],
    [12, 1, '2026-02-10', 'admission', '糖尿病コントロール不良', '自宅でのインスリン投与後も多飲多尿が続く。', '体温38.8℃、心拍数180、呼吸数28、BW5.5kg。削痩傾向。被毛粗剛。', '糖尿病 - コントロール不良。入院にて血糖曲線測定・インスリン量再調整', '入院管理にて血糖カーブ作成。インスリン量調整。', '', '糖尿病', 5.5, 38.8, 180, 28, 140, 90, 3],
    [4, 3, '2026-01-20', 'admission', '左後肢跛行・骨折', '昨日公園で走行中に急に鳴いて左後肢を挙上。', '体温38.9℃、心拍数120、呼吸数28、BW32.5kg。左後肢大腿部腫脹・疼痛著明。', '左大腿骨骨折。X線にて遠位1/3の斜骨折を確認。プレート固定術適応。', '本日緊急手術。プレート固定術施行。', '', '左大腿骨骨折', 32.5, 38.9, 120, 28, 140, 85, 5],
];

foreach ($records as $r) {
    $db->query("INSERT INTO medical_records (patient_id, staff_id, visit_date, visit_type, chief_complaint, subjective, objective, assessment, plan, diagnosis_code, diagnosis_name, body_weight, body_temperature, heart_rate, respiratory_rate, blood_pressure_sys, blood_pressure_dia, bcs, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,datetime('now','localtime'))", $r);
}
echo "✓ 診察記録データ投入完了\n";

// --- 8. オーダー ---
$orders = [
    // ポチ
    [1, 1, null, 'prescription', '消化器', 'メトクロプラミド錠 5mg', '1日2回 朝夕食前', 6, '錠', 50, 300, 'BID', '3日間', '経口', 'normal', 'completed', 2, 4],
    [1, 1, null, 'test', '血液検査', 'CBC（血球計数）', '', 1, '回', 3000, 3000, '', '', '', 'normal', 'completed', 2, 7],
    [1, 1, null, 'test', '血液検査', '血液化学検査（基本12項目）', '', 1, '回', 5000, 5000, '', '', '', 'normal', 'completed', 2, 7],
    // マロン
    [3, 2, null, 'prescription', '循環器', 'ピモベンダン 1.25mg', '1日2回 朝夕食前', 60, '錠', 80, 4800, 'BID', '30日間', '経口', 'normal', 'completed', 2, 4],
    [3, 2, null, 'prescription', '循環器', 'ベナゼプリル 2.5mg', '1日1回 朝食前', 30, '錠', 60, 1800, 'SID', '30日間', '経口', 'normal', 'completed', 2, 4],
    [3, 2, null, 'test', '画像検査', '心臓超音波検査', '', 1, '回', 8000, 8000, '', '', '', 'normal', 'completed', 2, 2],
    // ハナ（入院中）
    [7, 3, 1, 'prescription', '抗がん剤', 'シクロフォスファミド 200mg/m2', '単回投与', 1, '回', 15000, 15000, '', 'Day1', '静脈内', 'urgent', 'completed', 1, 2],
    [7, 3, 1, 'prescription', '抗がん剤', 'ドキソルビシン 30mg/m2', '40分かけて静注', 1, '回', 25000, 25000, '', 'Day1', '静脈内', 'urgent', 'completed', 1, 2],
    [7, 3, 1, 'prescription', '支持療法', 'マロピタント 1mg/kg', '1日1回', 5, '回', 800, 4000, 'SID', '5日間', '皮下注射', 'normal', 'completed', 1, 4],
    [7, 3, 1, 'test', '血液検査', 'CBC（経過観察）', '化学療法後モニタリング', 5, '回', 3000, 15000, '', '', '', 'urgent', 'completed', 1, 7],
    [7, 3, 1, 'prescription', '抗がん剤', 'ビンクリスチン 0.7mg/m2', 'Day8 単回静注', 1, '回', 12000, 12000, '', 'Day8', '静脈内', 'urgent', 'completed', 1, 2],
    // コタロウ（入院中）
    [12, 4, 2, 'prescription', '糖尿病', 'インスリン グラルギン', '2.0U BID', 10, 'mL', 3000, 3000, 'BID', '継続', '皮下注射', 'normal', 'in_progress', 1, 4],
    [12, 4, 2, 'test', '血液検査', '血糖値測定（血糖曲線用）', '4時間毎に測定', 15, '回', 500, 7500, '', '', '', 'normal', 'in_progress', 1, 7],
    [12, 4, 2, 'test', '血液検査', 'フルクトサミン', '', 1, '回', 2500, 2500, '', '', '', 'normal', 'completed', 1, 7],
];

foreach ($orders as $o) {
    $db->query("INSERT INTO orders (patient_id, record_id, admission_id, order_type, order_category, order_name, order_detail, quantity, unit, unit_price, total_price, frequency, duration, route, priority, status, ordered_by, executed_by, ordered_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,datetime('now','localtime'))", $o);
}
echo "✓ オーダーデータ投入完了\n";

// --- 9. 看護タスク ---
$tasks = [
    [7, 1, 'バイタルサイン測定', '体温・脈拍・呼吸数・血圧・SpO2', '2026-02-15 08:00:00', null, 4, null, 'pending', 'high', 'tid', 4],
    [7, 1, '点滴管理・刺入部確認', '留置針刺入部の発赤・腫脹確認', '2026-02-15 08:00:00', null, 4, null, 'pending', 'high', 'tid', 4],
    [7, 1, '食事介助', '低脂肪消化器サポート 120g', '2026-02-15 08:30:00', null, 5, null, 'pending', 'normal', 'bid', 4],
    [7, 1, '散歩（短時間）', '10分程度のリード歩行', '2026-02-15 10:00:00', null, 5, null, 'pending', 'normal', 'bid', 4],
    [7, 1, '体重測定', '毎朝体重計測', '2026-02-15 08:00:00', null, 4, null, 'pending', 'normal', 'sid', 4],
    [12, 2, 'バイタルサイン測定', '体温・脈拍・呼吸数', '2026-02-15 08:00:00', null, 5, null, 'pending', 'high', 'tid', 4],
    [12, 2, 'インスリン投与', 'グラルギン 2.0U 皮下注射', '2026-02-15 08:00:00', null, 4, null, 'pending', 'high', 'bid', 4],
    [12, 2, '血糖値測定', '耳介縁より採血', '2026-02-15 08:00:00', null, 5, null, 'pending', 'high', 'q4h', 4],
    [12, 2, '食事管理', 'w/d 40g 3回に分けて給餌', '2026-02-15 08:00:00', null, 5, null, 'pending', 'normal', 'tid', 4],
    [12, 2, '飲水量・尿量モニタリング', '24時間飲水量・排尿回数記録', '2026-02-15 08:00:00', null, 4, null, 'pending', 'normal', 'sid', 4],
];

foreach ($tasks as $tk) {
    $db->query("INSERT INTO nursing_tasks (patient_id, admission_id, task_name, task_detail, scheduled_at, completed_at, assigned_to, completed_by, status, priority, recurrence, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,datetime('now','localtime'))", $tk);
}
echo "✓ 看護タスクデータ投入完了\n";

// --- 10. 検査結果 ---
$labResults = [
    [7, 3, null, 'CBC', 'WBC（白血球数）', '12500', '/μL', '6000', '17000', 0],
    [7, 3, null, 'CBC', 'RBC（赤血球数）', '620', '万/μL', '550', '850', 0],
    [7, 3, null, 'CBC', 'Hb（ヘモグロビン）', '14.2', 'g/dL', '12.0', '18.0', 0],
    [7, 3, null, 'CBC', 'Ht（ヘマトクリット）', '42', '%', '37', '55', 0],
    [7, 3, null, 'CBC', 'PLT（血小板数）', '28.5', '万/μL', '17.5', '50.0', 0],
    [7, 3, null, 'CBC', '好中球', '8200', '/μL', '3000', '12000', 0],
    [7, 3, null, 'CBC', 'リンパ球', '2800', '/μL', '1000', '4800', 0],
    [7, 3, null, '生化学', 'TP（総タンパク）', '6.8', 'g/dL', '5.2', '8.2', 0],
    [7, 3, null, '生化学', 'ALB（アルブミン）', '3.2', 'g/dL', '2.3', '4.0', 0],
    [7, 3, null, '生化学', 'BUN（尿素窒素）', '18', 'mg/dL', '7', '27', 0],
    [7, 3, null, '生化学', 'CRE（クレアチニン）', '1.0', 'mg/dL', '0.5', '1.8', 0],
    [7, 3, null, '生化学', 'ALT（GPT）', '45', 'U/L', '10', '125', 0],
    [7, 3, null, '生化学', 'ALP', '120', 'U/L', '23', '212', 0],
    [7, 3, null, '生化学', 'GLU（血糖値）', '98', 'mg/dL', '74', '143', 0],

    // コタロウの検査結果
    [12, 4, null, '生化学', 'GLU（血糖値）', '380', 'mg/dL', '74', '159', 1],
    [12, 4, null, '生化学', 'フルクトサミン', '520', 'μmol/L', '190', '365', 1],
    [12, 4, null, '生化学', 'BUN', '28', 'mg/dL', '16', '36', 0],
    [12, 4, null, '生化学', 'CRE', '1.6', 'mg/dL', '0.8', '2.4', 0],
    [12, 4, null, '生化学', 'ALT', '68', 'U/L', '12', '130', 0],
    [12, 4, null, '生化学', 'T-Cho（総コレステロール）', '350', 'mg/dL', '110', '320', 1],
];

foreach ($labResults as $lr) {
    $db->query("INSERT INTO lab_results (patient_id, record_id, order_id, test_category, test_name, result_value, unit, reference_low, reference_high, is_abnormal, tested_at) VALUES (?,?,?,?,?,?,?,?,?,?,datetime('now','localtime'))", $lr);
}
echo "✓ 検査結果データ投入完了\n";

// --- 11. ワクチン接種記録 ---
$vaccines = [
    [1, '犬6種混合ワクチン', 'コアワクチン', 'LOT-A12345', 'メーカーA', '2025-05-15', '2026-05-15', 1, '右大腿部皮下', '特になし'],
    [1, '狂犬病ワクチン', '法定ワクチン', 'LOT-R67890', 'メーカーB', '2025-04-01', '2026-04-01', 1, '右肩部皮下', ''],
    [2, '猫3種混合ワクチン', 'コアワクチン', 'LOT-C11111', 'メーカーC', '2025-08-20', '2026-08-20', 1, '右大腿部皮下', ''],
    [3, '犬8種混合ワクチン', 'コアワクチン', 'LOT-A22222', 'メーカーA', '2025-03-10', '2026-03-10', 2, '右大腿部皮下', ''],
    [7, '犬6種混合ワクチン', 'コアワクチン', 'LOT-A33333', 'メーカーA', '2025-11-20', '2026-11-20', 1, '右大腿部皮下', '化学療法開始前に接種'],
];

foreach ($vaccines as $vac) {
    $db->query("INSERT INTO vaccinations (patient_id, vaccine_name, vaccine_type, lot_number, manufacturer, administered_date, next_due_date, administered_by, site, reaction, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,datetime('now','localtime'))", $vac);
}
echo "✓ ワクチン記録データ投入完了\n";

// --- 12. 薬品マスタ ---
$drugs = [
    ['D001', 'メトクロプラミド錠 5mg', 'メトクロプラミド', '消化器', '錠', 50, 500, 50, 'メーカーA'],
    ['D002', 'ピモベンダン錠 1.25mg', 'ピモベンダン', '循環器', '錠', 80, 300, 30, 'メーカーB'],
    ['D003', 'ベナゼプリル錠 2.5mg', 'ベナゼプリル', '循環器', '錠', 60, 200, 30, 'メーカーC'],
    ['D004', 'セファレキシン錠 250mg', 'セファレキシン', '抗菌薬', '錠', 40, 400, 50, 'メーカーA'],
    ['D005', 'マロピタント注 10mg/mL', 'マロピタント', '制吐剤', 'mL', 800, 50, 10, 'メーカーD'],
    ['D006', 'シクロフォスファミド注 500mg', 'シクロフォスファミド', '抗がん剤', 'バイアル', 15000, 10, 5, 'メーカーE'],
    ['D007', 'ドキソルビシン注 50mg', 'ドキソルビシン', '抗がん剤', 'バイアル', 25000, 5, 3, 'メーカーE'],
    ['D008', 'ビンクリスチン注 1mg', 'ビンクリスチン', '抗がん剤', 'バイアル', 12000, 5, 3, 'メーカーE'],
    ['D009', 'インスリン グラルギン 300U/3mL', 'インスリン グラルギン', '糖尿病', 'キット', 3000, 10, 5, 'メーカーF'],
    ['D010', 'メルカゾール錠 5mg', 'チアマゾール', '甲状腺', '錠', 30, 300, 30, 'メーカーG'],
    ['D011', 'プレドニゾロン錠 5mg', 'プレドニゾロン', 'ステロイド', '錠', 20, 500, 50, 'メーカーA'],
    ['D012', 'アモキシシリンカプセル 250mg', 'アモキシシリン', '抗菌薬', 'カプセル', 35, 400, 50, 'メーカーA'],
    ['D013', 'フィラリア予防薬（イベルメクチン）', 'イベルメクチン', '予防薬', '錠', 1200, 200, 20, 'メーカーH'],
    ['D014', 'ノミ・マダニ駆除薬（フルララネル）', 'フルララネル', '予防薬', '錠', 2500, 100, 10, 'メーカーI'],
    ['D015', '生理食塩液 500mL', '生理食塩水', '輸液', '本', 300, 100, 20, 'メーカーJ'],
    ['D016', 'ソルラクト輸液 500mL', '乳酸リンゲル液', '輸液', '本', 350, 80, 20, 'メーカーJ'],
];

foreach ($drugs as $d) {
    $db->query("INSERT INTO drug_master (drug_code, drug_name, generic_name, category, unit, unit_price, stock_quantity, min_stock, manufacturer, is_active, created_at) VALUES (?,?,?,?,?,?,?,?,?,1,datetime('now','localtime'))", $d);
}
echo "✓ 薬品マスタデータ投入完了\n";

// --- 13. 検査マスタ ---
$tests = [
    ['T001', 'CBC（血球計数）', '血液検査', '', '', '', 3000],
    ['T002', '血液化学検査（基本12項目）', '血液検査', '', '', '', 5000],
    ['T003', '血液化学検査（肝腎パネル）', '血液検査', '', '', '', 3500],
    ['T004', '血糖値', '血液検査', 'mg/dL', '74', '143', 500],
    ['T005', 'フルクトサミン', '血液検査', 'μmol/L', '190', '365', 2500],
    ['T006', '甲状腺ホルモン（T4）', '内分泌検査', 'μg/dL', '1.0', '4.0', 3500],
    ['T007', '尿検査（一般）', '尿検査', '', '', '', 1500],
    ['T008', 'X線撮影（1部位）', '画像検査', '', '', '', 4000],
    ['T009', 'X線撮影（2部位）', '画像検査', '', '', '', 6000],
    ['T010', '超音波検査（腹部）', '画像検査', '', '', '', 6000],
    ['T011', '超音波検査（心臓）', '画像検査', '', '', '', 8000],
    ['T012', 'CT検査', '画像検査', '', '', '', 30000],
    ['T013', '細胞診', '病理検査', '', '', '', 5000],
    ['T014', '病理組織検査', '病理検査', '', '', '', 15000],
    ['T015', '培養・感受性試験', '微生物検査', '', '', '', 8000],
    ['T016', 'CRP（犬）', '血液検査', 'mg/dL', '0', '1.0', 2000],
    ['T017', '凝固検査（PT/APTT）', '血液検査', '', '', '', 3000],
    ['T018', '血液ガス分析', '血液検査', '', '', '', 3000],
];

foreach ($tests as $t) {
    $db->query("INSERT INTO test_master (test_code, test_name, category, unit, reference_low, reference_high, unit_price, is_active, created_at) VALUES (?,?,?,?,?,?,?,1,datetime('now','localtime'))", $t);
}
echo "✓ 検査マスタデータ投入完了\n";

// --- 14. 処置マスタ ---
$procedures = [
    ['P001', '初診料', '基本診療', 1500, 1, '回'],
    ['P002', '再診料', '基本診療', 800, 1, '回'],
    ['P003', '皮下注射', '注射', 500, 1, '回'],
    ['P004', '静脈内注射', '注射', 1000, 1, '回'],
    ['P005', '点滴（皮下輸液）', '輸液', 2000, 1, '回'],
    ['P006', '点滴（静脈内持続）', '輸液', 3000, 1, '日'],
    ['P007', '採血', '検体採取', 500, 1, '回'],
    ['P008', '外科手術（基本）', '手術', 30000, 1, '回'],
    ['P009', '麻酔（全身）', '麻酔', 15000, 1, '回'],
    ['P010', '入院費（犬・大型）', '入院', 5000, 1, '日'],
    ['P011', '入院費（犬・小型）', '入院', 3000, 1, '日'],
    ['P012', '入院費（猫）', '入院', 3000, 1, '日'],
    ['P013', '創傷処置', '処置', 2000, 1, '回'],
    ['P014', '抜歯', '歯科', 5000, 1, '本'],
    ['P015', '歯石除去', '歯科', 15000, 1, '回'],
    ['P016', '去勢手術（犬）', '手術', 25000, 1, '回'],
    ['P017', '避妊手術（犬）', '手術', 40000, 1, '回'],
    ['P018', '去勢手術（猫）', '手術', 15000, 1, '回'],
    ['P019', '避妊手術（猫）', '手術', 25000, 1, '回'],
    ['P020', 'マイクロチップ挿入', '処置', 5000, 1, '回'],
];

foreach ($procedures as $pr) {
    $db->query("INSERT INTO procedure_master (procedure_code, procedure_name, category, unit_price, default_quantity, unit, is_active, created_at) VALUES (?,?,?,?,?,?,1,datetime('now','localtime'))", $pr);
}
echo "✓ 処置マスタデータ投入完了\n";

// --- 15. 看護記録 ---
$nursingRecords = [
    [7, 1, 4, 'observation', '化学療法Day3: 食欲廃絶。嘔吐1回あり。活気低下。好中球減少期に入った可能性。感染兆候には注意が必要。Dr.山田に報告済み。', 'high'],
    [7, 1, 5, 'observation', '化学療法Day5: 食欲回復傾向。自力で水を飲む姿確認。尾を振る姿も見られ、精神状態改善。', 'normal'],
    [7, 1, 4, 'care', '化学療法Day8: ビンクリスチン投与前の血液検査で好中球数十分（8200/μL）。投与可能と判断。Dr.山田に確認済み。', 'high'],
    [7, 1, 5, 'observation', 'Day12: リンパ節の触診で明らかな縮小を確認。エコー検査でも治療効果あり。食欲旺盛。', 'normal'],
    [12, 2, 4, 'observation', '入院初日: 多飲多尿顕著。飲水量は通常の約3倍。尿量も多い。血糖値380mg/dL。', 'high'],
    [12, 2, 5, 'care', 'Day3: インスリン量2.0Uに増量後、血糖値200mg/dL前後で推移。飲水量も減少傾向。', 'normal'],
    [12, 2, 4, 'observation', 'Day5: 血糖コントロール良好（150-190mg/dL）。飲水量ほぼ正常化。退院に向けた飼い主指導開始。', 'normal'],
];

foreach ($nursingRecords as $nr) {
    $db->query("INSERT INTO nursing_records (patient_id, admission_id, nurse_id, record_type, content, priority, created_at) VALUES (?,?,?,?,?,?,datetime('now','localtime'))", $nr);
}
echo "✓ 看護記録データ投入完了\n";

// --- 16. 病理検査 ---
$pathologies = [
    [7, 3, 'PATH-2025-001', 'リンパ節穿刺吸引細胞診', '右浅頸リンパ節', '2025-12-15', 2, 'アルコール固定', '右浅頸リンパ節より採取。細胞数豊富。', '大型のリンパ球様細胞がシート状に増殖。核は大型で核小体明瞭。有糸分裂像散見。小型リンパ球はほぼ認められない。', '高グレードリンパ腫（Diffuse Large B-cell Lymphoma相当）', '病理 佐々木教授', 'completed', '2025-12-20'],
    [4, 5, 'PATH-2026-001', '骨片（術中採取）', '左大腿骨遠位', '2026-01-20', 3, 'ホルマリン固定', '骨折部位より採取した骨片（約1cm大）2個。', '正常な骨構造。腫瘍性変化や感染兆候なし。骨折線に沿った反応性変化を認める。', '外傷性骨折に伴う反応性変化。悪性所見なし。', '病理 佐々木教授', 'completed', '2026-01-28'],
];

foreach ($pathologies as $path) {
    $db->query("INSERT INTO pathology (patient_id, record_id, pathology_number, specimen_type, collection_site, collection_date, collected_by, fixation_method, gross_description, microscopic_description, diagnosis, pathologist, status, report_date, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,datetime('now','localtime'))", $path);
}
echo "✓ 病理検査データ投入完了\n";

// --- 17. 会計データ ---
$invoices = [
    ['R20260210-0001', 1, 1, 1, 8300, 830, 0, 0, 9130, 'cash', 'paid'],
    ['R20260205-0001', 3, 2, 2, 14600, 1460, 0, 7300, 8760, 'credit', 'paid'],
];

foreach ($invoices as $inv) {
    $db->query("INSERT INTO invoices (invoice_number, patient_id, owner_id, record_id, subtotal, tax, discount, insurance_covered, total, payment_method, payment_status, paid_at, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,datetime('now','localtime'),1,datetime('now','localtime'))", $inv);
}

// --- 18. 会計明細 ---
$items = [
    [1, '再診料', '基本診療', 1, '回', 800, 800, 10],
    [1, 'メトクロプラミド錠 5mg × 6錠', '処方', 6, '錠', 50, 300, 10],
    [1, 'CBC（血球計数）', '検査', 1, '回', 3000, 3000, 10],
    [1, '血液化学検査（基本12項目）', '検査', 1, '回', 5000, 5000, 10],
    [1, '採血', '処置', 1, '回', 500, 500, 10],
    [2, '再診料', '基本診療', 1, '回', 800, 800, 10],
    [2, 'ピモベンダン 1.25mg × 60錠', '処方', 60, '錠', 80, 4800, 10],
    [2, 'ベナゼプリル 2.5mg × 30錠', '処方', 30, '錠', 60, 1800, 10],
    [2, '心臓超音波検査', '検査', 1, '回', 8000, 8000, 10],
];

foreach ($items as $it) {
    $db->query("INSERT INTO invoice_items (invoice_id, item_name, category, quantity, unit, unit_price, amount, tax_rate) VALUES (?,?,?,?,?,?,?,?)", $it);
}
echo "✓ 会計データ投入完了\n";

// --- 19. お知らせ ---
$notices = [
    ['【重要】化学療法患者の感染予防について', 'ハナちゃん（ラブラドール）が化学療法による好中球減少期に入ります。A棟入退室時は必ず手指消毒を行い、体調不良のスタッフはA-3ケージ周辺への立ち入りをお控えください。', 'high', '', 1],
    ['インスリン保管温度管理のお願い', 'コタロウ（猫）のインスリン グラルギンは薬品冷蔵庫（2-8℃）で保管してください。開封後は室温保管可能ですが、28日以内に使い切ること。', 'normal', 'nurse', 1],
    ['2月の当直スケジュールについて', '2月分の当直スケジュールを掲示板に貼り出しました。変更希望がある方は今週金曜日までにお申し出ください。', 'normal', '', 1],
    ['新しい心エコー装置の使い方研修', '来週月曜日15時より、新しく導入した心エコー装置の使い方研修を行います。獣医師・看護師の方は可能な限りご参加ください。', 'normal', '', 2],
];

foreach ($notices as $n) {
    $db->query("INSERT INTO notices (title, content, priority, target_role, posted_by, is_active, created_at) VALUES (?,?,?,?,?,1,datetime('now','localtime'))", $n);
}
echo "✓ お知らせデータ投入完了\n";

// --- 20. 予約データ ---
$appointments = [
    [1, 1, 2, '2026-02-18', '10:00', 30, 'follow_up', 'scheduled', '胃腸炎再診', ''],
    [3, 2, 2, '2026-05-05', '14:00', 60, 'checkup', 'scheduled', '心臓定期検診', '心エコー予定'],
    [9, 6, 1, '2026-02-17', '11:00', 30, 'vaccination', 'scheduled', 'ワクチン接種', '混合ワクチン'],
    [10, 7, 3, '2026-02-20', '15:00', 30, 'follow_up', 'scheduled', '椎間板ヘルニア経過観察', ''],
    [null, null, null, '2026-02-16', '09:00', 30, 'general', 'scheduled', '新規来院（電話予約）', '猫 嘔吐が続く'],
];

foreach ($appointments as $ap) {
    // updated_at を追加して挿入
    $db->query("INSERT INTO appointments (patient_id, owner_id, staff_id, appointment_date, appointment_time, duration, appointment_type, status, reason, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,datetime('now','localtime'), datetime('now','localtime'))", $ap);
}
echo "✓ 予約データ投入完了\n";

echo "\n全サンプルデータの投入が完了しました！\n";
echo "ログインアカウント:\n";
echo "  管理者: admin / admin123\n";
echo "  獣医師: dr_suzuki / pass1234, dr_tanaka / pass1234\n";
echo "  看護師: ns_sato / pass1234, ns_ito / pass1234\n";
echo "  受付: rc_kato / pass1234\n";
echo "  検査技師: lab_nakamura / pass1234\n";