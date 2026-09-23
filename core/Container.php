<?php
declare(strict_types=1);

/**
 * NuContainer - Lightweight Dependency Injection & Service Container
 */
class NuContainer {
    private static ?NuContainer $instance = null;
    private array $services = [];
    private array $factories = [];

    public function __construct() {}

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->registerDefaults();
        }
        return self::$instance;
    }

    private function registerDefaults(): void {
        $this->singleton('config', function () {
            global $nuConfig;
            return $nuConfig ?? [];
        });

        $this->singleton('db', function () {
            return NuDatabase::getInstance();
        });

        $this->singleton('auth', function () {
            return NuAuth::getInstance();
        });
    }

    public function set(string $id, $service): void {
        $this->services[$id] = $service;
    }

    public function singleton(string $id, callable $factory): void {
        $this->factories[$id] = $factory;
    }

    public function get(string $id) {
        if (array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }

        if (array_key_exists($id, $this->factories)) {
            $service = call_user_func($this->factories[$id], $this);
            $this->services[$id] = $service;
            return $service;
        }

        throw new InvalidArgumentException("Service not found in container: " . $id);
    }

    public function has(string $id): bool {
        return array_key_exists($id, $this->services) || array_key_exists($id, $this->factories);
    }
}

if (!function_exists('nu_container')) {
    function nu_container(?string $service = null) {
        $container = NuContainer::getInstance();
        if ($service === null) {
            return $container;
        }
        return $container->get($service);
    }
}
