<?php
/**
 * VetCare Pro v2.0 - Plugin Manager
 */
$auth->requireRole([ROLE_ADMIN]);

$pm = PluginManager::getInstance();
$msg = '';

// Handle enable/disable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $slug = $_POST['plugin_slug'] ?? '';
    if ($_POST['action'] === 'enable') {
        $result = $pm->enablePlugin($slug);
        $msg = isset($result['error']) ? $result['error'] : "プラグイン「{$slug}」を有効化しました。";
    } elseif ($_POST['action'] === 'disable') {
        $pm->disablePlugin($slug);
        $msg = "プラグイン「{$slug}」を無効化しました。";
    }
}

$plugins = $pm->discoverPlugins();
?>

<div class="fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-puzzle me-2"></i>プラグイン管理</h4>
            <small class="text-muted">プラグインの追加・有効化・無効化を管理します</small>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success glass-alert"><?= h($msg) ?></div><?php endif; ?>

    <!-- Plugin Info -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6 class="fw-bold mb-1"><i class="bi bi-info-circle me-2"></i>プラグインの追加方法</h6>
                    <small class="text-muted">
                        <code>plugins/</code> ディレクトリにプラグインフォルダを配置してください。
                        各プラグインには <code>manifest.json</code> と <code>index.php</code> が必要です。
                    </small>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-primary px-3 py-2">インストール済み: <?= count($plugins) ?></span>
                    <span class="badge bg-success px-3 py-2">有効: <?= count(array_filter($plugins, fn($p) => $p['enabled'] ?? false)) ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($plugins)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="bi bi-puzzle d-block"></i>
                <h5>プラグインが見つかりません</h5>
                <p><code>plugins/</code> ディレクトリにプラグインを配置してください。</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($plugins as $slug => $plugin): ?>
        <div class="col-md-6 col-lg-4">
            <div class="plugin-card <?= ($plugin['enabled'] ?? false) ? 'plugin-enabled' : '' ?>">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h6 class="fw-bold mb-1"><?= h($plugin['name'] ?? $slug) ?></h6>
                        <small class="text-muted">v<?= h($plugin['version'] ?? '1.0.0') ?></small>
                    </div>
                    <?php if ($plugin['enabled'] ?? false): ?>
                    <span class="badge bg-success"><i class="bi bi-check me-1"></i>有効</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">無効</span>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($plugin['description'])): ?>
                <p class="text-muted small mb-3"><?= h($plugin['description']) ?></p>
                <?php endif; ?>
                
                <?php if (isset($plugin['author'])): ?>
                <small class="text-muted d-block mb-3"><i class="bi bi-person me-1"></i><?= h($plugin['author']) ?></small>
                <?php endif; ?>
                
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="plugin_slug" value="<?= h($slug) ?>">
                    <?php if ($plugin['enabled'] ?? false): ?>
                    <button type="submit" name="action" value="disable" class="btn btn-outline-danger btn-sm w-100">
                        <i class="bi bi-power me-1"></i>無効化
                    </button>
                    <?php else: ?>
                    <button type="submit" name="action" value="enable" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-plug me-1"></i>有効化
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Plugin Development Guide -->
    <div class="card mt-4">
        <div class="card-header"><i class="bi bi-code-slash me-2"></i>プラグイン開発ガイド</div>
        <div class="card-body">
            <pre class="bg-dark text-light p-3 rounded" style="font-size:0.8rem; overflow-x:auto;"><code>// plugins/my_plugin/manifest.json
{
    "name": "My Plugin",
    "version": "1.0.0",
    "description": "プラグインの説明",
    "author": "Your Name"
}

// plugins/my_plugin/index.php
&lt;?php
// サイドバーにメニューを追加
add_hook('sidebar_menu', function($page, $auth) {
    echo '&lt;a href="?page=my_plugin_page" class="nav-link"&gt;My Plugin&lt;/a&gt;';
});

// カスタムページを登録
add_hook('register_routes', function() {
    return ['my_plugin_page' => 'plugins/my_plugin/page.php'];
});</code></pre>
        </div>
    </div>
</div>
