<?php
// nuBuilder Next - Plugin Hook System
// Allows plugins to intercept and extend core behavior

class NuPluginManager {
    private $db;
    private $hooks = [];
    private $plugins = [];

    public function __construct() {
        $this->db = NuDatabase::getInstance();
        $this->loadActivePlugins();
    }

    private function loadActivePlugins() {
        $plugins = $this->db->fetchAll("SELECT * FROM nu_plugins WHERE plugin_active = 1");
        foreach ($plugins as $p) {
            $this->plugins[$p['plugin_code']] = $p;
            $hooks = json_decode($p['plugin_hooks'], true) ?? [];
            foreach ($hooks as $hook => $callback) {
                if (!isset($this->hooks[$hook])) $this->hooks[$hook] = [];
                $this->hooks[$hook][] = [
                    'plugin' => $p['plugin_code'],
                    'callback' => $callback,
                    'path' => $p['plugin_path']
                ];
            }
        }
    }

    public function trigger($hook, $data = []) {
        if (!isset($this->hooks[$hook])) return $data;

        foreach ($this->hooks[$hook] as $handler) {
            $pluginFile = __DIR__ . '/../plugins/' . $handler['plugin'] . '/' . $handler['plugin'] . '.php';
            if (file_exists($pluginFile)) {
                require_once $pluginFile;
                $callback = $handler['callback'];
                if (function_exists($callback)) {
                    $data = $callback($data);
                }
            }
        }
        return $data;
    }

    public function hasHook($hook) {
        return isset($this->hooks[$hook]) && !empty($this->hooks[$hook]);
    }

    public function getActivePlugins() {
        return $this->plugins;
    }
}
?>
