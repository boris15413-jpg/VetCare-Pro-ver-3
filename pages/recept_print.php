<?php
/** レセプト印刷用 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/helpers.php';
$db = Database::getInstance();

$id = (int)($_GET['id'] ?? 0);
$claim = $db->fetch("
    SELECT ic.*, p.name as patient_name, p.patient_code, p.species, p.breed, p.birthdate, p.sex, p.microchip_id,
    o.name as owner_name, o.address as owner_address, o.phone as owner_phone,
    ip.company_name, ip.policy_number, ip.coverage_rate, ip.plan_name, ip.holder_name,
    im.postal_code as ins_postal, im.address as ins_address, im.phone as ins_phone, im.fax as ins_fax
    FROM insurance_claims ic
    JOIN patients p ON ic.patient_id = p.id
    JOIN owners o ON p.owner_id = o.id
    JOIN insurance_policies ip ON ic.policy_id = ip.id
    LEFT JOIN insurance_master im ON ip.insurance_master_id = im.id
    WHERE ic.id = ?
", [$id]);

if (!$claim) die('レセプトが見つかりません');

$items = $db->fetchAll("SELECT * FROM insurance_claim_items WHERE claim_id = ? ORDER BY item_date, id", [$id]);
$hospitalName = getSetting('hospital_name', 'VetCare動物病院');
$hospitalAddress = getSetting('hospital_address', '');
$hospitalPhone = getSetting('hospital_phone', '');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>保険請求書（レセプト） - <?= h($claim['claim_number']) ?></title>
<style>
@page { size: A4; margin: 15mm; }
body { font-family: 'Yu Gothic', 'Hiragino Sans', serif; font-size: 10pt; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
.header { text-align: center; border-bottom: 3px double #333; padding-bottom: 15px; margin-bottom: 20px; }
.header h1 { font-size: 18pt; letter-spacing: 0.5em; margin: 0; }
.header .sub { font-size: 9pt; color: #666; }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.info-box { border: 1px solid #ccc; padding: 12px; border-radius: 4px; }
.info-box h3 { font-size: 10pt; margin: 0 0 8px; padding-bottom: 4px; border-bottom: 1px solid #eee; color: #4f46e5; }
.info-row { display: flex; margin-bottom: 4px; font-size: 9pt; }
.info-label { width: 80px; color: #666; flex-shrink: 0; }
.info-value { font-weight: bold; }
table.detail { width: 100%; border-collapse: collapse; margin: 15px 0; }
table.detail th, table.detail td { border: 1px solid #999; padding: 6px 8px; font-size: 9pt; }
table.detail th { background: #f0f0f0; font-weight: bold; text-align: center; }
table.detail td.amount { text-align: right; }
.summary { margin-top: 20px; }
.summary table { width: 300px; margin-left: auto; border-collapse: collapse; }
.summary th, .summary td { padding: 6px 12px; border: 1px solid #999; font-size: 10pt; }
.summary th { background: #f5f5f5; text-align: left; width: 150px; }
.summary td { text-align: right; font-weight: bold; }
.summary .total { background: #4f46e5; color: #fff; font-size: 12pt; }
.footer { margin-top: 30px; display: flex; justify-content: space-between; font-size: 9pt; }
.stamp-area { width: 100px; height: 100px; border: 2px solid #c00; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #c00; font-size: 9pt; font-weight: bold; text-align: center; }
.no-print { position: fixed; top: 10px; right: 10px; z-index: 100; }
@media print { .no-print { display: none; } }
</style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()" style="padding:8px 20px; background:#4f46e5; color:#fff; border:none; border-radius:8px; cursor:pointer; font-size:14px;">
        <b>印刷</b>
    </button>
    <button onclick="window.close()" style="padding:8px 20px; background:#999; color:#fff; border:none; border-radius:8px; cursor:pointer; font-size:14px; margin-left:8px;">閉じる</button>
</div>

<div class="header">
    <h1>動物診療費明細書（保険請求用）</h1>
    <div class="sub">請求番号: <?= h($claim['claim_number']) ?> ｜ 発行日: <?= formatDate($claim['claim_date'], 'Y年m月d日') ?></div>
</div>

<div class="info-grid">
    <div class="info-box">
        <h3>患畜・飼い主情報</h3>
        <div class="info-row"><span class="info-label">患畜名</span><span class="info-value"><?= h($claim['patient_name']) ?></span></div>
        <div class="info-row"><span class="info-label">カルテNo.</span><span class="info-value"><?= h($claim['patient_code']) ?></span></div>
        <div class="info-row"><span class="info-label">種類・品種</span><span class="info-value"><?= h(getSpeciesName($claim['species'])) ?> / <?= h($claim['breed']) ?></span></div>
        <div class="info-row"><span class="info-label">性別</span><span class="info-value"><?= h(getSexName($claim['sex'])) ?></span></div>
        <div class="info-row"><span class="info-label">生年月日</span><span class="info-value"><?= formatDate($claim['birthdate'], 'Y年m月d日') ?> (<?= calculateAge($claim['birthdate']) ?>)</span></div>
        <div class="info-row"><span class="info-label">飼い主名</span><span class="info-value"><?= h($claim['owner_name']) ?> 様</span></div>
    </div>
    <div class="info-box">
        <h3>保険情報</h3>
        <div class="info-row"><span class="info-label">保険会社</span><span class="info-value"><?= h($claim['company_name']) ?></span></div>
        <div class="info-row"><span class="info-label">証券番号</span><span class="info-value"><?= h($claim['policy_number']) ?></span></div>
        <div class="info-row"><span class="info-label">プラン</span><span class="info-value"><?= h($claim['plan_name'] ?: '-') ?></span></div>
        <div class="info-row"><span class="info-label">補償割合</span><span class="info-value" style="color:#4f46e5; font-size:12pt;"><?= $claim['coverage_rate'] ?>%</span></div>
        <div class="info-row"><span class="info-label">契約者名</span><span class="info-value"><?= h($claim['holder_name'] ?: $claim['owner_name']) ?></span></div>
        <div class="info-row"><span class="info-label">治療期間</span><span class="info-value"><?= formatDate($claim['treatment_start_date'], 'Y/m/d') ?> ～ <?= formatDate($claim['treatment_end_date'], 'Y/m/d') ?></span></div>
    </div>
</div>

<div style="margin-bottom:15px;">
    <strong>傷病名: </strong><span style="font-size:12pt; border-bottom:2px solid #333; padding-bottom:2px;"><?= h($claim['diagnosis_name']) ?></span>
    <?php if ($claim['diagnosis_code']): ?><small style="color:#666; margin-left:10px;">(コード: <?= h($claim['diagnosis_code']) ?>)</small><?php endif; ?>
</div>

<table class="detail">
    <thead><tr><th style="width:90px">日付</th><th style="width:70px">区分</th><th>診療内容</th><th style="width:50px">数量</th><th style="width:50px">単位</th><th style="width:80px">単価</th><th style="width:80px">金額</th><th style="width:50px">対象</th></tr></thead>
    <tbody>
    <?php $totalCovered = 0; $totalNon = 0; ?>
    <?php foreach ($items as $it): ?>
    <tr>
        <td><?= formatDate($it['item_date'], 'm/d') ?></td>
        <td><?= h($it['item_category']) ?></td>
        <td><?= h($it['item_name']) ?></td>
        <td class="amount"><?= $it['quantity'] ?></td>
        <td><?= h($it['unit']) ?></td>
        <td class="amount"><?= formatCurrency($it['unit_price']) ?></td>
        <td class="amount"><?= formatCurrency($it['amount']) ?></td>
        <td style="text-align:center;"><?= $it['is_covered'] ? '<span style="color:green">○</span>' : '×' ?></td>
    </tr>
    <?php 
    if ($it['is_covered']) $totalCovered += $it['amount'];
    else $totalNon += $it['amount'];
    endforeach; ?>
    </tbody>
</table>

<div class="summary">
    <table>
        <tr><th>診療費合計</th><td><?= formatCurrency($claim['total_medical_fee']) ?></td></tr>
        <tr><th>保険対象額</th><td><?= formatCurrency($totalCovered) ?></td></tr>
        <tr><th>保険対象外</th><td><?= formatCurrency($totalNon) ?></td></tr>
        <tr><th>補償割合</th><td><?= $claim['coverage_rate'] ?>%</td></tr>
        <?php if ($claim['deductible'] > 0): ?>
        <tr><th>免責額</th><td><?= formatCurrency($claim['deductible']) ?></td></tr>
        <?php endif; ?>
        <tr><th>保険金請求額</th><td style="color:#4f46e5;"><?= formatCurrency($claim['covered_amount']) ?></td></tr>
        <tr class="total"><th style="background:#4f46e5; color:#fff;">飼い主様ご負担額</th><td style="background:#4f46e5;"><?= formatCurrency($claim['owner_copay']) ?></td></tr>
    </table>
</div>

<div class="footer">
    <div>
        <strong><?= h($hospitalName) ?></strong><br>
        <?= h($hospitalAddress) ?><br>
        TEL: <?= h($hospitalPhone) ?>
    </div>
    <div class="stamp-area">
        病院印
    </div>
</div>

<?php if ($claim['notes']): ?>
<div style="margin-top:20px; padding:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; font-size:9pt;">
    <strong>備考:</strong> <?= h($claim['notes']) ?>
</div>
<?php endif; ?>

</body></html>
