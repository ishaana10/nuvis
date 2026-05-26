<?php
// nuBuilder Next - Runtime Form Renderer
// Turns saved form metadata into real data-entry forms

class NuFormRenderer {
    private $db;
    private $auth;
    private $audit;

    public function __construct() {
        $this->db = NuDatabase::getInstance();
        $this->auth = new NuAuth();
        $this->audit = new NuAudit();
    }

    public function render($formCode, $recordId = null) {
        $form = $this->db->fetchOne(
            "SELECT * FROM nu_forms WHERE form_code = :code AND form_active = 1",
            [':code' => $formCode]
        );
        if (!$form) return '<div class="nu-toast error">Form not found</div>';

        $layout = json_decode($form['form_layout'], true);
        if (!is_array($layout)) $layout = [];

        $record = null;
        if ($recordId && $form['form_table']) {
            $record = $this->db->fetchOne(
                "SELECT * FROM {$form['form_table']} WHERE id = :id",
                [':id' => $recordId]
            );
        }

        $html = '<form class="nu-runtime-form" id="runtimeForm_' . $formCode . '" ';
        $html .= 'data-form-code="' . htmlspecialchars($formCode) . '" ';
        $html .= 'data-table="' . htmlspecialchars($form['form_table'] ?? '') . '" ';
        $html .= 'data-record-id="' . ($recordId ?? '') . '">';
        $html .= '<div class="nu-form-grid">';

        foreach ($layout as $field) {
            $html .= $this->renderField($field, $record);
        }

        $html .= '</div>';
        $html .= '<div class="nu-form-actions">';
        $html .= '<button type="submit" class="nu-btn nu-btn-primary">Save</button>';
        $html .= '<button type="button" class="nu-btn nu-btn-ghost" onclick="history.back()">Cancel</button>';
        if ($recordId) {
            $html .= '<button type="button" class="nu-btn nu-btn-danger" onclick="deleteRecord(this)">Delete</button>';
        }
        $html .= '</div>';
        $html .= '</form>';

        return $html;
    }

