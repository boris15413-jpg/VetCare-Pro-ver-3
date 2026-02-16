<?php
/**
 * VetCare Pro - Application Container
 * Central service container and bootstrap
 */
class App {
    private static $instance = null;
    private $services = [];

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a service
     */
    public function register($name, $instance) {
        $this->services[$name] = $instance;
    }

    /**
     * Get a service
     */
    public function get($name) {
        return $this->services[$name] ?? null;
    }

    /**
     * Get database instance
     */
    public function db() {
        return $this->services['db'] ?? Database::getInstance();
    }

    /**
     * Get auth instance
     */
    public function auth() {
        return $this->services['auth'] ?? null;
    }

    /**
     * Get router instance
     */
    public function router() {
        return $this->services['router'] ?? Router::getInstance();
    }

    /**
     * Get template instance
     */
    public function template() {
        return $this->services['template'] ?? Template::getInstance();
    }

    /**
     * Check if a setting-based feature is enabled
     */
    public function featureEnabled($key, $default = true) {
        try {
            $val = getSetting('feature_' . $key, $default ? '1' : '0');
            return $val === '1';
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Get setting with app-level cache
     */
    public function setting($key, $default = '') {
        static $cache = [];
        if (!isset($cache[$key])) {
            $cache[$key] = getSetting($key, $default);
        }
        return $cache[$key];
    }
}

/**
 * Global app() helper
 */
function app() {
    return App::getInstance();
}
