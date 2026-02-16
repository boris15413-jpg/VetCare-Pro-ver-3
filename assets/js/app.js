/**
 * VetCare Pro v2.0 - Enhanced JavaScript Framework
 */

// ===== Sidebar Toggle =====
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
}

// ===== Theme Management =====
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    document.cookie = `vc_theme=${next};path=/;max-age=${365*24*60*60}`;
    
    // Update icon
    const icon = document.querySelector('.topbar-right [onclick="toggleTheme()"] i');
    if (icon) {
        icon.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    }
}

// ===== DOM Ready =====
document.addEventListener('DOMContentLoaded', function() {
    // Flash message auto-dismiss
    document.querySelectorAll('.alert-dismissible').forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity 0.5s, transform 0.5s';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-10px)';
            setTimeout(() => el.remove(), 500);
        }, 5000);
    });

    // Initialize Flatpickr
    document.querySelectorAll('.datepicker').forEach(el => {
        flatpickr(el, { locale: 'ja', dateFormat: 'Y-m-d', allowInput: true });
    });

    document.querySelectorAll('.datetimepicker').forEach(el => {
        flatpickr(el, { locale: 'ja', enableTime: true, dateFormat: 'Y-m-d H:i', time_24hr: true, allowInput: true });
    });

    document.querySelectorAll('.timepicker').forEach(el => {
        flatpickr(el, { locale: 'ja', enableTime: true, noCalendar: true, dateFormat: 'H:i', time_24hr: true, allowInput: true });
    });

    // Confirm dialog
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // Table row click navigation
    document.querySelectorAll('tr[data-href]').forEach(el => {
        el.style.cursor = 'pointer';
        el.addEventListener('click', function(e) {
            if (e.target.closest('a, button, input, select, .no-navigate')) return;
            window.location = this.dataset.href;
        });
    });

    // Fade-in animation for page elements
    document.querySelectorAll('.fade-in').forEach((el, i) => {
        el.style.animationDelay = (i * 0.05) + 's';
    });

    // Auto-resize textareas
    document.querySelectorAll('textarea[data-autoresize]').forEach(el => {
        el.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
        el.dispatchEvent(new Event('input'));
    });

    // Real-time search
    document.querySelectorAll('[data-search-target]').forEach(input => {
        const target = document.getElementById(input.dataset.searchTarget);
        if (!target) return;
        input.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            target.querySelectorAll('[data-searchable]').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    });
});

// ===== Dosage Calculator =====
function calculateDosage(weight, mgPerKg) {
    return (weight * mgPerKg).toFixed(2);
}

// ===== Vital Sign Check =====
function checkVitalRange(value, type, species) {
    const ranges = {
        dog: { temperature: [37.5, 39.2], heart_rate: [60, 160], respiratory_rate: [10, 30] },
        cat: { temperature: [38.0, 39.5], heart_rate: [120, 240], respiratory_rate: [20, 40] },
        rabbit: { temperature: [38.5, 40.0], heart_rate: [130, 325], respiratory_rate: [30, 60] }
    };
    const sp = ranges[species] || ranges.dog;
    const range = sp[type];
    if (!range || !value) return 'normal';
    if (value < range[0]) return 'low';
    if (value > range[1]) return 'high';
    return 'normal';
}

function onVitalInput(input, type, species) {
    const val = parseFloat(input.value);
    const status = checkVitalRange(val, type, species || 'dog');
    input.classList.remove('border-danger', 'border-info', 'border-success', 'bg-danger-subtle', 'bg-info-subtle');
    if (status === 'high') {
        input.classList.add('border-danger', 'bg-danger-subtle');
    } else if (status === 'low') {
        input.classList.add('border-info', 'bg-info-subtle');
    }
}

// ===== Print =====
function printDocument(elementId) {
    const content = document.getElementById(elementId);
    if (!content) return;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`<!DOCTYPE html><html><head>
        <meta charset="UTF-8"><title>印刷</title>
        <style>
            body { font-family: 'Yu Gothic','Hiragino Sans',serif; font-size:11pt; line-height:1.8; margin:0; padding:20mm; }
            table { width:100%; border-collapse:collapse; }
            th, td { border:1px solid #333; padding:6px 10px; }
            .doc-title { text-align:center; font-size:18pt; font-weight:bold; margin:20px 0 30px; letter-spacing:0.3em; }
            .doc-stamp { width:80px; height:80px; border:2px solid #c00; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#c00; font-weight:bold; }
            @media print { body { padding: 15mm; } }
        </style></head><body>${content.innerHTML}</body></html>`);
    printWindow.document.close();
    printWindow.onload = function() { printWindow.print(); };
}