    private function renderField($field, $record = null) {
        $type = $field['type'] ?? 'text';
        $name = $field['name'] ?? '';
        $label = $field['label'] ?? $name;
        $value = $record[$name] ?? '';
        $required = !empty($field['required']) ? 'required' : '';
        $placeholder = $field['placeholder'] ?? '';
        $options = $field['options'] ?? [];
        $lookup = $field['lookup'] ?? null;

        $html = '<div class="nu-field nu-field-' . $type . '">';
        $html .= '<label>' . htmlspecialchars($label);
        if ($required) $html .= ' <span style="color:var(--danger)">*</span>';
        $html .= '</label>';

        switch ($type) {
            case 'textarea':
                $html .= '<textarea name="' . $name . '" class="nu-input" ' . $required . ' placeholder="' . htmlspecialchars($placeholder) . '" rows="4">' . htmlspecialchars($value) . '</textarea>';
                break;

            case 'select':
                $html .= '<select name="' . $name . '" class="nu-input" ' . $required . '>';
                $html .= '<option value="">-- Select --</option>';
                foreach ($options as $opt) {
                    $selected = $value == $opt['value'] ? 'selected' : '';
                    $html .= '<option value="' . htmlspecialchars($opt['value']) . '" ' . $selected . '>' . htmlspecialchars($opt['label']) . '</option>';
                }
                $html .= '</select>';
                break;

            case 'lookup':
                $html .= $this->renderLookup($field, $value);
                break;

            case 'file':
                $html .= '<input type="file" name="' . $name . '" class="nu-input" ' . $required . '>';
                if ($value) {
                    $html .= '<div style="margin-top:6px;font-size:12px;"><a href="uploads/' . htmlspecialchars($value) . '" target="_blank">View current file</a></div>';
                }
                break;

            case 'date':
                $html .= '<input type="date" name="' . $name . '" class="nu-input" value="' . htmlspecialchars($value) . '" ' . $required . '>';
                break;

            case 'datetime':
                $html .= '<input type="datetime-local" name="' . $name . '" class="nu-input" value="' . htmlspecialchars($value) . '" ' . $required . '>';
                break;

            case 'number':
                $min = isset($field['min']) ? 'min="' . $field['min'] . '"' : '';
                $max = isset($field['max']) ? 'max="' . $field['max'] . '"' : '';
                $step = isset($field['step']) ? 'step="' . $field['step'] . '"' : '';
                $html .= '<input type="number" name="' . $name . '" class="nu-input" value="' . htmlspecialchars($value) . '" ' . $required . ' ' . $min . ' ' . $max . ' ' . $step . ' placeholder="' . htmlspecialchars($placeholder) . '">';
                break;

            case 'checkbox':
                $checked = $value ? 'checked' : '';
                $html .= '<label class="nu-checkbox-label">';
                $html .= '<input type="checkbox" name="' . $name . '" value="1" ' . $checked . ' ' . $required . '>';
                $html .= '<span>' . htmlspecialchars($label) . '</span>';
                $html .= '</label>';
                break;

            case 'subform':
                $html .= $this->renderSubform($field, $record);
                break;

            case 'repeater':
                $html .= $this->renderRepeater($field, $record);
                break;

            default: // text, email, password, etc.
                $inputType = in_array($type, ['email', 'password', 'url', 'tel']) ? $type : 'text';
                $html .= '<input type="' . $inputType . '" name="' . $name . '" class="nu-input" value="' . htmlspecialchars($value) . '" ' . $required . ' placeholder="' . htmlspecialchars($placeholder) . '">';
        }

        if (!empty($field['help'])) {
            $html .= '<div class="nu-field-help">' . htmlspecialchars($field['help']) . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function renderLookup($field, $value) {
        $name = $field['name'];
        $lookup = $field['lookup'];
        $table = $lookup['table'] ?? '';
        $idCol = $lookup['id_column'] ?? 'id';
        $displayCol = $lookup['display_column'] ?? 'name';

        $options = [];
        if ($table) {
            $options = $this->db->fetchAll(
                "SELECT {$idCol} as id, {$displayCol} as label FROM {$table} LIMIT 500"
            );
        }

        $html = '<select name="' . $name . '" class="nu-input nu-lookup">';
        $html .= '<option value="">-- Select --</option>';
        foreach ($options as $opt) {
            $selected = $value == $opt['id'] ? 'selected' : '';
            $html .= '<option value="' . htmlspecialchars($opt['id']) . '" ' . $selected . '>' . htmlspecialchars($opt['label']) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private function renderSubform($field, $record) {
        $subformCode = $field['subform_code'] ?? '';
        $parentField = $field['parent_field'] ?? 'parent_id';
        $parentId = $record['id'] ?? 0;

        $html = '<div class="nu-subform" data-subform="' . $subformCode . '" data-parent-field="' . $parentField . '" data-parent-id="' . $parentId . '">';
        $html .= '<div class="nu-subform-header">';
        $html .= '<span>Subform: ' . htmlspecialchars($subformCode) . '</span>';
        $html .= '<button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" onclick="loadSubform(this)">Load</button>';
        $html .= '</div>';
        $html .= '<div class="nu-subform-content"></div>';
        $html .= '</div>';
        return $html;
    }

    private function renderRepeater($field, $record) {
        $name = $field['name'];
        $subfields = $field['subfields'] ?? [];
        $html = '<div class="nu-repeater" data-field="' . $name . '">';
        $html .= '<div class="nu-repeater-rows">';
        $html .= '</div>';
        $html .= '<button type="button" class="nu-btn nu-btn-ghost nu-btn-sm" onclick="addRepeaterRow(this)">+ Add Row</button>';
        $html .= '</div>';
        return $html;
    }

    public function save($formCode, $data, $recordId = null) {
        $form = $this->db->fetchOne(
            "SELECT * FROM nu_forms WHERE form_code = :code",
            [':code' => $formCode]
        );
        if (!$form || !$form['form_table']) {
            throw new Exception('Form has no target table');
        }

        $table = $form['form_table'];
        $layout = json_decode($form['form_layout'], true);

        // Filter only fields defined in layout
        $safeData = [];
        foreach ($layout as $field) {
            $name = $field['name'] ?? '';
            if (isset($data[$name])) {
                $safeData[$name] = $data[$name];
            }
        }

        if ($recordId) {
            $old = $this->db->fetchOne("SELECT * FROM {$table} WHERE id = :id", [':id' => $recordId]);
            $this->db->update($table, $safeData, "id = :id", [':id' => $recordId]);
            $this->audit->log('update', $table, $recordId, $old, $safeData);
            return $recordId;
        } else {
            $id = $this->db->insert($table, $safeData);
            $this->audit->log('create', $table, $id, null, $safeData);
            return $id;
        }
    }

    public function delete($formCode, $recordId) {
        $form = $this->db->fetchOne(
            "SELECT * FROM nu_forms WHERE form_code = :code",
            [':code' => $formCode]
        );
        if (!$form || !$form['form_table']) {
            throw new Exception('Form has no target table');
        }

        $old = $this->db->fetchOne(
            "SELECT * FROM {$form['form_table']} WHERE id = :id",
            [':id' => $recordId]
        );
        $this->db->delete($form['form_table'], "id = :id", [':id' => $recordId]);
        $this->audit->log('delete', $form['form_table'], $recordId, $old, null);
        return true;
    }

    public function browse($formCode, $page = 1, $perPage = 20, $filters = []) {
        $form = $this->db->fetchOne(
            "SELECT * FROM nu_forms WHERE form_code = :code",
            [':code' => $formCode]
        );
        if (!$form || !$form['form_table']) {
            throw new Exception('Form has no target table');
        }

        $table = $form['form_table'];
        $layout = json_decode($form['form_layout'], true);
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];
        foreach ($filters as $key => $val) {
            if ($val !== '') {
                $where[] = "{$key} LIKE :{$key}";
                $params[":{$key}"] = '%' . $val . '%';
            }
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = (int)$perPage;
        $params[':offset'] = (int)$offset;
        $records = $this->db->fetchAll($sql, $params);

        $countSql = "SELECT COUNT(*) as total FROM {$table} WHERE " . implode(' AND ', $where);
        unset($params[':limit'], $params[':offset']);
        $total = $this->db->fetchOne($countSql, $params)['total'];

        return [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => ceil($total / $perPage),
            'layout' => $layout
        ];
    }
}
?>
