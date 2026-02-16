<?php
/** 飼い主一覧 */
$search = $_GET['q'] ?? '';
$page_num = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$where = 'o.is_active = 1';
$params = [];
if ($search) {
    $where .= " AND (o.name LIKE ? OR o.owner_code LIKE ? OR o.phone LIKE ? OR o.email LIKE ?)";
    $s = "%{$search}%"; $params = [$s,$s,$s,$s];
}
$total = $db->fetch("SELECT COUNT(*) as cnt FROM owners o WHERE {$where}", $params)['cnt'];
$offset = ($page_num - 1) * $perPage;
$owners_list = $db->fetchAll("SELECT o.*, (SELECT COUNT(*) FROM patients p WHERE p.owner_id = o.id AND p.is_active = 1) as pet_count FROM owners o WHERE {$where} ORDER BY o.updated_at DESC LIMIT {$perPage} OFFSET {$offset}", $params);
?>
<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>飼い主一覧</h4>
        <a href="?page=owner_form" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>新規登録</a>
    </div>
    <div class="card mb-3"><div class="card-body py-2">
        <form method="GET" class="row g-2"><input type="hidden" name="page" value="owners">
            <div class="col-9 col-md-8"><div class="search-box"><i class="bi bi-search"></i>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="名前・電話番号で検索" value="<?= h($search) ?>">
            </div></div>
            <div class="col-3 col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">検索</button></div>
        </form>
    </div></div>
    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0"><thead><tr>
            <th>飼い主番号</th><th>氏名</th><th class="d-none d-md-table-cell">電話番号</th><th class="d-none d-lg-table-cell">住所</th><th>登録数</th>
        </tr></thead><tbody>
        <?php foreach ($owners_list as $ow): ?>
        <tr data-href="?page=owner_form&id=<?= $ow['id'] ?>">
            <td><code><?= h($ow['owner_code']) ?></code></td>
            <td><strong><?= h($ow['name']) ?></strong><br><small class="text-muted"><?= h($ow['name_kana']) ?></small></td>
            <td class="d-none d-md-table-cell"><?= h($ow['phone']) ?></td>
            <td class="d-none d-lg-table-cell"><?= h(mb_substr($ow['address'], 0, 20)) ?></td>
            <td><span class="badge bg-info"><?= $ow['pet_count'] ?>頭</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
    </div></div>
    <div class="card-footer text-center"><small class="text-muted"><?= $total ?>件</small>
        <?= pagination($total, $page_num, $perPage, '?page=owners&q=' . urlencode($search)) ?>
    </div></div>
</div>
