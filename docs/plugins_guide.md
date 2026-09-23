# Nuvis Plugin Architecture & Extension Guide

Nuvis supports modular plugins located under the `plugins/` directory.

## Plugin Structure

Each plugin resides in its own directory under `plugins/<plugin_name>/` and must contain a `plugin.php` entrypoint file.

Example layout:
```
plugins/
└── sample_plugin/
    └── plugin.php
```

## Creating a Plugin

`plugin.php` must return an array containing metadata and an `init` callable:

```php
<?php
declare(strict_types=1);

return [
    'name'        => 'My Custom Plugin',
    'version'     => '1.0.0',
    'author'      => 'Developer Name',
    'description' => 'Custom plugin description.',
    'init'        => function () {
        // Initialization logic, hook subscriptions, or custom event listeners
    },
];
```

## Enabling Plugins

Plugins placed in the `plugins/` directory can be registered and initialized during system boot in `config.php` or `core/module_bootstrap.php`.
