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

            $type = strtolower(trim((string)($item['type'] ?? '')));

            // ── Tab container ──────────────────────────────────────────────
            if ($type === 'tab') {
                foreach (($item['tabs'] ?? []) as $tab) {
                    if (!is_array($tab)) continue;
                    foreach (($tab['rows'] ?? []) as $row) {
                        $rowType = strtolower(trim((string)($row['type'] ?? 'row')));
                        if ($rowType === 'group') {
                            // group inside a tab panel — recurse its rows
                            $walk($row['rows'] ?? []);
                        } else {
                            // plain row inside a tab panel
                            $walk($row['fields'] ?? []);
                        }
                    }
                }
                continue;
            }

            // ── Section or Group (uses children[] at top level) ────────────
            if ($type === 'section' || $type === 'group') {
                // Top-level sections/groups store nested nodes in children[]
                $walk($item['children'] ?? []);
                // Also handle rows[] for groups that appear inside tab panels
                foreach (($item['rows'] ?? []) as $row) {
                    $walk($row['fields'] ?? []);
                }
                continue;
            }

            // ── Plain row (uses fields[]) ──────────────────────────────────
            if ($type === 'row' || isset($item['fields'])) {
                $walk($item['fields'] ?? []);
                continue;
            }

            // ── Subform — skip, handled separately ────────────────────────
            if ($type === 'subform') {
                continue;
            }

            // ── Skip layout-only / non-data types ─────────────────────────
            if (in_array($type, ['html', 'content', 'button', 'fieldset', 'heading', 'divider'], true)) {
                continue;
            }

            // ── Leaf field — must have a name ──────────────────────────────
            if (!empty($item['name']) || !empty($item['fieldname'])) {
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