function confirmDelete(msg) {
    return confirm(msg || 'このデータを削除してもよろしいですか？');
}

// ===== AJAX Helpers =====
async function postData(url, data) {
    const formData = new FormData();
    for (const key in data) {
        formData.append(key, data[key]);
    }
    try {
        const res = await fetch(url, { method: 'POST', body: formData });
        return await res.json();
    } catch (e) {
        console.error('API Error:', e);
        return { error: e.message };
    }
}

async function fetchJSON(url) {
    try {
        const res = await fetch(url);
        return await res.json();
    } catch (e) {
        console.error('Fetch Error:', e);
        return { error: e.message };
    }
}

// ===== Toast Notification =====
function showToast(message, type = 'success', duration = 4000) {
    const icons = {
        success: 'bi-check-circle-fill',
        danger: 'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-circle-fill',
        info: 'bi-info-circle-fill'
    };
    
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} shadow-lg border-0 glass-alert`;
    toast.style.cssText = `
        position:fixed; top:80px; right:24px; z-index:9999;
        min-width:280px; max-width:400px;
        animation: fadeIn 0.3s ease-out;
        border-radius: 12px;
        display:flex; align-items:center; gap:10px;
        padding: 14px 20px;
    `;
    toast.innerHTML = `<i class="bi ${icons[type] || icons.info} fs-5"></i><div>${message}</div>`;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transition = 'opacity 0.5s, transform 0.5s';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        setTimeout(() => toast.remove(), 500);
    }, duration);
}

// ===== Order Total Calculation =====
function updateOrderTotal() {
    const qty = parseFloat(document.getElementById('quantity')?.value || 0);
    const price = parseFloat(document.getElementById('unit_price')?.value || 0);
    const total = document.getElementById('total_price');
    if (total) total.value = Math.round(qty * price);
}

// ===== Time Slot Selection (for booking) =====
function selectTimeSlot(el, time) {
    document.querySelectorAll('.time-slot.selected').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    const hidden = document.getElementById('selected_time');
    if (hidden) hidden.value = time;
}

// ===== Reception Queue Management =====
function updateAppointmentStatus(id, status) {
    postData('index.php?page=api_booking', {
        action: 'update_status',
        appointment_id: id,
        status: status,
        csrf_token: document.querySelector('[name=csrf_token]')?.value || ''
    }).then(res => {
        if (res.success) {
            location.reload();
        } else {
            showToast(res.error || 'エラーが発生しました', 'danger');
        }
    });
}

// ===== CSV Import Preview =====
function previewCSV(input) {
    const file = input.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const text = e.target.result;
        const lines = text.split('\n').filter(l => l.trim());
        const preview = document.getElementById('csv-preview');
        if (!preview || lines.length === 0) return;
        
        let html = '<div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr>';
        const headers = lines[0].split(',');
        headers.forEach(h => { html += `<th class="bg-light">${h.trim().replace(/"/g, '')}</th>`; });
        html += '</tr></thead><tbody>';
        
        for (let i = 1; i < Math.min(lines.length, 6); i++) {
            const cols = lines[i].split(',');
            html += '<tr>';
            cols.forEach(c => { html += `<td>${c.trim().replace(/"/g, '')}</td>`; });
            html += '</tr>';
        }
        html += '</tbody></table></div>';
        if (lines.length > 6) html += `<small class="text-muted">他 ${lines.length - 6} 行...</small>`;
        
        preview.innerHTML = html;
        preview.style.display = 'block';
    };
    reader.readAsText(file);
}

// ===== Auto-Refresh for Reception Display =====
function startAutoRefresh(intervalMs = 30000) {
    setInterval(() => {
        fetch(window.location.href + '&ajax=1')
            .then(r => r.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.querySelector('.reception-content');
                const oldContent = document.querySelector('.reception-content');
                if (newContent && oldContent) {
                    oldContent.innerHTML = newContent.innerHTML;
                }
            })
            .catch(() => {});
    }, intervalMs);
}
