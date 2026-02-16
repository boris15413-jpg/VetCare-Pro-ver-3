<?php
/**
 * VetCare Pro - Template Engine
 * Simple, secure template rendering with layout support
 */
class Template {
    private static $instance = null;
    private $layoutPath = '';
    private $sections = [];
    private $currentSection = '';
    private $globals = [];

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set global template variable
     */
    public function set($key, $value) {
        $this->globals[$key] = $value;
        return $this;
    }

    /**
     * Get global template variable
     */
    public function get($key, $default = null) {
        return $this->globals[$key] ?? $default;
    }

    /**
     * Set all globals at once
     */
    public function setGlobals(array $data) {
        $this->globals = array_merge($this->globals, $data);
        return $this;
    }

    /**
     * Render a template file with data
     */
    public function render($templatePath, $data = []) {
        if (!file_exists($templatePath)) {
            throw new RuntimeException("Template not found: {$templatePath}");
        }
        $data = array_merge($this->globals, $data);
        extract($data, EXTR_SKIP);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }

    /**
     * Render a partial template (for includes/components)
     */
    public function partial($name, $data = []) {
        $path = BASE_PATH . '/templates/partials/' . $name . '.php';
        if (!file_exists($path)) {
            $path = BASE_PATH . '/templates/' . $name . '.php';
        }
        if (file_exists($path)) {
            echo $this->render($path, $data);
        }
    }

    /**
     * Start a named section for layout
     */
    public function startSection($name) {
        $this->currentSection = $name;
        ob_start();
    }

    /**
     * End current section
     */
    public function endSection() {
        if ($this->currentSection) {
            $this->sections[$this->currentSection] = ob_get_clean();
            $this->currentSection = '';
        }
    }

    /**
     * Output a section's content
     */
    public function yieldSection($name, $default = '') {
        echo $this->sections[$name] ?? $default;
    }

    /**
     * Check if a section has content
     */
    public function hasSection($name) {
        return isset($this->sections[$name]) && !empty($this->sections[$name]);
    }

    /**
     * Render a component with slot support
     */
    public function component($name, $props = [], $slot = '') {
        $path = BASE_PATH . '/templates/components/' . $name . '.php';
        if (file_exists($path)) {
            $data = array_merge($this->globals, $props, ['slot' => $slot]);
            echo $this->render($path, $data);
        }
    }

    /**
     * Escape output helper
     */
    public function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Include CSS file
     */
    public function css($path) {
        echo '<link href="' . $this->e($path) . '" rel="stylesheet">' . "\n";
    }

    /**
     * Include JS file
     */
    public function js($path) {
        echo '<script src="' . $this->e($path) . '"></script>' . "\n";
    }
}
