<?php
/**
 * 保険会社提出用レセプトデータ出力
 * アニコム・アイペット等の主要保険会社フォーマット対応
 */
$claimId = (int)($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'anicom'; // anicom, ipet, generic_csv

$claim = $db->fetch("
    SELECT ic.*, p.name as patient_name, p.patient_code, p.species, p.breed, p.sex, p.birthdate,
           p.microchip_id, p.insurance_number,
           o.name as owner_name, o.phone as owner_phone, o.address as owner_address, o.postal_code as owner_postal,
           ip.company_name, ip.policy_number, ip.coverage_rate, ip.plan_name,
           im.insurance_code, im.claim_format
    FROM insurance_claims ic
    JOIN patients p ON ic.patient_id = p.id
    JOIN owners o ON p.owner_id = o.id
    JOIN insurance_policies ip ON ic.policy_id = ip.id
    LEFT JOIN insurance_master im ON ip.company_name = im.company_name
    WHERE ic.id = ?
", [$claimId]);

if (!$claim) {
    echo '<p>レセプトが見つかりません</p>';
    exit;
}

$items = $db->fetchAll("SELECT * FROM insurance_claim_items WHERE claim_id = ? ORDER BY item_date, id", [$claimId]);
$hospital = [
    'name' => getSetting('hospital_name', ''),
    'address' => getSetting('hospital_address', ''),
    'phone' => getSetting('hospital_phone', ''),
    'fax' => getSetting('hospital_fax', ''),
    'director' => getSetting('hospital_director', ''),
    'license' => getSetting('hospital_license', ''),
];

// Determine output format
if ($format === 'csv') {
    // Generic CSV export for any insurance company
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="recept_' . $claim['claim_number'] . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    
    $out = fopen('php://output', 'w');
    // Header
    fputcsv($out, ['保険請求データ']);
    fputcsv($out, []);
    fputcsv($out, ['請求番号', $claim['claim_number']]);
    fputcsv($out, ['請求日', $claim['claim_date']]);
    fputcsv($out, ['保険会社', $claim['company_name']]);
    fputcsv($out, ['証券番号', $claim['policy_number']]);
    fputcsv($out, ['補償割合', $claim['coverage_rate'] . '%']);
    fputcsv($out, []);
    fputcsv($out, ['飼い主名', $claim['owner_name']]);
    fputcsv($out, ['住所', $claim['owner_address']]);
    fputcsv($out, ['電話', $claim['owner_phone']]);
    fputcsv($out, []);
    fputcsv($out, ['患畜名', $claim['patient_name']]);
    fputcsv($out, ['カルテ番号', $claim['patient_code']]);
    fputcsv($out, ['種別', getSpeciesName($claim['species'])]);
    fputcsv($out, ['品種', $claim['breed']]);
    fputcsv($out, ['性別', getSexName($claim['sex'])]);
    fputcsv($out, ['生年月日', $claim['birthdate']]);
    fputcsv($out, ['マイクロチップ', $claim['microchip_id']]);
    fputcsv($out, []);
    fputcsv($out, ['診断名', $claim['diagnosis_name']]);
    fputcsv($out, ['診断コード', $claim['diagnosis_code']]);
    fputcsv($out, ['治療開始日', $claim['treatment_start_date']]);
    fputcsv($out, ['治療終了日', $claim['treatment_end_date']]);
    fputcsv($out, []);
    fputcsv($out, ['日付', '区分', '内容', '数量', '単位', '単価', '金額', '保険対象']);
    foreach ($items as $it) {
        fputcsv($out, [
            $it['item_date'], $it['item_category'], $it['item_name'],
            $it['quantity'], $it['unit'], $it['unit_price'], $it['amount'],
            $it['is_covered'] ? '○' : '×'
        ]);
    }
    fputcsv($out, []);
    fputcsv($out, ['医療費合計', $claim['total_medical_fee']]);
    fputcsv($out, ['保険負担額', $claim['covered_amount']]);
    fputcsv($out, ['飼い主負担額', $claim['owner_copay']]);
    fputcsv($out, ['免責額', $claim['deductible']]);
    fputcsv($out, []);
    fputcsv($out, ['医療機関名', $hospital['name']]);
    fputcsv($out, ['医療機関住所', $hospital['address']]);
    fputcsv($out, ['医療機関電話', $hospital['phone']]);
    fputcsv($out, ['獣医師名', $hospital['director']]);
    fclose($out);
    exit;
}

// Printable format (A4 insurance company submission form)
$speciesJP = getSpeciesName($claim['species']);
$sexJP = getSexName($claim['sex']);
$age = calculateAge($claim['birthdate']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>保険請求書 - <?= h($claim['claim_number']) ?></title>
<style>
    @page { size: A4; margin: 10mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Yu Gothic", "Meiryo", sans-serif; font-size: 10pt; line-height: 1.5; color: #333; }
    .page { width: 190mm; margin: 0 auto; }
    h1 { text-align: center; font-size: 16pt; margin: 8mm 0 5mm; border-bottom: 2px solid #333; padding-bottom: 3mm; }
    h2 { font-size: 11pt; margin: 5mm 0 2mm; background: #f0f0f0; padding: 2mm 3mm; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 3mm; }
    th, td { border: 1px solid #999; padding: 2mm 3mm; text-align: left; font-size: 9pt; }
    th { background: #f5f5f5; font-weight: bold; white-space: nowrap; width: 25%; }
    .detail-table th { width: auto; text-align: center; background: #e8e8e8; }
    .detail-table td { text-align: center; }
    .detail-table td.text-left { text-align: left; }
    .amount { text-align: right !important; }
    .total-row { background: #f0f8ff; font-weight: bold; }
    .stamp-area { width: 25mm; height: 25mm; border: 1px dashed #ccc; display: inline-block; text-align: center; line-height: 25mm; color: #aaa; font-size: 8pt; }
    .footer { margin-top: 5mm; font-size: 8pt; text-align: center; color: #666; }
    .hospital-info { margin-top: 5mm; border: 1px solid #333; padding: 3mm; }
    @media print {
        .no-print { display: none !important; }
        body { -webkit-print-color-adjust: exact; }
    }
    .action-bar { text-align: center; margin: 10px 0; }
    .action-bar button, .action-bar a { margin: 0 5px; padding: 8px 20px; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-block; }
    .btn-print { background: #4f46e5; color: #fff; border: none; border-radius: 6px; }
    .btn-csv { background: #059669; color: #fff; border: none; border-radius: 6px; }
    .btn-back { background: #6b7280; color: #fff; border: none; border-radius: 6px; }
</style>
</head>
<body>
<div class="no-print action-bar">
    <button class="btn-print" onclick="window.print()">🖨️ 印刷</button>
    <a class="btn-csv" href="?page=insurance_export&id=<?= $claimId ?>&format=csv">📊 CSV出力</a>
    <a class="btn-back" href="?page=insurance_claims">← 戻る</a>
</div>

<div class="page">
    <h1>動物医療費保険金請求書</h1>
    
    <table>
        <tr><td colspan="4" style="text-align:right; border:none; font-size:9pt;">
            請求番号: <strong><?= h($claim['claim_number']) ?></strong> ／ 請求日: <?= formatDate($claim['claim_date']) ?>
        </td></tr>
    </table>

    <table>
        <tr><th colspan="4" style="text-align:center; background:#dbeafe;">保険契約情報</th></tr>
        <tr><th>保険会社</th><td><?= h($claim['company_name']) ?></td><th>証券番号</th><td><?= h($claim['policy_number']) ?></td></tr>
        <tr><th>補償割合</th><td><?= h($claim['coverage_rate']) ?>%</td><th>プラン</th><td><?= h($claim['plan_name'] ?? '-') ?></td></tr>
    </table>

    <table>
        <tr><th colspan="4" style="text-align:center; background:#dcfce7;">契約者（飼い主）情報</th></tr>
        <tr><th>氏名</th><td><?= h($claim['owner_name']) ?></td><th>電話番号</th><td><?= h($claim['owner_phone']) ?></td></tr>
        <tr><th>住所</th><td colspan="3"><?= h($claim['owner_address']) ?></td></tr>
    </table>

    <table>
        <tr><th colspan="6" style="text-align:center; background:#fef3c7;">被保険動物情報</th></tr>
        <tr><th>名前</th><td><?= h($claim['patient_name']) ?></td><th>カルテ番号</th><td><?= h($claim['patient_code']) ?></td><th>保険証番号</th><td><?= h($claim['insurance_number'] ?? '-') ?></td></tr>
        <tr><th>種別</th><td><?= h($speciesJP) ?></td><th>品種</th><td><?= h($claim['breed']) ?></td><th>性別</th><td><?= h($sexJP) ?></td></tr>
        <tr><th>生年月日</th><td><?= formatDate($claim['birthdate']) ?></td><th>年齢</th><td><?= h($age) ?></td><th>マイクロチップ</th><td><?= h($claim['microchip_id'] ?? '-') ?></td></tr>
    </table>

    <table>
        <tr><th colspan="4" style="text-align:center; background:#fce7f3;">診療情報</th></tr>
        <tr><th>診断名</th><td><?= h($claim['diagnosis_name']) ?></td><th>診断コード</th><td><?= h($claim['diagnosis_code']) ?></td></tr>
        <tr><th>治療開始日</th><td><?= formatDate($claim['treatment_start_date']) ?></td><th>治療終了日</th><td><?= formatDate($claim['treatment_end_date']) ?></td></tr>
    </table>

    <h2>診療明細</h2>
    <table class="detail-table">
        <thead>
            <tr><th>日付</th><th>区分</th><th>内容</th><th>数量</th><th>単位</th><th>単価</th><th>金額</th><th>対象</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
                <td><?= formatDate($it['item_date'], 'm/d') ?></td>
                <td><?= h($it['item_category']) ?></td>
                <td class="text-left"><?= h($it['item_name']) ?></td>
                <td><?= $it['quantity'] ?></td>
                <td><?= h($it['unit']) ?></td>
                <td class="amount"><?= formatCurrency($it['unit_price']) ?></td>
                <td class="amount"><?= formatCurrency($it['amount']) ?></td>
                <td><?= $it['is_covered'] ? '○' : '×' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table>
        <tr><th style="width:50%;">医療費合計</th><td class="amount total-row" style="font-size:12pt;"><?= formatCurrency($claim['total_medical_fee']) ?></td></tr>
        <tr><th>保険対象額</th><td class="amount"><?= formatCurrency($claim['covered_amount']) ?></td></tr>
        <tr><th>免責額</th><td class="amount"><?= formatCurrency($claim['deductible']) ?></td></tr>
        <tr><th>保険金請求額</th><td class="amount total-row" style="font-size:12pt; color:#1d4ed8;"><?= formatCurrency($claim['covered_amount'] - $claim['deductible']) ?></td></tr>
        <tr><th>飼い主ご負担額</th><td class="amount"><?= formatCurrency($claim['owner_copay'] + $claim['deductible']) ?></td></tr>
    </table>

    <?php if ($claim['notes']): ?>
    <h2>備考</h2>
    <p style="padding:2mm; border:1px solid #ddd; min-height:15mm; font-size:9pt;"><?= nl2br(h($claim['notes'])) ?></p>
    <?php endif; ?>

    <div class="hospital-info">
        <table style="border:none;">
            <tr style="border:none;">
                <td style="border:none; width:70%;">
                    <strong>医療機関証明</strong><br>
                    上記の通り診療したことを証明します。<br><br>
                    医療機関名: <strong><?= h($hospital['name']) ?></strong><br>
                    住所: <?= h($hospital['address']) ?><br>
                    電話: <?= h($hospital['phone']) ?><?= $hospital['fax'] ? ' / FAX: ' . h($hospital['fax']) : '' ?><br>
                    獣医師名: <?= h($hospital['director']) ?><br>
                    <?php if ($hospital['license']): ?>登録番号: <?= h($hospital['license']) ?><?php endif; ?>
                </td>
                <td style="border:none; text-align:center; vertical-align:bottom;">
                    <div class="stamp-area">
                        <?php 
                        $stampImg = getSetting('stamp_image', '');
                        if ($stampImg): ?>
                            <img src="uploads/<?= h($stampImg) ?>" style="max-width:23mm; max-height:23mm;">
                        <?php else: ?>
                            印
                        <?php endif; ?>
                    </div>
                    <br><small>証明日: <?= date('Y年m月d日') ?></small>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        この書類は <?= h($hospital['name']) ?> の電子カルテシステム (<?= APP_NAME ?> v<?= APP_VERSION ?>) より出力されました。
    </div>
</div>
</body>
</html>
