<?php
/**
 * VetCare Pro v2.0 - Plugin System
 * Provides hooks, events, and plugin management
 */

class PluginManager {
    private static $instance = null;
    private $hooks = [];
    private $plugins = [];
    private $loadedPlugins = [];
    
    private function __construct() {
        $this->loadPluginRegistry();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register a hook callback
     */
    public function addHook($hookName, $callback, $priority = 10) {
        if (!isset($this->hooks[$hookName])) {
            $this->hooks[$hookName] = [];
        }
        $this->hooks[$hookName][] = [
            'callback' => $callback,
            'priority' => $priority
        ];
        // Sort by priority
        usort($this->hooks[$hookName], fn($a, $b) => $a['priority'] <=> $b['priority']);
    }
    
    /**
     * Execute a hook (action - no return value expected)
     */
    public function doAction($hookName, ...$args) {
        if (!isset($this->hooks[$hookName])) return;
        foreach ($this->hooks[$hookName] as $hook) {
            call_user_func_array($hook['callback'], $args);
        }
    }
    
    /**
     * Execute a filter hook (returns modified value)
     */
    public function applyFilter($hookName, $value, ...$args) {
        if (!isset($this->hooks[$hookName])) return $value;
        foreach ($this->hooks[$hookName] as $hook) {
            $value = call_user_func($hook['callback'], $value, ...$args);
        }
        return $value;
    }
    
    /**
     * Load plugin registry from JSON
     */
    private function loadPluginRegistry() {
        if (file_exists(PLUGINS_ENABLED_FILE)) {
            $json = file_get_contents(PLUGINS_ENABLED_FILE);
            $this->plugins = json_decode($json, true) ?: [];
        }
    }
    
    /**
     * Save plugin registry
     */
    private function savePluginRegistry() {
        $dir = dirname(PLUGINS_ENABLED_FILE);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents(PLUGINS_ENABLED_FILE, json_encode($this->plugins, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Discover available plugins
     */
    public function discoverPlugins() {
        $available = [];
        if (!is_dir(PLUGINS_DIR)) {
            mkdir(PLUGINS_DIR, 0755, true);
            return $available;
        }
        
        $dirs = glob(PLUGINS_DIR . '*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $manifestFile = $dir . '/manifest.json';
            $mainFile = $dir . '/index.php';
            
            if (file_exists($manifestFile)) {
                $manifest = json_decode(file_get_contents($manifestFile), true);
                if ($manifest) {
                    $slug = basename($dir);
                    $manifest['slug'] = $slug;
                    $manifest['path'] = $dir;
                    $manifest['enabled'] = isset($this->plugins[$slug]) && $this->plugins[$slug]['enabled'];
                    $available[$slug] = $manifest;
                }
            }
        }
        return $available;
    }
    
    /**
     * Enable a plugin
     */
    public function enablePlugin($slug) {
        $pluginDir = PLUGINS_DIR . $slug;
        $manifestFile = $pluginDir . '/manifest.json';
        
        if (!file_exists($manifestFile)) {
            return ['error' => 'プラグインが見つかりません'];
        }
        
        $manifest = json_decode(file_get_contents($manifestFile), true);
        $this->plugins[$slug] = [
            'enabled' => true,
            'name' => $manifest['name'] ?? $slug,
            'version' => $manifest['version'] ?? '1.0.0',
            'enabled_at' => date('Y-m-d H:i:s')
        ];
        $this->savePluginRegistry();
        
        // Run install hook if exists
        $installFile = $pluginDir . '/install.php';
        if (file_exists($installFile)) {
            require_once $installFile;
        }
        
        return ['success' => true];
    }
    
    /**
     * Disable a plugin
     */
    public function disablePlugin($slug) {
        if (isset($this->plugins[$slug])) {
            $this->plugins[$slug]['enabled'] = false;
            $this->savePluginRegistry();
        }
        return ['success' => true];
    }
    
    /**
     * Load all enabled plugins
     */
    public function loadEnabledPlugins() {
        foreach ($this->plugins as $slug => $info) {
            if (!($info['enabled'] ?? false)) continue;
            $mainFile = PLUGINS_DIR . $slug . '/index.php';
            if (file_exists($mainFile) && !isset($this->loadedPlugins[$slug])) {
                try {
                    require_once $mainFile;
                    $this->loadedPlugins[$slug] = true;
                } catch (Exception $e) {
                    error_log("Plugin load error ({$slug}): " . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Get registered sidebar menu items from plugins
     */
    public function getPluginMenuItems() {
        $items = [];
        $this->doAction('register_menu_items', $items);
        return $items;
    }
    
    /**
     * Get registered page routes from plugins
     */
    public function getPluginRoutes() {
        $routes = [];
        if (isset($this->hooks['register_routes'])) {
            foreach ($this->hooks['register_routes'] as $hook) {
                $result = call_user_func($hook['callback']);
                if (is_array($result)) {
                    $routes = array_merge($routes, $result);
                }
            }
        }
        return $routes;
    }
    
    public function getEnabledPlugins() {
        return array_filter($this->plugins, fn($p) => $p['enabled'] ?? false);
    }
    
    public function isPluginEnabled($slug) {
        return isset($this->plugins[$slug]) && ($this->plugins[$slug]['enabled'] ?? false);
    }
}

// Global shortcut functions
function add_hook($name, $callback, $priority = 10) {
    PluginManager::getInstance()->addHook($name, $callback, $priority);
}

function do_action($name, ...$args) {
    PluginManager::getInstance()->doAction($name, ...$args);
}

function apply_filter($name, $value, ...$args) {
    return PluginManager::getInstance()->applyFilter($name, $value, ...$args);
}
