<?php
/** 書類印刷プレビュー  */
$id = (int)($_GET['id'] ?? 0);
$doc = $db->fetch("SELECT d.*, s.name as issued_name, s.license_number, s.stamp_image as personal_stamp_file 
                   FROM issued_documents d 
                   LEFT JOIN staff s ON d.issued_by = s.id 
                   WHERE d.id = ?", [$id]);

if (!$doc) {
    echo '<div style="padding:20px; color:red;">エラー: 書類が見つかりません。(ID: ' . h($id) . ')</div>';
    exit;
}

// JSONデータのデコード
$data = json_decode($doc['content'] ?: '{}', true);
$pat = $data['patient'] ?? [];

// 施設情報・医師情報
$hospName = $data['hospital_name'] ?? 'ーー動物病院';
$hospAddr = $data['hospital_address'] ?? '';
$hospPhone = $data['hospital_phone'] ?? '';
$vetName = $data['vet_name'] ?? $doc['issued_name'] ?? '担当獣医師';

// ▼ 印鑑画像のパス準備
$hospStampSetting = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'stamp_image'");
$hospStampFile = $hospStampSetting ? $hospStampSetting['setting_value'] : '';
$hospStampPath = ($hospStampFile && file_exists(UPLOAD_DIR . $hospStampFile)) ? 'uploads/' . $hospStampFile : null;

$personalStampFile = $doc['personal_stamp_file'];
$personalStampPath = ($personalStampFile && file_exists(UPLOAD_DIR . $personalStampFile)) ? 'uploads/' . $personalStampFile : null;

// 書類タイプごとの設定
$docType = $doc['document_type'];
$docConfig = [
    'diagnosis_certificate' => ['name' => '診　断　書', 'style' => 'a4-portrait'],
    'referral_letter'       => ['name' => '診療情報提供書', 'style' => 'a4-portrait'],
    'prescription'          => ['name' => '処　方　箋', 'style' => 'a5-landscape'],
    'vaccination_certificate'=>['name' => '混合ワクチン接種証明書', 'style' => 'a4-portrait'],
    'health_certificate'    => ['name' => '健康診断書', 'style' => 'a4-portrait'],
    'death_certificate'     => ['name' => '死亡診断書', 'style' => 'a4-portrait'],
    'insurance_claim'       => ['name' => '診療明細書', 'style' => 'a4-portrait'],
][$docType] ?? ['name' => '書　類', 'style' => 'a4-portrait'];

$docTypeName = $docConfig['name'];
$pageStyle = $docConfig['style']; 
$issuedDateStr = date('Y年 m月 d日', strtotime($doc['issued_date']));

/**
 * 署名欄生成関数
 */
function renderSignatureSection($hospAddr, $hospPhone, $hospName, $hospStampPath, $vetName, $personalStampPath) {
    ?>
    <div class="signature-section">
        <div class="hospital-block">
            <div class="hospital-text">
                <div class="hosp-address">〒<?= h($hospAddr) ?></div>
                <div class="hosp-phone">TEL: <?= h($hospPhone) ?></div>
                <div class="hosp-name"><?= h($hospName) ?></div>
            </div>
            <div class="stamp-area hospital-stamp">
                <?php if ($hospStampPath): ?>
                    <img src="<?= h($hospStampPath) ?>" alt="施設印">
                <?php else: ?>
                    <div class="stamp-placeholder-box">印</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="vet-block">
            <div class="vet-text">
                <div class="vet-role">獣医師</div>
                <div class="vet-name"><?= h($vetName) ?></div>
            </div>
            <div class="stamp-area personal-stamp">
                <?php if ($personalStampPath): ?>
                    <img src="<?= h($personalStampPath) ?>" alt="認印">
                <?php else: ?>
                    <div class="stamp-placeholder-circle">印</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title><?= h($docTypeName) ?> - <?= h($pat['name'] ?? '') ?></title>
<style>
    /* --- 基本リセット --- */
    * { box-sizing: border-box; }
    body {
        font-family: "Hiragino Mincho ProN", "Yu Mincho", serif;
        font-size: 10.5pt;
        line-height: 1.5;
        background: #eee;
        color: #000;
        margin: 0;
        padding: 20px;
    }

    /* --- 画面表示用 --- */
    .no-print-bar {
        position: fixed; top: 0; left: 0; right: 0; background: #333; color: #fff; padding: 10px 20px; z-index: 999;
        display: flex; justify-content: space-between; align-items: center; font-family: sans-serif;
    }
    .print-btn { background: #fff; color: #333; border: none; padding: 8px 16px; cursor: pointer; border-radius: 4px; font-weight: bold; }

    /* --- 用紙レイアウト --- */
    .sheet {
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin: 30px auto;
        position: relative;
    }

    /* A4縦 */
    .sheet.a4-portrait {
        width: 210mm; min-height: 297mm;
        padding: 20mm 20mm;
    }
    /* A5横 (処方箋) */
    .sheet.a5-landscape {
        width: 210mm; height: 148mm;
        padding: 10mm 15mm;
        display: flex; flex-direction: column;
    }

    /* --- 共通要素 --- */
    h1.doc-title {
        text-align: center; font-size: 18pt; font-weight: bold;
        margin: 0 0 15px 0; padding-bottom: 5px; border-bottom: 1px solid #000;
        letter-spacing: 0.2em;
    }
    .date-row { text-align: right; margin-bottom: 10px; font-size: 10pt; }
    .recipient-area { margin-bottom: 20px; font-size: 12pt; }
    .recipient-name { font-size: 14pt; font-weight: bold; border-bottom: 1px solid #ccc; display: inline-block; min-width: 200px; }

    /* --- 処方箋専用レイアウト --- */
    .prescription-header {
        display: flex; justify-content: space-between; align-items: flex-end;
        border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 10px;
    }
    .prescription-title { font-size: 20pt; font-weight: bold; letter-spacing: 0.5em; margin: 0; }
    .prescription-meta { text-align: right; font-size: 9pt; line-height: 1.3; }

    /* 飼い主・患者情報テーブル (枠線付き) */
    .rx-info-table {
        width: 100%; border-collapse: collapse; margin-bottom: 5px; font-size: 10pt;
    }
    .rx-info-table th, .rx-info-table td {
        border: 1px solid #000; padding: 4px 8px; vertical-align: middle;
    }
    .rx-info-table th { background: #f0f0f0; width: 12%; text-align: center; font-weight: normal; }
    .rx-info-table td { width: 38%; }

    /* 処方内容エリア */
    .rx-content-box {
        border: 1px solid #000; padding: 8px;
        flex-grow: 1; display: flex; flex-direction: column;
        margin-bottom: 5px;
    }
    .rx-label { font-weight: bold; font-size: 10pt; border-bottom: 1px dotted #999; margin-bottom: 5px; }
    .rx-body { 
        font-family: "Courier New", monospace; font-size: 11pt; line-height: 1.4; 
        white-space: pre-wrap; flex-grow: 1; overflow: hidden; 
    }
    .rx-footer { font-size: 9pt; margin-top: 5px; border-top: 1px dotted #ccc; padding-top: 2px; }

    /* --- 一般書類用 --- */
    .patient-table {
        width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt; border: 1px solid #000;
    }
    .patient-table th, .patient-table td { border: 1px solid #000; padding: 5px 10px; }
    .patient-table th { background: #f0f0f0; width: 15%; font-weight: normal; text-align: left; }

    /* 診断書等の本文エリア */
    .content-body {
        margin-bottom: 20px; line-height: 1.8; font-size: 11pt;
    }

    .content-text-area {
        border: 1px solid #ccc; padding: 15px; 
        min-height: 200px;
        white-space: pre-wrap;
        margin-top: 10px;
    }

    /* --- 署名捺印 --- */
    .signature-section {
        margin-top: auto; padding-top: 10px;
        display: flex; flex-direction: column; align-items: flex-end;
        page-break-inside: avoid;
    }
    .hospital-block { display: flex; align-items: flex-end; margin-bottom: 10px; }
    .hospital-text { text-align: left; margin-right: 15px; line-height: 1.3; }
    .hosp-name { font-size: 13pt; font-weight: bold; }
    .hosp-address { font-size: 9pt; }
    .hospital-stamp { width: 22mm; height: 22mm; position: relative; } 

    .vet-block { display: flex; align-items: center; }
    
    /* 修正箇所: text-alignをleftに変更して「獣医師」を左上に配置 */
    .vet-text { text-align: left; margin-right: 10px; line-height: 1.2; }
    
    .vet-role { font-size: 9pt; }
    .vet-name { font-size: 12pt; font-weight: bold; border-bottom: 1px solid #333; min-width: 120px; text-align: center; }
    .personal-stamp { width: 12mm; height: 12mm; position: relative; margin-left: 5px; }

    .stamp-area img { width: 100%; height: 100%; object-fit: contain; }
    .stamp-placeholder-box {
        width: 100%; height: 100%; border: 1px solid #ccc; color: #ccc;
        display: flex; align-items: center; justify-content: center; font-size: 9pt;
    }
    .stamp-placeholder-circle {
        width: 100%; height: 100%; border: 1px solid #ccc; border-radius: 50%; color: #ccc;
        display: flex; align-items: center; justify-content: center; font-size: 7pt;
    }

    /* --- 印刷設定 --- */
    @media print {
        body { margin: 0; padding: 0; background: none; }
        .no-print-bar { display: none; }
        .sheet { box-shadow: none; margin: 0; width: 100%; height: 100%; page-break-after: always; }
        @page { margin: 0; }
        @page a4-portrait { size: A4 portrait; margin: 0; }
        @page a5-landscape { size: A5 landscape; margin: 0; }
        .sheet.a4-portrait { page: a4-portrait; }
        .sheet.a5-landscape { page: a5-landscape; }
        
        .stamp-placeholder-box, .stamp-placeholder-circle { border-color: #eee; color: #eee; }
    }
</style>
</head>
<body>

<div class="no-print-bar">
    <span><?= h($docTypeName) ?> プレビュー</span>
    <button class="print-btn" onclick="window.print()">🖨️ 印刷する</button>
</div>

<div class="sheet <?= h($pageStyle) ?>">

    <?php /* --- 処方箋 (A5横) --- */ ?>
    <?php if ($docType === 'prescription'): ?>
        
        <div class="prescription-header">
            <h1 class="prescription-title">処方箋</h1>
            <div class="prescription-meta">
                交付年月日：<?= $issuedDateStr ?><br>
                <strong>有効期限：交付日より4日以内</strong>
            </div>
        </div>

        <table class="rx-info-table">
            <tr>
                <th>飼い主</th>
                <td>
                    <div style="font-size:9pt;"><?= h($pat['address'] ?? $pat['owner_address'] ?? '') ?></div>
                    <div style="font-size:11pt; font-weight:bold;"><?= h($pat['owner_name'] ?? '') ?> 様</div>
                </td>
                <th>患　者</th>
                <td>
                    <span style="font-weight:bold; font-size:11pt;"><?= h($pat['name'] ?? '') ?></span>
                    <span style="font-size:9pt;">
                        (<?= h(getSpeciesName($pat['species'] ?? '')) ?> / <?= h($pat['breed'] ?? '-') ?>)
                    </span><br>
                    <span style="font-size:9pt;">
                        <?= h(getSexName($pat['sex'] ?? '')) ?> / <?= calculateAge($pat['birthdate'] ?? '') ?> / <?= h($pat['weight'] ?? '-') ?>kg
                    </span>
                </td>
            </tr>
        </table>

        <div class="rx-content-box">
            <div class="rx-label">処方内容 (薬品名・分量・用法・用量)</div>
            <div class="rx-body"><?= empty($data['custom_text']) ? '（以下余白）' : nl2br(h($data['custom_text'])) ?></div>
            <div class="rx-footer">
                備考: <?= h($data['notes'] ?? '特になし') ?>
            </div>
        </div>

        <?php renderSignatureSection($hospAddr, $hospPhone, $hospName, $hospStampPath, $vetName, $personalStampPath); ?>


    <?php /* --- 診療情報提供書 (紹介状) --- */ ?>
    <?php elseif ($docType === 'referral_letter'): ?>
        
        <h1 class="doc-title">診療情報提供書</h1>
        <div class="date-row"><?= $issuedDateStr ?></div>

        <div class="recipient-area">
            紹介先医療機関：<br>
            <span class="recipient-name"><?= h($data['referral_to_hospital'] ?? '＿＿＿＿＿＿＿＿') ?>　御中</span><br>
            （担当医：<?= h($data['referral_to_vet'] ?? '＿＿＿＿＿＿') ?>　先生）
        </div>

        <div style="margin-bottom:15px;">
            下記の患者につきまして、ご紹介申し上げます。<br>ご高診のほど宜しくお願い致します。
        </div>

        <table class="patient-table">
            <tr>
                <th>飼い主様</th><td><?= h($pat['owner_name'] ?? '') ?> 様</td>
                <th>動物名</th><td><?= h($pat['name'] ?? '') ?> (<?= h(getSpeciesName($pat['species'] ?? '')) ?>)</td>
            </tr>
            <tr>
                <th>品種/性別</th><td><?= h($pat['breed'] ?? '') ?> / <?= h(getSexName($pat['sex'] ?? '')) ?></td>
                <th>生年月日</th><td><?= h($pat['birthdate'] ?? '-') ?> (<?= calculateAge($pat['birthdate'] ?? '') ?>)</td>
            </tr>
        </table>

        <div style="margin-bottom:5px; font-weight:bold; background:#eee; padding:2px 5px; border:1px solid #000; border-bottom:none;">傷病名・紹介目的</div>
        <div style="border:1px solid #000; padding:10px; margin-bottom:15px;">
            <?= nl2br(h($data['diagnosis'] ?? '')) ?>
            <?php if(!empty($data['purpose'])) echo ' (' . nl2br(h($data['purpose'])) . ')'; ?>
        </div>

        <div style="margin-bottom:5px; font-weight:bold; background:#eee; padding:2px 5px; border:1px solid #000; border-bottom:none;">臨床経過・検査所見</div>
        <div style="border:1px solid #000; padding:10px; margin-bottom:15px; min-height:150px; white-space:pre-wrap;"><?= nl2br(h($data['clinical_course'] ?? $data['custom_text'] ?? '')) ?></div>

        <div style="margin-bottom:5px; font-weight:bold; background:#eee; padding:2px 5px; border:1px solid #000; border-bottom:none;">処方・備考</div>
        <div style="border:1px solid #000; padding:10px; margin-bottom:15px; min-height:50px; white-space:pre-wrap;"><?= nl2br(h($data['medication'] ?? $data['notes'] ?? '')) ?></div>

        <?php renderSignatureSection($hospAddr, $hospPhone, $hospName, $hospStampPath, $vetName, $personalStampPath); ?>


    <?php /* --- 診断書・証明書・その他 --- */ ?>
    <?php else: ?>
        
        <h1 class="doc-title"><?= h($docTypeName) ?></h1>
        <div class="date-row"><?= $issuedDateStr ?></div>

        <div class="recipient-area" style="margin-bottom:30px;">
            <span class="recipient-name"><?= h($pat['owner_name'] ?? '＿＿＿＿＿＿') ?>　様</span>
        </div>

        <table class="patient-table">
            <tr>
                <th>動物名</th><td><?= h($pat['name'] ?? '') ?></td>
                <th>品種</th><td><?= h($pat['breed'] ?? '-') ?></td>
            </tr>
            <tr>
                <th>性別</th><td><?= h(getSexName($pat['sex'] ?? '')) ?></td>
                <th>生年月日</th><td><?= h($pat['birthdate'] ?? '-') ?> (<?= calculateAge($pat['birthdate'] ?? '') ?>)</td>
            </tr>
            <?php if(!empty($pat['microchip_id'])): ?>
            <tr><th>MC番号</th><td colspan="3"><?= h($pat['microchip_id']) ?></td></tr>
            <?php endif; ?>
        </table>

        <div class="content-body">
            <p>上記の通り<?= strpos($docTypeName, '診断') !== false ? '診断' : '証明' ?>いたします。</p>
            
            <?php if ($docType === 'death_certificate'): ?>
                <div style="margin:20px 0; border:1px solid #ccc; padding:15px;">
                    <p><strong>死亡日時:</strong> <?= h($data['death_date'] ?? '') ?></p>
                    <p><strong>死亡場所:</strong> <?= h($data['death_place']=='hospital'?'当院内':($data['death_place']=='home'?'自宅':'その他')) ?></p>
                    <p><strong>直接死因:</strong> <?= h($data['death_cause'] ?? '') ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($data['diagnosis'])): ?>
                <div style="font-size:12pt; margin:20px 0; font-weight:bold;">
                    診断名： <?= h($data['diagnosis']) ?>
                </div>
            <?php endif; ?>

            <div class="content-text-area">
                <?= nl2br(h($data['custom_text'] ?? '')) ?>
            </div>
        </div>

        <?php renderSignatureSection($hospAddr, $hospPhone, $hospName, $hospStampPath, $vetName, $personalStampPath); ?>

    <?php endif; ?>

</div>
</body>
</html>