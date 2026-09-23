<?php
declare(strict_types=1);

/**
 * Sample Plugin for Nuvis
 * Demonstrates plugin initialization, hook registration, and custom workflow extension.
 */

return [
    'name'        => 'Sample Audit Notifier Plugin',
    'version'     => '1.0.0',
    'author'      => 'Nuvis Core Team',
    'description' => 'Demonstrates custom plugin hooks and event listening within Nuvis.',
    'init'        => function () {
        // Register custom plugin hooks or event listeners
        if (class_exists('NuAudit')) {
            // Plugin loaded successfully
        }
    },
];
