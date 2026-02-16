<?php
/**
 * VetCare Pro - Router
 * Centralized routing with middleware support
 */
class Router {
    private static $instance = null;
    private $routes = [];
    private $middlewares = [];
    private $currentPage = '';
    private $pageFile = '';

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a route
     */
    public function register($name, $file, $options = []) {
        $this->routes[$name] = array_merge([
            'file' => $file,
            'layout' => 'default',     // default, none, login, install
            'auth' => true,             // requires login?
            'roles' => [],              // empty = all roles
            'public' => false,          // public page?
            'title' => '',
        ], $options);
        return $this;
    }

    /**
     * Register multiple routes at once
     */
    public function registerMany(array $routes) {
        foreach ($routes as $name => $config) {
            if (is_string($config)) {
                $this->register($name, $config);
            } else {
                $file = $config['file'] ?? $config[0] ?? '';
                unset($config['file'], $config[0]);
                $this->register($name, $file, $config);
            }
        }
        return $this;
    }

    /**
     * Add middleware
     */
    public function addMiddleware($name, callable $handler) {
        $this->middlewares[$name] = $handler;
        return $this;
    }

    /**
     * Resolve current page
     */
    public function resolve() {
        $page = $_GET['page'] ?? ($_SESSION['user_id'] ?? false ? 'dashboard' : 'login');
        $this->currentPage = $page;

        // Check route exists
        $route = $this->routes[$page] ?? null;
        if (!$route || !file_exists(BASE_PATH . '/' . $route['file'])) {
            $this->currentPage = 'dashboard';
            $route = $this->routes['dashboard'] ?? null;
        }

        $this->pageFile = $route ? $route['file'] : 'pages/dashboard.php';
        return $this;
    }

    /**
     * Get current page name
     */
    public function getCurrentPage() {
        return $this->currentPage;
    }

    /**
     * Get current route config
     */
    public function getCurrentRoute() {
        return $this->routes[$this->currentPage] ?? null;
    }

    /**
     * Get page file path
     */
    public function getPageFile() {
        return $this->pageFile;
    }

    /**
     * Check if current page is public
     */
    public function isPublicPage() {
        $route = $this->routes[$this->currentPage] ?? null;
        return $route ? ($route['public'] ?? false) : false;
    }

    /**
     * Check if current page needs no layout
     */
    public function getLayout() {
        $route = $this->routes[$this->currentPage] ?? null;
        return $route ? ($route['layout'] ?? 'default') : 'default';
    }

    /**
     * Get all routes (for plugin merging)
     */
    public function getRoutes() {
        return $this->routes;
    }

    /**
     * Merge plugin routes
     */
    public function mergeRoutes(array $pluginRoutes) {
        foreach ($pluginRoutes as $name => $file) {
            if (!isset($this->routes[$name])) {
                $this->register($name, $file);
            }
        }
    }

    /**
     * Get page files map (backward compatibility)
     */
    public function getPageFilesMap() {
        $map = [];
        foreach ($this->routes as $name => $config) {
            $map[$name] = $config['file'];
        }
        return $map;
    }

    /**
     * Check if a feature is enabled
     */
    public function isFeatureEnabled($feature) {
        try {
            $value = getSetting('feature_' . $feature, '1');
            return $value === '1';
        } catch (Exception $e) {
            return true;
        }
    }
}
