/* VetCare Pro - JavaScript */

// サイドバートグル
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}

// フラッシュメッセージの自動消去
document.addEventListener('DOMContentLoaded', function() {
    // フラッシュメッセージ
    document.querySelectorAll('.alert-dismissible').forEach(function(el) {
        setTimeout(function() { 
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 5000);
    });

    // Flatpickr初期化
    document.querySelectorAll('.datepicker').forEach(function(el) {
        flatpickr(el, { locale: 'ja', dateFormat: 'Y-m-d' });
    });

    document.querySelectorAll('.datetimepicker').forEach(function(el) {
        flatpickr(el, { locale: 'ja', enableTime: true, dateFormat: 'Y-m-d H:i', time_24hr: true });
    });

    document.querySelectorAll('.timepicker').forEach(function(el) {
        flatpickr(el, { locale: 'ja', enableTime: true, noCalendar: true, dateFormat: 'H:i', time_24hr: true });
    });

    // 確認ダイアログ
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // テーブル行クリック
    document.querySelectorAll('tr[data-href]').forEach(function(el) {
        el.style.cursor = 'pointer';
        el.addEventListener('click', function(e) {
            if (e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON' && !e.target.closest('a') && !e.target.closest('button')) {
                window.location = this.dataset.href;
            }
        });
    });
});

// 体重計算ヘルパー
function calculateDosage(weight, mgPerKg) {
    return (weight * mgPerKg).toFixed(2);
}

// バイタルサインの正常範囲チェック
function checkVitalRange(value, type, species) {
    const ranges = {
        dog: { temperature: [37.5, 39.2], heart_rate: [60, 160], respiratory_rate: [10, 30] },
        cat: { temperature: [38.0, 39.5], heart_rate: [120, 240], respiratory_rate: [20, 40] }
    };
    const sp = ranges[species] || ranges.dog;
    const range = sp[type];
    if (!range || !value) return 'normal';
    if (value < range[0]) return 'low';
    if (value > range[1]) return 'high';
    return 'normal';
}

// バイタル入力時のリアルタイムチェック
function onVitalInput(input, type, species) {
    const val = parseFloat(input.value);
    const status = checkVitalRange(val, type, species || 'dog');
    input.classList.remove('border-danger', 'border-info', 'border-success');
    if (status === 'high') input.classList.add('border-danger');
    else if (status === 'low') input.classList.add('border-info');
}

// 印刷
function printDocument(elementId) {
    const content = document.getElementById(elementId);
    if (!content) return;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html><html><head>
        <meta charset="UTF-8">
        <title>印刷</title>
        <style>
            body { font-family: 'Yu Gothic','Hiragino Sans',serif; font-size:11pt; line-height:1.8; margin:0; padding:20mm; }
            table { width:100%; border-collapse:collapse; }
            th, td { border:1px solid #333; padding:6px 10px; }
            .doc-title { text-align:center; font-size:18pt; font-weight:bold; margin:20px 0 30px; letter-spacing:0.3em; }
            .doc-stamp { width:80px; height:80px; border:2px solid #c00; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#c00; font-weight:bold; }
            @media print { body { padding: 15mm; } }
        </style>
        </head><body>${content.innerHTML}</body></html>
    `);
    printWindow.document.close();
    printWindow.onload = function() { printWindow.print(); };
}

// 削除確認
function confirmDelete(msg) {
    return confirm(msg || 'このデータを削除してもよろしいですか？');
}

// AJAX POST
async function postData(url, data) {
    const formData = new FormData();
    for (const key in data) {
        formData.append(key, data[key]);
    }
    const res = await fetch(url, { method: 'POST', body: formData });
    return res.json();
}

// 通知トースト
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed shadow`;
    toast.style.cssText = 'top:70px;right:20px;z-index:9999;min-width:250px;animation:fadeIn 0.3s ease-out;';
    toast.innerHTML = `<div class="d-flex align-items-center"><i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'} me-2"></i>${message}</div>`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.5s'; setTimeout(() => toast.remove(), 500); }, 3000);
}

// オーダー自動計算
function updateOrderTotal() {
    const qty = parseFloat(document.getElementById('quantity')?.value || 0);
    const price = parseFloat(document.getElementById('unit_price')?.value || 0);
    const total = document.getElementById('total_price');
    if (total) total.value = Math.round(qty * price);
}
