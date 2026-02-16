<?php
/**
 * データベース構造更新用スクリプト
 */
if (!$auth->hasRole('admin')) {
    redirect('?page=dashboard');
}

$pdo = $db->getPDO();
$messages = [];

// Run all migrations
$migrationFiles = glob(__DIR__ . '/../migrations/*.php');
sort($migrationFiles);

echo '<div class="fade-in">';
echo '<h4 class="fw-bold mb-3"><i class="bi bi-database-gear me-2"></i>データベース更新</h4>';
echo '<div class="card"><div class="card-body">';

foreach ($migrationFiles as $mf) {
    $basename = basename($mf);
    echo "<h6 class='fw-bold mt-3'>{$basename}</h6>";
    try {
        ob_start();
        require $mf;
        $output = ob_get_clean();
        echo "<pre class='bg-light p-2 rounded small'>" . h($output) . "</pre>";
    } catch (Exception $e) {
        ob_end_clean();
        echo "<div class='alert alert-warning small'>" . h($e->getMessage()) . "</div>";
    }
}

echo '<hr><div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>データベース更新が完了しました。</div>';
echo '<a href="?page=settings" class="btn btn-primary"><i class="bi bi-gear me-1"></i>施設設定画面へ</a>';
echo '</div></div></div>';
?>
