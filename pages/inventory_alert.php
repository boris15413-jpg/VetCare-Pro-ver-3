<?php
/** 在庫アラート・発注リスト */
$auth->requireRole([ROLE_ADMIN, ROLE_VET, ROLE_NURSE]);

// 発注点以下の薬品を取得
$alerts = $db->fetchAll("SELECT * FROM drug_master WHERE stock_quantity <= min_stock AND is_active = 1 ORDER BY category, drug_name");
?>
<div class="fade-in">
    <h4 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>発注点割れ在庫リスト</h4>
    
    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                該当: <strong><?= count($alerts) ?></strong> 品目
            </div>
            <button class="btn btn-outline-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>リスト印刷</button>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>薬品名</th>
                            <th>メーカー</th>
                            <th>現在在庫</th>
                            <th>発注点(Min)</th>
                            <th>不足数</th>
                            <th>状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alerts)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-success"><i class="bi bi-check-circle me-2"></i>発注点を下回っている薬品はありません</td></tr>
                        <?php else: ?>
                        <?php foreach ($alerts as $d): 
                            $shortage = $d['min_stock'] - $d['stock_quantity'] + 1; // 最低でもこれだけ必要
                        ?>
                        <tr>
                            <td>
                                <strong><?= h($d['drug_name']) ?></strong><br>
                                <small class="text-muted"><?= h($d['drug_code']) ?></small>
                            </td>
                            <td><?= h($d['manufacturer']) ?></td>
                            <td class="fw-bold text-danger"><?= $d['stock_quantity'] ?> <?= h($d['unit']) ?></td>
                            <td><?= $d['min_stock'] ?></td>
                            <td>
                                <span class="badge bg-danger">不足</span>
                            </td>
                            <td>
                                <a href="?page=master_drugs&id=<?= $d['id'] ?>" class="btn btn-sm btn-light">編集/入庫</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>