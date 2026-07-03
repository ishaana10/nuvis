<?php

function nu_flatten_layout_fields($layout) {
    if (is_string($layout)) {
        $decoded = json_decode($layout, true);
        $layout = is_array($decoded) ? $decoded : [];
    }

    $out = [];

    $walk = function($items) use (&$walk, &$out) {
        if (!is_array($items)) return;

        foreach ($items as $item) {
            if (!is_array($item)) continue;

            if (isset($item['fields']) && is_array($item['fields'])) {
                $walk($item['fields']);
                continue;
            }

            $type = strtolower(trim((string)($item['type'] ?? '')));

            if ($type === 'tab') {
                foreach (($item['tabs'] ?? []) as $tab) {
                    if (!is_array($tab)) continue;
                    $walk($tab['rows'] ?? []);
                }
                continue;
            }

            if ($type === 'group') {
                $walk($item['rows'] ?? []);
                continue;
            }

            if ($type === 'subform') {
                continue;
            }

            if (!empty($item['name'])) {
                $out[] = $item;
            }
        }
    };

    $walk($layout);
    return $out;
}

function nu_flatten_layout($layout) {
    return nu_flatten_layout_fields($layout);
}
