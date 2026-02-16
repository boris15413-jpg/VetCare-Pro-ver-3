<?php
$auth->requireLogin();

// 病院設定の取得
$hospName = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'hospital_name'")['setting_value'] ?? APP_NAME;
$hospAddr = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'hospital_address'")['setting_value'] ?? '';
$hospLogoFile = $db->fetch("SELECT setting_value FROM hospital_settings WHERE setting_key = 'hospital_logo'")['setting_value'] ?? '';
$hospLogoPath = ($hospLogoFile && file_exists(UPLOAD_DIR . $hospLogoFile)) ? 'uploads/' . $hospLogoFile : null;

// 会計IDからのデータ取得
$id = (int)($_GET['invoice_id'] ?? 0);
$prefill = [];
if ($id) {
    $inv = $db->fetch("SELECT p.name FROM invoices i JOIN patients p ON i.patient_id=p.id WHERE i.id=?", [$id]);
    if ($inv) $prefill['patient_name'] = $inv['name'];
    
    $item = $db->fetch("SELECT * FROM invoice_items WHERE invoice_id = ? AND (category LIKE '%処方%' OR item_name LIKE '%錠%') LIMIT 1", [$id]);
    if ($item) {
        $prefill['drug_name'] = $item['item_name'];
        $prefill['usage'] = "1日__回 __日分";
    }
}
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="fw-bold mb-0"><i class="bi bi-capsule me-2"></i>薬袋発行</h4>
        <a href="?page=settings" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear me-1"></i>病院情報設定</a>
    </div>
    
    <div class="row g-3">
        <div class="col-md-4 no-print">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white fw-bold"><i class="bi bi-sliders me-2"></i>発行設定</div>
                <div class="card-body">
                    <form id="labelForm">
                        <div class="mb-3">
                            <label class="form-label">印刷サイズ</label>
                            <select id="paperSize" class="form-select" onchange="updatePreview()">
                                <option value="yakutai_a5">薬袋 (A5)</option>
                                <option value="yakutai_b6">薬袋 (B6/ハガキ)</option>
                            </select>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">患畜名</label>
                            <input type="text" id="pName" class="form-control fw-bold" placeholder="ポチ" value="<?= h($prefill['patient_name'] ?? '') ?>" oninput="updatePreview()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">薬品名</label>
                            <input type="text" id="dName" class="form-control" placeholder="アモキシシリン錠" value="<?= h($prefill['drug_name'] ?? '') ?>" oninput="updatePreview()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">用法・用量</label>
                            <textarea id="usage" class="form-control" rows="5" oninput="updatePreview()"><?= h($prefill['usage'] ?? "1日2回 朝夕食後\n1回1錠\n5日分") ?></textarea>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary" onclick="window.print()">
                                <i class="bi bi-printer me-2"></i>印刷する
                            </button>
                            <a href="?page=dashboard" class="btn btn-outline-secondary">戻る</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card h-100 bg-secondary bg-opacity-25 border-0 d-flex justify-content-center align-items-center">
                <div class="preview-scroll-wrapper p-4" style="overflow: auto; max-height: 80vh; width: 100%; text-align: center;">
                    
                    <div id="previewArea" class="bg-white shadow-sm position-relative mx-auto text-start">
                        
                        <div class="yakutai-layout">
                            
                            <div class="yakutai-header text-center mb-2">
                                <h2 class="fw-bold m-0 border-bottom border-dark pb-2 d-inline-block w-100" style="font-family: serif; letter-spacing: 0.2em;">内　用　薬</h2>
                            </div>
                            
                            <div class="yakutai-patient mt-4 mb-3 text-center">
                                <span id="prevName" class="fs-2 fw-bold border-bottom border-dark d-inline-block px-4" style="min-width: 200px; line-height: 1.2;">
                                    <?= h($prefill['patient_name'] ?? '') ?>
                                </span>
                                <span class="fs-4 ms-2">様</span>
                            </div>
                            
                            <div class="yakutai-content-box mb-3">
                                <h3 id="prevDrug" class="fw-bold mb-3 text-break" style="font-size: 1.4rem;">
                                    <?= h($prefill['drug_name'] ?? '薬品名') ?>
                                </h3>
                                <div id="prevUsage" class="fs-5" style="white-space:pre-wrap; line-height: 1.8;">
                                    <?= h($prefill['usage'] ?? "用法") ?>
                                </div>
                            </div>
                            
                            <div class="yakutai-footer mt-2">
                                <div class="d-flex align-items-center justify-content-center gap-3">
                                    <?php if ($hospLogoPath): ?>
                                    <div class="logo-box">
                                        <img src="<?= h($hospLogoPath) ?>" alt="ロゴ">
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-box">
                                        <h4 class="fw-bold mb-1" style="font-size: 1.1rem; color: #000;"><?= h($hospName) ?></h4>
                                        <div class="small text-muted" style="font-size: 0.8rem; line-height: 1.2; color: #333 !important;">
                                            <?= nl2br(h($hospAddr)) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        </div></div></div>
            </div>
        </div>
    </div>
