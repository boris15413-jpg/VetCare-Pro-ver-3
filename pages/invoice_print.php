<?php
/** 領収書印刷 (修正版: 設定連動・サイズ設定・レイアウト固定) */
$auth->requireLogin();

$id = (int)($_GET['id'] ?? 0);
$inv = $db->fetch("SELECT i.*, p.name as pname, p.patient_code, o.name as oname, o.address as oaddr, s.name as staff_name 
                   FROM invoices i 
                   JOIN patients p ON i.patient_id=p.id 
                   JOIN owners o ON i.owner_id=o.id 
                   JOIN staff s ON i.created_by=s.id 
                   WHERE i.id=?", [$id]);

if (!$inv) { echo '<div style="padding:20px;">領収書が見つかりません</div>'; exit; }

$items = $db->fetchAll("SELECT * FROM invoice_items WHERE invoice_id=?", [$id]);

// 病院設定の取得 (設定画面のデータを反映)
$hospName = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'hospital_name'")['setting_value'] ?? APP_NAME;
$hospAddr = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'hospital_address'")['setting_value'] ?? '';
$hospPhone = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'hospital_phone'")['setting_value'] ?? '';
$hospLogoFile = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'hospital_logo'")['setting_value'] ?? '';
$hospStampFile = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'stamp_image'")['setting_value'] ?? '';

$hospLogoPath = ($hospLogoFile && file_exists(UPLOAD_DIR . $hospLogoFile)) ? 'uploads/' . $hospLogoFile : null;
$hospStampPath = ($hospStampFile && file_exists(UPLOAD_DIR . $hospStampFile)) ? 'uploads/' . $hospStampFile : null;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>領収書 - <?= h($inv['invoice_number']) ?></title>
    <style>
        /* --- 基本設定 --- */
        body {
            font-family: "Hiragino Mincho ProN", "Yu Mincho", serif;
            margin: 0; padding: 0; background: #eee;
            font-size: 10pt; color: #333;
        }
        * { box-sizing: border-box; }

        /* --- 画面用UI --- */
        .toolbar {
            position: fixed; top: 0; left: 0; right: 0; background: #333; color: #fff; padding: 10px 20px; z-index: 999;
            display: flex; justify-content: space-between; align-items: center; font-family: sans-serif;
        }
        .toolbar select { padding: 5px; border-radius: 4px; border: none; }
        .print-btn { background: #2563eb; color: #fff; border: none; padding: 8px 16px; cursor: pointer; border-radius: 4px; font-weight: bold; }

        /* --- プレビューエリア --- */
        .preview-container {
            padding: 70px 20px 20px; text-align: center;
            overflow: auto; height: 100vh;
        }
        
        .sheet {
            background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            margin: 0 auto; text-align: left; position: relative;
            /* Flexboxで縦並びレイアウトを構築 */
            display: flex; flex-direction: column;
            padding: 15mm;
        }

        /* --- コンテンツレイアウト --- */
        .header-area { flex: 0 0 auto; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 10px; }
        .title { text-align: center; font-size: 20pt; font-weight: bold; letter-spacing: 0.5em; margin-bottom: 5px; }
        .meta-info { display: flex; justify-content: space-between; align-items: flex-end; }
        
        .client-info { flex: 0 0 auto; margin-bottom: 15px; }
        .client-name { font-size: 16pt; font-weight: bold; border-bottom: 1px solid #333; padding-right: 20px; }

        .summary-area { 
            flex: 0 0 auto; 
            border: 2px solid #333; padding: 10px 20px; margin-bottom: 15px; 
            display: flex; justify-content: space-between; align-items: center; 
            background: #f9f9f9;
        }
        .total-amount { font-size: 22pt; font-weight: bold; }

        /* 明細テーブル (これが伸び縮みして余白を埋める) */
        .items-container {
            flex: 1 1 auto; /* 残りスペースを埋める */
            border: 1px solid #ccc;
            position: relative;
            overflow: hidden; /* はみ出し防止 */
        }
        .items-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        .items-table th { background: #eee; border-bottom: 1px solid #999; padding: 5px; text-align: center; }
        .items-table td { border-bottom: 1px dotted #ccc; padding: 5px; }
        .col-right { text-align: right; }
        .col-center { text-align: center; }

        /* フッターエリア (明細の下、固定) */
        .footer-area { 
            flex: 0 0 auto; 
            margin-top: 10px; 
            border-top: 2px solid #333; 
            padding-top: 10px; 
        }
        .calc-table { width: 50%; margin-left: auto; font-size: 10pt; margin-bottom: 10px; }
        .calc-table td { padding: 2px 5px; }
        
        /* 病院情報・印鑑 (横並び) */
        .hospital-footer {
            display: flex; justify-content: flex-end; align-items: flex-start; gap: 20px; margin-top: 10px;
        }
        .hosp-info-text { text-align: right; line-height: 1.4; font-size: 9pt; }
        .hosp-name-large { font-size: 12pt; font-weight: bold; }
        
        .logo-img { height: 50px; width: auto; object-fit: contain; }
        .stamp-img { width: 70px; height: 70px; object-fit: contain; opacity: 0.8; }

        /* --- 用紙サイズ定義 --- */
        .size-a4 { width: 210mm; height: 297mm; }
        .size-a5 { width: 148mm; height: 210mm; }
        .size-b5 { width: 182mm; height: 257mm; }

        /* --- 印刷時スタイル --- */
        @media print {
            body { background: #fff; margin: 0; }
            .toolbar, .preview-container::-webkit-scrollbar { display: none; }
            .preview-container { padding: 0; height: auto; overflow: visible; }
            .sheet { 
                box-shadow: none; margin: 0; 
                position: absolute; top: 0; left: 0;
                /* 用紙いっぱいに */
                width: 100%; height: 100%; 
            }
            .items-container { border: 1px solid #000; }
            .header-area, .summary-area, .footer-area { border-color: #000; }
            @page { margin: 0; size: auto; }
        }
    </style>
</head>
<body>

<div class="toolbar no-print">
    <div style="display:flex; gap:10px; align-items:center;">
        <span>用紙サイズ:</span>
        <select id="pageSize" onchange="changeSize()">
            <option value="size-a4">A4 (通常)</option>
            <option value="size-a5">A5 (小さめ)</option>
            <option value="size-b5">B5</option>
        </select>
    </div>
    <button class="print-btn" onclick="window.print()">印刷する</button>
</div>

<div class="preview-container">
    <div id="sheet" class="sheet size-a4">
        
        <div class="header-area">
            <div class="title">領 収 書</div>
            <div class="meta-info">
                <small>No. <?= h($inv['invoice_number']) ?></small>
                <span>発行日: <?= date('Y年m月d日') ?></span>
            </div>
        </div>

        <div class="client-info">
            <div class="client-name"><?= h($inv['oname']) ?> 様</div>
            <div style="margin-top:5px;">患畜名: <strong><?= h($inv['pname']) ?></strong></div>
        </div>

        <div class="summary-area">
            <span>ご請求金額 (税込)</span>
            <span class="total-amount"><?= formatCurrency($inv['total']) ?></span>
        </div>

        <div class="items-container">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:40%">項目名</th>
                        <th style="width:15%">単価</th>
                        <th style="width:15%">数量</th>
                        <th style="width:15%">金額</th>
                        <th style="width:15%">区分</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= h($it['item_name']) ?></td>
                        <td class="col-right"><?= number_format($it['unit_price']) ?></td>
                        <td class="col-center"><?= $it['quantity'] ?><?= h($it['unit']) ?></td>
                        <td class="col-right"><?= number_format($it['amount']) ?></td>
                        <td class="col-center"><small><?= h($it['category']) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
            </table>
        </div>

        <div class="footer-area">
            <table class="calc-table">
                <tr><td>小計</td><td class="col-right"><?= formatCurrency($inv['subtotal']) ?></td></tr>
                <tr><td>消費税 (10%)</td><td class="col-right"><?= formatCurrency($inv['tax']) ?></td></tr>
                <?php if ($inv['discount'] > 0): ?><tr><td>値引き</td><td class="col-right">-<?= formatCurrency($inv['discount']) ?></td></tr><?php endif; ?>
                <?php if ($inv['insurance_covered'] > 0): ?><tr><td>保険負担</td><td class="col-right">-<?= formatCurrency($inv['insurance_covered']) ?></td></tr><?php endif; ?>
            </table>

            <div style="margin-bottom:10px; font-size:9pt;">
                お支払方法: <?php 
                    $methods = ['cash'=>'現金','credit'=>'クレジットカード','electronic'=>'電子マネー','bank'=>'銀行振込'];
                    echo $methods[$inv['payment_method']] ?? $inv['payment_method']; 
                ?>
            </div>

            <div class="hospital-footer">
                <?php if ($hospLogoPath): ?>
                    <img src="<?= h($hospLogoPath) ?>" class="logo-img" alt="Logo">
                <?php endif; ?>

                <div class="hosp-info-text">
                    <div class="hosp-name-large"><?= h($hospName) ?></div>
                    <div>〒<?= h($hospAddr) ?></div>
                    <div>TEL: <?= h($hospPhone) ?></div>
                    <div style="font-size:8pt; margin-top:3px;">担当: <?= h($inv['staff_name']) ?></div>
                </div>

                <?php if ($hospStampPath): ?>
                    <img src="<?= h($hospStampPath) ?>" class="stamp-img" alt="印">
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
function changeSize() {
    const size = document.getElementById('pageSize').value;
    const sheet = document.getElementById('sheet');
    
    // クラスを入れ替え
    sheet.className = 'sheet ' + size;
    
    // 印刷設定用Styleタグ更新
    const oldStyle = document.getElementById('print-style');
    if (oldStyle) oldStyle.remove();
    
    const style = document.createElement('style');
    style.id = 'print-style';
    
    let pageSizeCSS = 'A4 portrait';
    if (size === 'size-a5') pageSizeCSS = '148mm 210mm'; // A5縦
    if (size === 'size-b5') pageSizeCSS = '182mm 257mm'; // B5縦
    
    style.innerHTML = `@page { size: ${pageSizeCSS}; margin: 0; }`;
    document.head.appendChild(style);
}

// 初期化
changeSize();
</script>

</body>
</html>