</div>

<style>
/* --- 共通スタイル --- */
#previewArea {
    box-sizing: border-box;
    font-family: "Hiragino Mincho ProN", "Yu Mincho", serif;
    background: white;
}

/* レイアウトコンテナ */
.yakutai-layout {
    width: 100%;
    /* 画面確認用の枠線（印刷時は消す） */
    border: 1px solid #ddd; 
    padding: 15mm;
    box-sizing: border-box;
}

/* 薬内容ボックス (枠線あり) */
.yakutai-content-box {
    border: 2px solid #333;
    border-radius: 8px;
    padding: 20px;
    width: 100%;
    box-sizing: border-box;
}

/* 病院情報エリア */
.logo-box {
    height: 50px;
    flex-shrink: 0;
}
.logo-box img {
    height: 100%;
    width: auto;
    object-fit: contain;
}


/* A5サイズ */
.size-a5 { 
    width: 148mm; 
    min-height: 210mm; 
}
/* A5の時のボックス高さ固定 */
.size-a5 .yakutai-content-box {
    height: 110mm; 
}

/* B6サイズ */
.size-b6 { 
    width: 128mm; 
    min-height: 182mm; 
}
/* B6の時のボックス高さ固定 */
.size-b6 .yakutai-content-box {
    height: 85mm; 
}


/* --- 印刷用スタイル --- */
@media print {
    /* 1. 不要な要素を非表示にする強力なリセット */
    body * {
        visibility: hidden;
    }
    
    /* 2. 印刷対象のみを表示 */
    #previewArea, #previewArea * {
        visibility: visible;
    }

    /* 3. 印刷領域の配置とサイズ */
    #previewArea {
        position: absolute;
        top: 0;
        left: 0;
        margin: 0;
        padding: 0;
        box-shadow: none !important;
        background: white !important;
    }

    /* 4. コンテナの調整 */
    .yakutai-layout {
        border: none; /* 外枠を消す */
        padding: 15mm; /* 印刷時の余白 */
    }
    
    /* 5. スクロールバー対策 */
    .preview-scroll-wrapper, body, html {
        overflow: visible !important;
        height: auto !important;
    }

    /* 6. 色の強制 */
    .yakutai-content-box {
        border-color: #000 !important;
    }
    .text-muted {
        color: #000 !important;
    }

    /* 7. 用紙設定 */
    @page {
        margin: 0;
        size: auto; 
    }
}
</style>

<script>
function updatePreview() {
    // 入力値の反映
    document.getElementById('prevName').textContent = document.getElementById('pName').value || '　　　　';
    document.getElementById('prevDrug').textContent = document.getElementById('dName').value || '薬品名';
    document.getElementById('prevUsage').textContent = document.getElementById('usage').value || '用法';
    
    // サイズ変更処理
    const size = document.getElementById('paperSize').value;
    const area = document.getElementById('previewArea');
    
    // クラスリセット
    area.className = 'bg-white shadow-sm position-relative mx-auto text-start'; 
    const oldStyle = document.getElementById('print-page-style');
    if (oldStyle) oldStyle.remove();

    const style = document.createElement('style');
    style.id = 'print-page-style';

    if (size === 'yakutai_a5') {
        area.classList.add('size-a5');
        // A5縦
        style.innerHTML = '@page { size: 148mm 210mm; margin: 0; }';
    } else if (size === 'yakutai_b6') {
        area.classList.add('size-b6');
        // B6縦
        style.innerHTML = '@page { size: 128mm 182mm; margin: 0; }';
    }
    document.head.appendChild(style);
}

// 初期実行
document.addEventListener('DOMContentLoaded', updatePreview);
</script>